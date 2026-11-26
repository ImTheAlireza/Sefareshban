<?php
/**
 * Plugin Name:       تولیدکننده سفارش فیک (برای تست سفارش‌بان)
 * Plugin URI:        https://github.com/ImTheAlireza/Sefareshban
 * Description:       ساخت سفارش‌های فیک ووکامرس برای تست افزونه سفارش‌بان. امکان ساخت دستی و ساخت خودکار هر ۵ دقیقه.
 * Version:           1.0.0
 * Author:            Sefareshban
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * Text Domain:       sefareshban-fake-orders
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SFOG_VERSION', '1.0.0' );
define( 'SFOG_OPTION', 'sfog_settings' );
define( 'SFOG_CRON_HOOK', 'sfog_generate_fake_order_event' );

register_activation_hook( __FILE__, 'sfog_activate' );
register_deactivation_hook( __FILE__, 'sfog_deactivate' );

function sfog_default_settings() {
	return array(
		'enabled'          => 0,
		'interval_minutes' => 5,
		'orders_per_run'   => 1,
		'last_run'         => 0,
		'last_order_id'    => 0,
		'total_generated'  => 0,
	);
}

function sfog_get_settings() {
	$defaults = sfog_default_settings();
	$opts     = get_option( SFOG_OPTION, array() );
	if ( ! is_array( $opts ) ) {
		$opts = array();
	}
	return wp_parse_args( $opts, $defaults );
}

function sfog_update_settings( $new ) {
	$settings = sfog_get_settings();
	$settings = array_merge( $settings, $new );
	update_option( SFOG_OPTION, $settings );
	return $settings;
}

function sfog_activate() {
	sfog_reschedule_cron();
}

function sfog_deactivate() {
	wp_clear_scheduled_hook( SFOG_CRON_HOOK );
}

add_filter( 'cron_schedules', function ( $schedules ) {
	$schedules['sfog_every_5_minutes'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => 'هر ۵ دقیقه (سفارش فیک)',
	);
	$schedules['sfog_every_10_minutes'] = array(
		'interval' => 10 * MINUTE_IN_SECONDS,
		'display'  => 'هر ۱۰ دقیقه (سفارش فیک)',
	);
	$schedules['sfog_every_15_minutes'] = array(
		'interval' => 15 * MINUTE_IN_SECONDS,
		'display'  => 'هر ۱۵ دقیقه (سفارش فیک)',
	);
	return $schedules;
} );

function sfog_schedule_name( $minutes ) {
	$minutes = (int) $minutes;
	if ( $minutes <= 5 ) {
		return 'sfog_every_5_minutes';
	}
	if ( $minutes <= 10 ) {
		return 'sfog_every_10_minutes';
	}
	return 'sfog_every_15_minutes';
}

function sfog_reschedule_cron() {
	wp_clear_scheduled_hook( SFOG_CRON_HOOK );
	$settings = sfog_get_settings();
	if ( ! empty( $settings['enabled'] ) ) {
		$schedule = sfog_schedule_name( $settings['interval_minutes'] );
		if ( ! wp_next_scheduled( SFOG_CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, $schedule, SFOG_CRON_HOOK );
		}
	}
}

add_action( SFOG_CRON_HOOK, 'sfog_cron_generate' );
function sfog_cron_generate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	$settings = sfog_get_settings();
	if ( empty( $settings['enabled'] ) ) {
		return;
	}
	$count = max( 1, min( 5, (int) $settings['orders_per_run'] ) );
	for ( $i = 0; $i < $count; $i++ ) {
		sfog_create_fake_order();
	}
}

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

add_action( 'admin_menu', function () {
	add_submenu_page(
		'woocommerce',
		'سفارش‌های فیک',
		'سفارش‌های فیک (تست)',
		'manage_woocommerce',
		'sfog-generator',
		'sfog_render_admin_page'
	);
} );

add_action( 'admin_init', 'sfog_handle_actions' );
function sfog_handle_actions() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	if ( isset( $_POST['sfog_action'] ) && $_POST['sfog_action'] === 'save_settings' ) {
		check_admin_referer( 'sfog_save_settings' );
		$enabled  = isset( $_POST['sfog_enabled'] ) ? 1 : 0;
		$interval = (int) ( $_POST['sfog_interval'] ?? 5 );
		if ( ! in_array( $interval, array( 5, 10, 15 ), true ) ) {
			$interval = 5;
		}
		$per_run = (int) ( $_POST['sfog_per_run'] ?? 1 );
		$per_run = max( 1, min( 5, $per_run ) );
		sfog_update_settings( array(
			'enabled'          => $enabled,
			'interval_minutes' => $interval,
			'orders_per_run'   => $per_run,
		) );
		sfog_reschedule_cron();
		add_settings_error( 'sfog', 'sfog_saved', 'تنظیمات ذخیره شد.', 'updated' );
	}

	if ( isset( $_POST['sfog_action'] ) && $_POST['sfog_action'] === 'generate_now' ) {
		check_admin_referer( 'sfog_generate_now' );
		$count = (int) ( $_POST['sfog_count'] ?? 1 );
		$count = max( 1, min( 20, $count ) );
		$created = 0;
		for ( $i = 0; $i < $count; $i++ ) {
			$id = sfog_create_fake_order();
			if ( $id ) {
				$created++;
			}
		}
		add_settings_error( 'sfog', 'sfog_generated', sprintf( '%d سفارش فیک ساخته شد.', $created ), 'updated' );
	}
}

function sfog_render_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$settings = sfog_get_settings();
	settings_errors( 'sfog' );
	$next = wp_next_scheduled( SFOG_CRON_HOOK );
	?>
	<div class="wrap" dir="rtl" style="max-width:860px">
		<h1>تولیدکننده سفارش فیک</h1>
		<p>این افزونه برای تست <strong>سفارش‌بان</strong> سفارش‌های فیک ووکامرس می‌سازد.</p>

		<div style="background:#fff;border:1px solid #dcdcde;padding:20px;border-radius:8px;margin:16px 0">
			<h2 style="margin-top:0">ساخت دستی</h2>
			<form method="post">
				<?php wp_nonce_field( 'sfog_generate_now' ); ?>
				<input type="hidden" name="sfog_action" value="generate_now">
				<label>تعداد سفارش:
					<input type="number" name="sfog_count" value="5" min="1" max="20" style="width:90px">
				</label>
				<button type="submit" class="button button-primary">ساخت سفارش فیک</button>
			</form>
		</div>

		<div style="background:#fff;border:1px solid #dcdcde;padding:20px;border-radius:8px;margin:16px 0">
			<h2 style="margin-top:0">ساخت خودکار (WP-Cron)</h2>
			<form method="post">
				<?php wp_nonce_field( 'sfog_save_settings' ); ?>
				<input type="hidden" name="sfog_action" value="save_settings">
				<table class="form-table" role="presentation">
					<tr>
						<th>فعال‌سازی</th>
						<td><label><input type="checkbox" name="sfog_enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> هر چند دقیقه یک سفارش فیک بساز</label></td>
					</tr>
					<tr>
						<th>بازه زمانی</th>
						<td>
							<select name="sfog_interval">
								<option value="5" <?php selected( (int) $settings['interval_minutes'], 5 ); ?>>هر ۵ دقیقه</option>
								<option value="10" <?php selected( (int) $settings['interval_minutes'], 10 ); ?>>هر ۱۰ دقیقه</option>
								<option value="15" <?php selected( (int) $settings['interval_minutes'], 15 ); ?>>هر ۱۵ دقیقه</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>تعداد در هر اجرا</th>
						<td><input type="number" name="sfog_per_run" value="<?php echo esc_attr( (int) $settings['orders_per_run'] ); ?>" min="1" max="5" style="width:90px"></td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary">ذخیره تنظیمات</button></p>
			</form>

			<hr>
			<p><strong>وضعیت:</strong>
				<?php if ( $next ) : ?>
					اجرای بعدی: <?php echo esc_html( wp_date( 'Y-m-d H:i:s', $next ) ); ?>
					(<?php echo esc_html( human_time_diff( time(), $next ) ); ?> دیگر)
				<?php else : ?>
					زمان‌بندی فعال نیست.
				<?php endif; ?>
			</p>
			<p><strong>تعداد کل ساخته‌شده:</strong> <?php echo esc_html( (int) $settings['total_generated'] ); ?>
			<?php if ( ! empty( $settings['last_order_id'] ) ) : ?>
				| <strong>آخرین سفارش:</strong> #<?php echo esc_html( (int) $settings['last_order_id'] ); ?>
			<?php endif; ?>
			</p>
			<p style="color:#666">نکته: WP-Cron با بازدید از سایت اجرا می‌شود. برای تست لوکال، صفحه ادمین یا سایت را باز نگه دارید یا با WP-CLI cron را اجرا کنید.</p>
		</div>
	</div>
	<?php
}

function sfog_random( $arr ) {
	return $arr[ array_rand( $arr ) ];
}

function sfog_fake_data() {
	$first_names = array( 'علی', 'محمد', 'مهدی', 'رضا', 'حسین', 'امیر', 'سارا', 'زهرا', 'مریم', 'نگار', 'الهام', 'فاطمه', 'نیلوفر', 'پریسا', 'آرش', 'سینا', 'کامران', 'بهناز', 'شیدا', 'حمید' );
	$last_names  = array( 'احمدی', 'محمدی', 'رضایی', 'حسینی', 'کریمی', 'صادقی', 'موسوی', 'نیک‌نام', 'کاظمی', 'شریفی', 'فتحی', 'یاسمی', 'دهقان', 'عابدی', 'بیات', 'مرادی', 'جعفری', 'قاسمی', 'هاشمی', 'اکبری' );
	$cities = array(
		array( 'city' => 'تهران', 'state' => 'تهران' ),
		array( 'city' => 'اصفهان', 'state' => 'اصفهان' ),
		array( 'city' => 'شیراز', 'state' => 'فارس' ),
		array( 'city' => 'مشهد', 'state' => 'خراسان رضوی' ),
		array( 'city' => 'تبریز', 'state' => 'آذربایجان شرقی' ),
		array( 'city' => 'کرج', 'state' => 'البرز' ),
		array( 'city' => 'قم', 'state' => 'قم' ),
		array( 'city' => 'اهواز', 'state' => 'خوزستان' ),
		array( 'city' => 'رشت', 'state' => 'گیلان' ),
		array( 'city' => 'کرمان', 'state' => 'کرمان' ),
		array( 'city' => 'یزد', 'state' => 'یزد' ),
		array( 'city' => 'همدان', 'state' => 'همدان' ),
	);
	$streets = array( 'خیابان ولیعصر', 'بلوار آزادی', 'خیابان امام', 'خیابان انقلاب', 'بلوار کشاورز', 'خیابان شریعتی', 'خیابان فردوسی', 'بلوار مدرس', 'خیابان سعدی', 'خیابان بهشتی' );
	$payments = array(
		array( 'id' => 'zarinpal', 'title' => 'زرین‌پال' ),
		array( 'id' => 'cod', 'title' => 'پرداخت در محل' ),
		array( 'id' => 'bacs', 'title' => 'کارت به کارت' ),
		array( 'id' => 'cheque', 'title' => 'درگاه بانک ملت' ),
	);
	$statuses = array( 'pending', 'processing', 'on-hold', 'completed', 'completed', 'processing', 'pending', 'cancelled' );
	return compact( 'first_names', 'last_names', 'cities', 'streets', 'payments', 'statuses' );
}

function sfog_ensure_dummy_products() {
	$existing = wc_get_products( array( 'limit' => 10, 'status' => 'publish', 'return' => 'ids' ) );
	if ( ! empty( $existing ) ) {
		return $existing;
	}
	$dummy = array(
		array( 'name' => 'قهوه اسپرسو ۲۵۰ گرمی', 'price' => 380000 ),
		array( 'name' => 'ماگ سرامیکی دست‌ساز', 'price' => 450000 ),
		array( 'name' => 'کوله پشتی سفری ۳۵ لیتری', 'price' => 2450000 ),
		array( 'name' => 'چراغ رومیزی چوبی', 'price' => 1290000 ),
		array( 'name' => 'هدفون بی‌سیم', 'price' => 3200000 ),
		array( 'name' => 'دفتر یادداشت پارچه‌ای', 'price' => 180000 ),
		array( 'name' => 'شال نخی طرح ترمه', 'price' => 680000 ),
		array( 'name' => 'عسل طبیعی ۱kg', 'price' => 1150000 ),
	);
	$ids = array();
	foreach ( $dummy as $d ) {
		$p = new WC_Product_Simple();
		$p->set_name( $d['name'] );
		$p->set_regular_price( $d['price'] );
		$p->set_price( $d['price'] );
		$p->set_status( 'publish' );
		$p->set_catalog_visibility( 'visible' );
		$p->set_manage_stock( false );
		$id = $p->save();
		if ( $id ) {
			$ids[] = $id;
		}
	}
	return $ids;
}

function sfog_persian_phone() {
	$prefixes = array( '0912', '0913', '0914', '0915', '0917', '0918', '0919', '0935', '0936', '0937', '0990', '0991' );
	$p = sfog_random( $prefixes );
	for ( $i = 0; $i < 7; $i++ ) {
		$p .= wp_rand( 0, 9 );
	}
	return $p;
}

function sfog_create_fake_order() {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_create_order' ) ) {
		return false;
	}

	$data = sfog_fake_data();
	$first = sfog_random( $data['first_names'] );
	$last  = sfog_random( $data['last_names'] );
	$loc   = sfog_random( $data['cities'] );
	$street = sfog_random( $data['streets'] );
	$payment = sfog_random( $data['payments'] );
	$status  = sfog_random( $data['statuses'] );

	$email = 'fake_' . sanitize_title( $first . '.' . $last ) . wp_rand( 10, 999 ) . '@example.test';
	$phone = sfog_persian_phone();
	$address1 = $street . '، کوچه ' . wp_rand( 1, 30 ) . '، پلاک ' . wp_rand( 1, 150 );
	$postcode = (string) wp_rand( 1000000000, 9999999999 );

	$product_ids = sfog_ensure_dummy_products();
	if ( empty( $product_ids ) ) {
		return false;
	}

	$order = wc_create_order( array( 'status' => 'pending' ) );
	if ( is_wp_error( $order ) ) {
		return false;
	}

	$order->set_billing_first_name( $first );
	$order->set_billing_last_name( $last );
	$order->set_billing_email( $email );
	$order->set_billing_phone( $phone );
	$order->set_billing_country( 'IR' );
	$order->set_billing_state( $loc['state'] );
	$order->set_billing_city( $loc['city'] );
	$order->set_billing_address_1( $address1 );
	$order->set_billing_postcode( $postcode );

	$order->set_shipping_first_name( $first );
	$order->set_shipping_last_name( $last );
	$order->set_shipping_country( 'IR' );
	$order->set_shipping_state( $loc['state'] );
	$order->set_shipping_city( $loc['city'] );
	$order->set_shipping_address_1( $address1 );
	$order->set_shipping_postcode( $postcode );

	$item_count = wp_rand( 1, 4 );
	shuffle( $product_ids );
	$picked = array_slice( $product_ids, 0, min( $item_count, count( $product_ids ) ) );
	foreach ( $picked as $pid ) {
		$product = wc_get_product( $pid );
		if ( ! $product ) {
			continue;
		}
		$qty = wp_rand( 1, 3 );
		$order->add_product( $product, $qty );
	}

	$shipping = new WC_Order_Item_Shipping();
	$shipping->set_method_title( 'ارسال پستی' );
	$shipping->set_method_id( 'flat_rate' );
	$shipping->set_total( wp_rand( 0, 1 ) ? wp_rand( 30, 120 ) * 1000 : 0 );
	$order->add_item( $shipping );

	$order->set_payment_method( $payment['id'] );
	$order->set_payment_method_title( $payment['title'] );
	$order->set_created_via( 'sfog_fake_generator' );
	$order->set_customer_note( sfog_random( array( '', '', '', 'لطفاً قبل از ارسال تماس بگیرید.', 'بسته‌بندی هدیه باشد.', 'در ساعات عصر ارسال شود.' ) ) );

	// Recent date (within last 2 hours) so dashboard feels live
	$minutes_ago = wp_rand( 0, 120 );
	$date = gmdate( 'Y-m-d H:i:s', time() - ( $minutes_ago * 60 ) );
	$order->set_date_created( $date );

	$order->calculate_totals();
	$order_id = $order->save();

	// Set status after save to trigger normal status transitions/notes.
	$order = wc_get_order( $order_id );
	if ( $order ) {
		$order->update_status( $status, 'سفارش فیک ساخته‌شده توسط افزونه تست سفارش‌بان', true );
	}

	sfog_update_settings( array(
		'last_run'        => time(),
		'last_order_id'   => $order_id,
		'total_generated' => (int) sfog_get_settings()['total_generated'] + 1,
	) );

	return $order_id;
}
