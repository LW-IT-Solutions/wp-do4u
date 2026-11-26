<?php
/**
 * Ajax endpoints.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/** Only for logged-in users: visitors have no hook here on purpose. */
final class Do4u_Ajax {

	const NONCE = 'do4u_wp';

	/** Registers the endpoints. */
	public static function boot(): void {
		add_action( 'wp_ajax_do4u_wp_open', array( self::class, 'open' ) );
		add_action( 'wp_ajax_do4u_wp_session', array( self::class, 'session' ) );
		add_action( 'wp_ajax_do4u_wp_apply', array( self::class, 'apply' ) );
		add_action( 'wp_ajax_do4u_wp_balance', array( self::class, 'balance' ) );
		add_action( 'wp_ajax_do4u_wp_job_start', array( self::class, 'job_start' ) );
		add_action( 'wp_ajax_do4u_wp_job_status', array( self::class, 'job_status' ) );
		add_action( 'wp_ajax_do4u_wp_find_posts', array( self::class, 'find_posts' ) );
		add_action( 'wp_ajax_do4u_wp_newsletter_fill', array( self::class, 'newsletter_fill' ) );
	}

	/** Nonce and base right, the same for every endpoint. */
	private static function guard(): void {
		check_ajax_referer( self::NONCE );
		if ( ! current_user_can( Do4u_Plugin::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to use do4u.eu tools.', 'do4u' ) ), 403 );
		}
	}

	/**
	 * Answers with the error of an exception. The API key cannot be in there: no message contains it.
	 *
	 * @param Do4u_Error $error Error.
	 */
	private static function fail( Do4u_Error $error ): void {
		$status = $error instanceof Do4u_Denied ? 403 : ( $error->status >= 400 && $error->status < 500 ? $error->status : 502 );
		wp_send_json_error(
			array(
				'message' => $error->plain(),
				'code'    => $error->api_code,
			),
			$status
		);
	}

	/**
	 * Data of a request parameter without slashes.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function field( string $name, string $kind = 'text' ): string {
		// guard() checked the nonce already; checking it where the input is read keeps that visible. The value is sanitized
		// as it is read: one line of text by default, several lines for a text result, allowed post HTML for an HTML result.
		// The callers narrow it further per target (sanitize_key, absint, Do4u_Apply).
		check_ajax_referer( self::NONCE );
		if ( ! isset( $_POST[ $name ] ) || ! is_string( $_POST[ $name ] ) ) {
			return '';
		}
		if ( 'html' === $kind ) {
			return wp_kses_post( wp_unslash( $_POST[ $name ] ) );
		}
		if ( 'textarea' === $kind ) {
			return sanitize_textarea_field( wp_unslash( $_POST[ $name ] ) );
		}
		return sanitize_text_field( wp_unslash( $_POST[ $name ] ) );
	}

	/**
	 * Context of a place from the request.
	 *
	 * @return array
	 */
	private static function context(): array {
		return array(
			'id'   => absint( self::field( 'context_id' ) ),
			'ids'  => array_map( 'absint', explode( ',', self::field( 'context_ids' ) ) ),
			'main' => absint( self::field( 'context_main' ) ),
		);
	}

	/** Everything the browser needs to open a tool: fresh session token, prefill, takeovers. */
	public static function open(): void {
		self::guard();
		try {
			$place = Do4u_Places::build( sanitize_key( self::field( 'tool' ) ), sanitize_key( self::field( 'preset' ) ), self::context() );
			if ( 'job' === $place['mode'] ) {
				wp_send_json_success( $place + array( 'request' => bin2hex( random_bytes( 16 ) ) ) );
				return;
			}
			$session = Do4u_Connection::session( $place['tool'], get_current_user_id() );
		} catch ( Do4u_Error $error ) {
			self::fail( $error );
			return;
		}
		wp_send_json_success(
			$place + array(
				'widget'      => Do4u_Connection::widget_id(),
				'token'       => $session['token'],
				'lang'        => Do4u_Plugin::iframe_lang(),
				'theme'       => 'light',
				'base'        => Do4u_Api::site(),
				'session_url' => add_query_arg(
					array(
						'action'      => 'do4u_wp_session',
						'tool'        => $place['tool'],
						'_ajax_nonce' => wp_create_nonce( self::NONCE ),
					),
					admin_url( 'admin-ajax.php' )
				),
			)
		);
	}

	/** Renews an expired session token. The widget loader calls this address and reads "token" from the answer. */
	public static function session(): void {
		self::guard();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- guard() checked the nonce.
		$tool = isset( $_GET['tool'] ) ? sanitize_key( wp_unslash( $_GET['tool'] ) ) : '';
		if ( ! Do4u_Catalog::valid_name( $tool ) || ! isset( Do4u_Places::plugin_tools()[ $tool ] ) ) {
			wp_send_json_error( array( 'message' => __( 'This tool is not available.', 'do4u' ) ), 400 );
			return;
		}
		try {
			$session = Do4u_Connection::session( $tool, get_current_user_id() );
		} catch ( Do4u_Error $error ) {
			self::fail( $error );
			return;
		}
		wp_send_json( array( 'token' => $session['token'] ) );
	}

	/** Takes a result over, see Do4u_Apply. */
	public static function apply(): void {
		self::guard();
		try {
			$answer = Do4u_Apply::run(
				array(
					'target'      => sanitize_key( self::field( 'target' ) ),
					'object_id'   => absint( self::field( 'object_id' ) ),
					'value'       => self::field( 'value', 'html' === sanitize_key( self::field( 'format' ) ) ? 'html' : 'textarea' ),
					'format'      => sanitize_key( self::field( 'format' ) ),
					'store'       => '1' === self::field( 'store' ),
					'overwrite'   => '1' === self::field( 'overwrite' ),
					'split_title' => '1' === self::field( 'split_title' ),
				)
			);
		} catch ( Do4u_Error $error ) {
			self::fail( $error );
			return;
		}
		wp_send_json_success( $answer );
	}

	/** Starts an alt text job. Only reached by the click on start in the dialog; opening the dialog sends nothing. */
	public static function job_start(): void {
		self::guard();
		try {
			$answer = Do4u_Jobs::start_alt( absint( self::field( 'object_id' ) ), self::field( 'request' ) );
		} catch ( Do4u_Error $error ) {
			self::fail( $error );
			return;
		}
		wp_send_json_success( $answer );
	}

	/** State of an alt text job. */
	public static function job_status(): void {
		self::guard();
		try {
			$answer = Do4u_Jobs::status( self::field( 'job' ) );
		} catch ( Do4u_Error $error ) {
			self::fail( $error );
			return;
		}
		wp_send_json_success( $answer );
	}

	/** Search for the newsletter selection: published posts and pages from the database. */
	public static function find_posts(): void {
		self::guard();
		$type  = sanitize_key( self::field( 'type' ) );
		$types = Do4u_Plugin::post_types();
		$found = get_posts(
			array(
				'post_type'    => in_array( $type, $types, true ) ? $type : $types,
				'post_status'  => 'publish',
				'has_password' => false,
				'numberposts'  => 40,
				's'            => sanitize_text_field( self::field( 'q' ) ),
			)
		);
		wp_send_json_success( array( 'items' => array_map( array( 'Do4u_Places', 'picker_item' ), $found ) ) );
	}

	/** Lines for the newsletter form from the selection. */
	public static function newsletter_fill(): void {
		self::guard();
		try {
			$place = Do4u_Places::build( '', 'posts_newsletter', self::context() );
		} catch ( Do4u_Error $error ) {
			self::fail( $error );
			return;
		}
		wp_send_json_success(
			array(
				'fill'     => $place['fill'],
				'notices'  => $place['notices'],
				'selected' => $place['selected'] ?? array(),
			)
		);
	}

	/** Balance for the dashboard box. Separate request, so that a slow connection never holds up the dashboard. */
	public static function balance(): void {
		self::guard();
		// The dashboard box asks at most every five minutes (GET /balance); in between the stored number is shown.
		$balance = get_transient( 'do4u_wp_balance' );
		if ( ! is_array( $balance ) ) {
			try {
				$balance = Do4u_Connection::balance();
			} catch ( Do4u_Error $error ) {
				self::fail( $error );
				return;
			}
			set_transient( 'do4u_wp_balance', array( 'credits' => (int) ( $balance['credits'] ?? 0 ) ), 5 * MINUTE_IN_SECONDS );
		}
		wp_send_json_success(
			array(
				'text' => sprintf(
					/* translators: %s: number of credits */
					_n( '%s credit available', '%s credits available', (int) ( $balance['credits'] ?? 0 ), 'do4u' ),
					number_format_i18n( (int) ( $balance['credits'] ?? 0 ) )
				),
			)
		);
	}
}
