<?php
/**
 * Plugin bootstrap: hooks, language handling, upgrade from 0.x.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin into WordPress.
 *
 * Everything hangs off is_admin(): the plugin must add nothing to the public part of a website,
 * so no script, style, cookie or shortcode can ever reach a visitor.
 */
final class Do4u_Plugin {

	const VERSION_OPTION = 'do4u_wp_version';

	/** Languages the do4u.eu interface knows. Anything else gets English inside the iframe. */
	const LANGS = array( 'en', 'de', 'pl', 'fr', 'es', 'it', 'nl', 'cs', 'sk', 'bg', 'da', 'el', 'et', 'fi', 'ga', 'hr', 'hu', 'lt', 'lv', 'mt', 'pt', 'ro', 'sl', 'sv', 'uk', 'nb' );

	/** Registers all hooks. */
	public static function boot(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_init', array( self::class, 'maybe_upgrade' ) );
		Do4u_Admin::boot();
		Do4u_Ajax::boot();
		Do4u_Lists::boot();
		Do4u_Editor::boot();
		add_filter( 'plugin_action_links_' . plugin_basename( DO4U_WP_FILE ), array( self::class, 'action_links' ) );
	}

	/**
	 * Links in the plugin list: settings, and the shop tools when they run.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ): array {
		$own = array();
		if ( current_user_can( 'manage_options' ) ) {
			$own[] = '<a href="' . esc_url( admin_url( 'admin.php?page=do4u-settings' ) ) . '">' . esc_html__( 'Settings', 'do4u' ) . '</a>';
		}
		if ( Do4u_Shop::usable() ) {
			$own[] = '<a href="' . esc_url( admin_url( 'admin.php?page=' . Do4u_Shop::PAGE ) ) . '">' . esc_html__( 'Shop', 'do4u' ) . '</a>';
		}
		return array_merge( $own, (array) $links );
	}

	/**
	 * Capability needed to use the tools at all. Editing rights on single items are checked separately.
	 *
	 * @return string
	 */
	public static function capability(): string {
		return (string) apply_filters( 'do4u_wp_capability', 'edit_posts' );
	}

	/**
	 * Post types that get the editor tools and the list actions.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		return (array) apply_filters( 'do4u_wp_post_types', array( 'post', 'page' ) );
	}

	/**
	 * Language for the iframe, taken from the WordPress user: de_DE_formal, pt_BR or nb_NO all map
	 * to the part before the underscore; do4u.eu does not know the regional variants.
	 *
	 * @return string
	 */
	public static function iframe_lang(): string {
		$code = strtolower( (string) strtok( (string) get_user_locale(), '_-' ) );
		return in_array( $code, self::LANGS, true ) ? $code : 'en';
	}

	/** Runs once after an update: removes what version 0.x left behind. The API key stays valid. */
	public static function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) === DO4U_WP_VERSION ) {
			return;
		}
		// 0.x kept jobs, previews and locks per user; 1.0 keeps none of that.
		wp_clear_scheduled_hook( 'do4u_wp_cleanup' );
		delete_metadata( 'user', 0, '_do4u_wp_state', '', true );
		// 1.1 took over the single plugin "do4u.eu for WooCommerce": its key and defaults, if this site had it.
		Do4u_Shop::migrate();
		update_option( self::VERSION_OPTION, DO4U_WP_VERSION, false );
	}

	/** Deactivating keeps the connection, so reactivating needs no new setup. */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'do4u_wp_cleanup' );
		Do4u_Catalog::forget();
	}
}
