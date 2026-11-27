<?php
/**
 * The plugin's own admin pages.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/** Renders overview, catalog, tool page, settings, website checks and the dashboard box. */
final class Do4u_Pages {

	/** Pending notice and the warnings every page shows. */
	private static function notices(): void {
		$key    = Do4u_Admin::NOTICE . get_current_user_id();
		$notice = get_transient( $key );
		if ( is_array( $notice ) ) {
			delete_transient( $key );
			printf( '<div class="notice notice-%1$s" role="status"><p>%2$s</p></div>', esc_attr( (string) $notice['type'] ), esc_html( (string) $notice['text'] ) );
		}
		if ( Do4u_Connection::origin_changed() ) {
			$message = current_user_can( 'manage_options' )
				? __( 'The address of this site has changed since it was connected. Please connect again under Settings, otherwise the browser will refuse to show the tools.', 'do4u' )
				: __( 'The address of this site has changed. An administrator needs to connect it again.', 'do4u' );
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $message ) );
		}
	}

	/** Note and next step for a site that is not connected yet. */
	private static function not_connected(): void {
		if ( Do4u_Secret::has() && Do4u_Shop::usable() ) {
			// Key saved, but no widget (for example wp-admin without https): the shop tools work, the writing tools do not.
			echo '<div class="notice notice-warning inline"><p>';
			esc_html_e( 'The API key is saved and the shop tools work. The writing tools also need the widget of this site at do4u.eu, which can only be created when wp-admin runs on https.', 'do4u' );
			printf( ' <a href="%1$s">%2$s</a>', esc_url( admin_url( 'admin.php?page=' . Do4u_Shop::PAGE ) ), esc_html__( 'Open the shop tools', 'do4u' ) );
			echo '</p></div>';
			return;
		}
		echo '<div class="do4u-empty"><span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>';
		echo '<h2>' . esc_html__( 'This site is not connected to do4u.eu yet.', 'do4u' ) . '</h2>';
		if ( current_user_can( 'manage_options' ) ) {
			echo '<ol class="do4u-steps">';
			echo '<li>' . esc_html__( 'Create an API key in your do4u.eu account.', 'do4u' ) . '</li>';
			echo '<li>' . esc_html__( 'Enter it under do4u.eu > Settings and set a budget for this site.', 'do4u' ) . '</li>';
			echo '<li>' . esc_html__( 'Open the tools where the content is: editor, media library, comments.', 'do4u' ) . '</li>';
			echo '</ol>';
			printf( '<p><a class="button button-primary button-large" href="%1$s">%2$s</a></p>', esc_url( admin_url( 'admin.php?page=do4u-settings' ) ), esc_html__( 'Connect now', 'do4u' ) );
		} else {
			echo '<p>' . esc_html__( 'This site is not connected to do4u.eu yet. An administrator can connect it under do4u.eu > Settings.', 'do4u' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Bar for a share of a budget, with the numbers in words for screen readers.
	 *
	 * @param string $label Label.
	 * @param int    $used  Used credits.
	 * @param int    $limit Budget.
	 * @param float  $pace  Elapsed share of the period from 0 to 1; below 0 shows no marker.
	 * @param string $note  Line below the bar, for example the forecast.
	 * @param string $mood  Class of the note: is-ok or is-warn.
	 */
	private static function meter( string $label, int $used, int $limit, float $pace = -1, string $note = '', string $mood = '' ): void {
		$share = $limit > 0 ? min( 100, (int) round( 100 * $used / $limit ) ) : 0;
		$state = $share >= 100 ? 'is-full' : ( $share >= 80 ? 'is-high' : '' );
		$text  = sprintf(
			/* translators: 1: credits used, 2: budget */
			__( '%1$s of %2$s credits', 'do4u' ),
			number_format_i18n( $used ),
			number_format_i18n( $limit )
		);
		echo '<div class="do4u-meter-row"><div class="do4u-meter-label"><span>' . esc_html( $label ) . '</span><span>' . esc_html( $text ) . '</span></div>';
		echo '<div class="do4u-meter-wrap">';
		printf(
			'<div class="do4u-meter %1$s" role="progressbar" aria-label="%2$s" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%3$d" aria-valuetext="%4$s"><span style="%5$s"></span></div>',
			esc_attr( $state ),
			esc_attr( $label ),
			(int) $share,
			esc_attr( $text . ' (' . $share . ' %)' ),
			esc_attr( 'width:' . $share . '%' )
		);
		if ( $pace >= 0 && $limit > 0 ) {
			$at = min( 100, max( 0, (int) round( 100 * $pace ) ) );
			/* translators: %s: credits */
			$even = sprintf( __( 'Even pace up to now: %s credits', 'do4u' ), number_format_i18n( (int) round( $limit * $pace ) ) );
			printf( '<span class="do4u-pace" style="%1$s" title="%2$s" aria-hidden="true"></span>', esc_attr( 'left:' . $at . '%' ), esc_attr( $even ) );
			echo '</div><p class="do4u-pace-text"><span class="do4u-pace-key" aria-hidden="true"></span>' . esc_html( $even ) . '</p>';
		} else {
			echo '</div>';
		}
		if ( '' !== $note ) {
			echo '<p class="do4u-forecast ' . esc_attr( $mood ) . '"><span class="dashicons ' . esc_attr( 'is-warn' === $mood ? 'dashicons-warning' : 'dashicons-chart-line' ) . '" aria-hidden="true"></span>' . esc_html( $note ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Forecast of the month at the pace so far.
	 *
	 * @param int $used  Credits used this month.
	 * @param int $limit Month budget.
	 * @param int $day   Day of the month.
	 * @param int $days  Days of the month.
	 * @return array{0:string, 1:string} Text and class.
	 */
	private static function forecast( int $used, int $limit, int $day, int $days ): array {
		if ( $used <= 0 || $limit <= 0 || $day < 1 ) {
			return array( '', '' );
		}
		$end = (int) round( $used / $day * $days );
		if ( $end > $limit ) {
			$left = (int) floor( ( $limit - $used ) / max( 1, $used / $day ) );
			$date = wp_date( get_option( 'date_format' ), time() + max( 0, $left ) * DAY_IN_SECONDS );
			return array(
				sprintf(
					/* translators: 1: forecast credits, 2: date */
					__( 'At this pace about %1$s credits by the end of the month; the budget would be reached around %2$s.', 'do4u' ),
					number_format_i18n( $end ),
					$date
				),
				'is-warn',
			);
		}
		return array(
			sprintf(
				/* translators: %s: forecast credits */
				__( 'At this pace about %s credits by the end of the month, within the budget.', 'do4u' ),
				number_format_i18n( $end )
			),
			'is-ok',
		);
	}

	/**
	 * When free and paid credits run out, as a small time line from today.
	 *
	 * @param array $balance Answer of GET /balance.
	 */
	private static function expiry( array $balance ): void {
		$now   = time();
		$marks = array();
		foreach ( array(
			/* translators: 1: date, 2: days left such as "34 days left" */
			'free_until'       => array( 'free', (int) ( $balance['free'] ?? 0 ), __( 'Free credits valid until %1$s (%2$s)', 'do4u' ) ),
			/* translators: 1: date, 2: days left such as "34 days left" */
			'paid_valid_until' => array( 'paid', (int) ( $balance['paid'] ?? 0 ), __( 'Paid credits valid until %1$s (%2$s)', 'do4u' ) ),
		) as $key => $def ) {
			$time = isset( $balance[ $key ] ) && is_string( $balance[ $key ] ) ? strtotime( $balance[ $key ] . ' 23:59:59' ) : false;
			if ( ! $time || $time <= $now || $def[1] <= 0 ) {
				continue;
			}
			$marks[] = array( $def[0], $time, $def[2] );
		}
		if ( ! $marks ) {
			return;
		}
		$last = max( array_column( $marks, 1 ) );
		echo '<div class="do4u-expiry"><span class="do4u-expiry-line" aria-hidden="true"><span class="do4u-expiry-today">' . esc_html__( 'Today', 'do4u' ) . '</span>';
		foreach ( $marks as $m ) {
			$at = 6 + (int) round( 88 * ( $m[1] - $now ) / max( 1, $last - $now ) );
			printf( '<span class="do4u-expiry-mark is-%1$s" style="%2$s"></span>', esc_attr( $m[0] ), esc_attr( 'left:' . $at . '%' ) );
		}
		echo '</span><ul class="do4u-expiry-list">';
		foreach ( $marks as $m ) {
			$days = (int) ceil( ( $m[1] - $now ) / DAY_IN_SECONDS );
			/* translators: %s: number of days */
			$left = sprintf( _n( '%s day left', '%s days left', $days, 'do4u' ), number_format_i18n( $days ) );
			echo '<li class="' . esc_attr( 'is-' . $m[0] . ( $days <= 30 ? ' is-soon' : '' ) ) . '"><span class="do4u-key do4u-key-' . esc_attr( $m[0] ) . '" aria-hidden="true"></span>' . esc_html( sprintf( $m[2], wp_date( get_option( 'date_format' ), $m[1] ), $left ) ) . '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Ring with the share of free and paid credits.
	 *
	 * @param int $free Free credits.
	 * @param int $paid Paid credits.
	 */
	private static function ring( int $free, int $paid ): void {
		$total = max( 1, $free + $paid );
		$len   = 2 * M_PI * 15.915;
		$part  = round( $len * $free / $total, 2 );
		/* translators: 1: credits, 2: share in percent */
		$tpl   = __( '%1$s credits (%2$s %%)', 'do4u' );
		echo '<span class="do4u-ring-box"><svg class="do4u-ring" viewBox="0 0 36 36" width="76" height="76" role="group" aria-label="' . esc_attr__( 'Balance', 'do4u' ) . '">';
		printf(
			'<circle cx="18" cy="18" r="15.915" fill="none" stroke-width="4" class="do4u-ring-paid do4u-ring-part" tabindex="0" role="img" aria-label="%1$s" data-do4u-tip="%1$s"></circle>',
			esc_attr( __( 'Paid', 'do4u' ) . ': ' . sprintf( $tpl, number_format_i18n( $paid ), number_format_i18n( round( 100 * $paid / $total ) ) ) )
		);
		if ( $free > 0 ) {
			printf(
				'<circle cx="18" cy="18" r="15.915" fill="none" stroke-width="4" class="do4u-ring-free do4u-ring-part" stroke-dasharray="%1$s %2$s" transform="rotate(-90 18 18)" tabindex="0" role="img" aria-label="%3$s" data-do4u-tip="%3$s"></circle>',
				esc_attr( (string) $part ),
				esc_attr( (string) round( $len - $part, 2 ) ),
				esc_attr( __( 'Free', 'do4u' ) . ': ' . sprintf( $tpl, number_format_i18n( $free ), number_format_i18n( round( 100 * $free / $total ) ) ) )
			);
		}
		echo '</svg><span class="do4u-tip" hidden></span></span>';
	}

	/**
	 * Address of the tool page.
	 *
	 * @param string $tool Public tool name.
	 * @param array  $args More query arguments.
	 * @return string
	 */
	public static function tool_url( string $tool, array $args = array() ): string {
		return add_query_arg( array( 'page' => 'do4u-tools', 'tool' => $tool ) + $args, admin_url( 'admin.php' ) );
	}

	/** Overview: connection, balance, budget, shortcuts. */
	public static function overview(): void {
		if ( ! current_user_can( Do4u_Plugin::capability() ) ) {
			return;
		}
		echo '<div class="wrap do4u-wrap"><h1>do4u.eu</h1>';
		self::notices();
		if ( ! Do4u_Connection::is_connected() ) {
			self::not_connected();
			echo '</div>';
			return;
		}
		$widget = Do4u_Connection::widget();
		echo '<p class="do4u-statusline"><span class="do4u-pill is-ok"><span class="do4u-dot" aria-hidden="true"></span>' . esc_html__( 'Connected', 'do4u' ) . '</span> <span class="do4u-origin">' . esc_html( (string) ( $widget['origin'] ?? '' ) ) . '</span>';
		if ( current_user_can( 'manage_options' ) ) {
			printf( ' <a href="%1$s">%2$s</a>', esc_url( admin_url( 'admin.php?page=do4u-settings' ) ), esc_html__( 'Settings', 'do4u' ) );
		}
		echo '</p>';
		try {
			$site    = Do4u_Api::site();
			$balance = Do4u_Connection::balance();
			$usage   = Do4u_Connection::usage();
			$free    = (int) ( $balance['free'] ?? 0 );
			$paid    = (int) ( $balance['paid'] ?? 0 );
			echo '<div class="do4u-cards">';
			echo '<section class="do4u-card do4u-card-balance"><h2><span class="dashicons dashicons-database" aria-hidden="true"></span>' . esc_html__( 'Balance', 'do4u' ) . '</h2><div class="do4u-balance">';
			self::ring( $free, $paid );
			echo '<div><p class="do4u-number"><span data-do4u-countup="' . esc_attr( (string) (int) ( $balance['credits'] ?? 0 ) ) . '">' . esc_html( number_format_i18n( (int) ( $balance['credits'] ?? 0 ) ) ) . '</span> <span class="do4u-unit">' . esc_html__( 'credits', 'do4u' ) . '</span></p>';
			echo '<ul class="do4u-legend"><li><span class="do4u-key do4u-key-free" aria-hidden="true"></span>' . esc_html(
				sprintf(
					/* translators: %s: free credits */
					__( 'Free: %s', 'do4u' ),
					number_format_i18n( $free )
				)
			) . '</li><li><span class="do4u-key do4u-key-paid" aria-hidden="true"></span>' . esc_html(
				sprintf(
					/* translators: %s: paid credits */
					__( 'Paid: %s', 'do4u' ),
					number_format_i18n( $paid )
				)
			) . '</li></ul></div></div>';
			self::expiry( $balance );
			printf( '<p class="do4u-card-foot"><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s<span class="dashicons dashicons-external" aria-hidden="true"></span></a></p></section>', esc_url( $site . '/credits' ), esc_html__( 'Buy credits at do4u.eu', 'do4u' ) );
			echo '<section class="do4u-card do4u-card-budget"><h2><span class="dashicons dashicons-dashboard" aria-hidden="true"></span>' . esc_html__( 'Budget of this site', 'do4u' ) . '</h2>';
			$day_part = ( (int) wp_date( 'G' ) * 60 + (int) wp_date( 'i' ) ) / 1440;
			$mday     = (int) wp_date( 'j' );
			$mdays    = (int) wp_date( 't' );
			$m_used   = (int) ( $usage['usage']['monat'] ?? 0 );
			$m_limit  = (int) ( $usage['budget_month'] ?? 0 );
			$fc       = self::forecast( $m_used, $m_limit, $mday, $mdays );
			self::meter( __( 'Today', 'do4u' ), (int) ( $usage['usage']['tag'] ?? 0 ), (int) ( $usage['budget_day'] ?? 0 ), $day_part );
			self::meter( __( 'This month', 'do4u' ), $m_used, $m_limit, ( $mday - 1 + $day_part ) / max( 1, $mdays ), $fc[0], $fc[1] );
			echo '<p class="do4u-card-foot description">' . esc_html__( 'do4u.eu stops new jobs when a budget is reached.', 'do4u' ) . '</p></section>';
			echo '</div>';
		} catch ( Do4u_Error $error ) {
			printf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html( $error->plain() ) );
		}
		echo '<h2>' . esc_html__( 'Where to find the tools', 'do4u' ) . '</h2><ul class="do4u-places">';
		$places = array(
			array( admin_url( 'admin.php?page=do4u-tools' ), __( 'All tools', 'do4u' ), __( 'The tools with a use in WordPress, and where each one is found.', 'do4u' ), 'dashicons-screenoptions' ),
			array( admin_url( 'admin.php?page=do4u-newsletter' ), __( 'Newsletter', 'do4u' ), __( 'Choose posts and pages, set the main article, and the newsletter tool opens filled in.', 'do4u' ), 'dashicons-email-alt' ),
			array( admin_url( 'edit.php' ), __( 'Posts and pages', 'do4u' ), __( 'In the sidebar or box of the editor; in the lists: newsletter from selected posts.', 'do4u' ), 'dashicons-edit-page' ),
			array( admin_url( 'upload.php' ), __( 'Media library', 'do4u' ), __( 'Alt text in the list, on the edit page and in the media dialog.', 'do4u' ), 'dashicons-format-image' ),
			array( admin_url( 'edit-comments.php' ), __( 'Comments', 'do4u' ), __( 'Check a comment or get a reply suggestion.', 'do4u' ), 'dashicons-admin-comments' ),
			array( admin_url( 'tools.php?page=do4u-site-checks' ), __( 'Website checks', 'do4u' ), __( 'Accessibility, links, structured data and more for your home page.', 'do4u' ), 'dashicons-yes-alt' ),
		);
		if ( Do4u_Shop::usable() ) {
			$places[] = array( admin_url( 'admin.php?page=' . Do4u_Shop::PAGE ), __( 'Shop', 'do4u' ), __( 'Product texts, SEO texts and alt texts for many WooCommerce products at once, with review and undo.', 'do4u' ), 'dashicons-cart' );
		}
		foreach ( $places as [ $url, $name, $text, $icon ] ) {
			printf( '<li><a href="%1$s"><span class="dashicons %4$s" aria-hidden="true"></span><strong>%2$s</strong><span class="do4u-meta">%3$s</span></a></li>', esc_url( $url ), esc_html( $name ), esc_html( $text ), esc_attr( $icon ) );
		}
		echo '</ul></div>';
	}

	/** Catalog, or one tool if the address names one. */
	public static function tools(): void {
		if ( ! current_user_can( Do4u_Plugin::capability() ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation; nothing is changed or started by these parameters.
		$tool = isset( $_GET['tool'] ) ? sanitize_key( wp_unslash( $_GET['tool'] ) ) : '';
		if ( Do4u_Catalog::valid_name( $tool ) ) {
			self::tool( $tool );
			return;
		}
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$category = isset( $_GET['cat'] ) ? sanitize_key( wp_unslash( $_GET['cat'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap do4u-wrap"><h1>' . esc_html__( 'All tools', 'do4u' ) . '</h1>';
		self::notices();
		if ( ! Do4u_Connection::is_connected() ) {
			self::not_connected();
			echo '</div>';
			return;
		}
		try {
			$all = Do4u_Catalog::all();
		} catch ( Do4u_Error $error ) {
			printf( '<div class="notice notice-error inline"><p>%s</p></div></div>', esc_html( $error->plain() ) );
			return;
		}
		$labels = Do4u_Catalog::categories();
		if ( ! isset( $labels[ $category ] ) ) {
			$category = '';
		}
		$tools = Do4u_Catalog::filter( $all, $search, $category );
		self::catalog_form( $search, $category );
		self::catalog_categories( $all, $labels, $search, $category );
		printf(
			'<p id="do4u-count" role="status" data-do4u-count>%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: number of tools */
					_n( '%s tool', '%s tools', count( $tools ), 'do4u' ),
					number_format_i18n( count( $tools ) )
				)
			)
		);
		echo '<ul class="do4u-catalog" data-do4u-catalog>';
		$icons = self::category_icons();
		foreach ( $tools as $item ) {
			$usable = $item['available'] && $item['widget'];
			echo '<li class="' . esc_attr( 'do4u-cat-' . $item['category'] . ( $usable ? '' : ' is-off' ) ) . '" data-do4u-search="' . esc_attr( mb_strtolower( $item['title'] . ' ' . str_replace( '_', ' ', $item['name'] ) ) ) . '" data-do4u-usable="' . esc_attr( $usable ? '1' : '0' ) . '" data-do4u-free="' . esc_attr( $usable && isset( $item['credits'] ) && 0 === $item['credits'] ? '1' : '0' ) . '">';
			echo '<span class="dashicons ' . esc_attr( $usable ? ( $icons[ $item['category'] ] ?? 'dashicons-admin-tools' ) : 'dashicons-lock' ) . ' do4u-tool-icon" aria-hidden="true"></span><span class="do4u-tool-body">';
			if ( $usable ) {
				printf( '<a class="do4u-tool-link" href="%1$s">%2$s</a>', esc_url( self::place_url( $item['name'] ) ), esc_html( $item['title'] ) );
			} else {
				printf( '<span class="do4u-tool-link do4u-off">%s</span>', esc_html( $item['title'] ) );
			}
			echo '<span class="do4u-meta">' . esc_html( self::place_label( $item['name'] ) ) . '</span><span class="do4u-badges">';
			$price = Do4u_Catalog::price_text( $item );
			if ( '' !== $price ) {
				$kind = ! $usable ? 'is-muted' : ( isset( $item['credits'] ) && 0 === $item['credits'] ? 'is-free' : '' );
				printf( '<span class="do4u-badge %1$s">%2$s</span>', esc_attr( $kind ), esc_html( $price ) );
			}
			if ( in_array( $item['name'], Do4u_Places::address_tools(), true ) ) {
				printf( '<span class="do4u-badge is-address"><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>%s</span>', esc_html__( 'needs a public address', 'do4u' ) );
			}
			echo '</span></span></li>';
		}
		echo '</ul>';
		$reset = add_query_arg( array( 'page' => 'do4u-tools' ), admin_url( 'admin.php' ) );
		printf(
			'<div class="%1$s" data-do4u-none><span class="dashicons dashicons-search" aria-hidden="true"></span><p>%2$s</p><p><a class="button" href="%3$s">%4$s</a></p></div>',
			esc_attr( $tools ? 'do4u-none is-hidden' : 'do4u-none' ),
			esc_html__( 'No tool found.', 'do4u' ),
			esc_url( $reset ),
			esc_html__( 'Show all tools', 'do4u' )
		);
		echo '</div>';
	}

	/**
	 * Dashicon of each catalog category.
	 *
	 * @return array Category id => Dashicon class.
	 */
	private static function category_icons(): array {
		return array(
			'text'    => 'dashicons-edit',
			'website' => 'dashicons-admin-site-alt3',
			'image'   => 'dashicons-format-image',
			'service' => 'dashicons-groups',
			'other'   => 'dashicons-admin-tools',
		);
	}

	/**
	 * Where a tool is used. The catalog leads there, because the tools work on the content of that place.
	 *
	 * @param string $name Public tool name.
	 * @return string
	 */
	private static function place_url( string $name ): string {
		switch ( Do4u_Places::plugin_tools()[ $name ] ?? '' ) {
			case 'post':
				return admin_url( 'edit.php' );
			case 'posts':
				return admin_url( 'admin.php?page=do4u-newsletter' );
			case 'attachment':
				return admin_url( 'upload.php?mode=list' );
			case 'comment':
				return admin_url( 'edit-comments.php' );
			case 'product':
				return admin_url( 'edit.php?post_type=product' );
			case 'create':
				foreach ( Do4u_Places::presets() as $id => $def ) {
					if ( $name === $def['tool'] && 'create' === $def['group'] ) {
						return self::tool_url( $name, array( 'preset' => $id ) );
					}
				}
		}
		return self::tool_url( $name, array( 'preset' => 'site_check' ) );
	}

	/**
	 * Name of that place.
	 *
	 * @param string $name Public tool name.
	 * @return string
	 */
	private static function place_label( string $name ): string {
		$labels = array(
			'post'       => __( 'Open a post or page: editor sidebar or box', 'do4u' ),
			'posts'      => __( 'Newsletter page', 'do4u' ),
			'attachment' => __( 'Media library', 'do4u' ),
			'comment'    => __( 'Comments list', 'do4u' ),
			'product'    => __( 'WooCommerce product page', 'do4u' ),
			'create'     => __( 'Open here: the result becomes a new draft', 'do4u' ),
			'site'       => __( 'Website checks', 'do4u' ),
		);
		return $labels[ Do4u_Places::plugin_tools()[ $name ] ?? 'site' ];
	}

	/**
	 * Search form. It works without script; do4u-admin.js only filters while typing.
	 *
	 * @param string $search   Search text.
	 * @param string $category Category id.
	 */
	private static function catalog_form( string $search, string $category ): void {
		echo '<form method="get" class="do4u-search" role="search"><input type="hidden" name="page" value="do4u-tools">';
		if ( '' !== $category ) {
			printf( '<input type="hidden" name="cat" value="%s">', esc_attr( $category ) );
		}
		printf(
			'<label for="do4u-search">%1$s</label> <span class="do4u-search-field"><input type="search" id="do4u-search" name="s" value="%2$s" data-do4u-filter autocomplete="off" aria-describedby="do4u-search-key"><kbd aria-hidden="true">/</kbd></span> <button type="submit" class="button">%3$s</button><span class="screen-reader-text" id="do4u-search-key">%4$s</span>',
			esc_html__( 'Find a tool', 'do4u' ),
			esc_attr( $search ),
			esc_html__( 'Search', 'do4u' ),
			esc_html__( 'Press the slash key to jump to this field.', 'do4u' )
		);
		printf(
			'<span class="do4u-quick" role="group" aria-label="%1$s" data-do4u-quick hidden><button type="button" class="do4u-quick-chip" aria-pressed="false" data-do4u-quick-key="usable"><span class="dashicons dashicons-unlock" aria-hidden="true"></span>%2$s</button><button type="button" class="do4u-quick-chip" aria-pressed="false" data-do4u-quick-key="free"><span class="dashicons dashicons-tag" aria-hidden="true"></span>%3$s</button></span></form>',
			esc_attr__( 'Quick filters', 'do4u' ),
			esc_html__( 'In my plan', 'do4u' ),
			esc_html__( 'Free', 'do4u' )
		);
	}

	/**
	 * Category links with counts.
	 *
	 * @param array  $all      All tools.
	 * @param array  $labels   Category labels.
	 * @param string $search   Search text.
	 * @param string $category Current category.
	 */
	private static function catalog_categories( array $all, array $labels, string $search, string $category ): void {
		$counts = array_count_values( array_column( $all, 'category' ) );
		$base   = array( 'page' => 'do4u-tools' ) + ( '' !== $search ? array( 's' => $search ) : array() );
		echo '<nav class="do4u-cats" aria-label="' . esc_attr__( 'Categories', 'do4u' ) . '"><ul>';
		printf(
			'<li><a href="%1$s"%2$s>%3$s <span class="do4u-count">%4$d</span></a></li>',
			esc_url( add_query_arg( $base, admin_url( 'admin.php' ) ) ),
			'' === $category ? ' aria-current="' . esc_attr( 'true' ) . '"' : '',
			esc_html__( 'All', 'do4u' ),
			count( $all )
		);
		$icons = self::category_icons();
		foreach ( $labels as $id => $label ) {
			if ( empty( $counts[ $id ] ) ) {
				continue;
			}
			printf(
				'<li><a href="%1$s"%2$s><span class="dashicons %5$s" aria-hidden="true"></span>%3$s <span class="do4u-count">%4$d</span></a></li>',
				esc_url( add_query_arg( $base + array( 'cat' => $id ), admin_url( 'admin.php' ) ) ),
				$category === $id ? ' aria-current="' . esc_attr( 'true' ) . '"' : '',
				esc_html( $label ),
				(int) $counts[ $id ],
				esc_attr( $icons[ $id ] ?? 'dashicons-admin-tools' )
			);
		}
		echo '</ul></nav>';
	}

	/**
	 * Page of one tool. The session token and the prefill come by Ajax, so the HTML of the page contains
	 * neither, and a place that the user has no right to fails with a message instead of content.
	 *
	 * @param string $tool Public tool name.
	 */
	private static function tool( string $tool ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation; the Ajax request checks nonce and rights.
		$preset = isset( $_GET['preset'] ) ? sanitize_key( wp_unslash( $_GET['preset'] ) ) : '';
		$open   = array(
			'tool'    => $tool,
			'preset'  => $preset,
			'context' => array(
				'id'  => isset( $_GET['item'] ) ? absint( $_GET['item'] ) : 0,
				'ids' => isset( $_GET['items'] ) ? implode( ',', array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['items'] ) ) ) ) ) : '',
			),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap do4u-wrap">';
		printf( '<p class="do4u-back"><a href="%1$s">%2$s</a></p>', esc_url( admin_url( 'admin.php?page=do4u-tools' ) ), esc_html__( '← All tools', 'do4u' ) );
		echo '<h1>' . esc_html( Do4u_Catalog::title( $tool ) ) . '</h1>';
		self::notices();
		if ( ! Do4u_Connection::is_connected() ) {
			self::not_connected();
			echo '</div>';
			return;
		}
		if ( 'newsletter_create' === $tool ) {
			$open['preset'] = 'posts_newsletter';
			self::newsletter_picker( (string) $open['context']['ids'] );
		}
		printf( '<div class="do4u-panel" data-do4u-panel="%s"></div>', esc_attr( (string) wp_json_encode( $open ) ) );
		echo '<noscript><div class="notice notice-error inline"><p>' . esc_html__( 'The tools need JavaScript.', 'do4u' ) . '</p></div></noscript></div>';
	}

	/** Newsletter tool with the selection of posts and pages. */
	public static function newsletter(): void {
		if ( current_user_can( Do4u_Plugin::capability() ) ) {
			self::tool( 'newsletter_create' );
		}
	}

	/**
	 * Selection of posts and pages. do4u-admin.js fills it; results come from the database by Ajax.
	 *
	 * @param string $ids Preselected post ids, comma separated.
	 */
	private static function newsletter_picker( string $ids ): void {
		echo '<section class="do4u-picker" aria-labelledby="do4u-picker-title" data-do4u-picker="' . esc_attr( $ids ) . '">';
		echo '<h2 id="do4u-picker-title">' . esc_html__( 'Which posts and pages should the newsletter promote?', 'do4u' ) . '</h2>';
		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %d: number of articles */
					__( 'Up to %d published posts or pages. Title and excerpt come from WordPress, the link from the post address.', 'do4u' ),
					Do4u_Places::NEWSLETTER_URLS
				)
			)
		);
		echo '<div class="do4u-picker-search"><label for="do4u-picker-q">' . esc_html__( 'Search', 'do4u' ) . '</label> <input type="search" id="do4u-picker-q" data-do4u-picker-q autocomplete="off"> ';
		echo '<label for="do4u-picker-type">' . esc_html__( 'Type', 'do4u' ) . '</label> <select id="do4u-picker-type" data-do4u-picker-type><option value="">' . esc_html__( 'All', 'do4u' ) . '</option>';
		foreach ( Do4u_Plugin::post_types() as $type ) {
			$object = get_post_type_object( $type );
			printf( '<option value="%1$s">%2$s</option>', esc_attr( $type ), esc_html( $object ? $object->labels->name : $type ) );
		}
		echo '</select></div><div class="do4u-picker-columns">';
		echo '<fieldset><legend>' . esc_html__( 'Found', 'do4u' ) . '</legend><ul data-do4u-picker-results></ul></fieldset>';
		echo '<fieldset><legend>' . esc_html__( 'In the newsletter', 'do4u' ) . ' <span class="do4u-count" data-do4u-picker-count>0 / ' . esc_html( (string) Do4u_Places::NEWSLETTER_URLS ) . '</span></legend><div class="do4u-slots" aria-hidden="true" data-do4u-picker-slots></div><ol class="do4u-picked" data-do4u-picker-selected></ol></fieldset></div>';
		echo '<p class="do4u-row-status" role="status" data-do4u-picker-status></p>';
		echo '<p><button type="button" class="button button-primary" data-do4u-picker-apply>' . esc_html__( 'Put into the newsletter form', 'do4u' ) . '</button></p></section>';
	}

	/** Settings: API key, budget, disconnect. Only for administrators. */
	public static function settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap do4u-wrap"><h1>' . esc_html__( 'do4u.eu settings', 'do4u' ) . '</h1>';
		self::notices();
		$problem = Do4u_Connection::origin_problem();
		if ( '' !== $problem ) {
			printf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html( $problem ) );
		}
		try {
			$site = Do4u_Api::site();
		} catch ( Do4u_Error $error ) {
			printf( '<div class="notice notice-error inline"><p>%s</p></div></div>', esc_html( $error->plain() ) );
			return;
		}
		$connected = Do4u_Connection::is_connected();
		$widget    = Do4u_Connection::widget();
		$day       = (int) ( $widget['budget_day'] ?? Do4u_Connection::BUDGET_DAY );
		$month     = (int) ( $widget['budget_month'] ?? Do4u_Connection::BUDGET_MONTH );
		$budget    = static function () use ( $day, $month ): void {
			echo '<tr><th scope="row"><label for="do4u-budget-day">' . esc_html__( 'Credits per day', 'do4u' ) . '</label></th>';
			printf( '<td><input type="number" class="small-text" id="do4u-budget-day" name="budget_day" min="1" max="10000000" value="%d" required> <span class="do4u-unit">%s</span></td></tr>', (int) $day, esc_html__( 'credits', 'do4u' ) );
			echo '<tr><th scope="row"><label for="do4u-budget-month">' . esc_html__( 'Credits per month', 'do4u' ) . '</label></th>';
			printf( '<td><input type="number" class="small-text" id="do4u-budget-month" name="budget_month" min="1" max="100000000" value="%d" required> <span class="do4u-unit">%s</span>', (int) $month, esc_html__( 'credits', 'do4u' ) );
			echo '<p class="description">' . esc_html__( 'Upper limit for all tools on this site together. do4u.eu stops new jobs when it is reached.', 'do4u' ) . '</p></td></tr>';
		};

		if ( $connected ) {
			$pill = array( 'is-ok', __( 'Connected', 'do4u' ) );
		} elseif ( Do4u_Secret::has() ) {
			$pill = array( 'is-warn', __( 'API key saved, no widget', 'do4u' ) );
		} else {
			$pill = array( 'is-off', __( 'Not connected', 'do4u' ) );
		}
		echo '<h2 class="do4u-heading">' . esc_html__( 'Connection', 'do4u' ) . ' <span class="do4u-pill ' . esc_attr( $pill[0] ) . '"><span class="do4u-dot" aria-hidden="true"></span>' . esc_html( $pill[1] ) . '</span></h2>';
		if ( $connected ) {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: address of the site */
						__( 'Connected for %s. All tools are available in wp-admin.', 'do4u' ),
						(string) ( $widget['origin'] ?? '' )
					)
				)
			);
		} else {
			echo '<p>' . esc_html__( 'Connecting creates a widget for this site in your do4u.eu account. It only works inside wp-admin, which is the login area do4u.eu requires. The API key stays on this server; the browser only gets short-lived session tokens.', 'do4u' ) . '</p>';
		}
		if ( Do4u_Shop::usable() ) {
			if ( ! $connected && Do4u_Secret::has() ) {
				echo '<div class="notice notice-info inline"><p>' . esc_html__( 'The API key is saved and the shop tools work. The writing tools also need the widget of this site at do4u.eu, which can only be created when wp-admin runs on https.', 'do4u' ) . '</p></div>';
			} else {
				echo '<p>' . esc_html__( 'The shop tools under do4u.eu > Shop use the same API key.', 'do4u' ) . '</p>';
			}
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="do4u_wp_connect">';
		wp_nonce_field( 'do4u_wp_connect' );
		echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="do4u-key">' . esc_html__( 'API key', 'do4u' ) . '</label></th><td>';
		printf(
			'<input type="password" class="regular-text" id="do4u-key" name="api_key" autocomplete="new-password" spellcheck="false" placeholder="%s">',
			esc_attr( Do4u_Secret::has() ? __( 'Saved. Never displayed again.', 'do4u' ) : 'd4u_…' )
		);
		printf(
			'<p class="description"><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></p></td></tr>',
			esc_url( $site . '/api-keys' ),
			esc_html__( 'Create a key in your do4u.eu account', 'do4u' )
		);
		if ( ! $connected ) {
			$budget();
		}
		echo '</table>';
		submit_button( $connected ? __( 'Save key and connect again', 'do4u' ) : __( 'Connect', 'do4u' ) );
		echo '</form>';

		if ( ! $connected && ! Do4u_Secret::has() ) {
			echo '</div>';
			return;
		}
		if ( $connected ) {
			echo '<h2>' . esc_html__( 'Budget of this site', 'do4u' ) . '</h2>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="do4u_wp_budget">';
			wp_nonce_field( 'do4u_wp_budget' );
			echo '<table class="form-table" role="presentation">';
			$budget();
			echo '</table>';
			submit_button( __( 'Save budget', 'do4u' ), 'secondary' );
			echo '</form>';
		}

		// Also offered when only the key is saved (no widget, for example without https), so that it can be removed again.
		echo '<div class="do4u-danger"><h2>' . esc_html__( 'Disconnect', 'do4u' ) . '</h2><p>' . esc_html__( 'Revokes the widget of this site at do4u.eu and removes the API key from WordPress. The key itself stays valid in your account.', 'do4u' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="do4u_wp_disconnect">';
		wp_nonce_field( 'do4u_wp_disconnect' );
		submit_button(
			__( 'Disconnect', 'do4u' ),
			'delete',
			'submit',
			true,
			array(
				'data-do4u-confirm'    => __( 'Disconnect this site from do4u.eu? The widget is revoked and the API key is removed from WordPress.', 'do4u' ),
				'data-do4u-confirm-ok' => __( 'Disconnect', 'do4u' ),
			)
		);
		echo '</form></div></div>';
	}

	/** Website checks below Tools, for the home page or a chosen page. */
	public static function site_checks(): void {
		if ( ! current_user_can( Do4u_Plugin::capability() ) ) {
			return;
		}
		echo '<div class="wrap do4u-wrap"><h1>' . esc_html__( 'Website checks', 'do4u' ) . '</h1>';
		self::notices();
		if ( ! Do4u_Connection::is_connected() ) {
			self::not_connected();
			echo '</div>';
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation; the Ajax request checks the rights for the page.
		$chosen = isset( $_GET['item'] ) ? absint( $_GET['item'] ) : 0;
		$pages  = array_filter(
			get_posts(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'has_password' => false,
					'numberposts'  => 200,
					'orderby'      => 'title',
					'order'        => 'ASC',
				)
			),
			static fn( $page ): bool => current_user_can( 'edit_post', $page->ID )
		);
		echo '<form method="get" class="do4u-search"><input type="hidden" name="page" value="do4u-site-checks"><label for="do4u-check-page">' . esc_html__( 'Page to check', 'do4u' ) . '</label> <select id="do4u-check-page" name="item"><option value="0">' . esc_html__( 'Home page', 'do4u' ) . '</option>';
		foreach ( $pages as $page ) {
			printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $page->ID, selected( $chosen, (int) $page->ID, false ), esc_html( wp_strip_all_tags( (string) $page->post_title ) ) );
		}
		echo '</select> <button type="submit" class="button">' . esc_html__( 'Choose', 'do4u' ) . '</button></form>';

		$groups = array(
			array(
				__( 'With your content from WordPress', 'do4u' ),
				__( 'These checks get the saved content directly from the WordPress database, so they also work on protected and staging sites. They see what is stored, not the theme, shortcodes or dynamic blocks.', 'do4u' ),
				Do4u_Places::site_checks_local(),
				'dashicons-database',
				'is-local',
			),
			array(
				__( 'Through the public address', 'do4u' ),
				__( 'These checks know nothing but an address: do4u.eu reads the page itself. The page must be reachable from outside, so protected, internal and staging pages cannot be checked.', 'do4u' ),
				Do4u_Places::site_checks_address(),
				'dashicons-admin-site-alt3',
				'is-public',
			),
		);
		foreach ( $groups as [ $heading, $note, $names, $icon, $kind ] ) {
			printf(
				'<section class="do4u-checkgroup %4$s"><h2><span class="dashicons %3$s" aria-hidden="true"></span>%1$s <span class="do4u-count">%5$d</span></h2><p>%2$s</p><ul class="do4u-catalog do4u-checks">',
				esc_html( $heading ),
				esc_html( $note ),
				esc_attr( $icon ),
				esc_attr( $kind ),
				count( $names )
			);
			foreach ( $names as $name ) {
				$args = array( 'preset' => 'site_check' ) + ( $chosen > 0 ? array( 'item' => $chosen ) : array() );
				printf( '<li><a class="do4u-tool-link" href="%1$s"><span class="dashicons dashicons-yes" aria-hidden="true"></span>%2$s<span class="dashicons dashicons-arrow-right-alt2 do4u-go" aria-hidden="true"></span></a></li>', esc_url( self::tool_url( $name, $args ) ), esc_html( Do4u_Catalog::title( $name ) ) );
			}
			echo '</ul></section>';
		}
		echo '</div>';
	}

	/** Dashboard box. The balance arrives by Ajax so that the dashboard never waits for do4u.eu. */
	public static function dashboard(): void {
		if ( ! Do4u_Connection::is_connected() ) {
			self::not_connected();
			return;
		}
		echo '<p class="do4u-number" data-do4u-balance role="status">' . esc_html__( 'Loading balance …', 'do4u' ) . '</p><ul class="do4u-list">';
		$links = array(
			array( admin_url( 'admin.php?page=do4u-tools' ), __( 'All tools', 'do4u' ) ),
			array( admin_url( 'tools.php?page=do4u-site-checks' ), __( 'Website checks', 'do4u' ) ),
			array( admin_url( 'admin.php?page=do4u-newsletter' ), Do4u_Catalog::title( 'newsletter_create' ) ),
		);
		foreach ( $links as [ $url, $name ] ) {
			printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $url ), esc_html( $name ) );
		}
		echo '</ul>';
	}
}
