<?php
/**
 * The admin page do4u.eu > Shop: create, batches, history, settings of the shop tools.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/** Renders the admin page. All output is escaped where it is printed. */
final class Do4u_Shop_Admin {

	/** Products per page in the product choice. */
	const PER_PAGE = 50;

	/** Hook suffix of the page. @var string */
	public static $hook = '';

	/** Registers menu, assets and help. */
	public static function boot() {
		add_action( 'admin_menu', array( self::class, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}

	/** Adds the page to the do4u.eu menu, before Settings. */
	public static function menu() {
		self::$hook = (string) add_submenu_page( 'do4u', __( 'Shop products', 'do4u' ), __( 'Shop', 'do4u' ), Do4u_Shop_Settings::capability(), Do4u_Shop::PAGE, array( self::class, 'render' ), 3 );
		add_action( 'load-' . self::$hook, array( self::class, 'help' ) );
	}

	/**
	 * Styles and script, only on the own page.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( $hook ) {
		if ( $hook !== self::$hook ) {
			return;
		}
		$url = plugin_dir_url( DO4U_WP_FILE );
		wp_enqueue_style( 'do4u-shop-admin', $url . 'assets/css/do4u-shop-admin.css', array(), DO4U_WP_VERSION );
		wp_enqueue_script( 'do4u-shop-admin', $url . 'assets/js/do4u-shop-admin.js', array(), DO4U_WP_VERSION, true );
		wp_localize_script(
			'do4u-shop-admin',
			'do4uShopAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'do4u_shop_status' ),
				// Texts are translated here, so that the translation file of the plugin is enough (no JSON files).
				'i18n'  => array(
					/* translators: %d: number of selected products */
					'selected' => __( '%d selected.', 'do4u' ),
					'cancel'   => __( 'Cancel', 'do4u' ),
					'confirm'  => __( 'Continue', 'do4u' ),
					/* translators: 1: entries finished, 2: all entries */
					'progress' => __( '%1$d of %2$d entries finished.', 'do4u' ),
				),
			)
		);
	}

	/** Help tabs of the page. */
	public static function help() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$screen->add_help_tab(
			array(
				'id'      => 'do4u-shop-how',
				'title'   => __( 'How it works', 'do4u' ),
				'content' => '<p>' . esc_html__( '1. Choose what to create and select products. 2. Get a free estimate of the credits. 3. Start the batch. 4. When it is done, compare each suggestion with the current text and apply what you want. Nothing changes in your shop before you apply it, and every change can be undone under History.', 'do4u' ) . '</p>',
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'do4u-shop-costs',
				'title'   => __( 'Costs', 'do4u' ),
				'content' => '<p>' . esc_html__( 'A batch uses credits of your do4u.eu account. The estimate is free. Entries that fail or are cancelled before they run cost nothing. Prices:', 'do4u' ) . ' <a href="' . esc_url( Do4u_Shop_Api::site_url( '/en/pricing' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'do4u.eu pricing', 'do4u' ) . '</a></p>',
			)
		);
		$screen->add_help_tab(
			array(
				'id'      => 'do4u-shop-privacy',
				'title'   => __( 'Privacy', 'do4u' ),
				'content' => '<p>' . esc_html__( 'Only when you ask for an estimate or start a batch, the facts of the selected products (name, categories, attributes, weight, dimensions, tags, existing texts, focus keyword) or the addresses of their images go to do4u.eu. No customer or order data is sent. Results are deleted at do4u.eu after import if the setting is on, otherwise after its storage period.', 'do4u' ) . '</p>',
			)
		);
	}

	/**
	 * Current tab.
	 *
	 * @return string
	 */
	private static function tab() {
		$tab = sanitize_key( self::arg( 'tab' ) );
		$tab = '' === $tab ? 'create' : $tab;
		return in_array( $tab, array( 'create', 'batches', 'batch', 'history', 'settings' ), true ) ? $tab : 'create';
	}

	/**
	 * A text query argument. Used for navigation and filters only; nothing is changed with it.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function arg( $name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation and filters only; nothing is changed.
		return isset( $_GET[ $name ] ) && is_string( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $name ] ) ) : '';
	}

	/** The page. */
	public static function render() {
		if ( ! Do4u_Shop_Settings::can_use() ) {
			return;
		}
		$tab = self::tab();
		echo '<div class="wrap do4u-wrap do4u-shop">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Shop products', 'do4u' ) . '</h1>';
		echo '<hr class="wp-header-end">';
		$notice = Do4u_Shop_Actions::take_notice();
		if ( $notice ) {
			$type = in_array( $notice['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $notice['type'] : 'info';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
		}
		if ( ! Do4u_Secret::has() && 'settings' !== $tab ) {
			echo '<div class="notice notice-warning"><p>';
			if ( current_user_can( 'manage_options' ) ) {
				echo esc_html__( 'Connect your do4u.eu account first.', 'do4u' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=do4u-settings' ) ) . '">' . esc_html__( 'Go to do4u.eu > Settings', 'do4u' ) . '</a>';
			} else {
				echo esc_html__( 'An administrator has to connect a do4u.eu account first.', 'do4u' );
			}
			echo '</p></div>';
		}
		$tabs = array(
			'create'   => __( 'Create texts', 'do4u' ),
			'batches'  => __( 'Batches', 'do4u' ),
			'history'  => __( 'History', 'do4u' ),
			'settings' => __( 'Settings', 'do4u' ),
		);
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Sections', 'do4u' ) . '">';
		foreach ( $tabs as $k => $label ) {
			$active = $k === $tab || ( 'batch' === $tab && 'batches' === $k );
			echo '<a href="' . esc_url( Do4u_Shop_Actions::url( $k ) ) . '" class="nav-tab' . ( $active ? ' nav-tab-active' : '' ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		switch ( $tab ) {
			case 'batches':
				self::render_batches();
				break;
			case 'batch':
				self::render_batch();
				break;
			case 'history':
				self::render_history();
				break;
			case 'settings':
				self::render_settings();
				break;
			default:
				self::render_create();
		}
		echo '</div>';
	}

	/**
	 * Hidden fields of a form to admin-post.php.
	 *
	 * @param string $action Action without prefix.
	 * @param array  $fields More hidden fields.
	 * @param string $class  CSS class of the form.
	 */
	private static function form_start( $action, array $fields = array(), $class = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>';
		echo '<input type="hidden" name="action" value="' . esc_attr( 'do4u_shop_' . $action ) . '">';
		wp_nonce_field( 'do4u_shop_' . $action );
		foreach ( $fields as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) $v ) . '">';
		}
	}

	// ------------------------------------------------------------------------------------------------------------------
	// Create
	// ------------------------------------------------------------------------------------------------------------------

	/** Tab "Create texts": tool, products, options. */
	private static function render_create() {
		$tools  = Do4u_Shop_Tools::all();
		$tool   = self::arg( 'tool' );
		$tool   = isset( $tools[ $tool ] ) ? $tool : '';
		$ids    = array_values( array_filter( array_map( 'absint', explode( ',', self::arg( 'selected' ) ) ) ) );
		$cat    = absint( self::arg( 'cat' ) );
		$status = self::arg( 'status' );
		$status = in_array( $status, array( 'publish', 'draft', 'pending', 'private' ), true ) ? $status : 'any';
		$empty  = self::arg( 'empty' );
		$empty  = in_array( $empty, array( 'description', 'short_description' ), true ) ? $empty : '';
		$search = self::arg( 's' );
		$paged  = max( 1, absint( self::arg( 'paged' ) ) );

		echo '<p class="do4u-shop-lead">' . esc_html__( 'Let do4u.eu write texts for many products at once. You get a free estimate first, start the batch yourself, and then decide for every product and field what is taken over. Nothing is published or changed before that.', 'do4u' ) . '</p>';

		// Step 1 and filters: a GET form, it changes nothing.
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="do4u-shop-step">';
		echo '<input type="hidden" name="page" value="do4u-shop"><input type="hidden" name="tab" value="create">';
		if ( $ids ) {
			echo '<input type="hidden" name="selected" value="' . esc_attr( implode( ',', $ids ) ) . '">';
		}
		echo '<h2><span class="do4u-shop-num" aria-hidden="true">1</span> ' . esc_html__( 'What should do4u.eu write?', 'do4u' ) . '</h2>';
		echo '<fieldset class="do4u-shop-tools"><legend class="screen-reader-text">' . esc_html__( 'Tool', 'do4u' ) . '</legend>';
		foreach ( $tools as $name => $def ) {
			echo '<label class="do4u-shop-tool' . ( $name === $tool ? ' is-selected' : '' ) . '"><input type="radio" name="tool" value="' . esc_attr( $name ) . '"' . checked( $name, $tool, false ) . '> <strong>' . esc_html( $def['label'] ) . '</strong><span>' . esc_html( $def['help'] ) . '</span></label>';
		}
		echo '</fieldset>';
		echo '<h2><span class="do4u-shop-num" aria-hidden="true">2</span> ' . esc_html__( 'Which products?', 'do4u' ) . '</h2>';
		if ( $ids ) {
			/* translators: %d: number of products */
			echo '<p>' . esc_html( sprintf( _n( 'Showing the %d product you selected in the product list.', 'Showing the %d products you selected in the product list.', count( $ids ), 'do4u' ), count( $ids ) ) ) . ' <a href="' . esc_url( Do4u_Shop_Actions::url( 'create', array( 'tool' => $tool ) ) ) . '">' . esc_html__( 'Show all products instead', 'do4u' ) . '</a></p>';
		} else {
			echo '<div class="do4u-shop-filters">';
			echo '<label>' . esc_html__( 'Category', 'do4u' ) . ' ';
			wp_dropdown_categories(
				array(
					'taxonomy'         => 'product_cat',
					'name'             => 'cat',
					'selected'         => $cat,
					'show_option_all'  => __( 'All categories', 'do4u' ),
					'hierarchical'     => true,
					'hide_empty'       => false,
					'value_field'      => 'term_id',
					'orderby'          => 'name',
				)
			);
			echo '</label> <label>' . esc_html__( 'Status', 'do4u' ) . ' <select name="status">';
			foreach ( array(
				'any'     => __( 'All', 'do4u' ),
				'publish' => __( 'Published', 'do4u' ),
				'draft'   => __( 'Draft', 'do4u' ),
				'pending' => __( 'Pending review', 'do4u' ),
				'private' => __( 'Private', 'do4u' ),
			) as $k => $label ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $k, $status, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></label> <label>' . esc_html__( 'Only products with', 'do4u' ) . ' <select name="empty">';
			foreach ( array(
				''                  => __( 'any texts', 'do4u' ),
				'description'       => __( 'an empty description', 'do4u' ),
				'short_description' => __( 'an empty short description', 'do4u' ),
			) as $k => $label ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $k, $empty, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></label> <label><span class="screen-reader-text">' . esc_html__( 'Search products', 'do4u' ) . '</span><input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search products', 'do4u' ) . '"></label>';
			echo '</div>';
		}
		echo '<p><button type="submit" class="button">' . esc_html__( 'Show products', 'do4u' ) . '</button></p>';
		echo '</form>';

		if ( '' === $tool ) {
			echo '<p class="description">' . esc_html__( 'Choose what to write and click "Show products".', 'do4u' ) . '</p>';
			return;
		}

		$query = self::product_query( $ids, $cat, $status, $empty, $search, $paged );
		$def   = $tools[ $tool ];
		$max   = (int) Do4u_Shop_Settings::get( 'batch_size' );

		self::form_start( 'estimate', array( 'tool' => $tool ), 'do4u-shop-estimate' );
		if ( ! $query->posts ) {
			echo '<p>' . esc_html__( 'No products match these filters.', 'do4u' ) . '</p></form>';
			return;
		}
		/* translators: 1: number of products found, 2: entries per batch */
		echo '<p>' . esc_html( sprintf( __( '%1$d products found. Select up to %2$d entries per batch (see Settings).', 'do4u' ), (int) $query->found_posts, $max ) ) . ' <span class="do4u-shop-count" data-max="' . esc_attr( (string) $max ) . '" aria-live="polite"></span></p>';
		echo '<div class="do4u-shop-table-wrap"><table class="widefat striped do4u-shop-products"><thead><tr>';
		echo '<td class="check-column"><input type="checkbox" class="do4u-shop-check-all" aria-label="' . esc_attr__( 'Select all products on this page', 'do4u' ) . '"></td>';
		echo '<th scope="col">' . esc_html__( 'Product', 'do4u' ) . '</th><th scope="col">' . esc_html__( 'Description', 'do4u' ) . '</th><th scope="col">' . esc_html__( 'Short description', 'do4u' ) . '</th><th scope="col">' . esc_html__( 'Image alt text', 'do4u' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $query->posts as $pid ) {
			$p = wc_get_product( $pid );
			if ( ! $p ) {
				continue;
			}
			$img     = (int) $p->get_image_id();
			$alt     = $img ? trim( (string) get_post_meta( $img, '_wp_attachment_image_alt', true ) ) : '';
			$checked = $ids && in_array( (int) $pid, $ids, true );
			/* translators: %s: product name */
			echo '<tr><th scope="row" class="check-column"><input type="checkbox" name="products[]" value="' . esc_attr( (string) $pid ) . '" id="do4u-shop-p-' . esc_attr( (string) $pid ) . '" aria-label="' . esc_attr( sprintf( __( 'Select %s', 'do4u' ), $p->get_name() ) ) . '"' . checked( $checked, true, false ) . '></th>';
			echo '<td class="do4u-shop-product"><label for="do4u-shop-p-' . esc_attr( (string) $pid ) . '">' . ( $img ? wp_kses_post( wp_get_attachment_image( $img, array( 40, 40 ), false, array( 'alt' => '' ) ) ) : '<span class="do4u-shop-noimg" aria-hidden="true"></span>' ) . '<strong>' . esc_html( $p->get_name() ) . '</strong></label>';
			echo '<span class="do4u-shop-meta">' . esc_html( self::status_label( $p->get_status() ) ) . ( $p->get_sku() ? ' · ' . esc_html__( 'SKU', 'do4u' ) . ' ' . esc_html( $p->get_sku() ) : '' ) . ' · <a href="' . esc_url( (string) get_edit_post_link( $pid ) ) . '">' . esc_html__( 'Edit', 'do4u' ) . '</a></span></td>';
			echo '<td>' . esc_html( self::words( $p->get_description( 'edit' ) ) ) . '</td>';
			echo '<td>' . esc_html( self::words( $p->get_short_description( 'edit' ) ) ) . '</td>';
			echo '<td>' . ( $img ? ( '' === $alt ? '<span class="do4u-shop-missing">' . esc_html__( 'missing', 'do4u' ) . '</span>' : esc_html( wp_trim_words( $alt, 8 ) ) ) : esc_html__( 'no image', 'do4u' ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		self::pagination( $query, $paged );

		echo '<h2><span class="do4u-shop-num" aria-hidden="true">3</span> ' . esc_html__( 'Fields and options', 'do4u' ) . '</h2>';
		$labels = Do4u_Shop_Tools::field_labels();
		echo '<fieldset class="do4u-shop-fields"><legend>' . esc_html__( 'Fill these fields', 'do4u' ) . '</legend>';
		foreach ( $def['fields'] as $f ) {
			echo '<label><input type="checkbox" name="fields[]" value="' . esc_attr( $f ) . '"' . checked( in_array( $f, $def['default_fields'], true ), true, false ) . '> ' . esc_html( $labels[ $f ] ) . '</label>';
		}
		echo '</fieldset>';
		self::render_options( $tool );
		if ( $def['image'] && self::local_site() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'This shop seems to run on a local or private address. do4u.eu loads images from their public address, so image tasks will fail here unless the images are reachable from the internet.', 'do4u' ) . '</p></div>';
		}
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Get free estimate', 'do4u' ) . '</button> <span class="description">' . esc_html__( 'Nothing is charged yet. You start the batch on the next page.', 'do4u' ) . '</span></p>';
		echo '</form>';
	}

	/**
	 * Options of a tool.
	 *
	 * @param string $tool Tool.
	 */
	private static function render_options( $tool ) {
		$s     = Do4u_Shop_Settings::all();
		$names = Do4u_Shop_Settings::language_names();
		echo '<div class="do4u-shop-options">';
		if ( 'product_description' === $tool ) {
			self::select(
				'opt[tone]',
				__( 'Tone', 'do4u' ),
				array(
					'neutral'    => __( 'Neutral', 'do4u' ),
					'friendly'   => __( 'Friendly', 'do4u' ),
					'persuasive' => __( 'Persuasive', 'do4u' ),
					'formal'     => __( 'Formal', 'do4u' ),
				),
				$s['tone']
			);
			self::select(
				'opt[length]',
				__( 'Length', 'do4u' ),
				array(
					'short'  => __( 'Short', 'do4u' ),
					'medium' => __( 'Medium', 'do4u' ),
					'long'   => __( 'Long', 'do4u' ),
				),
				$s['length']
			);
			self::select(
				'opt[channel]',
				__( 'Written for', 'do4u' ),
				array(
					'shop'        => __( 'Own shop', 'do4u' ),
					'marketplace' => __( 'Marketplace listing (factual, no promotional claims)', 'do4u' ),
				),
				$s['channel']
			);
		}
		if ( 'alt_text' === $tool ) {
			echo '<label><input type="checkbox" name="opt[only_missing_alt]" value="1" checked> ' . esc_html__( 'Only images without alt text', 'do4u' ) . '</label> ';
			echo '<label><input type="checkbox" name="opt[gallery]" value="1"> ' . esc_html__( 'Include gallery images', 'do4u' ) . '</label>';
			/* translators: %s: language name */
			echo '<p class="description">' . esc_html( sprintf( __( 'Alt texts are written in %s. do4u.eu writes alt texts in a batch in English, German or Polish only.', 'do4u' ), $names[ Do4u_Shop_Settings::batch_language() ] ) ) . '</p>';
		} else {
			$auto    = 'seo_meta' !== $tool;
			$choices = $auto ? array( 'auto' => __( 'Same as the product data', 'do4u' ) ) : array();
			foreach ( Do4u_Shop_Settings::TARGETS as $code ) {
				$choices[ $code ] = $names[ $code ];
			}
			self::select( 'opt[target]', __( 'Language of the texts', 'do4u' ), $choices, Do4u_Shop_Settings::target( $auto ) );
		}
		echo '</div>';
	}

	/**
	 * A labelled select field.
	 *
	 * @param string $name     Name.
	 * @param string $label    Label.
	 * @param array  $choices  Value => label.
	 * @param string $selected Selected value.
	 */
	private static function select( $name, $label, array $choices, $selected ) {
		$id = 'do4u-shop-' . sanitize_html_class( str_replace( array( '[', ']' ), array( '-', '' ), $name ) );
		echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label> <select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		foreach ( $choices as $k => $v ) {
			echo '<option value="' . esc_attr( (string) $k ) . '"' . selected( (string) $k, (string) $selected, false ) . '>' . esc_html( $v ) . '</option>';
		}
		echo '</select> ';
	}

	/**
	 * Query for the product choice.
	 *
	 * @param int[]  $ids    Fixed products from the bulk action.
	 * @param int    $cat    Category.
	 * @param string $status Status.
	 * @param string $empty  Only products with this field empty.
	 * @param string $search Search.
	 * @param int    $paged  Page.
	 * @return WP_Query
	 */
	private static function product_query( array $ids, $cat, $status, $empty, $search, $paged ) {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => ( 'any' === $status ) ?array( 'publish', 'draft', 'pending', 'private', 'future' ) : $status,
			'fields'         => 'ids',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => $paged,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( $ids ) {
			$args['post__in']       = $ids;
			$args['orderby']        = 'post__in';
			$args['posts_per_page'] = 200;
			$args['paged']          = 1;
			return new WP_Query( $args );
		}
		$term = $cat ? get_term( $cat, 'product_cat' ) : null;
		if ( $term instanceof WP_Term ) {
			$args['product_cat'] = $term->slug;
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( '' !== $empty ) {
			$args['do4u_shop_empty'] = $empty;
			add_filter( 'posts_where', array( self::class, 'where_empty' ), 10, 2 );
		}
		$q = new WP_Query( $args );
		remove_filter( 'posts_where', array( self::class, 'where_empty' ), 10 );
		return $q;
	}

	/**
	 * Limits the product choice to products with an empty description or short description.
	 *
	 * @param string   $where WHERE clause.
	 * @param WP_Query $query Query.
	 * @return string
	 */
	public static function where_empty( $where, $query ) {
		global $wpdb;
		$field = $query->get( 'do4u_shop_empty' );
		if ( 'description' === $field ) {
			$where .= " AND {$wpdb->posts}.post_content = ''";
		} elseif ( 'short_description' === $field ) {
			$where .= " AND {$wpdb->posts}.post_excerpt = ''";
		}
		return $where;
	}

	/**
	 * Pagination of the product choice.
	 *
	 * @param WP_Query $query Query.
	 * @param int      $paged Page.
	 */
	private static function pagination( $query, $paged ) {
		if ( $query->max_num_pages < 2 ) {
			return;
		}
		$links = paginate_links(
			array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $paged,
				'total'   => (int) $query->max_num_pages,
				'type'    => 'array',
			)
		);
		echo '<nav class="do4u-shop-pages" aria-label="' . esc_attr__( 'Pages', 'do4u' ) . '">' . wp_kses_post( implode( ' ', (array) $links ) ) . '</nav>';
		echo '<p class="description">' . esc_html__( 'Selections are per page: create one batch per page, then continue with the next page.', 'do4u' ) . '</p>';
	}

	/**
	 * Short description of the length of a text.
	 *
	 * @param string $html Text.
	 * @return string
	 */
	private static function words( $html ) {
		$n = str_word_count( wp_strip_all_tags( (string) $html ) );
		if ( 0 === $n ) {
			return __( 'empty', 'do4u' );
		}
		/* translators: %d: number of words */
		return sprintf( _n( '%d word', '%d words', $n, 'do4u' ), $n );
	}

	/**
	 * Label of a post status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function status_label( $status ) {
		$o = get_post_status_object( $status );
		return $o ? (string) $o->label : $status;
	}

	/**
	 * Whether the shop runs on an address do4u.eu cannot reach.
	 *
	 * @return bool
	 */
	private static function local_site() {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}
		// A name without a dot is only known inside a network; these endings are reserved for private use.
		if ( ! str_contains( $host, '.' ) || in_array( substr( (string) strrchr( $host, '.' ), 1 ), array( 'local', 'test', 'internal', 'lan', 'home', 'corp', 'example', 'invalid' ), true ) ) {
			return true;
		}
		return false;
	}

	// ------------------------------------------------------------------------------------------------------------------
	// Batches
	// ------------------------------------------------------------------------------------------------------------------

	/**
	 * Label of a batch state.
	 *
	 * @param array $b Batch.
	 * @return string
	 */
	private static function state_label( array $b ) {
		$labels = array(
			'estimated' => __( 'Estimated, not started', 'do4u' ),
			'starting'  => __( 'Starting', 'do4u' ),
			'running'   => __( 'Running', 'do4u' ),
			'imported'  => __( 'Ready for review', 'do4u' ),
			'failed'    => __( 'Failed', 'do4u' ),
			'unknown'   => __( 'Start not confirmed', 'do4u' ),
		);
		if ( 'imported' === $b['status'] ) {
			$open = 0;
			foreach ( $b['results'] as $r ) {
				if ( 'done' === $r['status'] && empty( $r['discarded'] ) && count( $r['applied'] ) < count( $r['proposals'] ) ) {
					++$open;
				}
			}
			/* translators: %d: number of products with suggestions not applied yet */
			return 0 === $open ? __( 'Reviewed', 'do4u' ) : sprintf( _n( 'Ready for review (%d open)', 'Ready for review (%d open)', $open, 'do4u' ), $open );
		}
		return $labels[ $b['status'] ] ?? $b['status'];
	}

	/**
	 * Date and time in the site format.
	 *
	 * @param int $time Unix time.
	 * @return string
	 */
	private static function when( $time ) {
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $time );
	}

	/** Tab "Batches". */
	private static function render_batches() {
		$ids = Do4u_Shop_Batches::ids();
		if ( ! $ids ) {
			echo '<p>' . esc_html__( 'No batches yet.', 'do4u' ) . ' <a href="' . esc_url( Do4u_Shop_Actions::url( 'create' ) ) . '">' . esc_html__( 'Create texts', 'do4u' ) . '</a></p>';
			return;
		}
		$tools = Do4u_Shop_Tools::all();
		echo '<div class="do4u-shop-table-wrap"><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Created', 'do4u' ) . '</th><th scope="col">' . esc_html__( 'What', 'do4u' ) . '</th><th scope="col">' . esc_html__( 'Entries', 'do4u' ) . '</th><th scope="col">' . esc_html__( 'Status', 'do4u' ) . '</th><th scope="col">' . esc_html__( 'Credits', 'do4u' ) . '</th><th scope="col">' . esc_html__( 'By', 'do4u' ) . '</th></tr></thead><tbody>';
		foreach ( $ids as $id ) {
			$b = Do4u_Shop_Batches::get( $id );
			if ( ! $b ) {
				continue;
			}
			$user = get_userdata( (int) $b['user'] );
			echo '<tr><td><a href="' . esc_url( Do4u_Shop_Actions::url( 'batch', array( 'batch' => $id ) ) ) . '">' . esc_html( self::when( (int) $b['created'] ) ) . '</a></td>';
			echo '<td>' . esc_html( $tools[ $b['tool'] ]['label'] ?? $b['tool'] ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( count( $b['items'] ) ) ) . '</td>';
			echo '<td>' . esc_html( self::state_label( $b ) ) . '</td>';
			echo '<td>' . esc_html( 'estimated' === $b['status'] ? '≈ ' . number_format_i18n( (int) ( $b['estimate']['credits'] ?? 0 ) ) : number_format_i18n( (int) $b['credits'] ) ) . '</td>';
			echo '<td>' . esc_html( $user ? $user->display_name : '–' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		/* translators: %d: number of batches kept */
		echo '<p class="description">' . esc_html( sprintf( __( 'The newest %d batches are kept here. Applied changes stay in the History even when a batch is deleted.', 'do4u' ), Do4u_Shop_Batches::KEEP ) ) . '</p>';
	}

	/** One batch: estimate, progress or review. */
	private static function render_batch() {
		$b = Do4u_Shop_Batches::get( self::arg( 'batch' ) );
		if ( ! $b ) {
			echo '<p>' . esc_html__( 'This batch does not exist (any more).', 'do4u' ) . '</p>';
			return;
		}
		$tools  = Do4u_Shop_Tools::all();
		$labels = Do4u_Shop_Tools::field_labels();
		$user   = get_userdata( (int) $b['user'] );
		echo '<h2>' . esc_html( $tools[ $b['tool'] ]['label'] ?? $b['tool'] ) . ' <span class="do4u-shop-badge do4u-shop-badge-' . esc_attr( $b['status'] ) . '">' . esc_html( self::state_label( $b ) ) . '</span></h2>';
		/* translators: 1: date, 2: user name, 3: list of fields */
		echo '<p class="do4u-shop-meta">' . esc_html( sprintf( __( 'Created %1$s by %2$s · Fields: %3$s', 'do4u' ), self::when( (int) $b['created'] ), $user ? $user->display_name : '–', implode( ', ', array_intersect_key( $labels, array_flip( $b['fields'] ) ) ) ) ) . '</p>';
		if ( '' !== (string) $b['message'] ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( (string) $b['message'] ) . '</p></div>';
		}
		switch ( $b['status'] ) {
			case 'estimated':
				self::render_estimate( $b );
				break;
			case 'running':
			case 'starting':
				self::render_progress( $b );
				break;
			case 'imported':
				self::render_review( $b );
				break;
			case 'unknown':
				echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The connection broke while the batch was being started, so it is not known whether do4u.eu started it. Look at your batches in your do4u.eu account before you start the same products again.', 'do4u' ) . ' <a href="' . esc_url( Do4u_Shop_Api::site_url( '/batch' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open do4u.eu batches', 'do4u' ) . '</a></p></div>';
				break;
		}
		echo '<div class="do4u-shop-batch-actions">';
		if ( 'running' !== $b['status'] && 'starting' !== $b['status'] ) {
			self::form_start( 'delete', array( 'batch' => $b['id'] ) );
			echo '<button type="submit" class="button button-link-delete" data-do4u-shop-confirm="' . esc_attr__( 'Delete this batch here and its results at do4u.eu? Changes you applied stay.', 'do4u' ) . '">' . esc_html__( 'Delete batch', 'do4u' ) . '</button></form>';
		}
		echo '</div>';
	}

	/**
	 * Estimate and start.
	 *
	 * @param array $b Batch.
	 */
	private static function render_estimate( array $b ) {
		$e       = $b['estimate'];
		$balance = self::balance();
		echo '<div class="do4u-shop-card do4u-shop-estimate-box">';
		echo '<p class="do4u-shop-big">' . esc_html(
			sprintf(
				/* translators: 1: estimated credits, 2: number of entries */
				_n( 'About %1$s credits for %2$d entry', 'About %1$s credits for %2$d entries', (int) $e['valid'], 'do4u' ),
				number_format_i18n( (int) $e['credits'] ),
				(int) $e['valid']
			)
		) . '</p>';
		if ( null !== $balance ) {
			/* translators: %s: number of credits */
			echo '<p>' . esc_html( sprintf( __( 'Your balance: %s credits.', 'do4u' ), number_format_i18n( (int) $balance ) ) ) . '</p>';
			if ( (int) $balance < (int) $e['credits'] ) {
				echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Your balance is lower than the estimate. do4u.eu reserves the estimate when the batch starts and would refuse it.', 'do4u' ) . ' <a href="' . esc_url( Do4u_Shop_Api::site_url( '/credits' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Buy credits', 'do4u' ) . '</a></p></div>';
			}
		}
		echo '<p class="description">' . esc_html__( 'The estimate is reserved when the batch starts; you pay what the results really use, entries that fail cost nothing. Batches run with low priority at do4u.eu, at busy times this can take a while.', 'do4u' ) . '</p>';
		if ( (int) $e['skipped'] > 0 || $b['skipped'] ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Some products are left out:', 'do4u' ) . '</p><ul class="ul-disc">';
			foreach ( $b['skipped'] as $s ) {
				echo '<li>' . esc_html( $s[0] . ': ' . $s[1] ) . '</li>';
			}
			foreach ( $e['reasons'] as $r ) {
				$label = $b['items'][ max( 0, (int) $r['row'] - 1 ) ]['label'] ?? '';
				echo '<li>' . esc_html( $label . ': ' . $r['message'] ) . '</li>';
			}
			echo '</ul></div>';
		}
		self::form_start( 'start', array( 'batch' => $b['id'] ) );
		echo '<p><button type="submit" class="button button-primary button-hero" data-do4u-shop-confirm="' . esc_attr__( 'Start the batch now? This uses credits of your do4u.eu account.', 'do4u' ) . '">' . esc_html(
			/* translators: %s: estimated credits */
			sprintf( __( 'Start batch (about %s credits)', 'do4u' ), number_format_i18n( (int) $e['credits'] ) )
		) . '</button></p></form>';
		echo '</div>';
		echo '<h3>' . esc_html__( 'What is sent to do4u.eu', 'do4u' ) . '</h3>';
		echo '<ul class="do4u-shop-sent">';
		foreach ( $b['items'] as $item ) {
			echo '<li><details><summary>' . esc_html( $item['label'] ) . ( 'image' === $item['kind'] ? ' · ' . esc_html__( 'image', 'do4u' ) . ' #' . esc_html( (string) $item['id'] ) : '' ) . '</summary><pre>';
			foreach ( $item['row'] as $k => $v ) {
				echo esc_html( $k . ': ' . $v ) . "\n";
			}
			echo '</pre></details></li>';
		}
		echo '</ul>';
	}

	/**
	 * Balance of the account, cached for five minutes.
	 *
	 * @return int|null
	 */
	private static function balance() {
		$b = get_transient( 'do4u_shop_balance' );
		if ( ! is_array( $b ) ) {
			try {
				$b = Do4u_Shop_Api::request( 'GET', 'balance', null, 10 );
				set_transient( 'do4u_shop_balance', $b, 5 * MINUTE_IN_SECONDS );
			} catch ( Do4u_Error $e ) {
				return null;
			}
		}
		return isset( $b['credits'] ) ? (int) $b['credits'] : null;
	}

	/**
	 * Progress of a running batch.
	 *
	 * @param array $b Batch.
	 */
	private static function render_progress( array $b ) {
		$s     = $b['remote_status'] ?? array();
		$total = max( 1, (int) ( $s['total'] ?? count( $b['items'] ) ) );
		$done  = (int) ( $s['done'] ?? 0 ) + (int) ( $s['failed'] ?? 0 ) + (int) ( $s['skipped'] ?? 0 ) + (int) ( $s['cancelled'] ?? 0 );
		echo '<div class="do4u-shop-card" data-do4u-shop-poll="' . esc_attr( $b['id'] ) . '">';
		echo '<progress class="do4u-shop-progress" max="' . esc_attr( (string) $total ) . '" value="' . esc_attr( (string) $done ) . '"></progress>';
		/* translators: 1: entries finished, 2: all entries */
		echo '<p class="do4u-shop-progress-text" aria-live="polite">' . esc_html( sprintf( __( '%1$d of %2$d entries finished.', 'do4u' ), $done, $total ) ) . '</p>';
		echo '<p class="description">' . esc_html__( 'This page checks the progress every 15 seconds and shows the suggestions as soon as the batch is done. You can also leave it and come back later.', 'do4u' ) . '</p>';
		echo '<div class="do4u-shop-inline-forms">';
		self::form_start( 'refresh', array( 'batch' => $b['id'] ) );
		echo '<button type="submit" class="button">' . esc_html__( 'Check now', 'do4u' ) . '</button></form>';
		if ( 'running' === $b['status'] ) {
			self::form_start( 'cancel', array( 'batch' => $b['id'] ) );
			echo '<button type="submit" class="button" data-do4u-shop-confirm="' . esc_attr__( 'Cancel the batch? Entries that are done already stay available; the rest costs nothing.', 'do4u' ) . '">' . esc_html__( 'Cancel batch', 'do4u' ) . '</button></form>';
		}
		echo '</div></div>';
	}

	/**
	 * Review of the suggestions.
	 *
	 * @param array $b Batch.
	 */
	private static function render_review( array $b ) {
		$labels    = Do4u_Shop_Tools::field_labels();
		$flagtext  = Do4u_Shop_Tools::flag_labels();
		$conflicts = '' === self::arg( 'conflict' ) ? array() : array_map( 'absint', explode( ',', self::arg( 'conflict' ) ) );
		$counts    = array(
			'done'   => 0,
			'failed' => 0,
		);
		foreach ( $b['results'] as $r ) {
			++$counts[ 'done' === $r['status'] ? 'done' : 'failed' ];
		}
		echo '<p>' . esc_html(
			sprintf(
				/* translators: 1: entries with suggestions, 2: entries without, 3: credits used */
				__( '%1$d entries with suggestions, %2$d without. Credits used: %3$s.', 'do4u' ),
				$counts['done'],
				$counts['failed'],
				number_format_i18n( (int) $b['credits'] )
			)
		) . '</p>';
		echo '<p class="description">' . esc_html__( 'Compare and tick what you want to take over. Fields with a warning are not ticked. Suggestions are AI-generated: check facts before you publish.', 'do4u' ) . '</p>';
		self::form_start( 'apply', array( 'batch' => $b['id'] ), 'do4u-shop-review' );
		echo '<p class="do4u-shop-toolbar"><button type="submit" class="button button-primary">' . esc_html__( 'Apply ticked suggestions', 'do4u' ) . '</button> <button type="button" class="button do4u-shop-tick" data-do4u-shop-tick="none">' . esc_html__( 'Untick all', 'do4u' ) . '</button> <button type="button" class="button do4u-shop-tick" data-do4u-shop-tick="safe">' . esc_html__( 'Tick all without warnings', 'do4u' ) . '</button></p>';
		foreach ( $b['items'] as $i => $item ) {
			$r = $b['results'][ $i ] ?? array(
				'status' => 'failed',
				'error'  => '',
			);
			echo '<section class="do4u-shop-item' . ( ! empty( $r['discarded'] ) ? ' is-discarded' : '' ) . '" id="do4u-shop-item-' . esc_attr( (string) $i ) . '" aria-labelledby="do4u-shop-item-h-' . esc_attr( (string) $i ) . '">';
			echo '<header>' . ( $item['image'] ? wp_kses_post( wp_get_attachment_image( (int) $item['image'], array( 48, 48 ), false, array( 'alt' => '' ) ) ) : '' );
			echo '<h3 id="do4u-shop-item-h-' . esc_attr( (string) $i ) . '">' . esc_html( $item['label'] ) . ( 'image' === $item['kind'] ? ' <span class="do4u-shop-meta">' . esc_html__( 'image', 'do4u' ) . ' #' . esc_html( (string) $item['id'] ) . '</span>' : '' ) . '</h3>';
			echo '<a href="' . esc_url( (string) get_edit_post_link( (int) $item['product'] ) ) . '">' . esc_html__( 'Edit product', 'do4u' ) . '</a>';
			echo '</header>';
			if ( 'done' !== $r['status'] ) {
				echo '<p class="do4u-shop-error">' . esc_html( '' !== (string) $r['error'] ? (string) $r['error'] : __( 'No suggestion for this entry. It costs nothing.', 'do4u' ) ) . '</p></section>';
				continue;
			}
			foreach ( (array) $r['notes'] as $note ) {
				echo '<p class="do4u-shop-note">' . esc_html( (string) $note ) . '</p>';
			}
			if ( ! empty( $r['discarded'] ) ) {
				echo '<p>' . esc_html__( 'Discarded.', 'do4u' ) . ' <button type="submit" class="button-link" name="discard" value="' . esc_attr( (string) $i ) . '">' . esc_html__( 'Show again', 'do4u' ) . '</button></p></section>';
				continue;
			}
			if ( count( $r['variants'] ) > 1 ) {
				echo '<fieldset class="do4u-shop-variants"><legend>' . esc_html__( 'Variant', 'do4u' ) . '</legend>';
				foreach ( $r['variants'] as $n => $v ) {
					echo '<label><input type="radio" name="variant[' . esc_attr( (string) $i ) . ']" value="' . esc_attr( (string) $n ) . '"' . checked( 0, $n, false ) . '> <strong>' . esc_html( $v['seo_title'] ) . '</strong><br>' . esc_html( $v['meta_description'] ) . ( $v['too_long'] ? ' <em>(' . esc_html( $flagtext['too_long'] ) . ')</em>' : '' ) . '</label>';
				}
				echo '</fieldset>';
			}
			echo '<div class="do4u-shop-fields-table">';
			$conflict_here = false;
			$open_here     = false;
			foreach ( $r['proposals'] as $f => $value ) {
				$applied = isset( $r['applied'][ $f ] );
				$now     = Do4u_Shop_Tools::current( $item, $f );
				$changed = ! $applied && array_key_exists( $f, $item['before'] ) && $now !== (string) $item['before'][ $f ];
				$flags   = $r['flags'][ $f ] ?? array();
				$same    = ! $applied && Do4u_Shop_Apply::clean( $f, (string) $value ) === $now;
				$tick    = ! $applied && ! $flags && ! $changed && ! $same;
				$conflict_here = $conflict_here || $changed;
				$open_here     = $open_here || ( ! $applied && ! $same );
				echo '<div class="do4u-shop-field' . ( $applied ? ' is-applied' : '' ) . '">';
				echo '<div class="do4u-shop-field-head">';
				if ( $applied ) {
					echo '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <strong>' . esc_html( $labels[ $f ] ) . '</strong> <span class="do4u-shop-meta">' . esc_html__( 'applied', 'do4u' ) . ' · <a href="' . esc_url( Do4u_Shop_Actions::url( 'history', array( 'product' => (int) $item['product'] ) ) ) . '">' . esc_html__( 'undo in History', 'do4u' ) . '</a></span>';
				} elseif ( $same ) {
					echo '<span class="dashicons dashicons-minus" aria-hidden="true"></span> <strong>' . esc_html( $labels[ $f ] ) . '</strong> <span class="do4u-shop-meta">' . esc_html__( 'the suggestion is the same as the current value', 'do4u' ) . '</span>';
				} else {
					echo '<label><input type="checkbox" name="apply[' . esc_attr( (string) $i ) . '][' . esc_attr( $f ) . ']" value="1" data-do4u-shop-safe="' . ( $flags || $changed ? '0' : '1' ) . '"' . checked( $tick, true, false ) . '> <strong>' . esc_html( $labels[ $f ] ) . '</strong></label>';
				}
				echo '</div>';
				foreach ( $flags as $flag ) {
					echo '<p class="do4u-shop-flag"><span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html( $flagtext[ $flag ] ?? $flag ) . '</p>';
				}
				if ( $changed ) {
					echo '<p class="do4u-shop-flag"><span class="dashicons dashicons-warning" aria-hidden="true"></span> ' . esc_html__( 'This field was changed in the shop after the batch was prepared. Tick it and use "Overwrite anyway" to replace it regardless.', 'do4u' ) . '</p>';
				}
				echo '<div class="do4u-shop-compare"><div><span class="do4u-shop-col">' . esc_html__( 'Current', 'do4u' ) . '</span>' . wp_kses_post( self::show( $f, $now ) ) . '</div>';
				echo '<div><span class="do4u-shop-col">' . esc_html__( 'Suggestion', 'do4u' ) . '</span>' . wp_kses_post( self::show( $f, (string) $value ) ) . '</div></div>';
				echo '</div>';
			}
			echo '</div>';
			if ( ! $open_here ) {
				echo '</section>';
				continue;
			}
			echo '<p class="do4u-shop-item-actions">';
			echo '<button type="submit" class="button" name="only" value="' . esc_attr( (string) ( $i + 1 ) ) . '">' . esc_html__( 'Apply ticked fields of this entry', 'do4u' ) . '</button> ';
			if ( $conflict_here || in_array( $i, $conflicts, true ) ) {
				echo '<button type="submit" class="button" name="force" value="' . esc_attr( (string) ( $i + 1 ) ) . '" data-do4u-shop-confirm="' . esc_attr__( 'Overwrite the ticked fields of this entry, although they were changed in the shop?', 'do4u' ) . '">' . esc_html__( 'Overwrite anyway', 'do4u' ) . '</button> ';
			}
			echo '<button type="submit" class="button-link" name="discard" value="' . esc_attr( (string) $i ) . '">' . esc_html__( 'Discard', 'do4u' ) . '</button></p>';
			echo '</section>';
		}
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Apply ticked suggestions', 'do4u' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * A field value for the comparison, escaped.
	 *
	 * @param string $field Field.
	 * @param string $value Value.
	 * @return string HTML.
	 */
	private static function show( $field, $value ) {
		if ( '' === trim( wp_strip_all_tags( $value ) ) ) {
			return '<div class="do4u-shop-value is-empty">' . esc_html__( '(empty)', 'do4u' ) . '</div>';
		}
		if ( in_array( $field, array( 'description', 'short_description' ), true ) ) {
			return '<div class="do4u-shop-value do4u-shop-html">' . wp_kses_post( $value ) . '</div>';
		}
		/* translators: %d: number of characters */
		$len = sprintf( _n( '%d character', '%d characters', mb_strlen( $value ), 'do4u' ), mb_strlen( $value ) );
		return '<div class="do4u-shop-value">' . esc_html( $value ) . ' <span class="do4u-shop-meta">(' . esc_html( $len ) . ')</span></div>';
	}

	// ------------------------------------------------------------------------------------------------------------------
	// History
	// ------------------------------------------------------------------------------------------------------------------

	/** Tab "History". */
	private static function render_history() {
		$only   = absint( self::arg( 'product' ) );
		$labels = Do4u_Shop_Tools::field_labels();
		$open   = self::arg( 'undo_conflict' );
		if ( $only ) {
			$entries = array();
			foreach ( Do4u_Shop_Apply::history( $only ) as $h ) {
				$entries[] = array(
					'key'     => $h['key'],
					'time'    => $h['time'],
					'user'    => $h['user'],
					'product' => $only,
					'fields'  => array_keys( $h['changes'] ),
					'undone'  => $h['undone'],
				);
			}
			/* translators: %s: product name */
			echo '<p>' . esc_html( sprintf( __( 'Changes of "%s".', 'do4u' ), get_the_title( $only ) ) ) . ' <a href="' . esc_url( Do4u_Shop_Actions::url( 'history' ) ) . '">' . esc_html__( 'Show all products', 'do4u' ) . '</a></p>';
		} else {
			$entries = get_option( Do4u_Shop_Apply::LOG, array() );
			$entries = is_array( $entries ) ? $entries : array();
		}
		if ( ! $entries ) {
			echo '<p>' . esc_html__( 'Nothing was applied yet.', 'do4u' ) . '</p>';
			return;
		}
		echo '<p class="description">' . esc_html__( 'Undo restores the values from before the change. If a field was changed again since, you are asked first.', 'do4u' ) . '</p>';
		echo '<div class="do4u-shop-table-wrap"><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'When', 'do4u' ) . '</th><th scope="col">' . esc_html__( 'Product', 'do4u' ) . '</th><th scope="col">' . esc_html__( 'Fields', 'do4u' ) . '</th><th scope="col">' . esc_html__( 'By', 'do4u' ) . '</th><th scope="col">' . esc_html__( 'Undo', 'do4u' ) . '</th></tr></thead><tbody>';
		foreach ( $entries as $e ) {
			$pid  = (int) ( $e['product'] ?? 0 );
			$user = get_userdata( (int) ( $e['user'] ?? 0 ) );
			echo '<tr><td>' . esc_html( self::when( (int) $e['time'] ) ) . '</td>';
			echo '<td><a href="' . esc_url( Do4u_Shop_Actions::url( 'history', array( 'product' => $pid ) ) ) . '">' . esc_html( get_the_title( $pid ) ) . '</a></td>';
			echo '<td>' . esc_html( implode( ', ', array_intersect_key( $labels, array_flip( (array) $e['fields'] ) ) ) ) . '</td>';
			echo '<td>' . esc_html( $user ? $user->display_name : '–' ) . '</td><td>';
			if ( ! empty( $e['undone'] ) ) {
				/* translators: %s: date */
				echo esc_html( sprintf( __( 'undone %s', 'do4u' ), self::when( (int) $e['undone'] ) ) );
			} elseif ( current_user_can( 'edit_post', $pid ) ) {
				self::form_start(
					'undo',
					array(
						'product' => $pid,
						'key'     => $e['key'],
					),
					'do4u-shop-undo'
				);
				echo '<button type="submit" class="button button-small">' . esc_html__( 'Undo', 'do4u' ) . '</button>';
				if ( $open === $e['key'] ) {
					echo ' <button type="submit" class="button button-small" name="force" value="1" data-do4u-shop-confirm="' . esc_attr__( 'Restore the old values, although the fields were changed again since?', 'do4u' ) . '">' . esc_html__( 'Undo anyway', 'do4u' ) . '</button>';
				}
				echo '</form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	// ------------------------------------------------------------------------------------------------------------------
	// Settings
	// ------------------------------------------------------------------------------------------------------------------

	/** Tab "Settings". */
	private static function render_settings() {
		$admin = current_user_can( 'manage_options' );
		echo '<h2>' . esc_html__( 'Connection', 'do4u' ) . '</h2>';
		if ( Do4u_Secret::has() ) {
			$balance = self::balance();
			echo '<p><span class="do4u-shop-badge do4u-shop-badge-imported">' . esc_html__( 'Connected', 'do4u' ) . '</span> ';
			if ( null === $balance ) {
				echo esc_html__( 'do4u.eu does not answer at the moment, or the key is no longer valid.', 'do4u' );
			} else {
				/* translators: %s: number of credits */
				echo esc_html( sprintf( __( 'Balance: %s credits.', 'do4u' ), number_format_i18n( (int) $balance ) ) );
			}
			echo ' <a href="' . esc_url( Do4u_Shop_Api::site_url( '/credits' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Credits and account at do4u.eu', 'do4u' ) . '</a></p>';
		} else {
			echo '<p>' . esc_html__( 'The shop tools use the connection of do4u.eu. No API key is saved yet.', 'do4u' ) . '</p>';
		}
		if ( ! $admin ) {
			echo '<p>' . esc_html__( 'Only administrators can change the connection and the settings.', 'do4u' ) . '</p>';
			return;
		}
		echo '<p>' . esc_html__( 'The API key is saved and changed in one place for all do4u.eu tools.', 'do4u' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=do4u-settings' ) ) . '">' . esc_html__( 'Connection under do4u.eu > Settings', 'do4u' ) . '</a></p>';

		$s     = Do4u_Shop_Settings::all();
		$names = Do4u_Shop_Settings::language_names();
		$seo   = Do4u_Shop_Seo::target();
		self::form_start( 'save_settings' );
		echo '<h2>' . esc_html__( 'Defaults', 'do4u' ) . '</h2><table class="form-table" role="presentation">';
		$rows = array(
			'tone'    => array(
				__( 'Tone', 'do4u' ),
				array(
					'neutral'    => __( 'Neutral', 'do4u' ),
					'friendly'   => __( 'Friendly', 'do4u' ),
					'persuasive' => __( 'Persuasive', 'do4u' ),
					'formal'     => __( 'Formal', 'do4u' ),
				),
			),
			'length'  => array(
				__( 'Length', 'do4u' ),
				array(
					'short'  => __( 'Short', 'do4u' ),
					'medium' => __( 'Medium', 'do4u' ),
					'long'   => __( 'Long', 'do4u' ),
				),
			),
			'channel' => array(
				__( 'Written for', 'do4u' ),
				array(
					'shop'        => __( 'Own shop', 'do4u' ),
					'marketplace' => __( 'Marketplace listing', 'do4u' ),
				),
			),
			'target'  => array(
				__( 'Language of the texts', 'do4u' ),
				array(
					/* translators: %s: language code of the site */
					'site' => sprintf( __( 'Language of the shop (%s)', 'do4u' ), Do4u_Shop_Settings::site_language() ),
					'auto' => __( 'Same as the product data', 'do4u' ),
				) + array_intersect_key( $names, array_flip( Do4u_Shop_Settings::TARGETS ) ),
			),
		);
		foreach ( $rows as $k => $row ) {
			echo '<tr><th scope="row"><label for="do4u-shop-s-' . esc_attr( $k ) . '">' . esc_html( $row[0] ) . '</label></th><td><select id="do4u-shop-s-' . esc_attr( $k ) . '" name="do4u_shop[' . esc_attr( $k ) . ']">';
			foreach ( $row[1] as $v => $label ) {
				echo '<option value="' . esc_attr( (string) $v ) . '"' . selected( (string) $v, (string) $s[ $k ], false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></td></tr>';
		}
		echo '<tr><th scope="row">' . esc_html__( 'Bullet points', 'do4u' ) . '</th><td><label><input type="checkbox" name="do4u_shop[bullets]" value="1"' . checked( (int) $s['bullets'], 1, false ) . '> ' . esc_html__( 'Add the bullet points of do4u.eu as a list below the description', 'do4u' ) . '</label></td></tr>';
		echo '<tr><th scope="row"><label for="do4u-shop-s-size">' . esc_html__( 'Entries per batch', 'do4u' ) . '</label></th><td><input type="number" id="do4u-shop-s-size" name="do4u_shop[batch_size]" min="1" max="200" value="' . esc_attr( (string) $s['batch_size'] ) . '" class="small-text"><p class="description">' . esc_html__( 'Your do4u.eu package sets the upper limit (Free: 20).', 'do4u' ) . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Results at do4u.eu', 'do4u' ) . '</th><td><label><input type="checkbox" name="do4u_shop[delete_remote]" value="1"' . checked( (int) $s['delete_remote'], 1, false ) . '> ' . esc_html__( 'Delete a batch at do4u.eu as soon as its results are imported here', 'do4u' ) . '</label></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'SEO fields', 'do4u' ) . '</th><td>';
		if ( Do4u_Shop_Seo::has_plugin() ) {
			/* translators: %s: name of the SEO plugin */
			echo '<p>' . esc_html( sprintf( __( 'SEO title and meta description are saved in %s.', 'do4u' ), $seo['name'] ) ) . '</p><input type="hidden" name="do4u_shop[output_meta]" value="' . esc_attr( (string) $s['output_meta'] ) . '">';
		} else {
			echo '<p>' . esc_html__( 'No supported SEO plugin is active (Yoast SEO, Rank Math, SEOPress, The SEO Framework). SEO title and meta description are saved in own fields of the product.', 'do4u' ) . '</p>';
			echo '<label><input type="checkbox" name="do4u_shop[output_meta]" value="1"' . checked( (int) $s['output_meta'], 1, false ) . '> ' . esc_html__( 'Use these fields on product pages (document title and meta description tag)', 'do4u' ) . '</label>';
		}
		echo '</td></tr></table>';
		submit_button( __( 'Save settings', 'do4u' ) );
		echo '</form>';
		if ( defined( 'DO4U_API_BASE' ) ) {
			try {
				$base = Do4u_Api::base();
			} catch ( Do4u_Error $e ) {
				$base = $e->plain();
			}
			/* translators: %s: API address */
			echo '<p class="description">' . esc_html( sprintf( __( 'API address from wp-config.php (DO4U_API_BASE): %s', 'do4u' ), $base ) ) . '</p>';
		}
	}
}
