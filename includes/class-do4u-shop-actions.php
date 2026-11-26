<?php
/**
 * Form handlers (admin-post.php) and the status request (admin-ajax.php). Each handler checks nonce and rights itself.
 *
 * @package Do4u_WordPress
 */

defined( 'ABSPATH' ) || exit;

/** Handlers for every action of the admin page. */
final class Do4u_Shop_Actions {

	/** Notice for the next page view of the current user. */
	const NOTICE = 'do4u_shop_notice';

	/** Registers the handlers. */
	public static function boot() {
		foreach ( array( 'save_settings', 'estimate', 'start', 'refresh', 'cancel', 'delete', 'apply', 'undo' ) as $a ) {
			add_action( 'admin_post_do4u_shop_' . $a, array( self::class, 'handle_' . $a ) );
		}
		add_action( 'wp_ajax_do4u_shop_status', array( self::class, 'ajax_status' ) );
	}

	/**
	 * Remembers a notice for the next page view.
	 *
	 * @param string $type    success, error, warning or info.
	 * @param string $message Plain text.
	 */
	public static function notice( $type, $message ) {
		update_user_meta(
			get_current_user_id(),
			self::NOTICE,
			array(
				'type'    => $type,
				'message' => $message,
			)
		);
	}

	/**
	 * Takes the notice of the current user (once).
	 *
	 * @return array|null
	 */
	public static function take_notice() {
		$n = get_user_meta( get_current_user_id(), self::NOTICE, true );
		if ( ! is_array( $n ) ) {
			return null;
		}
		delete_user_meta( get_current_user_id(), self::NOTICE );
		return $n;
	}

	/**
	 * Address of a tab of the admin page.
	 *
	 * @param string $tab  Tab.
	 * @param array  $args More query arguments.
	 * @return string
	 */
	public static function url( $tab = 'create', array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => Do4u_Shop::PAGE, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Redirects and ends the request.
	 *
	 * @param string $url Address.
	 */
	private static function go( $url ) {
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Stops when the user may not use the plugin. Every handler checks its nonce right after this.
	 *
	 * @param bool $admin Whether the administrator capability is needed.
	 */
	private static function guard( $admin = false ) {
		if ( ! current_user_can( $admin ? 'manage_options' : Do4u_Shop_Settings::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'do4u' ), 403 );
		}
	}

	/**
	 * A valid batch id, or an empty string.
	 *
	 * @param string $id Sanitized input.
	 * @return string
	 */
	private static function batch_id( $id ) {
		return Do4u_Shop_Batches::valid_id( $id ) ? $id : '';
	}

	/** Saves the settings. */
	public static function handle_save_settings() {
		self::guard( true );
		check_admin_referer( 'do4u_shop_save_settings' );
		$in = isset( $_POST['do4u_shop'] ) && is_array( $_POST['do4u_shop'] ) ? map_deep( wp_unslash( $_POST['do4u_shop'] ), 'sanitize_text_field' ) : array();
		update_option( Do4u_Shop_Settings::OPTION, Do4u_Shop_Settings::sanitize( $in ), false );
		self::notice( 'success', __( 'Settings saved.', 'do4u' ) );
		self::go( self::url( 'settings' ) );
	}

	/** Prepares a batch and asks for the price. */
	public static function handle_estimate() {
		self::guard();
		check_admin_referer( 'do4u_shop_estimate' );
		$tool   = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : '';
		$ids    = isset( $_POST['products'] ) && is_array( $_POST['products'] ) ? array_map( 'absint', wp_unslash( $_POST['products'] ) ) : array();
		$fields = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['fields'] ) ) : array();
		$choice = isset( $_POST['opt'] ) && is_array( $_POST['opt'] ) ? map_deep( wp_unslash( $_POST['opt'] ), 'sanitize_text_field' ) : array();
		try {
			$b = Do4u_Shop_Batches::estimate( $tool, $ids, $fields, $choice );
			self::go( self::url( 'batch', array( 'batch' => $b['id'] ) ) );
		} catch ( Do4u_Error $e ) {
			self::notice( 'error', $e->plain() );
			self::go(
				self::url(
					'create',
					array(
						'tool' => $tool,
						'selected' => implode( ',', array_slice( $ids, 0, 200 ) ),
					)
				)
			);
		}
	}

	/** Starts a batch (uses credits). */
	public static function handle_start() {
		self::guard();
		check_admin_referer( 'do4u_shop_start' );
		$id = self::batch_id( isset( $_POST['batch'] ) ? sanitize_key( wp_unslash( $_POST['batch'] ) ) : '' );
		try {
			Do4u_Shop_Batches::start( $id );
			delete_transient( 'do4u_shop_balance' );
			self::notice( 'success', __( 'The batch was started. This page shows the progress; you can also leave it and come back later.', 'do4u' ) );
		} catch ( Do4u_Error $e ) {
			self::notice( 'error', $e->plain() );
		}
		self::go( self::url( 'batch', array( 'batch' => $id ) ) );
	}

	/** Asks do4u.eu for the state now. */
	public static function handle_refresh() {
		self::guard();
		check_admin_referer( 'do4u_shop_refresh' );
		$id = self::batch_id( isset( $_POST['batch'] ) ? sanitize_key( wp_unslash( $_POST['batch'] ) ) : '' );
		try {
			Do4u_Shop_Batches::refresh( $id, true );
		} catch ( Do4u_Error $e ) {
			self::notice( 'error', $e->plain() );
		}
		self::go( self::url( 'batch', array( 'batch' => $id ) ) );
	}

	/** Cancels a running batch. */
	public static function handle_cancel() {
		self::guard();
		check_admin_referer( 'do4u_shop_cancel' );
		$id = self::batch_id( isset( $_POST['batch'] ) ? sanitize_key( wp_unslash( $_POST['batch'] ) ) : '' );
		try {
			Do4u_Shop_Batches::cancel( $id );
			delete_transient( 'do4u_shop_balance' );
			self::notice( 'success', __( 'The batch was cancelled. Entries that were not done yet cost nothing.', 'do4u' ) );
		} catch ( Do4u_Error $e ) {
			self::notice( 'error', $e->plain() );
		}
		self::go( self::url( 'batch', array( 'batch' => $id ) ) );
	}

	/** Deletes a batch here and its results at do4u.eu. Applied changes stay. */
	public static function handle_delete() {
		self::guard();
		check_admin_referer( 'do4u_shop_delete' );
		$id = self::batch_id( isset( $_POST['batch'] ) ? sanitize_key( wp_unslash( $_POST['batch'] ) ) : '' );
		try {
			Do4u_Shop_Batches::cancel( $id, true );
			self::notice( 'success', __( 'The batch was deleted. Changes you applied stay, and can still be undone in the history.', 'do4u' ) );
		} catch ( Do4u_Error $e ) {
			self::notice( 'error', $e->plain() );
		}
		self::go( self::url( 'batches' ) );
	}

	/** Applies the chosen suggestions of one or all entries. */
	public static function handle_apply() {
		self::guard();
		check_admin_referer( 'do4u_shop_apply' );
		$id       = self::batch_id( isset( $_POST['batch'] ) ? sanitize_key( wp_unslash( $_POST['batch'] ) ) : '' );
		// apply[<entry>][<field>]: entry numbers and field names (the keys) are sanitized as well as the values.
		$chosen = array();
		$raw    = isset( $_POST['apply'] ) && is_array( $_POST['apply'] ) ? map_deep( wp_unslash( $_POST['apply'] ), 'sanitize_key' ) : array();
		foreach ( $raw as $entry => $fields ) {
			if ( is_array( $fields ) ) {
				$chosen[ absint( $entry ) ] = array_combine( array_map( 'sanitize_key', array_map( 'strval', array_keys( $fields ) ) ), array_values( $fields ) );
			}
		}
		$variants = isset( $_POST['variant'] ) && is_array( $_POST['variant'] ) ? array_map( 'absint', wp_unslash( $_POST['variant'] ) ) : array();
		$only     = isset( $_POST['only'] ) ? absint( wp_unslash( $_POST['only'] ) ) : 0;
		$force    = isset( $_POST['force'] ) ? absint( wp_unslash( $_POST['force'] ) ) : 0;
		if ( isset( $_POST['discard'] ) ) {
			$index = absint( wp_unslash( $_POST['discard'] ) );
			Do4u_Shop_Apply::discard( $id, $index );
			self::go( self::url( 'batch', array( 'batch' => $id ) ) . '#do4u-shop-item-' . $index );
		}
		if ( $force > 0 ) {
			$only = $force;
		}
		$done     = 0;
		$skipped  = array();
		$errors   = array();
		foreach ( $chosen as $index => $fields ) {
			$index = absint( $index );
			if ( $only > 0 && $index !== $only - 1 ) {
				continue;
			}
			if ( ! is_array( $fields ) ) {
				continue;
			}
			try {
				$r     = Do4u_Shop_Apply::apply( $id, $index, array_keys( array_filter( $fields ) ), $force > 0 && $force - 1 === $index, $variants[ $index ] ?? 0 );
				$done += count( $r['applied'] );
				if ( $r['conflicts'] ) {
					$skipped[] = $index;
				}
			} catch ( Do4u_Error $e ) {
				$errors[] = $e->plain();
			}
		}
		if ( $errors ) {
			self::notice( 'error', implode( ' ', array_unique( $errors ) ) );
		} elseif ( $skipped ) {
			/* translators: %d: number of fields applied */
			self::notice( 'warning', sprintf( _n( '%d field applied.', '%d fields applied.', $done, 'do4u' ), $done ) . ' ' . __( 'Some fields were changed in the shop after the batch was prepared and were left as they are. They are marked below; you can apply them anyway.', 'do4u' ) );
		} elseif ( $done ) {
			/* translators: %d: number of fields applied */
			self::notice( 'success', sprintf( _n( '%d field applied. You can undo it in the history.', '%d fields applied. You can undo them in the history.', $done, 'do4u' ), $done ) );
		} else {
			self::notice( 'info', __( 'Nothing was selected.', 'do4u' ) );
		}
		$conflict = $skipped ? array( 'conflict' => implode( ',', $skipped ) ) : array();
		self::go( self::url( 'batch', array( 'batch' => $id ) + $conflict ) . ( $only > 0 ? '#do4u-shop-item-' . ( $only - 1 ) : '' ) );
	}

	/** Undoes a change. */
	public static function handle_undo() {
		self::guard();
		check_admin_referer( 'do4u_shop_undo' );
		$pid    = isset( $_POST['product'] ) ? absint( wp_unslash( $_POST['product'] ) ) : 0;
		$key    = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
		$force  = ! empty( $_POST['force'] );
		$back   = isset( $_POST['back'] ) && 'product' === sanitize_key( wp_unslash( $_POST['back'] ) ) ? get_edit_post_link( $pid, 'raw' ) : self::url( 'history' );
		try {
			$r = Do4u_Shop_Apply::undo( $pid, $key, $force );
			if ( $r['conflicts'] ) {
				$labels = array_intersect_key( Do4u_Shop_Tools::field_labels(), array_flip( $r['conflicts'] ) );
				/* translators: %s: list of fields */
				self::notice( 'warning', sprintf( __( 'Not undone: these fields were changed again after do4u.eu wrote them: %s. Use "Undo anyway" to restore the old values regardless.', 'do4u' ), implode( ', ', $labels ) ) );
				$back = add_query_arg( 'undo_conflict', $key, (string) $back );
			} else {
				self::notice( 'success', __( 'The change was undone.', 'do4u' ) );
			}
		} catch ( Do4u_Error $e ) {
			self::notice( 'error', $e->plain() );
		}
		self::go( (string) $back );
	}

	/** Status of a batch for the progress display; imports the result once the batch is done. */
	public static function ajax_status() {
		if ( ! Do4u_Shop_Settings::can_use() ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'do4u' ) ), 403 );
		}
		check_ajax_referer( 'do4u_shop_status' );
		$id = isset( $_POST['batch'] ) ? sanitize_key( wp_unslash( $_POST['batch'] ) ) : '';
		try {
			$b = Do4u_Shop_Batches::refresh( $id );
		} catch ( Do4u_Error $e ) {
			wp_send_json_error( array( 'message' => $e->plain() ), 200 );
		}
		$s = $b['remote_status'] ?? array();
		wp_send_json_success(
			array(
				'status'  => (string) ( $b['status'] ?? '' ),
				'total'   => (int) ( $s['total'] ?? 0 ),
				'done'    => (int) ( $s['done'] ?? 0 ) + (int) ( $s['failed'] ?? 0 ) + (int) ( $s['skipped'] ?? 0 ) + (int) ( $s['cancelled'] ?? 0 ),
				'running' => (int) ( $s['running'] ?? 0 ),
				'waiting' => (int) ( $s['waiting'] ?? 0 ),
			)
		);
	}
}
