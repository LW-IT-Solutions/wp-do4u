<?php
/**
 * Catalog of all tools.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * The tools with a use in WordPress (Do4u_Places::plugin_tools), checked against GET /api/v1/tools for
 * availability and price. A new do4u.eu tool only appears after someone decided where it belongs.
 */
final class Do4u_Catalog {

	const CACHE = 'do4u_wp_tools';

	/** Forgets the cached list, for instance when the account changes. */
	public static function forget(): void {
		delete_transient( self::CACHE );
	}

	/**
	 * Names of the categories in display order.
	 *
	 * @return array Category id => label.
	 */
	public static function categories(): array {
		return array(
			'text'    => __( 'Writing & language', 'do4u' ),
			'website' => __( 'Website & visibility', 'do4u' ),
			'image'   => __( 'Images & accessibility', 'do4u' ),
			'service' => __( 'Customers & content', 'do4u' ),
			'other'   => __( 'More tools', 'do4u' ),
		);
	}

	/**
	 * Titles and categories of the known tools.
	 *
	 * @return array Name => array( category, title ).
	 */
	public static function known(): array {
		static $known = null;
		if ( null === $known ) {
			$known = require __DIR__ . '/catalog-data.php';
		}
		return $known;
	}

	/**
	 * Valid form of a public tool name; the same rule as in the iframe address at do4u.eu.
	 *
	 * @param string $name Text.
	 * @return bool
	 */
	public static function valid_name( string $name ): bool {
		return (bool) preg_match( '/^[a-z0-9_]{2,40}$/D', $name );
	}

	/**
	 * Display title of a tool.
	 *
	 * @param string $name Public name.
	 * @return string
	 */
	public static function title( string $name ): string {
		$known = self::known();
		return isset( $known[ $name ] ) ? (string) $known[ $name ][1] : ucfirst( str_replace( '_', ' ', $name ) );
	}

	/**
	 * The catalog. What comes from the API is only data: names that do not fit the form are dropped
	 * and everything is escaped when it is displayed. GET /widgets says what can be embedded; that can
	 * include tools without an entry in GET /tools, and they belong in the catalog too.
	 *
	 * @return array List of name, title, category, available, widget, unpriced, credits.
	 * @throws Do4u_Error When the list is not cached and cannot be fetched.
	 */
	public static function all(): array {
		$tools = get_transient( self::CACHE );
		if ( ! is_array( $tools ) ) {
			$tools = self::fetch();
			set_transient( self::CACHE, $tools, 6 * HOUR_IN_SECONDS );
		}
		$known  = self::known();
		$result = array();
		foreach ( $tools as $tool ) {
			$tool['title']    = self::title( $tool['name'] );
			$tool['category'] = isset( $known[ $tool['name'] ] ) ? (string) $known[ $tool['name'] ][0] : 'other';
			$result[]         = $tool;
		}
		usort( $result, static fn( array $a, array $b ): int => strcasecmp( $a['title'], $b['title'] ) );
		return $result;
	}

	/**
	 * Fetches and normalizes the lists.
	 *
	 * @return array
	 * @throws Do4u_Error When GET /tools fails. A failing GET /widgets only means that nothing is known about embedding.
	 */
	private static function fetch(): array {
		$answer = Do4u_Api::request( 'GET', 'tools' );
		try {
			$list       = Do4u_Api::request( 'GET', 'widgets' );
			$embeddable = array_values( array_filter( (array) ( $list['tools'] ?? array() ), 'is_string' ) );
		} catch ( Do4u_Error $error ) {
			$embeddable = null;
		}
		$tools = array();
		foreach ( (array) ( $answer['tools'] ?? array() ) as $tool ) {
			$name = is_array( $tool ) ? (string) ( $tool['name'] ?? '' ) : '';
			if ( ! self::valid_name( $name ) ) {
				continue;
			}
			$price          = is_array( $tool['price'] ?? null ) ? $tool['price'] : array();
			$tools[ $name ] = array(
				'name'      => $name,
				'available' => ! empty( $tool['available'] ),
				'widget'    => null === $embeddable || in_array( $name, $embeddable, true ),
				'unpriced'  => false,
				'credits'   => ( $price['type'] ?? '' ) === 'fixed' ? (int) ( $price['credits'] ?? 0 ) : null,
			);
		}
		foreach ( (array) $embeddable as $name ) {
			if ( ! isset( $tools[ $name ] ) && self::valid_name( $name ) ) {
				$tools[ $name ] = array(
					'name'      => $name,
					'available' => true,
					'widget'    => true,
					'unpriced'  => true,
					'credits'   => null,
				);
			}
		}
		return array_values( array_intersect_key( $tools, Do4u_Places::plugin_tools() ) );
	}

	/**
	 * Price note of a catalog entry.
	 *
	 * @param array $item Catalog entry.
	 * @return string
	 */
	public static function price_text( array $item ): string {
		if ( empty( $item['widget'] ) ) {
			return __( 'only at do4u.eu', 'do4u' );
		}
		if ( empty( $item['available'] ) ) {
			return __( 'not in your plan', 'do4u' );
		}
		if ( ! empty( $item['unpriced'] ) ) {
			return '';
		}
		if ( null === $item['credits'] ) {
			return __( 'credits by usage', 'do4u' );
		}
		if ( 0 === $item['credits'] ) {
			return __( 'free', 'do4u' );
		}
		return sprintf(
			/* translators: %s: number of credits */
			_n( '%s credit', '%s credits', $item['credits'], 'do4u' ),
			number_format_i18n( $item['credits'] )
		);
	}

	/**
	 * Price note of a tool by name, empty if the catalog cannot be read: a missing note must not block the tool.
	 *
	 * @param string $name Public name.
	 * @return string
	 */
	public static function price_of( string $name ): string {
		try {
			foreach ( self::all() as $item ) {
				if ( $item['name'] === $name ) {
					return self::price_text( $item );
				}
			}
		} catch ( Do4u_Error $error ) {
			return '';
		}
		return '';
	}

	/**
	 * Filters the catalog.
	 *
	 * @param array  $tools    Result of all().
	 * @param string $search   Search text.
	 * @param string $category Category id or empty.
	 * @return array
	 */
	public static function filter( array $tools, string $search, string $category ): array {
		$search = mb_strtolower( trim( $search ) );
		return array_values(
			array_filter(
				$tools,
				static function ( array $tool ) use ( $search, $category ): bool {
					if ( '' !== $category && $tool['category'] !== $category ) {
						return false;
					}
					return '' === $search || str_contains( mb_strtolower( $tool['title'] . ' ' . str_replace( '_', ' ', $tool['name'] ) ), $search );
				}
			)
		);
	}
}
