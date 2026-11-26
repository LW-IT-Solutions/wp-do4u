<?php
/**
 * Runs when an administrator deletes the plugin in WordPress.
 *
 * Revokes the site widget at do4u.eu as far as it can be reached, then removes everything the plugin has
 * stored: API key, widget data, cache, notices, leftovers of version 0.x, and the batches, history, settings and own SEO
 * fields of the shop tools. Drafts, product texts and alt texts that a user took over belong to the user and remain,
 * as do values written into an SEO plugin.
 *
 * @package Do4u_WordPress
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'DO4U_WP_VERSION' ) ) {
	define( 'DO4U_WP_VERSION', 'uninstall' );
}
require_once __DIR__ . '/includes/class-do4u-api.php';
require_once __DIR__ . '/includes/class-do4u-secret.php';

$do4u_clean_site = static function (): void {
	global $wpdb;
	$widget = get_option( 'do4u_wp_widget', array() );
	$id     = is_array( $widget ) ? (string) ( $widget['id'] ?? '' ) : '';
	if ( preg_match( '/^w_[0-9a-f]{16}$/D', $id ) ) {
		try {
			// Short timeout: a do4u.eu that cannot be reached must not hold up the deletion.
			Do4u_Api::request( 'DELETE', 'widgets/' . $id, null, 5 );
		} catch ( Do4u_Error $error ) {
			unset( $error );
		}
	}
	delete_option( 'do4u_wp_key_cipher' );
	delete_option( 'do4u_wp_widget' );
	delete_option( 'do4u_wp_version' );
	delete_transient( 'do4u_wp_tools' );
	wp_clear_scheduled_hook( 'do4u_wp_cleanup' );
	delete_post_meta_by_key( '_do4u_wp_source' );
	delete_post_meta_by_key( '_do4u_wp_job' );
	delete_transient( 'do4u_wp_balance' );
	// Per-user entries of this site (old locks, notices, which user started an alt text job), all at once.
	foreach ( array( 'do4u_wp_lock_', '_transient_do4u_wp_notice_', '_transient_timeout_do4u_wp_notice_', '_transient_do4u_wp_job_', '_transient_timeout_do4u_wp_job_' ) as $prefix ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall: removes the plugin's own options by prefix.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );
	}
	// Shop tools.
	$batches = get_option( 'do4u_shop_batch_index', array() );
	foreach ( is_array( $batches ) ? $batches : array() as $batch ) {
		if ( is_string( $batch ) && preg_match( '/^[a-f0-9]{12}$/D', $batch ) ) {
			delete_option( 'do4u_shop_batch_' . $batch );
			delete_option( 'do4u_shop_lock_' . $batch );
		}
	}
	foreach ( array( 'do4u_shop_batch_index', 'do4u_shop_settings', 'do4u_shop_log' ) as $option ) {
		delete_option( $option );
	}
	delete_transient( 'do4u_shop_balance' );
	foreach ( array( '_do4u_shop_history', '_do4u_shop_seo_title', '_do4u_shop_meta_description' ) as $meta ) {
		delete_post_meta_by_key( $meta );
	}
};

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $do4u_blog ) {
		switch_to_blog( (int) $do4u_blog );
		$do4u_clean_site();
		restore_current_blog();
	}
} else {
	$do4u_clean_site();
}

// User meta of all users at once (user meta is shared by all sites of a network).
delete_metadata( 'user', 0, '_do4u_wp_state', '', true );
delete_metadata( 'user', 0, 'do4u_shop_notice', '', true );

unset( $do4u_blog, $do4u_clean_site );
