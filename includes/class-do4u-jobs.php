<?php
/**
 * Jobs this server sends itself.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Alt text is the one place that does not run through the iframe. The form inside the iframe cannot be
 * given a file from outside, and an address would only work if do4u.eu can read the image from the public
 * internet, which fails on protected and staging sites. So this server sends the file from the media
 * library itself, after the user clicked start.
 */
final class Do4u_Jobs {

	/** do4u.eu takes images up to this size. */
	const MAX_BYTES = 5242880;

	const OWNER = 'do4u_wp_job_';

	/** Image types do4u.eu accepts. */
	const TYPES = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );

	/**
	 * Starts an alt text job for an image of the media library.
	 *
	 * @param int    $id      Attachment id.
	 * @param string $request Id of this click. A repeat after a network error must not cost twice, so it
	 *                        becomes the Idempotency-Key: do4u.eu answers a repeat with the same job.
	 * @return array Key job.
	 * @throws Do4u_Denied When a right is missing.
	 * @throws Do4u_Error  When the image cannot be sent or do4u.eu refuses.
	 */
	public static function start_alt( int $id, string $request ): array {
		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type || ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $id ) ) {
			throw new Do4u_Denied( esc_html__( 'You cannot edit this item.', 'do4u' ), 'forbidden', 403 );
		}
		if ( ! wp_attachment_is_image( $id ) || ! preg_match( '/^[a-f0-9]{16,64}$/D', $request ) ) {
			throw new Do4u_Error( esc_html__( 'This tool does not fit this item.', 'do4u' ), 'bad_item', 400 );
		}
		$path = self::image_path( $id );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file of the media library; image_path() checked that it lies in the uploads folder.
		$bytes = file_get_contents( $path );
		if ( false === $bytes ) {
			throw new Do4u_Error( esc_html__( 'The image file could not be read on this server.', 'do4u' ), 'no_file', 400 );
		}
		try {
			$answer = Do4u_Api::request(
				'POST',
				'jobs',
				array(
					'tool'     => 'alt_text',
					// The API takes the image file inside the JSON body, so it is base64 encoded (not obfuscation).
					'image'    => base64_encode( $bytes ),
					'filename' => wp_basename( $path ),
					'text'     => sanitize_text_field( (string) $post->post_title ),
					'language' => Do4u_Plugin::iframe_lang(),
					'no_store' => true,
				),
				45,
				null,
				array( 'Idempotency-Key' => 'do4u-wp-' . hash( 'sha256', get_current_user_id() . '|' . $id . '|' . $request ) )
			);
		} catch ( Do4u_Error $error ) {
			if ( 'insufficient_credits' === $error->api_code ) {
				throw new Do4u_Error( esc_html__( 'Your do4u.eu account has too few credits for this job.', 'do4u' ), esc_html( $error->api_code ), 402 );
			}
			if ( 'unreachable' === $error->api_code ) {
				throw new Do4u_Error( esc_html__( 'do4u.eu could not be reached. Start again: do4u.eu recognizes the repeat and does not charge twice.', 'do4u' ), esc_html( $error->api_code ) );
			}
			throw $error;
		}
		$job = (string) ( $answer['id'] ?? '' );
		if ( ! preg_match( '/^[0-9a-f]{32}$/D', $job ) ) {
			throw new Do4u_Error( esc_html__( 'do4u.eu sent an unexpected answer.', 'do4u' ), 'invalid_response' );
		}
		// The API key belongs to the whole site. This note keeps one WordPress user from reading the job of another.
		set_transient( self::OWNER . $job, get_current_user_id(), 2 * HOUR_IN_SECONDS );
		return array( 'job' => $job );
	}

	/**
	 * Picks the file to send: the original if do4u.eu takes it, otherwise the largest smaller size that fits.
	 *
	 * @param int $id Attachment id.
	 * @return string Path of the file.
	 * @throws Do4u_Error When no local file fits, for instance with media stored in the cloud.
	 */
	private static function image_path( int $id ): string {
		$base = realpath( (string) ( wp_get_upload_dir()['basedir'] ?? '' ) );
		$main = (string) get_attached_file( $id );
		$list = array( $main );
		$meta = wp_get_attachment_metadata( $id );
		if ( is_array( $meta ) && is_array( $meta['sizes'] ?? null ) ) {
			$sizes = $meta['sizes'];
			uasort( $sizes, static fn( array $a, array $b ): int => (int) ( $b['width'] ?? 0 ) <=> (int) ( $a['width'] ?? 0 ) );
			foreach ( $sizes as $size ) {
				$list[] = path_join( dirname( $main ), wp_basename( (string) ( $size['file'] ?? '' ) ) );
			}
		}
		foreach ( $list as $file ) {
			$real = realpath( $file );
			if ( false === $real || false === $base || ! str_starts_with( $real, $base . DIRECTORY_SEPARATOR ) || ! is_file( $real ) ) {
				continue;
			}
			$type = wp_check_filetype( $real );
			if ( in_array( $type['type'], self::TYPES, true ) && filesize( $real ) <= self::MAX_BYTES ) {
				return $real;
			}
		}
		throw new Do4u_Error( esc_html__( 'No file of this image can be sent: it must be a JPEG, PNG, WebP or GIF of at most 5 MB, stored on this server.', 'do4u' ), 'no_file', 400 );
	}

	/**
	 * State of a job started here.
	 *
	 * @param string $job Job id.
	 * @return array Keys status, and result and credits or message.
	 * @throws Do4u_Denied When this is not a job of this user.
	 * @throws Do4u_Error  When do4u.eu cannot be asked.
	 */
	public static function status( string $job ): array {
		if ( ! preg_match( '/^[0-9a-f]{32}$/D', $job ) || (int) get_transient( self::OWNER . $job ) !== get_current_user_id() ) {
			throw new Do4u_Denied( esc_html__( 'This job does not belong to you.', 'do4u' ), 'forbidden', 403 );
		}
		$answer = Do4u_Api::request( 'GET', 'jobs/' . $job );
		$status = (string) ( $answer['status'] ?? '' );
		if ( 'done' === $status ) {
			delete_transient( self::OWNER . $job );
			$alt = $answer['result']['alt'] ?? '';
			return array(
				'status'  => 'done',
				'result'  => array( 'alt' => is_string( $alt ) ? sanitize_text_field( $alt ) : '' ),
				'credits' => (int) ( $answer['credits'] ?? 0 ),
			);
		}
		if ( 'queued' === $status || 'running' === $status ) {
			return array( 'status' => $status );
		}
		delete_transient( self::OWNER . $job );
		return array(
			'status'  => 'failed',
			'message' => __( 'The job could not be completed. It cost no credits.', 'do4u' ),
		);
	}
}
