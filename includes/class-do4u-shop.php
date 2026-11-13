<?php
/**
 * Shop module: batch tools for WooCommerce products. Loaded only when WooCommerce is active.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether the shop tools run and wires them in.
 *
 * WooCommerce is not required: without it the module registers nothing but the compatibility declaration, which
 * WooCommerce alone would call. The tools use the API key of the do4u.eu connection (do4u.eu > Settings); they need
 * no widget, because batches run between this server and do4u.eu.
 */
final class Do4u_Shop {

	/** Slug of the admin page do4u.eu > Shop. */
	const PAGE = 'do4u-shop';

	/** Oldest WooCommerce version the module was tested with. */
	const MIN_WC = '9.0';

	/** Options of the former single plugin "do4u.eu for WooCommerce", read once for the takeover. */
	const OLD_KEY      = 'do4uwc_key_cipher';
	const OLD_SETTINGS = 'do4uwc_settings';

	/**
	 * State after plugins_loaded: active, no_wc, old_wc or old_plugin.
	 *
	 * @var string
	 */
	private static $state = 'no_wc';

	/** Registers the hooks. */
	public static function boot(): void {
		add_action( 'before_woocommerce_init', array( self::class, 'declare_compatibility' ) );
		add_action( 'plugins_loaded', array( self::class, 'loaded' ), 20 );
	}

	/** Declares compatibility with the WooCommerce order tables (HPOS) and the block checkout. The plugin touches neither. */
	public static function declare_compatibility(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DO4U_WP_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', DO4U_WP_FILE, true );
		}
	}

	/** After all plugins are loaded: start the module if WooCommerce is there and nothing stands in the way. */
	public static function loaded(): void {
		if ( ! class_exists( 'WooCommerce', false ) ) {
			self::$state = 'no_wc';
			return;
		}
		if ( defined( 'DO4UWC_VERSION' ) || class_exists( 'Do4uwc_Plugin', false ) ) {
			// The former single plugin is still active. Both would write the same products; it has to go first.
			self::$state = 'old_plugin';
		} elseif ( ! defined( 'WC_VERSION' ) || version_compare( (string) WC_VERSION, self::MIN_WC, '<' ) ) {
			self::$state = 'old_wc';
		} else {
			self::$state = 'active';
		}
		if ( 'active' !== self::$state ) {
			if ( is_admin() ) {
				add_action( 'admin_notices', array( self::class, 'notice' ) );
			}
			return;
		}
		if ( is_admin() ) {
			Do4u_Shop_Admin::boot();
			Do4u_Shop_Actions::boot();
			Do4u_Shop_Products::boot();
		} else {
			add_action( 'wp', array( 'Do4u_Shop_Seo', 'boot_frontend' ) );
		}
	}

	/**
	 * Whether the shop tools run on this request.
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return 'active' === self::$state;
	}

	/**
	 * Whether the current user may use the shop tools now.
	 *
	 * @return bool
	 */
	public static function usable(): bool {
		return self::active() && Do4u_Shop_Settings::can_use();
	}

	/** Explains why the shop tools are off. Only on the plugin list and the do4u.eu pages, only for administrators. */
	public static function notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		if ( ! current_user_can( 'activate_plugins' ) || ( 'plugins' !== $id && ! str_contains( $id, '_page_do4u' ) ) ) {
			return;
		}
		if ( 'old_plugin' === self::$state ) {
			$text = __( 'The plugin "do4u.eu for WooCommerce" is still active. Its tools are now part of do4u.eu under do4u.eu > Shop, using the connection of do4u.eu. Please deactivate and delete "do4u.eu for WooCommerce"; until then the shop tools of do4u.eu stay off.', 'do4u' );
		} else {
			/* translators: %s: version number of WooCommerce */
			$text = sprintf( __( 'The shop tools of do4u.eu need WooCommerce %s or newer. Please update WooCommerce.', 'do4u' ), self::MIN_WC );
		}
		printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( $text ) );
	}

	/**
	 * Takes over what the former single plugin stored on a test or early site, once (called from the version upgrade):
	 * the API key, if do4u.eu has none yet and the stored value can be read, and the defaults of the shop tools.
	 * Batches and histories of the single plugin stay with it; its own uninstall removes them.
	 */
	public static function migrate(): void {
		if ( ! Do4u_Secret::has() ) {
			$old = get_option( self::OLD_KEY, '' );
			if ( is_string( $old ) && '' !== $old ) {
				// Same format and same salts: the value can be copied. If it cannot be read, it is dropped again.
				update_option( Do4u_Secret::OPTION, $old, false );
				try {
					if ( ! Do4u_Secret::valid_form( Do4u_Secret::get() ) ) {
						delete_option( Do4u_Secret::OPTION );
					}
				} catch ( Do4u_Error $error ) {
					unset( $error );
					delete_option( Do4u_Secret::OPTION );
				}
			}
		}
		$settings = get_option( self::OLD_SETTINGS, null );
		if ( is_array( $settings ) && false === get_option( Do4u_Shop_Settings::OPTION, false ) ) {
			update_option( Do4u_Shop_Settings::OPTION, Do4u_Shop_Settings::sanitize( $settings ), false );
		}
	}
}
