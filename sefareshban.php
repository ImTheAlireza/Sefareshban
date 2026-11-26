<?php
/**
 * Plugin Name:       سفارش‌بان - مدیریت سفارشات ووکامرس
 * Plugin URI:        https://github.com/ImTheAlireza/Sefareshban
 * Description:       رابط کاربری مدرن برای مدیریت سفارشات ووکامرس با صفحه اختصاصی جایگزین صفحه پیش‌فرض سفارشات.
 * Version:           1.2.0
 * Author:            Sefareshban
 * Text Domain:       sefareshban
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEFARESHBAN_VERSION', '1.2.0' );
define( 'SEFARESHBAN_FILE', __FILE__ );
define( 'SEFARESHBAN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SEFARESHBAN_URL', plugin_dir_url( __FILE__ ) );
define( 'SEFARESHBAN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check WooCommerce dependency.
 */
function sefareshban_missing_wc_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'افزونه «سفارش‌بان» برای اجرا نیاز به ووکامرس فعال دارد.', 'sefareshban' ); ?></p>
	</div>
	<?php
}

function sefareshban_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'sefareshban_missing_wc_notice' );
		return;
	}

	require_once SEFARESHBAN_PATH . 'includes/class-sefareshban-admin.php';
	require_once SEFARESHBAN_PATH . 'includes/class-sefareshban-api.php';

	Sefareshban_Admin::instance();
	Sefareshban_API::instance();
}
add_action( 'plugins_loaded', 'sefareshban_init' );

/**
 * Declare HPOS compatibility.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Activation: add capability flag for redirect flush if needed.
 */
function sefareshban_activate() {
	set_transient( 'sefareshban_activated', true, 30 );
}
register_activation_hook( __FILE__, 'sefareshban_activate' );
