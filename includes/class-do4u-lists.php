<?php
/**
 * Actions in the lists of posts, pages, media and comments, and in the media dialog.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Links open a dialog on the same screen when script is available, so the list keeps its place.
 * Their address is the tool page, so opening in a new tab also works.
 */
final class Do4u_Lists {

	const BULK = 'do4u_newsletter';

	/** Registers the hooks. */
	public static function boot(): void {
		add_action( 'admin_init', array( self::class, 'register_bulk' ) );
		add_filter( 'post_row_actions', array( self::class, 'post_row' ), 10, 2 );
		add_filter( 'page_row_actions', array( self::class, 'post_row' ), 10, 2 );
		add_filter( 'media_row_actions', array( self::class, 'media_row' ), 10, 2 );
		add_filter( 'comment_row_actions', array( self::class, 'comment_row' ), 10, 2 );
		add_filter( 'attachment_fields_to_edit', array( self::class, 'attachment_field' ), 10, 2 );
	}

	/** Adds the bulk action for each post type. */
	public static function register_bulk(): void {
		foreach ( Do4u_Plugin::post_types() as $type ) {
			add_filter( 'bulk_actions-edit-' . $type, array( self::class, 'bulk_actions' ) );
			add_filter( 'handle_bulk_actions-edit-' . $type, array( self::class, 'handle_bulk' ), 10, 3 );
		}
	}

	/**
	 * Adds "Create newsletter" to the bulk menu.
	 *
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public static function bulk_actions( array $actions ): array {
		if ( Do4u_Connection::is_connected() && current_user_can( Do4u_Plugin::capability() ) ) {
			$actions[ self::BULK ] = __( 'Create newsletter (do4u.eu)', 'do4u' );
		}
		return $actions;
	}

	/**
	 * Sends the selected posts to the newsletter page. WordPress has checked its bulk nonce at this point;
	 * which posts may go in is decided when the tool opens.
	 *
	 * @param string $redirect Address WordPress would go to.
	 * @param string $action   Bulk action.
	 * @param array  $ids      Selected posts.
	 * @return string
	 */
	public static function handle_bulk( $redirect, $action, $ids ) {
		if ( self::BULK !== $action ) {
			return $redirect;
		}
		return self::newsletter_url( array_map( 'absint', (array) $ids ) );
	}

	/**
	 * Address of the newsletter tool for some posts.
	 *
	 * @param int[] $ids Post ids in list order; the first is the main article.
	 * @return string
	 */
	public static function newsletter_url( array $ids ): string {
		return add_query_arg(
			array(
				'page'  => 'do4u-newsletter',
				'items' => implode( ',', $ids ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Row action in the post and page lists. Only for public posts: a newsletter needs links that work.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function post_row( $actions, $post ) {
		if ( ! Do4u_Connection::is_connected() || ! in_array( $post->post_type, Do4u_Plugin::post_types(), true ) ) {
			return $actions;
		}
		if ( 'publish' === $post->post_status && '' === $post->post_password && current_user_can( Do4u_Plugin::capability() ) ) {
			$actions['do4u_newsletter'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( self::newsletter_url( array( (int) $post->ID ) ) ), esc_html__( 'Create newsletter', 'do4u' ) );
		}
		return $actions;
	}

	/**
	 * Row action in the media list.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Attachment.
	 * @return array
	 */
	public static function media_row( $actions, $post ) {
		if ( Do4u_Connection::is_connected() && wp_attachment_is_image( $post->ID ) && current_user_can( 'upload_files' ) && current_user_can( 'edit_post', $post->ID ) && current_user_can( Do4u_Plugin::capability() ) ) {
			$actions['do4u_alt'] = self::open_link( 'alt_text', 'media_alt', (int) $post->ID, __( 'Alt text (do4u.eu)', 'do4u' ) );
		}
		return $actions;
	}

	/**
	 * Row actions in the comment list.
	 *
	 * @param array      $actions Row actions.
	 * @param WP_Comment $comment Comment.
	 * @return array
	 */
	public static function comment_row( $actions, $comment ) {
		if ( Do4u_Connection::is_connected() && current_user_can( 'moderate_comments' ) && current_user_can( 'edit_comment', $comment->comment_ID ) && current_user_can( Do4u_Plugin::capability() ) ) {
			$actions['do4u_check'] = self::open_link( 'moderate_comment', 'comment_check', (int) $comment->comment_ID, __( 'Check (do4u.eu)', 'do4u' ) );
			$actions['do4u_reply'] = 'review' === $comment->comment_type
				? self::open_link( 'review_reply', 'comment_review', (int) $comment->comment_ID, __( 'Reply suggestion (do4u.eu)', 'do4u' ) )
				: self::open_link( 'support_reply', 'comment_reply', (int) $comment->comment_ID, __( 'Reply suggestion (do4u.eu)', 'do4u' ) );
		}
		return $actions;
	}

	/**
	 * A link that opens a tool in the dialog.
	 *
	 * @param string $tool   Public tool name.
	 * @param string $preset Preset id.
	 * @param int    $id     Item id.
	 * @param string $label  Link text.
	 * @return string HTML.
	 */
	private static function open_link( string $tool, string $preset, int $id, string $label ): string {
		$data = array(
			'tool'    => $tool,
			'preset'  => $preset,
			'title'   => $label,
			'context' => array( 'id' => $id ),
		);
		$url  = Do4u_Pages::tool_url(
			$tool,
			array(
				'preset' => $preset,
				'item'   => $id,
			)
		);
		return sprintf( '<a href="%1$s" data-do4u-open="%2$s">%3$s</a>', esc_url( $url ), esc_attr( (string) wp_json_encode( $data ) ), esc_html( $label ) );
	}

	/**
	 * Adds the alt text button to the attachment details. WordPress shows these fields both on the edit page
	 * and in the media dialog, so this one filter covers both places.
	 *
	 * @param array   $fields Fields.
	 * @param WP_Post $post   Attachment.
	 * @return array
	 */
	public static function attachment_field( $fields, $post ) {
		if ( ! Do4u_Connection::is_connected() || ! wp_attachment_is_image( $post->ID ) || ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $post->ID ) || ! current_user_can( Do4u_Plugin::capability() ) ) {
			return $fields;
		}
		$data             = array(
			'tool'    => 'alt_text',
			'preset'  => 'media_alt',
			'title'   => __( 'Alt text', 'do4u' ),
			'context' => array( 'id' => (int) $post->ID ),
		);
		$fields['do4u_alt'] = array(
			'label' => 'do4u.eu',
			'input' => 'html',
			'html'  => sprintf(
				'<button type="button" class="button do4u-alt-button" data-do4u-open="%1$s">%2$s</button>',
				esc_attr( (string) wp_json_encode( $data ) ),
				esc_html__( 'Suggest alt text', 'do4u' )
			),
		);
		return $fields;
	}
}
