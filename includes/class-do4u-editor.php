<?php
/**
 * Tools in the post editor: block editor sidebar, box in the classic editor, WooCommerce product box.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/** Both editors offer the same actions, and do4u-admin.js treats them alike. */
final class Do4u_Editor {

	/** Registers the hooks. */
	public static function boot(): void {
		add_action( 'enqueue_block_editor_assets', array( self::class, 'block_assets' ) );
		add_action( 'add_meta_boxes', array( self::class, 'meta_boxes' ) );
		add_action( 'add_meta_boxes_product', array( self::class, 'product_box' ) );
	}

	/**
	 * Actions for the editor of a post.
	 *
	 * @param int $post_id Post.
	 * @return array List of tool, preset, title, context.
	 */
	public static function actions( int $post_id ): array {
		$presets = Do4u_Places::presets();
		$actions = array();
		foreach ( Do4u_Places::post_preset_ids() as $id ) {
			$actions[] = array(
				'tool'    => $presets[ $id ]['tool'],
				'preset'  => $id,
				'title'   => $presets[ $id ]['label'],
				'context' => array( 'id' => $post_id ),
			);
		}
		return $actions;
	}

	/** Script of the sidebar. No build step: it only uses what WordPress already provides as globals. */
	public static function block_assets(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || ! in_array( $screen->post_type, Do4u_Plugin::post_types(), true ) ) {
			return;
		}
		if ( ! current_user_can( Do4u_Plugin::capability() ) || ! Do4u_Connection::is_connected() ) {
			return;
		}
		$post_id = (int) get_the_ID();
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		Do4u_Admin::register_assets();
		wp_enqueue_script(
			'do4u-editor',
			plugin_dir_url( DO4U_WP_FILE ) . 'assets/js/do4u-editor.js',
			array( 'do4u-admin', 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-blocks' ),
			DO4U_WP_VERSION,
			true
		);
		wp_localize_script(
			'do4u-editor',
			'do4uWpEditor',
			array(
				'actions' => self::actions( $post_id ),
				'intro'   => __( 'Each tool opens filled in with this post, including unsaved changes. Nothing starts without your click.', 'do4u' ),
			)
		);
	}

	/**
	 * Box in the classic editor. The flag hides it in the block editor, which has the sidebar.
	 *
	 * @param string $post_type Post type.
	 */
	public static function meta_boxes( $post_type ): void {
		if ( ! in_array( $post_type, Do4u_Plugin::post_types(), true ) || ! current_user_can( Do4u_Plugin::capability() ) || ! Do4u_Connection::is_connected() ) {
			return;
		}
		add_meta_box( 'do4u-tools', 'do4u.eu', array( self::class, 'render_box' ), $post_type, 'side', 'default', array( '__back_compat_meta_box' => true ) );
	}

	/**
	 * Content of the box in the classic editor.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_box( $post ): void {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		echo '<p class="description">' . esc_html__( 'Each tool opens filled in with this post, including unsaved changes. Nothing starts without your click.', 'do4u' ) . '</p><div class="do4u-actions">';
		foreach ( self::actions( (int) $post->ID ) as $action ) {
			printf( '<button type="button" class="button" data-do4u-open="%1$s">%2$s</button>', esc_attr( (string) wp_json_encode( $action ) ), esc_html( $action['title'] ) );
		}
		echo '</div>';
	}

	/**
	 * Box on the WooCommerce product page. The hook only exists when WooCommerce registered the post type. The box holds
	 * the product description tool (needs the site widget) and the shop part of the shop tools (needs the API key only).
	 */
	public static function product_box(): void {
		if ( ! self::product_widget() && ! Do4u_Shop::usable() ) {
			return;
		}
		add_meta_box( 'do4u-product', 'do4u.eu', array( self::class, 'render_product_box' ), 'product', 'side', 'default', array( '__back_compat_meta_box' => true ) );
	}

	/**
	 * Content of the product box.
	 *
	 * @param WP_Post $post Product.
	 */
	public static function render_product_box( $post ): void {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		if ( self::product_widget() ) {
			self::render_product_tool( (int) $post->ID );
		}
		if ( Do4u_Shop::usable() ) {
			Do4u_Shop_Products::render_box( $post );
		}
	}

	/**
	 * Whether the widget tool of the product box is available.
	 *
	 * @return bool
	 */
	private static function product_widget(): bool {
		return current_user_can( Do4u_Plugin::capability() ) && Do4u_Connection::is_connected();
	}

	/**
	 * Button that opens the product description tool for one product.
	 *
	 * @param int $post_id Product.
	 */
	private static function render_product_tool( int $post_id ): void {
		$presets = Do4u_Places::presets();
		$action  = array(
			'tool'    => $presets['product_text']['tool'],
			'preset'  => 'product_text',
			'title'   => $presets['product_text']['label'],
			'context' => array( 'id' => $post_id ),
		);
		printf( '<div class="do4u-actions"><button type="button" class="button" data-do4u-open="%1$s">%2$s</button></div>', esc_attr( (string) wp_json_encode( $action ) ), esc_html( $action['title'] ) );
	}
}
