<?php
/**
 * Takes results of tools over into WordPress.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Results of a tool are untrusted: they come from an AI that read arbitrary text, and they reach this
 * server through the browser. So every takeover runs here, after a click, with a right check and a
 * cleanup, whatever the browser claims.
 *
 * Nothing is ever published. Most targets only return the cleaned value for a field that the user then
 * saves. Two targets write: a new post that is always a draft, and the alt text of an image, which never
 * replaces existing text without a second, explicit request.
 */
final class Do4u_Apply {

	/** Upper limit for a text result; do4u.eu results are far smaller. */
	const MAX_BYTES = 500000;

	/**
	 * Handles one takeover.
	 *
	 * @param array $input Keys target, object_id, value, format, store, overwrite, split_title.
	 * @return array Keys value and/or edit_link, or conflict and current.
	 * @throws Do4u_Denied When a right is missing.
	 * @throws Do4u_Error  When the value is unusable or the target is unknown.
	 */
	public static function run( array $input ): array {
		$target    = (string) ( $input['target'] ?? '' );
		$object_id = absint( $input['object_id'] ?? 0 );
		$value     = (string) ( $input['value'] ?? '' );
		if ( strlen( $value ) > self::MAX_BYTES ) {
			throw new Do4u_Error( esc_html__( 'The result is too long to take over.', 'do4u' ), 'too_long', 400 );
		}
		switch ( $target ) {
			case 'title':
			case 'excerpt':
				self::require_post( $object_id );
				return array( 'value' => self::not_empty( sanitize_text_field( $value ) ) );
			case 'content':
			case 'append':
			case 'product_content':
			case 'product_short':
				$post = self::require_post( $object_id );
				return array( 'value' => self::not_empty( self::html( $value, (string) ( $input['format'] ?? 'text' ), (string) $post->post_content ) ) );
			case 'draft':
				return self::draft( $object_id, $value, (string) ( $input['format'] ?? 'text' ), ! empty( $input['split_title'] ) );
			case 'new_draft':
				return self::new_draft( $value, (string) ( $input['format'] ?? 'text' ), ! empty( $input['split_title'] ) );
			case 'alt':
				return self::alt( $object_id, $value, ! empty( $input['store'] ), ! empty( $input['overwrite'] ) );
			case 'seo_title':
			case 'seo_description':
				return self::seo( $target, $object_id, $value, ! empty( $input['store'] ), ! empty( $input['overwrite'] ) );
			case 'comment_reply':
				self::require_comment( $object_id );
				return array( 'value' => self::not_empty( sanitize_textarea_field( $value ) ) );
		}
		throw new Do4u_Error( esc_html__( 'This result cannot be taken over here.', 'do4u' ), 'bad_target', 400 );
	}

	/**
	 * @param int $id Post id.
	 * @return WP_Post
	 * @throws Do4u_Denied When the user may not edit the post.
	 */
	private static function require_post( int $id ) {
		$post = get_post( $id );
		if ( ! $post || ! current_user_can( 'edit_post', $id ) ) {
			throw new Do4u_Denied( esc_html__( 'You cannot edit this item.', 'do4u' ), 'forbidden', 403 );
		}
		return $post;
	}

	/**
	 * @param int $id Comment id.
	 * @throws Do4u_Denied When the user may not moderate the comment.
	 */
	private static function require_comment( int $id ): void {
		if ( ! get_comment( $id ) || ! current_user_can( 'moderate_comments' ) || ! current_user_can( 'edit_comment', $id ) ) {
			throw new Do4u_Denied( esc_html__( 'You cannot moderate this comment.', 'do4u' ), 'forbidden', 403 );
		}
	}

	/**
	 * @param string $value Cleaned value.
	 * @return string
	 * @throws Do4u_Error When nothing is left after the cleanup.
	 */
	private static function not_empty( string $value ): string {
		if ( '' === trim( $value ) ) {
			throw new Do4u_Error( esc_html__( 'The result is empty.', 'do4u' ), 'empty', 400 );
		}
		return $value;
	}

	/**
	 * Cleans a result for the post content. The format comes from the request, so it decides nothing about
	 * safety: wp_kses_post() runs in both cases.
	 *
	 * @param string $value  Result.
	 * @param string $format html or text.
	 * @param string $source Content the result was made from; its links stay allowed.
	 * @return string
	 */
	private static function html( string $value, string $format, string $source = '' ): string {
		if ( 'html' === $format ) {
			return self::links( wp_kses_post( $value ), $source );
		}
		// A plain text result must not run a shortcode once the post is published.
		return str_replace( '[', '&#91;', wp_kses_post( wpautop( esc_html( sanitize_textarea_field( $value ) ) ) ) );
	}

	/**
	 * Keeps a link only if the source already had it, or it stays on this site (own host, relative path, anchor).
	 * Any other link from a model result is unwrapped to its text: page text could have planted it.
	 *
	 * @param string $html   Cleaned result.
	 * @param string $source Content the result was made from.
	 * @return string
	 */
	private static function links( string $html, string $source ): string {
		$allowed = array();
		if ( preg_match_all( '#\bhref\s*=\s*(["\'])(.*?)\1#is', $source, $found ) ) {
			foreach ( $found[2] as $href ) {
				$allowed[ trim( html_entity_decode( $href, ENT_QUOTES ) ) ] = true;
			}
		}
		$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$out  = preg_replace_callback(
			'#<a\b([^>]*)>(.*?)</a>#is',
			static function ( array $tag ) use ( $allowed, $home ): string {
				if ( ! preg_match( '#\bhref\s*=\s*(["\'])(.*?)\1#is', $tag[1], $href ) ) {
					return $tag[2];
				}
				$url = trim( html_entity_decode( $href[2], ENT_QUOTES ) );
				if ( isset( $allowed[ $url ] ) ) {
					return $tag[0];
				}
				if ( '' !== $url && ( '#' === $url[0] || ( '/' === $url[0] && ( 1 === strlen( $url ) || '/' !== $url[1] ) ) ) ) {
					return $tag[0];
				}
				$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
				$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
				if ( '' !== $home && $host === $home && in_array( $scheme, array( 'http', 'https' ), true ) ) {
					return $tag[0];
				}
				return $tag[2];
			},
			$html
		);
		return is_string( $out ) ? $out : wp_strip_all_tags( $html );
	}

	/**
	 * Saves a result as a new draft of the same type. The original stays as it is.
	 *
	 * @param int    $source_id   Post the result belongs to.
	 * @param string $value       Result.
	 * @param string $format      html or text.
	 * @param bool   $split_title Take the title from a leading <h1> of the result.
	 * @return array Keys edit_link and draft_id.
	 * @throws Do4u_Denied When a right is missing.
	 * @throws Do4u_Error  When the draft cannot be saved.
	 */
	private static function draft( int $source_id, string $value, string $format, bool $split_title ): array {
		$source = self::require_post( $source_id );
		$type   = get_post_type_object( $source->post_type );
		if ( ! $type || ! current_user_can( $type->cap->create_posts ) ) {
			throw new Do4u_Denied( esc_html__( 'You cannot create this kind of item.', 'do4u' ), 'forbidden', 403 );
		}
		$title = (string) $source->post_title;
		if ( $split_title && 'html' === $format && preg_match( '#^\s*<h1\b[^>]*>(.*?)</h1>#is', $value, $match ) ) {
			$found = sanitize_text_field( $match[1] );
			if ( '' !== $found ) {
				$title = $found;
				$value = (string) substr( $value, strlen( $match[0] ) );
			}
		}
		$content = self::not_empty( self::html( $value, $format, (string) $source->post_content ) );
		$draft   = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => $source->post_type,
					'post_status'  => 'draft',
					'post_author'  => get_current_user_id(),
					'post_title'   => $title,
					'post_content' => $content,
					'meta_input'   => array( '_do4u_wp_source' => $source_id ),
				)
			),
			true
		);
		if ( is_wp_error( $draft ) ) {
			throw new Do4u_Error( esc_html__( 'The draft could not be saved.', 'do4u' ), 'draft_failed', 500 );
		}
		return array(
			'draft_id'  => (int) $draft,
			'edit_link' => (string) get_edit_post_link( (int) $draft, 'raw' ),
		);
	}

	/**
	 * Saves a result as a new draft post without a source post. Nothing is published.
	 *
	 * @param string $value       Result.
	 * @param string $format      html or text.
	 * @param bool   $split_title Take the title from a leading <h1> of the result.
	 * @return array Keys edit_link and draft_id.
	 * @throws Do4u_Denied When a right is missing.
	 * @throws Do4u_Error  When the draft cannot be saved.
	 */
	private static function new_draft( string $value, string $format, bool $split_title ): array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new Do4u_Denied( esc_html__( 'You cannot create this kind of item.', 'do4u' ), 'forbidden', 403 );
		}
		$title = '';
		if ( $split_title && 'html' === $format && preg_match( '#^\s*<h1\b[^>]*>(.*?)</h1>#is', $value, $match ) ) {
			$title = sanitize_text_field( $match[1] );
			if ( '' !== $title ) {
				$value = (string) substr( $value, strlen( $match[0] ) );
			}
		}
		$content = self::not_empty( self::html( $value, $format ) );
		$draft   = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'post',
					'post_status'  => 'draft',
					'post_author'  => get_current_user_id(),
					'post_title'   => $title,
					'post_content' => $content,
				)
			),
			true
		);
		if ( is_wp_error( $draft ) ) {
			throw new Do4u_Error( esc_html__( 'The draft could not be saved.', 'do4u' ), 'draft_failed', 500 );
		}
		return array(
			'draft_id'  => (int) $draft,
			'edit_link' => (string) get_edit_post_link( (int) $draft, 'raw' ),
		);
	}

	/**
	 * SEO title or meta description for Yoast SEO or Rank Math. In the editor the value goes into their own
	 * field and is saved with the post. It is only stored here when no such field is on the screen, and
	 * existing text is only replaced when the request says so.
	 *
	 * @param string $target    seo_title or seo_description.
	 * @param int    $id        Post id.
	 * @param string $value     Result.
	 * @param bool   $store     Store now instead of returning for a field.
	 * @param bool   $overwrite The user confirmed replacing existing text.
	 * @return array
	 * @throws Do4u_Denied When a right is missing.
	 * @throws Do4u_Error  When the value is unusable or no SEO plugin is active.
	 */
	private static function seo( string $target, int $id, string $value, bool $store, bool $overwrite ): array {
		self::require_post( $id );
		$clean = self::not_empty( sanitize_text_field( $value ) );
		if ( mb_strlen( $clean ) > 1000 ) {
			throw new Do4u_Error( esc_html__( 'The result is too long to take over.', 'do4u' ), 'too_long', 400 );
		}
		if ( ! $store ) {
			return array( 'value' => $clean );
		}
		$keys = Do4u_Places::seo_meta_keys( $target );
		if ( ! $keys ) {
			throw new Do4u_Error( esc_html__( 'This result cannot be taken over here.', 'do4u' ), 'bad_target', 400 );
		}
		foreach ( $keys as $key ) {
			$current = (string) get_post_meta( $id, $key, true );
			if ( '' !== trim( $current ) && $current !== $clean && ! $overwrite ) {
				return array(
					'conflict' => true,
					'current'  => $current,
				);
			}
		}
		foreach ( $keys as $key ) {
			update_post_meta( $id, $key, wp_slash( $clean ) );
		}
		return array(
			'value'  => $clean,
			'stored' => true,
		);
	}

	/**
	 * Alt text of an image: returns the cleaned value for a field, or stores it when no field is on the
	 * screen. Existing text is only replaced when the request says so.
	 *
	 * @param int    $id        Attachment id.
	 * @param string $value     Result.
	 * @param bool   $store     Store now instead of returning for a field.
	 * @param bool   $overwrite The user confirmed replacing existing text.
	 * @return array
	 * @throws Do4u_Denied When a right is missing.
	 * @throws Do4u_Error  When the item is no image or the value is unusable.
	 */
	private static function alt( int $id, string $value, bool $store, bool $overwrite ): array {
		$post = get_post( $id );
		if ( ! $post || 'attachment' !== $post->post_type || ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $id ) ) {
			throw new Do4u_Denied( esc_html__( 'You cannot edit this item.', 'do4u' ), 'forbidden', 403 );
		}
		if ( ! wp_attachment_is_image( $id ) ) {
			throw new Do4u_Error( esc_html__( 'This tool does not fit this item.', 'do4u' ), 'bad_item', 400 );
		}
		$clean = self::not_empty( sanitize_text_field( $value ) );
		if ( mb_strlen( $clean ) > 2000 ) {
			throw new Do4u_Error( esc_html__( 'The result is too long to take over.', 'do4u' ), 'too_long', 400 );
		}
		if ( ! $store ) {
			return array( 'value' => $clean );
		}
		$current = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		if ( '' !== trim( $current ) && $current !== $clean && ! $overwrite ) {
			return array(
				'conflict' => true,
				'current'  => $current,
			);
		}
		update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $clean ) );
		return array(
			'value'  => $clean,
			'stored' => true,
		);
	}
}
