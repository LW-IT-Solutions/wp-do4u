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

	/** Note and link for a site that is not connected yet. */
	private static function not_connected(): void {
		echo '<div class="notice notice-warning inline"><p>';
		if ( Do4u_Secret::has() && Do4u_Shop::usable() ) {
			// Key saved, but no widget (for example wp-admin without https): the shop tools work, the writing tools do not.
			esc_html_e( 'The API key is saved and the shop tools work. The writing tools also need the widget of this site at do4u.eu, which can only be created when wp-admin runs on https.', 'do4u' );
			printf( ' <a href="%1$s">%2$s</a>', esc_url( admin_url( 'admin.php?page=' . Do4u_Shop::PAGE ) ), esc_html__( 'Open the shop tools', 'do4u' ) );
		} elseif ( current_user_can( 'manage_options' ) ) {
			esc_html_e( 'This site is not connected to do4u.eu yet.', 'do4u' );
			printf( ' <a href="%1$s">%2$s</a>', esc_url( admin_url( 'admin.php?page=do4u-settings' ) ), esc_html__( 'Connect now', 'do4u' ) );
		} else {
			esc_html_e( 'This site is not connected to do4u.eu yet. An administrator can connect it under do4u.eu > Settings.', 'do4u' );
		}
		echo '</p></div>';
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
		try {
			$site    = Do4u_Api::site();
			$balance = Do4u_Connection::balance();
			$usage   = Do4u_Connection::usage();
			echo '<div class="do4u-cards">';
			echo '<section class="do4u-card"><h2>' . esc_html__( 'Balance', 'do4u' ) . '</h2><p class="do4u-number">' . esc_html( number_format_i18n( (int) ( $balance['credits'] ?? 0 ) ) ) . '</p>';
			echo '<p>' . esc_html(
				sprintf(
					/* translators: 1: free credits, 2: paid credits */
					__( 'Free: %1$s · Paid: %2$s', 'do4u' ),
					number_format_i18n( (int) ( $balance['free'] ?? 0 ) ),
					number_format_i18n( (int) ( $balance['paid'] ?? 0 ) )
				)
			) . '</p>';
			printf( '<p><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></p></section>', esc_url( $site . '/credits' ), esc_html__( 'Buy credits at do4u.eu', 'do4u' ) );
			echo '<section class="do4u-card"><h2>' . esc_html__( 'Budget of this site', 'do4u' ) . '</h2>';
			echo '<p>' . esc_html(
				sprintf(
					/* translators: 1: credits used today, 2: daily budget */
					__( 'Today: %1$s of %2$s credits', 'do4u' ),
					number_format_i18n( (int) ( $usage['usage']['tag'] ?? 0 ) ),
					number_format_i18n( (int) ( $usage['budget_day'] ?? 0 ) )
				)
			) . '</p>';
			echo '<p>' . esc_html(
				sprintf(
					/* translators: 1: credits used this month, 2: monthly budget */
					__( 'This month: %1$s of %2$s credits', 'do4u' ),
					number_format_i18n( (int) ( $usage['usage']['monat'] ?? 0 ) ),
					number_format_i18n( (int) ( $usage['budget_month'] ?? 0 ) )
				)
			) . '</p></section>';
			echo '</div>';
		} catch ( Do4u_Error $error ) {
			printf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html( $error->plain() ) );
		}
		echo '<h2>' . esc_html__( 'Where to find the tools', 'do4u' ) . '</h2><ul class="do4u-list">';
		$places = array(
			array( admin_url( 'admin.php?page=do4u-tools' ), __( 'All tools', 'do4u' ), __( 'The tools with a use in WordPress, and where each one is found.', 'do4u' ) ),
			array( admin_url( 'admin.php?page=do4u-newsletter' ), __( 'Newsletter', 'do4u' ), __( 'Choose posts and pages, set the main article, and the newsletter tool opens filled in.', 'do4u' ) ),
			array( admin_url( 'edit.php' ), __( 'Posts and pages', 'do4u' ), __( 'In the sidebar or box of the editor; in the lists: newsletter from selected posts.', 'do4u' ) ),
			array( admin_url( 'upload.php' ), __( 'Media library', 'do4u' ), __( 'Alt text in the list, on the edit page and in the media dialog.', 'do4u' ) ),
			array( admin_url( 'edit-comments.php' ), __( 'Comments', 'do4u' ), __( 'Check a comment or get a reply suggestion.', 'do4u' ) ),
			array( admin_url( 'tools.php?page=do4u-site-checks' ), __( 'Website checks', 'do4u' ), __( 'Accessibility, links, structured data and more for your home page.', 'do4u' ) ),
		);
		if ( Do4u_Shop::usable() ) {
			$places[] = array( admin_url( 'admin.php?page=' . Do4u_Shop::PAGE ), __( 'Shop', 'do4u' ), __( 'Product texts, SEO texts and alt texts for many WooCommerce products at once, with review and undo.', 'do4u' ) );
		}
		foreach ( $places as [ $url, $name, $text ] ) {
			printf( '<li><a href="%1$s">%2$s</a>: %3$s</li>', esc_url( $url ), esc_html( $name ), esc_html( $text ) );
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
		foreach ( $tools as $item ) {
			echo '<li data-do4u-search="' . esc_attr( mb_strtolower( $item['title'] . ' ' . str_replace( '_', ' ', $item['name'] ) ) ) . '">';
			if ( $item['available'] && $item['widget'] ) {
				printf( '<a class="do4u-tool-link" href="%1$s">%2$s</a>', esc_url( self::place_url( $item['name'] ) ), esc_html( $item['title'] ) );
			} else {
				printf( '<span class="do4u-tool-link do4u-off">%s</span>', esc_html( $item['title'] ) );
			}
			$meta = array_filter( array( self::place_label( $item['name'] ), Do4u_Catalog::price_text( $item ), in_array( $item['name'], Do4u_Places::address_tools(), true ) ? __( 'needs a public address', 'do4u' ) : '' ) );
			echo '<span class="do4u-meta">' . esc_html( implode( ' · ', $meta ) ) . '</span></li>';
		}
		echo '</ul>';
		if ( ! $tools ) {
			echo '<p>' . esc_html__( 'No tool found.', 'do4u' ) . '</p>';
		}
		echo '</div>';
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
			'<label for="do4u-search">%1$s</label> <input type="search" id="do4u-search" name="s" value="%2$s" data-do4u-filter autocomplete="off"> <button type="submit" class="button">%3$s</button></form>',
			esc_html__( 'Find a tool', 'do4u' ),
			esc_attr( $search ),
			esc_html__( 'Search', 'do4u' )
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
			'<li><a href="%1$s"%2$s>%3$s <span>(%4$d)</span></a></li>',
			esc_url( add_query_arg( $base, admin_url( 'admin.php' ) ) ),
			'' === $category ? ' aria-current="' . esc_attr( 'true' ) . '"' : '',
			esc_html__( 'All', 'do4u' ),
			count( $all )
		);
		foreach ( $labels as $id => $label ) {
			if ( empty( $counts[ $id ] ) ) {
				continue;
			}
			printf(
				'<li><a href="%1$s"%2$s>%3$s <span>(%4$d)</span></a></li>',
				esc_url( add_query_arg( $base + array( 'cat' => $id ), admin_url( 'admin.php' ) ) ),
				$category === $id ? ' aria-current="' . esc_attr( 'true' ) . '"' : '',
				esc_html( $label ),
				(int) $counts[ $id ]
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
		echo '<fieldset><legend>' . esc_html__( 'In the newsletter', 'do4u' ) . '</legend><ul data-do4u-picker-selected></ul></fieldset></div>';
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
			printf( '<td><input type="number" class="small-text" id="do4u-budget-day" name="budget_day" min="1" max="10000000" value="%d" required></td></tr>', (int) $day );
			echo '<tr><th scope="row"><label for="do4u-budget-month">' . esc_html__( 'Credits per month', 'do4u' ) . '</label></th>';
			printf( '<td><input type="number" class="small-text" id="do4u-budget-month" name="budget_month" min="1" max="100000000" value="%d" required>', (int) $month );
			echo '<p class="description">' . esc_html__( 'Upper limit for all tools on this site together. do4u.eu stops new jobs when it is reached.', 'do4u' ) . '</p></td></tr>';
		};

		echo '<h2>' . esc_html__( 'Connection', 'do4u' ) . '</h2>';
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
		echo '<h2>' . esc_html__( 'Disconnect', 'do4u' ) . '</h2><p>' . esc_html__( 'Revokes the widget of this site at do4u.eu and removes the API key from WordPress. The key itself stays valid in your account.', 'do4u' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="do4u_wp_disconnect">';
		wp_nonce_field( 'do4u_wp_disconnect' );
		submit_button( __( 'Disconnect', 'do4u' ), 'delete' );
		echo '</form></div>';
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
			),
			array(
				__( 'Through the public address', 'do4u' ),
				__( 'These checks know nothing but an address: do4u.eu reads the page itself. The page must be reachable from outside, so protected, internal and staging pages cannot be checked.', 'do4u' ),
				Do4u_Places::site_checks_address(),
			),
		);
		foreach ( $groups as [ $heading, $note, $names ] ) {
			printf( '<h2>%1$s</h2><p>%2$s</p><ul class="do4u-catalog">', esc_html( $heading ), esc_html( $note ) );
			foreach ( $names as $name ) {
				$args = array( 'preset' => 'site_check' ) + ( $chosen > 0 ? array( 'item' => $chosen ) : array() );
				printf( '<li><a class="do4u-tool-link" href="%1$s">%2$s</a></li>', esc_url( self::tool_url( $name, $args ) ), esc_html( Do4u_Catalog::title( $name ) ) );
			}
			echo '</ul>';
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
