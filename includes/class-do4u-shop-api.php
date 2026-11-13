<?php
/**
 * Batch requests of the shop tools to the do4u.eu API.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Batch requests of the shop tools to the API of do4u.eu (path /api/v1/). Address, API key and error class are the ones of
 * the do4u.eu plugin (Do4u_Api, Do4u_Secret, Do4u_Error): one connection for all tools.
 */
final class Do4u_Shop_Api {

	/** Largest answer that is read (the result file of a batch can be large). */
	const MAX_RESPONSE = 8388608;

	/**
	 * Web address of the service that belongs to the API address, for links.
	 *
	 * @param string $path Path on the website, with a leading slash.
	 * @return string
	 */
	public static function site_url( $path = '' ) {
		try {
			$site = Do4u_Api::site();
		} catch ( Do4u_Error $e ) {
			$site = (string) preg_replace( '#/api/v1/$#', '', Do4u_Api::BASE );
		}
		return $site . $path;
	}

	/**
	 * Sends one request.
	 *
	 * @param string      $method  GET, POST or DELETE.
	 * @param string      $path    Path below /api/v1/.
	 * @param array|null  $body    JSON body.
	 * @param int         $timeout Seconds.
	 * @param string|null $key     API key; null reads the saved key.
	 * @param bool        $raw     True returns the body as text (CSV), false decodes JSON.
	 * @return array|string Decoded answer, or the raw body.
	 * @throws Do4u_Error On a network error, an error status or an invalid answer.
	 */
	public static function request( $method, $path, $body = null, $timeout = 20, $key = null, $raw = false ) {
		[ $status, $text ] = Do4u_Api::send( (string) $method, ltrim( (string) $path, '/' ), $body, (int) $timeout, null === $key ? null : (string) $key, array( 'Accept' => $raw ? 'text/csv' : 'application/json' ), self::MAX_RESPONSE );
		if ( $raw && $status >= 200 && $status < 300 ) {
			return $text;
		}
		$data = json_decode( $text, true );
		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			$code    = is_array( $data ) && isset( $data['error']['code'] ) ? (string) $data['error']['code'] : 'invalid_response';
			$message = is_array( $data ) && isset( $data['error']['message'] ) ? (string) $data['error']['message'] : '';
			throw new Do4u_Error( esc_html( self::message( $code, $message, $status ) ), esc_html( $code ), absint( $status ) );
		}
		return $data;
	}

	/**
	 * Readable text for an error of the API. Known codes get an own explanation; the rest uses the text of do4u.eu.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message of do4u.eu.
	 * @param int    $status  HTTP status.
	 * @return string Plain text.
	 */
	public static function message( $code, $message, $status ) {
		$known = array(
			'missing_key'           => __( 'The API key is missing.', 'do4u' ),
			'invalid_key'           => __( 'do4u.eu does not accept this API key. It may have been revoked. Create a new key in your do4u.eu account.', 'do4u' ),
			'insufficient_credits'  => __( 'Your do4u.eu balance is not enough for this batch. Buy credits in your do4u.eu account or choose fewer products.', 'do4u' ),
			'package_required'      => __( 'Batches are not part of your do4u.eu package.', 'do4u' ),
			'too_many_rows'         => __( 'Your do4u.eu package allows fewer entries per batch. Choose fewer products, or lower "Entries per batch" in the settings.', 'do4u' ),
			'too_many_open_batches' => __( 'Your do4u.eu package allows no further open batch right now. Wait until a running batch is finished, or cancel it.', 'do4u' ),
			'batches_unavailable'   => __( 'do4u.eu does not accept batches at the moment. Please try again later.', 'do4u' ),
			'rate_limited'          => __( 'Too many requests to do4u.eu in a short time. Please wait a minute.', 'do4u' ),
			'email_not_verified'    => __( 'Please confirm the email address of your do4u.eu account first.', 'do4u' ),
		);
		if ( isset( $known[ $code ] ) ) {
			return $known[ $code ];
		}
		if ( '' !== $message ) {
			/* translators: %s: error message of do4u.eu */
			return sprintf( __( 'do4u.eu: %s', 'do4u' ), wp_strip_all_tags( $message ) );
		}
		/* translators: %d: HTTP status code */
		return sprintf( __( 'do4u.eu sent an unexpected answer (HTTP %d).', 'do4u' ), (int) $status );
	}
}
