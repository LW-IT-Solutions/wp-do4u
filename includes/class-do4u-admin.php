<?php
/**
 * Menus, assets and settings actions.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/** Registers the wp-admin menu and loads scripts only on the screens that use them. */
final class Do4u_Admin {

	const NOTICE = 'do4u_wp_notice_';

	/** Registers the hooks. */
	public static function boot(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'wp_enqueue_media', array( self::class, 'media_assets' ) );
		add_action( 'wp_dashboard_setup', array( self::class, 'dashboard' ) );
		add_action( 'admin_post_do4u_wp_connect', array( self::class, 'handle_connect' ) );
		add_action( 'admin_post_do4u_wp_budget', array( self::class, 'handle_budget' ) );
		add_action( 'admin_post_do4u_wp_disconnect', array( self::class, 'handle_disconnect' ) );
	}

	/** Adds the main menu and the website checks below Tools. */
	public static function menu(): void {
		$cap = Do4u_Plugin::capability();
		add_menu_page( 'do4u.eu', 'do4u.eu', $cap, 'do4u', array( 'Do4u_Pages', 'overview' ), 'dashicons-superhero-alt', 58 );
		add_submenu_page( 'do4u', __( 'Overview', 'do4u' ), __( 'Overview', 'do4u' ), $cap, 'do4u', array( 'Do4u_Pages', 'overview' ) );
		add_submenu_page( 'do4u', __( 'All tools', 'do4u' ), __( 'All tools', 'do4u' ), $cap, 'do4u-tools', array( 'Do4u_Pages', 'tools' ) );
		add_submenu_page( 'do4u', __( 'Newsletter', 'do4u' ), __( 'Newsletter', 'do4u' ), $cap, 'do4u-newsletter', array( 'Do4u_Pages', 'newsletter' ) );
		add_submenu_page( 'do4u', __( 'Settings', 'do4u' ), __( 'Settings', 'do4u' ), 'manage_options', 'do4u-settings', array( 'Do4u_Pages', 'settings' ) );
		add_management_page( __( 'Website checks', 'do4u' ), __( 'Website checks (do4u.eu)', 'do4u' ), $cap, 'do4u-site-checks', array( 'Do4u_Pages', 'site_checks' ) );
	}

	/**
	 * Whether a screen uses the plugin assets. Own pages are matched by their slug, because WordPress
	 * derives the rest of the hook name from the menu title.
	 *
	 * @param string $hook Value of $hook_suffix.
	 * @return bool
	 */
	public static function uses_assets( string $hook ): bool {
		return str_contains( $hook, '_page_do4u' ) || in_array( $hook, array( 'index.php', 'upload.php', 'edit-comments.php', 'post.php', 'post-new.php' ), true );
	}

	/**
	 * Loads style and script where they are needed. The widget loader of do4u.eu is not among them:
	 * do4u-admin.js adds it when a tool is opened, so that merely looking at a list causes no request to do4u.eu.
	 *
	 * @param string $hook Current screen.
	 */
	public static function assets( string $hook ): void {
		if ( ! self::uses_assets( $hook ) || ! current_user_can( Do4u_Plugin::capability() ) ) {
			return;
		}
		self::register_assets();
	}

	/** The media dialog can open on any screen; it needs the alt text button there too. */
	public static function media_assets(): void {
		if ( is_admin() && current_user_can( Do4u_Plugin::capability() ) ) {
			self::register_assets();
		}
	}

	/** Enqueues the common assets once. */
	public static function register_assets(): void {
		if ( wp_script_is( 'do4u-admin', 'enqueued' ) ) {
			return;
		}
		$url = plugin_dir_url( DO4U_WP_FILE );
		wp_enqueue_style( 'do4u-admin', $url . 'assets/css/do4u-admin.css', array(), DO4U_WP_VERSION );
		wp_enqueue_script( 'do4u-admin', $url . 'assets/js/do4u-admin.js', array(), DO4U_WP_VERSION, true );
		wp_localize_script( 'do4u-admin', 'do4uWpData', self::script_data() );
	}

	/**
	 * Data for do4u-admin.js. All texts are translated here, so one translation file is enough.
	 *
	 * @return array
	 */
	private static function script_data(): array {
		return array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( Do4u_Ajax::NONCE ),
			'shortcodes' => array_keys( (array) ( $GLOBALS['shortcode_tags'] ?? array() ) ),
			'maxPosts'   => Do4u_Places::NEWSLETTER_URLS,
			'i18n'       => array(
				'close'         => __( 'Close', 'do4u' ),
				'loading'       => __( 'Opening the tool …', 'do4u' ),
				'hint'          => __( 'The tool is filled in, not started. Check the form, then start it yourself: a job uses credits. Keep this window open until the result is there.', 'do4u' ),
				'hintEmpty'     => __( 'Fill in the form and start it yourself: a job uses credits. Keep this window open until the result is there.', 'do4u' ),
				/* translators: %d: number of tools */
				'matches'       => __( 'Tools found: %d', 'do4u' ),
				'failed'        => __( 'The tool could not be opened.', 'do4u' ),
				'loaderFailed'  => __( 'The tool could not be loaded from do4u.eu. A browser extension or a content security policy may be blocking it.', 'do4u' ),
				'network'       => __( 'The request failed. Please try again.', 'do4u' ),
				'expired'       => __( 'The session has expired and is being renewed. If the tool does not respond, open it again.', 'do4u' ),
				'resultHeading' => __( 'Insert the result', 'do4u' ),
				'copy'          => __( 'Copy', 'do4u' ),
				'copied'        => __( 'Copied.', 'do4u' ),
				'copyFailed'    => __( 'Copying failed. Please select the text by hand.', 'do4u' ),
				'noField'       => __( 'This field is not on the screen. Please copy the text.', 'do4u' ),
				'applied'       => __( 'Taken over into the field. Nothing is saved until you save.', 'do4u' ),
				'stored'        => __( 'Saved.', 'do4u' ),
				'draftSaved'    => __( 'New draft saved. Nothing was published.', 'do4u' ),
				'openDraft'     => __( 'Open draft', 'do4u' ),
				/* translators: %s: current text of the field */
				'conflict'      => __( 'This field already contains: “%s”. Replace it?', 'do4u' ),
				'replace'       => __( 'Replace', 'do4u' ),
				'keep'          => __( 'Keep the current text', 'do4u' ),
				'kept'          => __( 'Nothing was changed.', 'do4u' ),
				'jobHint'       => __( 'When you start, this server sends the image file from the media library and its title to do4u.eu. A job uses credits.', 'do4u' ),
				'jobStart'      => __( 'Suggest alt text', 'do4u' ),
				'jobRunning'    => __( 'do4u.eu is working on it. Keep this window open.', 'do4u' ),
				'jobDone'       => __( 'Done. Check the suggestion before you use it.', 'do4u' ),
				'jobLost'       => __( 'No result yet. Your job may still finish; check your jobs at do4u.eu before you start it again.', 'do4u' ),
				'jobCurrent'    => __( 'Current alt text:', 'do4u' ),
				'jobNoCurrent'  => __( 'This image has no alt text yet.', 'do4u' ),
				'jobPrice'      => __( 'Price:', 'do4u' ),
				'pickFound'     => __( 'Add to the newsletter:', 'do4u' ),
				'pickNone'      => __( 'Nothing found.', 'do4u' ),
				'pickMain'      => __( 'Main article', 'do4u' ),
				'pickRemove'    => __( 'Remove', 'do4u' ),
				/* translators: %d: number of posts */
				'pickFull'      => __( 'A newsletter can hold %d articles. Remove one first.', 'do4u' ),
				'pickEmpty'     => __( 'Choose at least one post or page first.', 'do4u' ),
				'pickFilled'    => __( 'The form below is filled in. Check it and start it yourself.', 'do4u' ),
			),
		);
	}

	/** Dashboard box with the balance and shortcuts. */
	public static function dashboard(): void {
		if ( current_user_can( Do4u_Plugin::capability() ) ) {
			wp_add_dashboard_widget( 'do4u_wp_dashboard', 'do4u.eu', array( 'Do4u_Pages', 'dashboard' ) );
		}
	}

	/**
	 * Remembers a message for the next page view of this user.
	 *
	 * @param string $text Text.
	 * @param string $type success, error or warning.
	 */
	public static function notify( string $text, string $type = 'success' ): void {
		set_transient(
			self::NOTICE . get_current_user_id(),
			array(
				'text' => $text,
				'type' => $type,
			),
			60
		);
	}

	/** Back to the settings page. */
	private static function back(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=do4u-settings' ) );
		exit;
	}

	/**
	 * Budget values from the form, within what the API accepts.
	 *
	 * @param string $action Nonce action of the form; checked again here, where the input is read.
	 * @return array Day and month.
	 */
	private static function budget_from_form( string $action ): array {
		check_admin_referer( $action );
		$day   = isset( $_POST['budget_day'] ) ? absint( wp_unslash( $_POST['budget_day'] ) ) : Do4u_Connection::BUDGET_DAY;
		$month = isset( $_POST['budget_month'] ) ? absint( wp_unslash( $_POST['budget_month'] ) ) : Do4u_Connection::BUDGET_MONTH;
		return array( min( max( 1, $day ), 10000000 ), min( max( 1, $month ), 100000000 ) );
	}

	/** Saves the API key and connects the site. */
	public static function handle_connect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'do4u' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'do4u_wp_connect' );
		// The key is also checked against its exact form in connect(); a valid key passes sanitize_text_field() unchanged.
		$key = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';
		[ $day, $month ] = self::budget_from_form( 'do4u_wp_connect' );
		try {
			Do4u_Connection::connect( $key, $day, $month );
			self::notify( __( 'Connected. The tools are ready.', 'do4u' ) );
		} catch ( Do4u_Error $error ) {
			self::notify( $error->plain(), 'error' );
		}
		self::back();
	}

	/** Changes the budget of the site widget. */
	public static function handle_budget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'do4u' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'do4u_wp_budget' );
		[ $day, $month ] = self::budget_from_form( 'do4u_wp_budget' );
		try {
			Do4u_Connection::change_budget( $day, $month );
			self::notify( __( 'Budget saved. Tools that are open must be opened again.', 'do4u' ) );
		} catch ( Do4u_Error $error ) {
			self::notify( $error->plain(), 'error' );
		}
		self::back();
	}

	/** Revokes the widget and removes the key. */
	public static function handle_disconnect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'do4u' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'do4u_wp_disconnect' );
		if ( Do4u_Connection::disconnect() ) {
			self::notify( __( 'Disconnected. The widget is revoked and the API key is removed from WordPress.', 'do4u' ) );
		} else {
			self::notify( __( 'The API key is removed from WordPress, but do4u.eu could not be reached to revoke the widget. Please remove it under Widgets in your do4u.eu account.', 'do4u' ), 'warning' );
		}
		self::back();
	}
}
