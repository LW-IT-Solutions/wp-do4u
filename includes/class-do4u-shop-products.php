<?php
/**
 * Places in the WooCommerce product screens: bulk action in the product list and the shop part of the do4u.eu box on the product page.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/** Product list and product edit screen. */
final class Do4u_Shop_Products {

	/** Registers the hooks. */
	public static function boot() {
		add_filter( 'bulk_actions-edit-product', array( self::class, 'bulk_action' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( self::class, 'handle_bulk' ), 10, 3 );
	}

	/**
	 * Adds "Create texts with do4u.eu" to the bulk actions of the product list.
	 *
	 * @param array $actions Actions.
	 * @return array
	 */
	public static function bulk_action( $actions ) {
		if ( Do4u_Shop_Settings::can_use() ) {
			$actions['do4u_shop_create'] = __( 'Create texts with do4u.eu', 'do4u' );
		}
		return $actions;
	}

	/**
	 * Opens the create page with the selected products. WordPress has checked the nonce of the list (bulk-posts).
	 *
	 * @param string $redirect Address.
	 * @param string $action   Action.
	 * @param int[]  $ids      Products.
	 * @return string
	 */
	public static function handle_bulk( $redirect, $action, $ids ) {
		if ( 'do4u_shop_create' !== $action || ! Do4u_Shop_Settings::can_use() ) {
			return $redirect;
		}
		$ids = array_slice( array_values( array_filter( array_map( 'absint', (array) $ids ) ) ), 0, 200 );
		return Do4u_Shop_Actions::url( 'create', array( 'selected' => implode( ',', $ids ) ) );
	}

	/**
	 * Shop part of the do4u.eu box (Do4u_Editor::render_product_box): open suggestions, last changes, a link to create texts.
	 * Only links, because the box sits inside the product form.
	 *
	 * @param WP_Post $post Product.
	 */
	public static function render_box( $post ) {
		$pid = (int) $post->ID;
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'Save the product first.', 'do4u' ) . '</p>';
			return;
		}
		$tools = Do4u_Shop_Tools::all();
		foreach ( Do4u_Shop_Batches::open_for_product( $pid ) as $open ) {
			/* translators: %s: what was created, for example "Product texts" */
			echo '<p><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span> <a href="' . esc_url( Do4u_Shop_Actions::url( 'batch', array( 'batch' => $open[0] ) ) . '#do4u-shop-item-' . (int) $open[3] ) . '">' . esc_html( sprintf( __( 'Suggestions waiting: %s', 'do4u' ), $tools[ $open[1] ]['label'] ?? $open[1] ) ) . '</a></p>';
		}
		$history = array_slice( Do4u_Shop_Apply::history( $pid ), 0, 3 );
		if ( $history ) {
			$labels = Do4u_Shop_Tools::field_labels();
			echo '<p><strong>' . esc_html__( 'Last changes by do4u.eu', 'do4u' ) . '</strong></p><ul>';
			foreach ( $history as $h ) {
				echo '<li>' . esc_html( wp_date( get_option( 'date_format' ), (int) $h['time'] ) . ': ' . implode( ', ', array_intersect_key( $labels, (array) $h['changes'] ) ) ) . ( ! empty( $h['undone'] ) ? ' (' . esc_html__( 'undone', 'do4u' ) . ')' : '' ) . '</li>';
			}
			echo '</ul><p><a href="' . esc_url( Do4u_Shop_Actions::url( 'history', array( 'product' => $pid ) ) ) . '">' . esc_html__( 'History and undo', 'do4u' ) . '</a></p>';
		}
		echo '<p><a class="button" href="' . esc_url( Do4u_Shop_Actions::url( 'create', array( 'selected' => (string) $pid ) ) ) . '">' . esc_html__( 'Create texts for this product', 'do4u' ) . '</a></p>';
	}
}
