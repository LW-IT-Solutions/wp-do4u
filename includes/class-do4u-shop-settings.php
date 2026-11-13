<?php
/**
 * Settings, rights and languages.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/** Saved settings of the plugin (one option, not autoloaded) and small helpers around them. */
final class Do4u_Shop_Settings {

	const OPTION = 'do4u_shop_settings';

	/** Languages do4u.eu writes product texts in (GET /api/v1/tools, option "target"). */
	const TARGETS = array( 'en', 'de', 'pl', 'fr', 'es', 'it', 'nl', 'cs', 'sk', 'uk', 'bg', 'da', 'el', 'et', 'fi', 'ga', 'hr', 'hu', 'lt', 'lv', 'mt', 'pt', 'ro', 'sl', 'sv' );

	/** Languages a batch itself accepts (messages, and the language of alt texts). */
	const BATCH_LANGUAGES = array( 'en', 'de', 'pl' );

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'tone'          => 'neutral',
			'length'        => 'medium',
			'channel'       => 'shop',
			'target'        => 'site',
			'bullets'       => 1,
			'batch_size'    => 20,
			'delete_remote' => 1,
			'output_meta'   => 0,
		);
	}

	/**
	 * All settings, completed with the defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * One setting.
	 *
	 * @param string $name Name.
	 * @return mixed
	 */
	public static function get( $name ) {
		$all = self::all();
		return isset( $all[ $name ] ) ? $all[ $name ] : null;
	}

	/**
	 * Cleans values from the settings form.
	 *
	 * @param array $in Unslashed input.
	 * @return array
	 */
	public static function sanitize( array $in ) {
		$d   = self::defaults();
		$out = array();
		$out['tone']          = in_array( $in['tone'] ?? '', array( 'neutral', 'friendly', 'persuasive', 'formal' ), true ) ? $in['tone'] : $d['tone'];
		$out['length']        = in_array( $in['length'] ?? '', array( 'short', 'medium', 'long' ), true ) ? $in['length'] : $d['length'];
		$out['channel']       = in_array( $in['channel'] ?? '', array( 'shop', 'marketplace' ), true ) ? $in['channel'] : $d['channel'];
		$target               = sanitize_key( $in['target'] ?? '' );
		$out['target']        = ( 'site' === $target || 'auto' === $target || in_array( $target, self::TARGETS, true ) ) ? $target : $d['target'];
		$out['bullets']       = empty( $in['bullets'] ) ? 0 : 1;
		$out['batch_size']    = max( 1, min( 200, absint( $in['batch_size'] ?? $d['batch_size'] ) ) );
		$out['delete_remote'] = empty( $in['delete_remote'] ) ? 0 : 1;
		$out['output_meta']   = empty( $in['output_meta'] ) ? 0 : 1;
		return $out;
	}

	/**
	 * Capability needed to use the plugin (create batches, see suggestions). Rights for each product are checked in addition.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability needed to use the shop tools of do4u.eu.
		 *
		 * @param string $capability Default manage_woocommerce.
		 */
		return (string) apply_filters( 'do4u_shop_capability', 'manage_woocommerce' );
	}

	/**
	 * Whether the current user may use the plugin.
	 *
	 * @return bool
	 */
	public static function can_use() {
		return current_user_can( self::capability() );
	}

	/**
	 * Two-letter code of the shop language.
	 *
	 * @return string
	 */
	public static function site_language() {
		return strtolower( substr( (string) get_locale(), 0, 2 ) );
	}

	/**
	 * Language of the texts for an API option "target".
	 *
	 * @param bool $auto_allowed Whether the tool knows "auto" (language of the product data).
	 * @return string
	 */
	public static function target( $auto_allowed = true ) {
		$t = (string) self::get( 'target' );
		if ( 'auto' === $t ) {
			return $auto_allowed ? 'auto' : self::fallback_target();
		}
		if ( 'site' === $t ) {
			$t = self::site_language();
		}
		if ( in_array( $t, self::TARGETS, true ) ) {
			return $t;
		}
		return $auto_allowed ? 'auto' : 'en';
	}

	/**
	 * Shop language if do4u.eu writes it, otherwise English.
	 *
	 * @return string
	 */
	private static function fallback_target() {
		$l = self::site_language();
		return in_array( $l, self::TARGETS, true ) ? $l : 'en';
	}

	/**
	 * Language of a batch: messages of do4u.eu and the language of alt texts. Only English, German and Polish exist.
	 *
	 * @return string
	 */
	public static function batch_language() {
		$t = self::target( false );
		if ( in_array( $t, self::BATCH_LANGUAGES, true ) ) {
			return $t;
		}
		$l = strtolower( substr( (string) get_user_locale(), 0, 2 ) );
		return in_array( $l, self::BATCH_LANGUAGES, true ) ? $l : 'en';
	}

	/**
	 * Names of the languages, in the language itself.
	 *
	 * @return array Code => name.
	 */
	public static function language_names() {
		return array(
			'en' => 'English',
			'de' => 'Deutsch',
			'pl' => 'Polski',
			'fr' => 'Français',
			'es' => 'Español',
			'it' => 'Italiano',
			'nl' => 'Nederlands',
			'cs' => 'Čeština',
			'sk' => 'Slovenčina',
			'uk' => 'Українська',
			'bg' => 'Български',
			'da' => 'Dansk',
			'el' => 'Ελληνικά',
			'et' => 'Eesti',
			'fi' => 'Suomi',
			'ga' => 'Gaeilge',
			'hr' => 'Hrvatski',
			'hu' => 'Magyar',
			'lt' => 'Lietuvių',
			'lv' => 'Latviešu',
			'mt' => 'Malti',
			'pt' => 'Português',
			'ro' => 'Română',
			'sl' => 'Slovenščina',
			'sv' => 'Svenska',
		);
	}
}
