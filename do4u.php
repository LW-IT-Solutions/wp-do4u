<?php
/**
 * Plugin Name: do4u.eu
 * Plugin URI: https://do4u.eu/en/wordpress-plugin
 * Description: do4u.eu tools directly in wp-admin: writing and checking posts and pages, alt texts, comments, website checks and, with WooCommerce, texts for many products at once. Nothing starts without your click.
 * Version: 1.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: do4u.eu
 * Author URI: https://do4u.eu
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: do4u
 * WC requires at least: 9.0
 * WC tested up to: 11.1
 *
 * @package Do4u_WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const DO4U_WP_VERSION = '1.1.0';
const DO4U_WP_FILE    = __FILE__;

require_once __DIR__ . '/includes/class-do4u-api.php';
require_once __DIR__ . '/includes/class-do4u-secret.php';
require_once __DIR__ . '/includes/class-do4u-connection.php';
require_once __DIR__ . '/includes/class-do4u-catalog.php';
require_once __DIR__ . '/includes/class-do4u-places.php';
require_once __DIR__ . '/includes/class-do4u-apply.php';
require_once __DIR__ . '/includes/class-do4u-jobs.php';
require_once __DIR__ . '/includes/class-do4u-ajax.php';
require_once __DIR__ . '/includes/class-do4u-pages.php';
require_once __DIR__ . '/includes/class-do4u-admin.php';
require_once __DIR__ . '/includes/class-do4u-lists.php';
require_once __DIR__ . '/includes/class-do4u-editor.php';
require_once __DIR__ . '/includes/class-do4u-plugin.php';
// Shop module: only does something when WooCommerce is active (see Do4u_Shop::loaded()).
require_once __DIR__ . '/includes/class-do4u-shop-api.php';
require_once __DIR__ . '/includes/class-do4u-shop-settings.php';
require_once __DIR__ . '/includes/class-do4u-shop-seo.php';
require_once __DIR__ . '/includes/class-do4u-shop-tools.php';
require_once __DIR__ . '/includes/class-do4u-shop-batches.php';
require_once __DIR__ . '/includes/class-do4u-shop-apply.php';
require_once __DIR__ . '/includes/class-do4u-shop-actions.php';
require_once __DIR__ . '/includes/class-do4u-shop-admin.php';
require_once __DIR__ . '/includes/class-do4u-shop-products.php';
require_once __DIR__ . '/includes/class-do4u-shop.php';

Do4u_Plugin::boot();
Do4u_Shop::boot();

register_activation_hook( __FILE__, array( 'Do4u_Plugin', 'maybe_upgrade' ) );
register_deactivation_hook( __FILE__, array( 'Do4u_Plugin', 'deactivate' ) );
