<?php
/**
 * Connection of this site to a do4u.eu account: the site widget and its session tokens.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Holds the site widget.
 *
 * The API key stays in WordPress and only ever travels from this server to do4u.eu. The browser gets a
 * session token that is short-lived and bound to one tool and one WordPress user. That is why the
 * session mode of do4u.eu fits wp-admin, which is the login area do4u.eu asks for.
 */
final class Do4u_Connection {

	const OPTION = 'do4u_wp_widget';

	const BUDGET_DAY   = 100;
	const BUDGET_MONTH = 1000;
	const SESSION_TTL  = 60;

	/**
	 * Origin of wp-admin. The iframe sits on the admin pages, so this is what the browser compares
	 * with frame-ancestors; the public address can differ.
	 *
	 * @return string Such as https://example.com or https://example.com:8443.
	 */
	public static function site_origin(): string {
		$parts = wp_parse_url( admin_url() );
		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
		if ( function_exists( 'idn_to_ascii' ) && preg_match( '/[^\x00-\x7f]/', $host ) ) {
			$host = (string) idn_to_ascii( $host );
		}
		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		return strtolower( (string) ( $parts['scheme'] ?? '' ) ) . '://' . $host . $port;
	}

	/**
	 * Why this site cannot embed widgets. do4u.eu accepts https origins only; a development site may
	 * still try, do4u.eu itself then decides.
	 *
	 * @return string Message, empty if all is well.
	 */
	public static function origin_problem(): string {
		if ( str_starts_with( self::site_origin(), 'https://' ) || 'local' === wp_get_environment_type() ) {
			return '';
		}
		return sprintf(
			/* translators: %s: address of the site */
			__( 'This site runs at %s without https. do4u.eu only allows embedding on https addresses, so the tools cannot open here. Switch wp-admin to https and connect again.', 'do4u' ),
			self::site_origin()
		);
	}

	/** @return array Stored widget data, empty if not connected. */
	public static function widget(): array {
		$widget = get_option( self::OPTION, array() );
		return is_array( $widget ) ? $widget : array();
	}

	/** @return string Public id of the widget, empty if not connected. */
	public static function widget_id(): string {
		return (string) ( self::widget()['id'] ?? '' );
	}

	/** @return bool Key and widget are there. */
	public static function is_connected(): bool {
		return Do4u_Secret::has() && '' !== self::widget_id();
	}

	/** @return bool The site moved after connecting: the browser would refuse the iframe. */
	public static function origin_changed(): bool {
		return self::is_connected() && (string) ( self::widget()['origin'] ?? '' ) !== self::site_origin();
	}

	/**
	 * Connects: checks the key against the account, then creates or finds the site widget.
	 *
	 * @param string $key   New key; empty keeps the saved one.
	 * @param int    $day   Credits per day.
	 * @param int    $month Credits per month.
	 * @throws Do4u_Error When something fails. A new key is only saved after the account accepted it.
	 */
	public static function connect( string $key, int $day, int $month ): void {
		$problem = self::origin_problem();
		if ( '' !== $problem ) {
			if ( '' !== $key && Do4u_Shop::active() && Do4u_Secret::valid_form( $key ) ) {
				// The shop tools need no widget: keep the key once the account accepted it, and say what works.
				Do4u_Api::request( 'GET', 'balance', null, 15, $key );
				Do4u_Secret::set( $key );
				throw new Do4u_Error( esc_html( $problem . ' ' . __( 'The API key is saved: the shop tools under do4u.eu > Shop work without https.', 'do4u' ) ), 'origin' );
			}
			throw new Do4u_Error( esc_html( $problem ), 'origin' );
		}
		if ( '' !== $key ) {
			if ( ! Do4u_Secret::valid_form( $key ) ) {
				throw new Do4u_Error( esc_html__( 'This is not a valid API key. A do4u.eu API key starts with d4u_.', 'do4u' ), 'bad_key' );
			}
			Do4u_Api::request( 'GET', 'balance', null, 15, $key );
			Do4u_Secret::set( $key );
		} elseif ( ! Do4u_Secret::has() ) {
			throw new Do4u_Error( esc_html__( 'Please enter your API key.', 'do4u' ), 'no_key' );
		}
		if ( ! self::adopt_existing_widget() ) {
			self::store( self::create_widget( $day, $month ) );
		} elseif ( (int) self::widget()['budget_day'] !== $day || (int) self::widget()['budget_month'] !== $month ) {
			self::change_budget( $day, $month );
		}
		Do4u_Catalog::forget();
	}

	/**
	 * Reuses a widget this site created earlier, so that saving the key again neither piles up widgets
	 * nor uses up the widget limit of the account.
	 *
	 * @return bool Found one and stored it.
	 */
	private static function adopt_existing_widget(): bool {
		$list   = Do4u_Api::request( 'GET', 'widgets' );
		$origin = self::site_origin();
		foreach ( (array) ( $list['widgets'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) || ( $item['mode'] ?? '' ) !== 'session' || ( $item['tools'] ?? null ) !== array( '*' ) || false === ( $item['no_store'] ?? true ) ) {
				continue;
			}
			if ( ( $item['origins'] ?? null ) === array( $origin ) && self::valid_id( (string) ( $item['id'] ?? '' ) ) ) {
				self::store(
					array(
						'id'           => (string) $item['id'],
						'origin'       => $origin,
						'budget_day'   => (int) ( $item['budget_day'] ?? 0 ),
						'budget_month' => (int) ( $item['budget_month'] ?? 0 ),
					)
				);
				return true;
			}
		}
		return false;
	}

	/**
	 * Creates the widget. The token in the answer is not kept: with session mode and the API key it is not needed.
	 *
	 * @param int $day   Budget per day.
	 * @param int $month Budget per month.
	 * @return array Data for store().
	 * @throws Do4u_Error When do4u.eu refuses.
	 */
	private static function create_widget( int $day, int $month ): array {
		$origin = self::site_origin();
		try {
			$answer = Do4u_Api::request(
				'POST',
				'widgets',
				array(
					'name'               => mb_substr( 'WordPress: ' . (string) wp_parse_url( $origin, PHP_URL_HOST ), 0, 80 ),
					'tools'              => '*',
					'mode'               => 'session',
					'origins'            => array( $origin ),
					'confirm_login_area' => true,
					'budget_day'         => $day,
					'budget_month'       => $month,
					'no_store'           => true,
				)
			);
		} catch ( Do4u_Error $error ) {
			$friendly = self::friendly( $error );
			throw $friendly;
		}
		$id = (string) ( $answer['id'] ?? '' );
		if ( ! self::valid_id( $id ) ) {
			throw new Do4u_Error( esc_html__( 'do4u.eu sent an unexpected answer.', 'do4u' ), 'invalid_response' );
		}
		return array(
			'id'           => $id,
			'origin'       => $origin,
			'budget_day'   => (int) ( $answer['budget_day'] ?? $day ),
			'budget_month' => (int) ( $answer['budget_month'] ?? $month ),
		);
	}

	/**
	 * Replaces API messages the user can act on with a translated one.
	 *
	 * @param Do4u_Error $error Error.
	 * @return Do4u_Error
	 */
	private static function friendly( Do4u_Error $error ): Do4u_Error {
		if ( 'limit' === $error->api_code ) {
			return new Do4u_Error( esc_html__( 'Your do4u.eu plan allows no further widget. Remove one under Widgets in your do4u.eu account and connect again.', 'do4u' ), 'limit', $error->status );
		}
		if ( 'origin' === $error->api_code ) {
			return new Do4u_Error( esc_html__( 'do4u.eu did not accept the address of this site. It must be a public https address.', 'do4u' ), 'origin', $error->status );
		}
		return $error;
	}

	/**
	 * @param string $id Text.
	 * @return bool
	 */
	private static function valid_id( string $id ): bool {
		return (bool) preg_match( '/^w_[0-9a-f]{16}$/D', $id );
	}

	/**
	 * @param array $widget Widget data.
	 */
	private static function store( array $widget ): void {
		update_option( self::OPTION, $widget, false );
	}

	/**
	 * Changes the budget. The API cannot edit a widget, so a new one replaces the old one. It is created
	 * first, so that a refusal leaves the site connected; only if the plan limit is the reason, the old
	 * one has to go first.
	 *
	 * @param int $day   Credits per day.
	 * @param int $month Credits per month.
	 * @throws Do4u_Error When do4u.eu refuses.
	 */
	public static function change_budget( int $day, int $month ): void {
		$old = self::widget_id();
		try {
			$new = self::create_widget( $day, $month );
		} catch ( Do4u_Error $error ) {
			if ( 'limit' !== $error->api_code || '' === $old ) {
				throw $error;
			}
			Do4u_Api::request( 'DELETE', 'widgets/' . $old );
			delete_option( self::OPTION );
			$old = '';
			$new = self::create_widget( $day, $month );
		}
		self::store( $new );
		if ( '' !== $old ) {
			try {
				Do4u_Api::request( 'DELETE', 'widgets/' . $old );
			} catch ( Do4u_Error $error ) {
				unset( $error ); // A leftover widget has no token anywhere and can be removed in the account.
			}
		}
	}

	/**
	 * Revokes the widget and forgets key and widget.
	 *
	 * @return bool Whether do4u.eu confirmed the revocation (or nothing had to be revoked).
	 */
	public static function disconnect(): bool {
		$revoked = true;
		$id      = self::widget_id();
		if ( '' !== $id ) {
			try {
				Do4u_Api::request( 'DELETE', 'widgets/' . $id );
			} catch ( Do4u_Error $error ) {
				$revoked = 'not_found' === $error->api_code;
			}
		}
		delete_option( self::OPTION );
		Do4u_Secret::clear();
		Do4u_Catalog::forget();
		return $revoked;
	}

	/**
	 * Fetches a session token for one tool and one WordPress user.
	 *
	 * @param string $tool    Public tool name.
	 * @param int    $user_id WordPress user id; do4u.eu stores only a hash of it.
	 * @return array Keys token and expires_at.
	 * @throws Do4u_Error When there is no connection or do4u.eu refuses.
	 */
	public static function session( string $tool, int $user_id ): array {
		$id = self::widget_id();
		if ( '' === $id ) {
			throw new Do4u_Error( esc_html__( 'This site is not connected to do4u.eu yet. An administrator can connect it under do4u.eu > Settings.', 'do4u' ), 'not_connected' );
		}
		try {
			$answer = Do4u_Api::request(
				'POST',
				'widgets/session',
				array(
					'widget'      => $id,
					'tool'        => $tool,
					// A pseudonym per site and user, never the WordPress user id itself.
					'subject'     => 'wp_' . substr( hash_hmac( 'sha256', (string) $user_id, wp_salt( 'auth' ) ), 0, 32 ),
					'ttl_minutes' => self::SESSION_TTL,
				)
			);
		} catch ( Do4u_Error $error ) {
			if ( 'not_found' === $error->api_code ) {
				// Revoked in the account. Stale data would only produce the same error on every click.
				delete_option( self::OPTION );
				throw new Do4u_Error( esc_html__( 'The widget of this site no longer exists at do4u.eu. An administrator can connect the site again under do4u.eu > Settings.', 'do4u' ), 'not_connected', 404 );
			}
			if ( 'tool_not_allowed' === $error->api_code ) {
				throw new Do4u_Error( esc_html__( 'This tool is not available.', 'do4u' ), 'tool_not_allowed', 403 );
			}
			throw $error;
		}
		$token = (string) ( $answer['token'] ?? '' );
		if ( ! preg_match( '/^d4s_[A-Za-z0-9_.-]+$/D', $token ) ) {
			throw new Do4u_Error( esc_html__( 'do4u.eu sent an unexpected answer.', 'do4u' ), 'invalid_response' );
		}
		return array(
			'token'      => $token,
			'expires_at' => sanitize_text_field( (string) ( $answer['expires_at'] ?? '' ) ),
		);
	}

	/**
	 * Balance of the account.
	 *
	 * @return array Keys credits, free, paid, free_until, paid_valid_until.
	 */
	public static function balance(): array {
		return Do4u_Api::request( 'GET', 'balance', null, 8 );
	}

	/**
	 * Budget and use of the site widget.
	 *
	 * @return array Answer of GET /widgets/{id}.
	 */
	public static function usage(): array {
		return Do4u_Api::request( 'GET', 'widgets/' . self::widget_id(), null, 8 );
	}
}
