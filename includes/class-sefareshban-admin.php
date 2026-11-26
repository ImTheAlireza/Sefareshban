<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sefareshban_Admin {

	private static $instance = null;
	private $page_hook       = '';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'current_screen', array( $this, 'maybe_redirect_default_orders' ) );
		add_action( 'admin_head', array( $this, 'hide_default_orders_menu_css' ) );
		add_filter( 'parent_file', array( $this, 'highlight_menu' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_submenu' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
	}

	public function register_menu() {
		// Add our page under WooCommerce menu.
		$this->page_hook = add_submenu_page(
			'woocommerce',
			__( 'سفارش‌ها', 'sefareshban' ),
			__( 'سفارش‌ها (سفارش‌بان)', 'sefareshban' ),
			'manage_woocommerce',
			'sefareshban-orders',
			array( $this, 'render_page' ),
			1
		);

		// Remove default WooCommerce orders submenu entries to avoid duplication.
		// HPOS.
		remove_submenu_page( 'woocommerce', 'wc-orders' );
		// Legacy post type.
		remove_submenu_page( 'woocommerce', 'edit.php?post_type=shop_order' );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'sefareshban' ) );
		}
		include SEFARESHBAN_PATH . 'templates/admin-page.php';
	}

	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'sefareshban-fonts',
			'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'sefareshban-admin',
			SEFARESHBAN_URL . 'assets/css/admin.css',
			array( 'sefareshban-fonts' ),
			SEFARESHBAN_VERSION
		);

		wp_enqueue_script(
			'sefareshban-admin',
			SEFARESHBAN_URL . 'assets/js/admin.js',
			array(),
			SEFARESHBAN_VERSION,
			true
		);

		$current_user = wp_get_current_user();
		wp_localize_script(
			'sefareshban-admin',
			'SEFARESHBAN',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'sefareshban_admin' ),
				'adminUrl'  => admin_url(),
				'pageUrl'   => admin_url( 'admin.php?page=sefareshban-orders' ),
				'wcOrdersUrl' => $this->get_wc_orders_url(),
				'newOrderUrl' => function_exists( 'wc_get_container' ) && class_exists( 'Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
					? admin_url( 'admin.php?page=wc-orders&action=new' )
					: admin_url( 'post-new.php?post_type=shop_order' ),
				'user'      => array(
					'name'    => $current_user ? $current_user->display_name : '',
					'initial' => $current_user && $current_user->display_name ? mb_substr( $current_user->display_name, 0, 1 ) : 'م',
					'role'    => __( 'مدیر فروشگاه', 'sefareshban' ),
				),
				'statuses'  => $this->get_statuses(),
				'i18n'      => array(
					'toman'          => __( 'تومان', 'sefareshban' ),
					'free'           => __( 'رایگان', 'sefareshban' ),
					'order'          => __( 'سفارش', 'sefareshban' ),
					'orders'         => __( 'سفارش‌ها', 'sefareshban' ),
					'noOrders'       => __( 'سفارشی پیدا نشد', 'sefareshban' ),
					'noOrdersDesc'   => __( 'عبارت جست‌وجو یا فیلتر وضعیت را تغییر دهید.', 'sefareshban' ),
					'clearFilters'   => __( 'پاک‌کردن فیلترها', 'sefareshban' ),
					'refresh'        => __( 'فهرست سفارش‌ها به‌روز شد', 'sefareshban' ),
					'exported'       => __( 'خروجی CSV آماده شد', 'sefareshban' ),
					'noteSaved'      => __( 'یادداشت ثبت شد', 'sefareshban' ),
					'statusChanged'  => __( 'وضعیت سفارش تغییر کرد', 'sefareshban' ),
					'loading'        => __( 'در حال بارگذاری…', 'sefareshban' ),
					'error'          => __( 'خطایی رخ داد. دوباره تلاش کنید.', 'sefareshban' ),
					'confirmDelete'  => __( 'آیا از حذف سفارش‌های انتخاب‌شده مطمئن هستید؟', 'sefareshban' ),
					'deleted'        => __( 'سفارش‌ها به سطل زباله منتقل شدند', 'sefareshban' ),
					'bulkStatus'     => __( 'وضعیت سفارش‌های انتخاب‌شده تغییر کرد', 'sefareshban' ),
					'invoiceReady'   => __( 'فاکتور برای چاپ آماده شد', 'sefareshban' ),
				),
			)
		);
	}

	private function get_statuses() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}
		$wc_statuses = wc_get_order_statuses();
		$out         = array();
		foreach ( $wc_statuses as $key => $label ) {
			$slug         = str_replace( 'wc-', '', $key );
			$out[ $slug ] = array(
				'key'   => $slug,
				'label' => wp_strip_all_tags( $label ),
			);
		}
		return $out;
	}

	private function get_wc_orders_url() {
		if ( function_exists( 'wc_get_page_screen_id' ) && class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
			// Try HPOS-aware URL.
			return admin_url( 'admin.php?page=wc-orders' );
		}
		return admin_url( 'edit.php?post_type=shop_order' );
	}

	public function maybe_redirect_default_orders( $screen ) {
		if ( ! $screen || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Legacy orders screen.
		if ( 'edit-shop_order' === $screen->id || 'shop_order' === $screen->id ) {
			// Allow direct access if explicitly requested (escape hatch).
			if ( isset( $_GET['sefareshban_native'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
			wp_safe_redirect( admin_url( 'admin.php?page=sefareshban-orders' ) );
			exit;
		}

		// HPOS orders screen.
		if ( 'woocommerce_page_wc-orders' === $screen->id ) {
			if ( isset( $_GET['sefareshban_native'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
			$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Let native edit/create/trash/view flows work.
			if ( in_array( $action, array( 'edit', 'new', 'trash', 'untrash', 'delete', 'view' ), true ) ) {
				return;
			}
			// Any single-order operation on wc-orders should pass through.
			if ( isset( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
			wp_safe_redirect( admin_url( 'admin.php?page=sefareshban-orders' ) );
			exit;
		}
	}

	public function hide_default_orders_menu_css() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<style>
			#adminmenu a[href="edit.php?post_type=shop_order"],
			#adminmenu a[href="admin.php?page=wc-orders"] { display:none !important; }
		</style>
		<?php
	}

	public function highlight_menu( $parent_file ) {
		$screen = get_current_screen();
		if ( $screen && $screen->id === $this->page_hook ) {
			return 'woocommerce';
		}
		return $parent_file;
	}

	public function highlight_submenu( $submenu_file ) {
		$screen = get_current_screen();
		if ( $screen && $screen->id === $this->page_hook ) {
			return 'sefareshban-orders';
		}
		return $submenu_file;
	}

	public function admin_body_class( $classes ) {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'sefareshban-orders' === $page ) {
			$classes .= ' sefareshban-admin-page ';
		}
		return $classes;
	}
}
