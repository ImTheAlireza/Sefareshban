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
		add_action( 'admin_notices', array( $this, 'render_return_to_sefareshban_banner' ) );
		add_filter( 'parent_file', array( $this, 'highlight_menu' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_submenu' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
	}

	public function register_menu() {
		// NOTE: Do NOT call remove_submenu_page( 'woocommerce', 'wc-orders' ) or 'edit.php?post_type=shop_order'
		// because WordPress unregisters the page capability hook from $_registered_pages, causing
		// "Sorry, you are not allowed to access this page" when opening single orders or native WC orders.
		$this->page_hook = add_submenu_page(
			'woocommerce',
			__( 'سفارش‌ها', 'sefareshban' ),
			__( 'سفارش‌ها (سفارش‌بان)', 'sefareshban' ),
			'manage_woocommerce',
			'sefareshban-orders',
			array( $this, 'render_page' ),
			1
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'sefareshban' ) );
		}
		include SEFARESHBAN_PATH . 'templates/admin-page.php';
	}

	private function get_currency_label() {
		if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
			$sym = wp_strip_all_tags( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) );
			$sym = trim( $sym );
			if ( '' !== $sym ) {
				return $sym;
			}
		}
		return __( 'تومان', 'sefareshban' );
	}

	public function get_saved_settings() {
		$defaults = array(
			'per_page'        => 8,
			'auto_refresh'    => 0,
			'sound_alert'     => 1,
			'compact'         => 0,
			'replace_wc_menu' => 1,
			'sender_phone'    => '',
			'sender_address'  => '',
		);
		$opts = get_option( 'sefareshban_settings', array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
		$opts = wp_parse_args( $opts, $defaults );
		$opts['per_page']        = in_array( (int) $opts['per_page'], array( 8, 12, 20, 50 ), true ) ? (int) $opts['per_page'] : 8;
		$opts['auto_refresh']    = ! empty( $opts['auto_refresh'] ) ? 1 : 0;
		$opts['sound_alert']     = isset( $opts['sound_alert'] ) ? ( ! empty( $opts['sound_alert'] ) ? 1 : 0 ) : 1;
		$opts['compact']         = ! empty( $opts['compact'] ) ? 1 : 0;
		$opts['replace_wc_menu'] = isset( $opts['replace_wc_menu'] ) ? ( ! empty( $opts['replace_wc_menu'] ) ? 1 : 0 ) : 1;
		$opts['sender_phone']    = sanitize_text_field( (string) $opts['sender_phone'] );
		$opts['sender_address']  = sanitize_textarea_field( (string) $opts['sender_address'] );
		return $opts;
	}

	private function get_store_info( $settings ) {
		$raw_country = get_option( 'woocommerce_default_country', 'IR:THR' );
		$parts       = explode( ':', (string) $raw_country );
		$country     = $parts[0] ?? 'IR';
		$state_code  = $parts[1] ?? '';
		$state_label = $state_code;
		if ( $state_code && function_exists( 'WC' ) && WC()->countries ) {
			$states = WC()->countries->get_states( $country );
			if ( is_array( $states ) && isset( $states[ $state_code ] ) ) {
				$state_label = wp_strip_all_tags( $states[ $state_code ] );
			}
		}
		$city      = (string) get_option( 'woocommerce_store_city', '' );
		$addr1     = (string) get_option( 'woocommerce_store_address', '' );
		$addr2     = (string) get_option( 'woocommerce_store_address_2', '' );
		$auto_addr = implode( '، ', array_filter( array( $state_label, $city, $addr1, $addr2 ) ) );

		return array(
			'name'     => get_bloginfo( 'name' ) ?: __( 'فروشگاه', 'sefareshban' ),
			'address'  => '' !== $settings['sender_address'] ? $settings['sender_address'] : $auto_addr,
			'postcode' => (string) get_option( 'woocommerce_store_postcode', '' ),
			'phone'    => $settings['sender_phone'],
		);
	}

	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'sefareshban-admin',
			SEFARESHBAN_URL . 'assets/css/admin.css',
			array(),
			SEFARESHBAN_VERSION . '.' . filemtime( SEFARESHBAN_PATH . 'assets/css/admin.css' )
		);

		wp_enqueue_script(
			'sefareshban-admin',
			SEFARESHBAN_URL . 'assets/js/admin.js',
			array(),
			SEFARESHBAN_VERSION . '.' . filemtime( SEFARESHBAN_PATH . 'assets/js/admin.js' ),
			true
		);

		$current_user     = wp_get_current_user();
		$currency         = $this->get_currency_label();
		$settings         = $this->get_saved_settings();
		$is_hpos          = $this->is_hpos_enabled();
		$initial_order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$native_orders_url = add_query_arg(
			'sefareshban_native',
			'1',
			$is_hpos ? admin_url( 'admin.php?page=wc-orders' ) : admin_url( 'edit.php?post_type=shop_order' )
		);

		$new_order_url = add_query_arg(
			'sefareshban_native',
			'1',
			$is_hpos ? admin_url( 'admin.php?page=wc-orders&action=new' ) : admin_url( 'post-new.php?post_type=shop_order' )
		);

		wp_localize_script(
			'sefareshban-admin',
			'SEFARESHBAN',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'sefareshban_admin' ),
				'adminUrl'        => admin_url(),
				'pluginUrl'       => SEFARESHBAN_URL,
				'fontUrl'         => SEFARESHBAN_URL . 'assets/fonts/Vazirmatn-Variable.woff2',
				'pageUrl'         => admin_url( 'admin.php?page=sefareshban-orders' ),
				'wcOrdersUrl'     => $native_orders_url,
				'newOrderUrl'     => $new_order_url,
				'newProductUrl'   => admin_url( 'post-new.php?post_type=product' ),
				'initialOrderId'  => $initial_order_id,
				'siteName'        => get_bloginfo( 'name' ),
				'store'           => $this->get_store_info( $settings ),
				'currency'        => $currency,
				'initialSettings' => $settings,
				'user'            => array(
					'name'    => $current_user ? $current_user->display_name : '',
					'initial' => ( $current_user && $current_user->display_name )
						? ( function_exists( 'mb_substr' ) ? mb_substr( $current_user->display_name, 0, 1 ) : preg_replace( '/^(.).*$/us', '$1', $current_user->display_name ) )
						: 'م',
					'role'    => __( 'مدیر فروشگاه', 'sefareshban' ),
				),
				'statuses'        => $this->get_statuses(),
				'paymentGateways' => $this->get_payment_gateways(),
				'shippingMethods' => $this->get_shipping_methods(),
				'states'          => $this->get_iran_states(),
				'i18n'            => array(
					'toman'              => $currency,
					'currency'           => $currency,
					'free'               => __( 'رایگان', 'sefareshban' ),
					'order'              => __( 'سفارش', 'sefareshban' ),
					'orders'             => __( 'سفارش‌ها', 'sefareshban' ),
					'noOrders'           => __( 'سفارشی پیدا نشد', 'sefareshban' ),
					'noOrdersDesc'       => __( 'عبارت جست‌وجو یا فیلترها را تغییر دهید.', 'sefareshban' ),
					'clearFilters'       => __( 'پاک‌کردن فیلترها', 'sefareshban' ),
					'refresh'            => __( 'فهرست سفارش‌ها به‌روز شد', 'sefareshban' ),
					'exported'           => __( 'خروجی CSV آماده شد', 'sefareshban' ),
					'noteSaved'          => __( 'یادداشت ثبت شد', 'sefareshban' ),
					'statusChanged'      => __( 'وضعیت سفارش تغییر کرد', 'sefareshban' ),
					'loading'            => __( 'در حال بارگذاری…', 'sefareshban' ),
					'error'              => __( 'خطایی رخ داد. دوباره تلاش کنید.', 'sefareshban' ),
					'confirmDelete'      => __( 'آیا از انتقال سفارش‌های انتخاب‌شده به زباله‌دان مطمئن هستید؟', 'sefareshban' ),
					'confirmForceDelete' => __( 'هشدار: این سفارش‌ها برای همیشه حذف می‌شوند و قابل بازیابی نیستند. ادامه می‌دهید؟', 'sefareshban' ),
					'deleted'            => __( 'سفارش‌ها به زباله‌دان منتقل شدند', 'sefareshban' ),
					'restored'           => __( 'سفارش‌ها با موفقیت بازیابی شدند', 'sefareshban' ),
					'forceDeleted'       => __( 'سفارش‌ها برای همیشه حذف شدند', 'sefareshban' ),
					'bulkStatus'         => __( 'وضعیت سفارش‌های انتخاب‌شده تغییر کرد', 'sefareshban' ),
					'invoiceReady'       => __( 'فاکتور برای چاپ آماده شد', 'sefareshban' ),
				),
			)
		);
	}

	private function is_hpos_enabled() {
		return class_exists( 'Automattic\\WooCommerce\\Utilities\\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	private function get_statuses() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}
		$wc_statuses = wc_get_order_statuses();
		$out         = array();
		foreach ( $wc_statuses as $key => $label ) {
			$slug         = preg_replace( '/^wc-/', '', $key );
			$out[ $slug ] = array(
				'key'   => $slug,
				'label' => wp_strip_all_tags( $label ),
			);
		}
		return $out;
	}

	private function get_payment_gateways() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return array();
		}
		$gateways = WC()->payment_gateways()->payment_gateways();
		$out      = array();
		if ( is_array( $gateways ) ) {
			foreach ( $gateways as $id => $gw ) {
				$title = $gw->get_title() ? wp_strip_all_tags( $gw->get_title() ) : $gw->get_method_title();
				if ( $title ) {
					$out[] = array(
						'id'    => (string) $id,
						'title' => wp_strip_all_tags( $title ),
					);
				}
			}
		}
		return $out;
	}

	private function get_shipping_methods() {
		if ( ! function_exists( 'WC' ) || ! WC()->shipping() ) {
			return array();
		}
		$methods = WC()->shipping()->get_shipping_methods();
		$out     = array();
		if ( is_array( $methods ) ) {
			foreach ( $methods as $id => $m ) {
				$title = method_exists( $m, 'get_method_title' ) ? $m->get_method_title() : '';
				if ( $title ) {
					$out[] = array(
						'id'    => (string) $id,
						'title' => wp_strip_all_tags( $title ),
					);
				}
			}
		}
		return $out;
	}

	private function get_iran_states() {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return array();
		}
		$states = WC()->countries->get_states( 'IR' );
		$out    = array();
		if ( is_array( $states ) ) {
			foreach ( $states as $code => $name ) {
				$out[] = array(
					'code' => (string) $code,
					'name' => wp_strip_all_tags( $name ),
				);
			}
		}
		return $out;
	}

	public function maybe_redirect_default_orders( $screen ) {
		if ( ! $screen || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Never redirect POST submissions (saving native WC order forms).
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method ) {
			return;
		}

		$settings = $this->get_saved_settings();
		if ( empty( $settings['replace_wc_menu'] ) ) {
			return;
		}

		// Allow explicit native WooCommerce view via ?sefareshban_native=1
		if ( isset( $_GET['sefareshban_native'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Keep user on native WC page after saving/updating a native WC order (message= or referer from sefareshban_native)
		$referer = wp_get_raw_referer();
		if ( isset( $_GET['message'] ) || ( $referer && false !== strpos( $referer, 'sefareshban_native=' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// 1. Single Order Edit Screen (Legacy CPT: post.php?post=123&action=edit) -> Redirect to Sefareshban Dedicated Order Page
		if ( 'shop_order' === $screen->id && 'edit' === $action && ! empty( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$oid = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $oid > 0 ) {
				wp_safe_redirect( admin_url( 'admin.php?page=sefareshban-orders&order_id=' . $oid ) );
				exit;
			}
		}

		// 2. Single Order Edit Screen (HPOS: admin.php?page=wc-orders&action=edit&id=123) -> Redirect to Sefareshban Dedicated Order Page
		if ( 'woocommerce_page_wc-orders' === $screen->id && 'edit' === $action && ! empty( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$oid = absint( $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $oid > 0 ) {
				wp_safe_redirect( admin_url( 'admin.php?page=sefareshban-orders&order_id=' . $oid ) );
				exit;
			}
		}

		// Do not redirect other native actions (e.g. action=new, trash, untrash, delete) or filtered native table navigation
		$has_native_params = ( '' !== $action ) || isset( $_GET['action2'] ) || isset( $_GET['post'] ) || isset( $_GET['id'] ) || isset( $_GET['s'] ) || isset( $_GET['post_status'] ) || isset( $_GET['status'] ) || isset( $_GET['_customer_user'] ) || isset( $_GET['paged'] ) || isset( $_GET['m'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $has_native_params ) {
			return;
		}

		if ( $referer && ( false !== strpos( $referer, 'page=wc-orders' ) || false !== strpos( $referer, 'post_type=shop_order' ) ) ) {
			return;
		}

		// 3. Orders List Screen -> Redirect to Sefareshban Orders List
		if ( 'edit-shop_order' === $screen->id || 'woocommerce_page_wc-orders' === $screen->id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=sefareshban-orders' ) );
			exit;
		}
	}

	/**
	 * Show a convenient "Back to Sefareshban Order Page" banner when viewing an order in native WooCommerce editor.
	 */
	public function render_return_to_sefareshban_banner() {
		if ( ! function_exists( 'get_current_screen' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$oid = 0;
		if ( 'shop_order' === $screen->id && ! empty( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$oid = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( 'woocommerce_page_wc-orders' === $screen->id && isset( $_GET['action'] ) && 'edit' === $_GET['action'] && ! empty( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$oid = absint( $_GET['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! $oid ) {
			return;
		}
		$sb_url = admin_url( 'admin.php?page=sefareshban-orders&order_id=' . $oid );
		?>
		<div class="notice notice-info" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px;border-right-color:#e01b48">
			<span style="font-weight:600"><?php esc_html_e( 'در حال مشاهده سفارش در ویرایشگر پیش‌فرض ووکامرس هستید.', 'sefareshban' ); ?></span>
			<a href="<?php echo esc_url( $sb_url ); ?>" class="button button-primary" style="background:#e01b48;border-color:#e01b48"><?php esc_html_e( 'مشاهده در صفحه اختصاصی سفارش‌بان ←', 'sefareshban' ); ?></a>
		</div>
		<?php
	}

	public function hide_default_orders_menu_css() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$settings = $this->get_saved_settings();
		if ( empty( $settings['replace_wc_menu'] ) ) {
			return;
		}
		?>
		<style>
			#adminmenu li#toplevel_page_woocommerce ul.wp-submenu li a[href="edit.php?post_type=shop_order"],
			#adminmenu li#toplevel_page_woocommerce ul.wp-submenu li a[href^="edit.php?post_type=shop_order&"],
			#adminmenu li#toplevel_page_woocommerce ul.wp-submenu li a[href="admin.php?page=wc-orders"] {
				display: none !important;
			}
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
