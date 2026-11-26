<?php
/**
 * SEO title and meta description of a product: in the fields of an active SEO plugin, otherwise in own fields.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/** Where SEO title and meta description of a product live. */
final class Do4u_Shop_Seo {

	/** Own fields, used when no supported SEO plugin is active. */
	const OWN_TITLE = '_do4u_shop_seo_title';
	const OWN_DESC  = '_do4u_shop_meta_description';

	/**
	 * The active SEO plugin that the plugin writes to.
	 *
	 * @return array ['id', 'name', 'title' => meta key, 'desc' => meta key, 'focus' => meta key or ''].
	 */
	public static function target() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return array(
				'id'    => 'yoast',
				'name'  => 'Yoast SEO',
				'title' => '_yoast_wpseo_title',
				'desc'  => '_yoast_wpseo_metadesc',
				'focus' => '_yoast_wpseo_focuskw',
			);
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return array(
				'id'    => 'rankmath',
				'name'  => 'Rank Math',
				'title' => 'rank_math_title',
				'desc'  => 'rank_math_description',
				'focus' => 'rank_math_focus_keyword',
			);
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return array(
				'id'    => 'seopress',
				'name'  => 'SEOPress',
				'title' => '_seopress_titles_title',
				'desc'  => '_seopress_titles_desc',
				'focus' => '_seopress_analysis_target_kw',
			);
		}
		if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
			return array(
				'id'    => 'tsf',
				'name'  => 'The SEO Framework',
				'title' => '_genesis_title',
				'desc'  => '_genesis_description',
				'focus' => '',
			);
		}
		return array(
			'id'    => 'own',
			'name'  => __( 'do4u.eu fields', 'do4u' ),
			'title' => self::OWN_TITLE,
			'desc'  => self::OWN_DESC,
			'focus' => '',
		);
	}

	/**
	 * Whether a supported SEO plugin is active.
	 *
	 * @return bool
	 */
	public static function has_plugin() {
		return 'own' !== self::target()['id'];
	}

	/**
	 * Current value of a field.
	 *
	 * @param int    $product_id Product.
	 * @param string $field      seo_title or meta_description.
	 * @return string
	 */
	public static function get( $product_id, $field ) {
		$t = self::target();
		return (string) get_post_meta( $product_id, 'seo_title' === $field ? $t['title'] : $t['desc'], true );
	}

	/**
	 * Writes a field through the product object, so that WooCommerce and the SEO plugin see a normal save.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $field   seo_title or meta_description.
	 * @param string     $value   Plain text.
	 */
	public static function set( $product, $field, $value ) {
		$t   = self::target();
		$key = 'seo_title' === $field ? $t['title'] : $t['desc'];
		if ( '' === $value ) {
			$product->delete_meta_data( $key );
		} else {
			$product->update_meta_data( $key, sanitize_text_field( $value ) );
		}
	}

	/**
	 * Focus keyword of the SEO plugin, if one is set (first one only).
	 *
	 * @param int $product_id Product.
	 * @return string
	 */
	public static function focus_keyword( $product_id ) {
		$t = self::target();
		if ( '' === $t['focus'] ) {
			return '';
		}
		$kw = (string) get_post_meta( $product_id, $t['focus'], true );
		$kw = trim( explode( ',', $kw )[0] );
		return mb_substr( sanitize_text_field( $kw ), 0, 100 );
	}

	/** Hooks for the optional output of the own fields on product pages. */
	public static function boot_frontend() {
		if ( self::has_plugin() || ! Do4u_Shop_Settings::get( 'output_meta' ) ) {
			return;
		}
		add_filter( 'pre_get_document_title', array( self::class, 'document_title' ), 20 );
		add_action( 'wp_head', array( self::class, 'meta_tag' ), 1 );
	}

	/**
	 * Own SEO title on a product page.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function document_title( $title ) {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $title;
		}
		$own = (string) get_post_meta( get_queried_object_id(), self::OWN_TITLE, true );
		return '' !== $own ? esc_html( $own ) : $title;
	}

	/** Own meta description on a product page. */
	public static function meta_tag() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$desc = (string) get_post_meta( get_queried_object_id(), self::OWN_DESC, true );
		if ( '' !== $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
	}
}
