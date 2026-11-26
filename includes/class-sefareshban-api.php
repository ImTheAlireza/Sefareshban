<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sefareshban_API {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$actions = array(
			'sefareshban_get_orders',
			'sefareshban_get_order',
			'sefareshban_get_orders_batch',
			'sefareshban_get_stats',
			'sefareshban_get_reports',
			'sefareshban_update_status',
			'sefareshban_add_note',
			'sefareshban_delete_note',
			'sefareshban_update_order_details',
			'sefareshban_save_tracking',
			'sefareshban_create_order',
			'sefareshban_create_refund',
			'sefareshban_bulk_action',
			'sefareshban_export_csv',
			'sefareshban_get_customers',
			'sefareshban_get_products',
			'sefareshban_get_settings',
			'sefareshban_save_settings',
		);
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, $action ) );
		}
	}

	private function verify() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'شما دسترسی لازم را ندارید.', 'sefareshban' ) ), 403 );
		}
		check_ajax_referer( 'sefareshban_admin', 'nonce' );
	}

	private function is_real_order( $order ) {
		return ( $order instanceof WC_Order ) && ! ( $order instanceof WC_Order_Refund );
	}

	private function is_hpos_enabled() {
		return class_exists( 'Automattic\\WooCommerce\\Utilities\\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	private function normalize_digits( $str ) {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return str_replace( $fa, $en, (string) $str );
	}

	private function normalize_status( $status ) {
		$status = preg_replace( '/^wc-/', '', (string) $status );
		if ( 'on-hold' === $status ) {
			return 'onhold';
		}
		return $status;
	}

	private function wc_status( $status ) {
		$status = preg_replace( '/^wc-/', '', (string) $status );
		if ( 'onhold' === $status ) {
			return 'on-hold';
		}
		return $status;
	}

	private function status_label( $status ) {
		$status = $this->wc_status( $status );
		if ( 'trash' === $status ) {
			return __( 'زباله‌دان', 'sefareshban' );
		}
		$statuses = wc_get_order_statuses();
		$key      = 'wc-' . $status;
		if ( isset( $statuses[ $key ] ) ) {
			return wp_strip_all_tags( $statuses[ $key ] );
		}
		return $status;
	}

	private function get_timezone() {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}
		$tz_string = get_option( 'timezone_string' );
		if ( $tz_string ) {
			try {
				return new DateTimeZone( $tz_string );
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}
		$offset  = (float) get_option( 'gmt_offset', 0 );
		$hours   = (int) $offset;
		$minutes = abs( ( $offset - $hours ) * 60 );
		$sign    = $offset < 0 ? '-' : '+';
		return new DateTimeZone( sprintf( '%s%02d:%02d', $sign, abs( $hours ), $minutes ) );
	}

	/**
	 * Pure Gregorian to Jalali (Shamsi) conversion — immune to third-party date plugin double-conversion.
	 */
	public static function gregorian_to_jalali( $gy, $gm, $gd ) {
		$gy  = (int) $gy;
		$gm  = (int) $gm;
		$gd  = (int) $gd;
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
		$days %= 12053;
		$jy   += 4 * intdiv( $days, 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy   += intdiv( $days - 1, 365 );
			$days  = ( $days - 1 ) % 365;
		}
		if ( $days < 186 ) {
			$jm = 1 + intdiv( $days, 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + intdiv( $days - 186, 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}
		return array( $jy, $jm, $jd );
	}

	private function format_jalali_ts( $ts, $mode = 'date' ) {
		$dt = ( new DateTimeImmutable( '@' . (int) $ts ) )->setTimezone( $this->get_timezone() );
		$gy = (int) $dt->format( 'Y' );
		$gm = (int) $dt->format( 'n' );
		$gd = (int) $dt->format( 'j' );
		list( $jy, $jm, $jd ) = self::gregorian_to_jalali( $gy, $gm, $gd );

		$months = array(
			1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
			5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
			9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
		);
		$weekdays = array(
			'Sat' => 'شنبه', 'Sun' => 'یکشنبه', 'Mon' => 'دوشنبه',
			'Tue' => 'سه‌شنبه', 'Wed' => 'چهارشنبه', 'Thu' => 'پنج‌شنبه', 'Fri' => 'جمعه',
		);

		$m_name = $months[ $jm ] ?? '';
		$en     = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$fa     = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$to_fa  = function( $v ) use ( $en, $fa ) {
			return str_replace( $en, $fa, (string) $v );
		};

		if ( 'weekday' === $mode ) {
			return $weekdays[ $dt->format( 'D' ) ] ?? '';
		}
		if ( 'short' === $mode ) {
			return $to_fa( $jd ) . ' ' . $m_name;
		}
		if ( 'time' === $mode ) {
			return $to_fa( $dt->format( 'H:i' ) );
		}
		if ( 'full' === $mode ) {
			return $to_fa( $jd ) . ' ' . $m_name . ' ' . $to_fa( $jy ) . ' — ' . $to_fa( $dt->format( 'H:i' ) );
		}
		return $to_fa( $jd ) . ' ' . $m_name . ' ' . $to_fa( $jy );
	}

	private function get_local_day_bounds_utc( $days_ago = 0 ) {
		$tz    = $this->get_timezone();
		$start = new DateTimeImmutable( 'today', $tz );
		if ( $days_ago > 0 ) {
			$start = $start->modify( '-' . absint( $days_ago ) . ' days' );
		} elseif ( $days_ago < 0 ) {
			$start = $start->modify( '+' . absint( $days_ago ) . ' days' );
		}
		$end = $start->setTime( 23, 59, 59 );
		return array(
			'start_utc' => $start->getTimestamp(),
			'end_utc'   => $end->getTimestamp(),
			'local_dt'  => $start,
		);
	}

	private function parse_date_filter_utc( $ymd, $is_end = false ) {
		$ymd = trim( $this->normalize_digits( $ymd ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
			return null;
		}
		$tz   = $this->get_timezone();
		$time = $is_end ? '23:59:59' : '00:00:00';
		$dt   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $ymd . ' ' . $time, $tz );
		return $dt ? $dt->getTimestamp() : null;
	}

	private function format_date_parts( $order ) {
		$date = $order->get_date_created();
		if ( ! $date ) {
			return array(
				'iso'       => '',
				'timestamp' => 0,
				'date'      => '',
				'shortDate' => '',
				'time'      => '',
				'full'      => '',
			);
		}
		$ts = $date->getTimestamp();
		$dt = ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( $this->get_timezone() );
		return array(
			'iso'       => $dt->format( 'Y-m-d\TH:i:s' ),
			'timestamp' => $ts,
			'date'      => $this->format_jalali_ts( $ts, 'date' ),
			'shortDate' => $this->format_jalali_ts( $ts, 'short' ),
			'time'      => $this->format_jalali_ts( $ts, 'time' ),
			'full'      => $this->format_jalali_ts( $ts, 'full' ),
		);
	}

	private function translate_channel( $via ) {
		$map = array(
			'checkout'  => __( 'وب‌سایت', 'sefareshban' ),
			'web'       => __( 'وب‌سایت', 'sefareshban' ),
			'admin'     => __( 'پنل مدیریت', 'sefareshban' ),
			'rest-api'  => __( 'وب‌سرویس (API)', 'sefareshban' ),
			'store-api' => __( 'فروشگاه (بلوک)', 'sefareshban' ),
		);
		$key = strtolower( trim( (string) $via ) );
		if ( '' === $key ) {
			return __( 'وب‌سایت', 'sefareshban' );
		}
		return isset( $map[ $key ] ) ? $map[ $key ] : $via;
	}

	private function state_name( $country, $state ) {
		if ( ! $state ) {
			return '';
		}
		if ( function_exists( 'WC' ) && WC()->countries ) {
			$states = WC()->countries->get_states( $country ? $country : 'IR' );
			if ( is_array( $states ) && isset( $states[ $state ] ) ) {
				return wp_strip_all_tags( $states[ $state ] );
			}
		}
		return $state;
	}

	private function get_order_tracking( $order ) {
		$code    = trim( (string) $order->get_meta( '_sefareshban_tracking_code', true ) );
		$carrier = trim( (string) $order->get_meta( '_sefareshban_tracking_carrier', true ) );
		if ( '' === $code ) {
			$fallback_keys = array( '_tracking_code', 'tracking_code', 'post_tracking_code', '_post_barcode', 'tipax_tracking_code' );
			foreach ( $fallback_keys as $fk ) {
				$val = trim( (string) $order->get_meta( $fk, true ) );
				if ( '' !== $val ) {
					$code = $val;
					break;
				}
			}
		}
		if ( '' === $carrier && '' !== $code ) {
			$carrier = __( 'پست / مرسوله', 'sefareshban' );
		}
		return array(
			'code'    => $code,
			'carrier' => $carrier,
		);
	}

	private function get_cached_customer_order_count( $order, &$cache ) {
		$cid = (int) $order->get_customer_id();
		if ( $cid > 0 ) {
			$ck = 'u_' . $cid;
			if ( ! isset( $cache[ $ck ] ) ) {
				$cache[ $ck ] = max( 1, (int) wc_get_customer_order_count( $cid ) );
			}
			return $cache[ $ck ];
		}
		$email = strtolower( trim( (string) $order->get_billing_email() ) );
		if ( '' !== $email ) {
			$ck = 'e_' . $email;
			if ( ! isset( $cache[ $ck ] ) ) {
				$res = wc_get_orders(
					array(
						'type'          => 'shop_order',
						'billing_email' => $email,
						'return'        => 'ids',
						'limit'         => 1,
						'paginate'      => true,
					)
				);
				$cache[ $ck ] = ( is_object( $res ) && isset( $res->total ) ) ? max( 1, (int) $res->total ) : 1;
			}
			return $cache[ $ck ];
		}
		$phone = trim( (string) $order->get_billing_phone() );
		if ( '' !== $phone ) {
			$ck = 'p_' . $phone;
			if ( ! isset( $cache[ $ck ] ) ) {
				$res = wc_get_orders(
					array(
						'type'          => 'shop_order',
						'billing_phone' => $phone,
						'return'        => 'ids',
						'limit'         => 1,
						'paginate'      => true,
					)
				);
				$cache[ $ck ] = ( is_object( $res ) && isset( $res->total ) ) ? max( 1, (int) $res->total ) : 1;
			}
			return $cache[ $ck ];
		}
		return 1;
	}

	private function order_to_row( $order, &$cust_cache = array() ) {
		$date     = $this->format_date_parts( $order );
		$status   = $this->normalize_status( $order->get_status() );
		$tracking = $this->get_order_tracking( $order );

		$qty           = 0;
		$items_preview = array();
		foreach ( $order->get_items() as $item ) {
			$item_qty = (int) $item->get_quantity();
			$qty     += $item_qty;
			if ( count( $items_preview ) < 6 ) {
				$img     = '';
				$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : false;
				if ( $product ) {
					$img_id = $product->get_image_id();
					if ( $img_id ) {
						$src = wp_get_attachment_image_src( $img_id, 'thumbnail' );
						$img = $src ? $src[0] : '';
					}
				}
				$items_preview[] = array(
					'n'   => $item->get_name(),
					'q'   => $item_qty,
					'p'   => (float) $order->get_item_subtotal( $item, false, false ),
					'img' => $img,
				);
			}
		}

		$full_name = trim( $order->get_formatted_billing_full_name() );
		if ( '' === $full_name ) {
			$full_name = trim( $order->get_formatted_shipping_full_name() );
		}

		$b_country  = $order->get_billing_country();
		$s_country  = $order->get_shipping_country();
		$state_raw  = $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state();
		$state_lbl  = $this->state_name( $s_country ? $s_country : $b_country, $state_raw );
		$city_lbl   = $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city();

		$elapsed_sec = $date['timestamp'] > 0 ? max( 0, time() - $date['timestamp'] ) : 0;
		$is_delayed  = in_array( $status, array( 'pending', 'processing', 'onhold' ), true ) && ( $elapsed_sec > DAY_IN_SECONDS );
		$delay_hours = (int) floor( $elapsed_sec / HOUR_IN_SECONDS );

		return array(
			'id'                 => $order->get_id(),
			'orderNumber'        => $order->get_order_number(),
			'name'               => '' !== $full_name ? $full_name : __( 'مهمان', 'sefareshban' ),
			'city'               => $city_lbl,
			'state'              => $state_lbl,
			'email'              => $order->get_billing_email(),
			'phone'              => $order->get_billing_phone(),
			'iso'                => $date['iso'],
			'timestamp'          => $date['timestamp'],
			'date'               => $date['date'],
			'shortDate'          => $date['shortDate'],
			'time'               => $date['time'],
			'status'             => $status,
			'statusLabel'        => $this->status_label( $status ),
			'pay'                => $order->get_payment_method_title() ? $order->get_payment_method_title() : '—',
			'payMethod'          => $order->get_payment_method(),
			'shippingMethod'     => $order->get_shipping_method() ? $order->get_shipping_method() : '—',
			'trackingCode'       => $tracking['code'],
			'trackingCarrier'    => $tracking['carrier'],
			'customerNote'       => trim( (string) $order->get_customer_note() ),
			'isDelayed'          => $is_delayed,
			'delayHours'         => $delay_hours,
			'customerOrderCount' => $this->get_cached_customer_order_count( $order, $cust_cache ),
			'channel'            => $this->translate_channel( $order->get_created_via() ),
			'qty'                => $qty,
			'itemsPreview'       => $items_preview,
			'total'              => (float) $order->get_total(),
			'currency'           => $order->get_currency(),
		);
	}

	private function order_to_detail( $order ) {
		$cache = array();
		$row   = $this->order_to_row( $order, $cache );
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$product   = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : false;
			$pid       = is_callable( array( $item, 'get_product_id' ) ) ? (int) $item->get_product_id() : 0;
			$img       = '';
			$sku       = '';
			$edit_prod = '';
			$view_prod = '';
			if ( $product ) {
				$sku    = $product->get_sku();
				$img_id = $product->get_image_id();
				if ( $img_id ) {
					$src = wp_get_attachment_image_src( $img_id, 'thumbnail' );
					$img = $src ? $src[0] : '';
				}
			}
			if ( $pid > 0 ) {
				$edit_prod = get_edit_post_link( $pid, 'raw' );
				$view_prod = get_permalink( $pid );
			}

			$item_meta = array();
			if ( is_callable( array( $item, 'get_formatted_meta_data' ) ) ) {
				$raw_meta = $item->get_formatted_meta_data( '_', true );
				if ( is_array( $raw_meta ) ) {
					foreach ( $raw_meta as $m ) {
						if ( ! empty( $m->display_key ) && isset( $m->display_value ) ) {
							$item_meta[] = array(
								'k' => wp_strip_all_tags( (string) $m->display_key ),
								'v' => wp_strip_all_tags( (string) $m->display_value ),
							);
						}
					}
				}
			}

			$line_sub = (float) $order->get_item_subtotal( $item, false, false ) * max( 1, (int) $item->get_quantity() );
			$line_tot = (float) $order->get_line_total( $item, false, false );

			$items[] = array(
				'productId' => $pid,
				'n'         => $item->get_name(),
				'sku'       => $sku,
				'q'         => (int) $item->get_quantity(),
				'p'         => (float) $order->get_item_subtotal( $item, false, false ),
				'sub'       => $line_sub,
				't'         => $line_tot,
				'img'       => $img,
				'editUrl'   => $edit_prod,
				'viewUrl'   => $view_prod,
				'meta'      => $item_meta,
				'c'         => function_exists( 'mb_substr' ) ? mb_substr( $item->get_name(), 0, 1 ) : preg_replace( '/^(.).*$/us', '$1', (string) $item->get_name() ),
			);
		}

		$fees = array();
		if ( method_exists( $order, 'get_fees' ) ) {
			foreach ( $order->get_fees() as $fee ) {
				$fees[] = array(
					'name'  => $fee->get_name(),
					'total' => (float) $fee->get_total(),
				);
			}
		}

		$coupons = method_exists( $order, 'get_coupon_codes' ) ? array_values( (array) $order->get_coupon_codes() ) : array();

		$notes    = array();
		$wc_notes = wc_get_order_notes(
			array(
				'order_id' => $order->get_id(),
				'orderby'  => 'date_created',
				'order'    => 'DESC',
			)
		);
		foreach ( $wc_notes as $n ) {
			$by        = isset( $n->added_by ) ? (string) $n->added_by : '';
			$is_system = false;
			if ( '' === $by || 'system' === strtolower( $by ) || 'woocommerce' === strtolower( $by ) ) {
				$by        = __( 'سیستم', 'sefareshban' );
				$is_system = true;
			}
			$date_created = isset( $n->date_created ) ? $n->date_created : null;
			$at           = $date_created ? $this->format_jalali_ts( $date_created->getTimestamp(), 'full' ) : '';
			$notes[]      = array(
				'id'       => isset( $n->id ) ? (int) $n->id : 0,
				't'        => wp_strip_all_tags( $n->content ),
				'by'       => $by,
				'at'       => $at,
				'sys'      => $is_system,
				'customer' => ! empty( $n->customer_note ),
			);
		}

		$b_country   = $order->get_billing_country();
		$s_country   = $order->get_shipping_country();
		$b_state_lbl = $this->state_name( $b_country, $order->get_billing_state() );
		$s_state_lbl = $this->state_name( $s_country, $order->get_shipping_state() );

		$addr_parts = array_filter(
			array(
				$b_state_lbl,
				$order->get_billing_city(),
				$order->get_billing_address_1(),
				$order->get_billing_address_2(),
			)
		);
		$shipping_addr = array_filter(
			array(
				$s_state_lbl,
				$order->get_shipping_city(),
				$order->get_shipping_address_1(),
				$order->get_shipping_address_2(),
			)
		);
		$addr     = ! empty( $shipping_addr ) ? implode( '، ', $shipping_addr ) : implode( '، ', $addr_parts );
		$postcode = $order->get_shipping_postcode() ? $order->get_shipping_postcode() : $order->get_billing_postcode();

		$billing_obj = array(
			'firstName' => method_exists( $order, 'get_billing_first_name' ) ? (string) $order->get_billing_first_name() : '',
			'lastName'  => method_exists( $order, 'get_billing_last_name' ) ? (string) $order->get_billing_last_name() : '',
			'company'   => method_exists( $order, 'get_billing_company' ) ? (string) $order->get_billing_company() : '',
			'phone'     => (string) $order->get_billing_phone(),
			'email'     => (string) $order->get_billing_email(),
			'state'     => (string) $order->get_billing_state(),
			'stateName' => $b_state_lbl,
			'city'      => (string) $order->get_billing_city(),
			'address1'  => (string) $order->get_billing_address_1(),
			'address2'  => (string) $order->get_billing_address_2(),
			'postcode'  => (string) $order->get_billing_postcode(),
		);
		$shipping_obj = array(
			'firstName' => method_exists( $order, 'get_shipping_first_name' ) ? (string) $order->get_shipping_first_name() : '',
			'lastName'  => method_exists( $order, 'get_shipping_last_name' ) ? (string) $order->get_shipping_last_name() : '',
			'company'   => method_exists( $order, 'get_shipping_company' ) ? (string) $order->get_shipping_company() : '',
			'state'     => (string) $order->get_shipping_state(),
			'stateName' => $s_state_lbl,
			'city'      => (string) $order->get_shipping_city(),
			'address1'  => (string) $order->get_shipping_address_1(),
			'address2'  => (string) $order->get_shipping_address_2(),
			'postcode'  => (string) $order->get_shipping_postcode(),
		);

		$date_paid      = ( method_exists( $order, 'get_date_paid' ) && $order->get_date_paid() ) ? $this->format_jalali_ts( $order->get_date_paid()->getTimestamp(), 'full' ) : '';
		$date_completed = ( method_exists( $order, 'get_date_completed' ) && $order->get_date_completed() ) ? $this->format_jalali_ts( $order->get_date_completed()->getTimestamp(), 'full' ) : '';
		$customer_ip    = method_exists( $order, 'get_customer_ip_address' ) ? (string) $order->get_customer_ip_address() : '';

		$cid         = (int) $order->get_customer_id();
		$total_spent = $cid > 0 && function_exists( 'wc_get_customer_total_spent' ) ? (float) wc_get_customer_total_spent( $cid ) : (float) $order->get_total();

		$refunded  = (float) $order->get_total_refunded();
		$remaining = max( 0.0, (float) $order->get_total() - $refunded );

		$row['addr']               = $addr;
		$row['postcode']           = $postcode;
		$row['billing']            = $billing_obj;
		$row['shipping']           = $shipping_obj;
		$row['transactionId']      = $order->get_transaction_id();
		$row['datePaid']           = $date_paid;
		$row['dateCompleted']      = $date_completed;
		$row['customerIp']         = $customer_ip;
		$row['customerId']         = $cid;
		$row['customerEditUrl']    = $cid > 0 ? get_edit_user_link( $cid ) : '';
		$row['customerTotalSpent'] = $total_spent;
		$row['coupons']            = $coupons;
		$row['items']              = $items;
		$row['fees']               = $fees;
		$row['ship']               = (float) $order->get_shipping_total();
		$row['disc']               = (float) $order->get_discount_total();
		$row['tax']                = (float) $order->get_total_tax();
		$row['refunded']           = $refunded;
		$row['remainingRefund']    = $remaining;
		$row['subtotal']           = (float) $order->get_subtotal();
		$row['notes']              = $notes;
		$edit_url                  = $order->get_edit_order_url();
		$row['editUrl']            = $edit_url ? add_query_arg( 'sefareshban_native', '1', $edit_url ) : '';
		$row['orderCount']         = $row['customerOrderCount'];
		return $row;
	}

	private function compute_all_status_counts() {
		$status_counts = array( 'all' => 0, 'trash' => 0 );
		$all_statuses  = wc_get_order_statuses();
		$total_all     = 0;

		foreach ( $all_statuses as $k => $label ) {
			$slug    = $this->normalize_status( $k );
			$wc_slug = $this->wc_status( $slug );
			$count   = 0;
			if ( function_exists( 'wc_orders_count' ) ) {
				$count = (int) wc_orders_count( $wc_slug );
			} else {
				$counts_obj = wp_count_posts( 'shop_order' );
				$prop       = 'wc-' . $wc_slug;
				$count      = ( is_object( $counts_obj ) && isset( $counts_obj->$prop ) ) ? (int) $counts_obj->$prop : 0;
			}
			$status_counts[ $slug ] = $count;
			$total_all             += $count;
		}
		$status_counts['all'] = $total_all;

		// Count trashed orders reliably in both HPOS and Legacy CPT
		$trash_res = wc_get_orders(
			array(
				'type'     => 'shop_order',
				'status'   => 'trash',
				'limit'    => 1,
				'paginate' => true,
				'return'   => 'ids',
			)
		);
		$status_counts['trash'] = ( is_object( $trash_res ) && isset( $trash_res->total ) ) ? (int) $trash_res->total : 0;

		return $status_counts;
	}

	private function get_latest_order_id() {
		$latest = wc_get_orders(
			array(
				'type'    => 'shop_order',
				'status'  => array_keys( wc_get_order_statuses() ),
				'limit'   => 1,
				'orderby' => 'ID',
				'order'   => 'DESC',
				'return'  => 'ids',
			)
		);
		return ( is_array( $latest ) && ! empty( $latest ) ) ? (int) $latest[0] : 0;
	}

	private function intersect_order_ids( &$args, array $candidate_ids ) {
		$key          = $this->is_hpos_enabled() ? 'id' : 'post__in';
		$clean_cands  = array_values( array_unique( array_filter( array_map( 'absint', $candidate_ids ) ) ) );
		if ( isset( $args[ $key ] ) && is_array( $args[ $key ] ) ) {
			if ( array( 0 ) === $args[ $key ] ) {
				return;
			}
			$merged       = array_values( array_intersect( $args[ $key ], $clean_cands ) );
			$args[ $key ] = empty( $merged ) ? array( 0 ) : $merged;
		} else {
			$args[ $key ] = empty( $clean_cands ) ? array( 0 ) : $clean_cands;
		}
	}

	private function apply_search_to_args( &$args, $search, $status_context = 'all' ) {
		$search = trim( $this->normalize_digits( $search ) );
		if ( '' === $search ) {
			return;
		}

		$clean_id = trim( ltrim( $search, '#' ) );

		if ( strpos( $search, '#' ) === 0 && ctype_digit( $clean_id ) ) {
			$order = wc_get_order( absint( $clean_id ) );
			if ( $this->is_real_order( $order ) ) {
				$this->intersect_order_ids( $args, array( $order->get_id() ) );
				return;
			}
		}

		if ( ctype_digit( $clean_id ) ) {
			$matched_ids = array();
			if ( strlen( $clean_id ) <= 9 ) {
				$maybe_order = wc_get_order( absint( $clean_id ) );
				if ( $this->is_real_order( $maybe_order ) ) {
					$matched_ids[] = $maybe_order->get_id();
				}
			}

			$search_statuses = ( 'trash' === $status_context ) ? 'trash' : array_keys( wc_get_order_statuses() );
			$phone_args      = array(
				'type'   => 'shop_order',
				'status' => $search_statuses,
				'limit'  => 200,
				'return' => 'ids',
				's'      => $clean_id,
			);
			if ( $this->is_hpos_enabled() ) {
				$phone_args['search_filter'] = 'all';
			}
			$found_by_s = wc_get_orders( $phone_args );
			if ( is_array( $found_by_s ) ) {
				$matched_ids = array_values( array_unique( array_merge( $matched_ids, array_map( 'absint', $found_by_s ) ) ) );
			}

			$by_phone = wc_get_orders(
				array(
					'type'          => 'shop_order',
					'status'        => $search_statuses,
					'billing_phone' => $clean_id,
					'limit'         => 100,
					'return'        => 'ids',
				)
			);
			if ( is_array( $by_phone ) && ! empty( $by_phone ) ) {
				$matched_ids = array_values( array_unique( array_merge( $matched_ids, array_map( 'absint', $by_phone ) ) ) );
			}

			// Also search tracking code match
			$by_tracking = wc_get_orders(
				array(
					'type'       => 'shop_order',
					'status'     => $search_statuses,
					'meta_key'   => '_sefareshban_tracking_code', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $clean_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'limit'      => 50,
					'return'     => 'ids',
				)
			);
			if ( is_array( $by_tracking ) && ! empty( $by_tracking ) ) {
				$matched_ids = array_values( array_unique( array_merge( $matched_ids, array_map( 'absint', $by_tracking ) ) ) );
			}

			$this->intersect_order_ids( $args, $matched_ids );
			return;
		}

		$args['s'] = $search;
		if ( $this->is_hpos_enabled() ) {
			$args['search_filter'] = 'all';
		}
	}

	public function sefareshban_get_stats() {
		$this->verify();

		$now_utc     = time();
		$today_b     = $this->get_local_day_bounds_utc( 0 );
		$yesterday_b = $this->get_local_day_bounds_utc( 1 );
		$days30_b    = $this->get_local_day_bounds_utc( 29 );
		$days60_b    = $this->get_local_day_bounds_utc( 59 );

		$recent_orders = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'limit'        => 3000,
				'return'       => 'objects',
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => $days60_b['start_utc'] . '...' . $today_b['end_utc'],
				'status'       => array_keys( wc_get_order_statuses() ),
			)
		);

		$paid_statuses = wc_get_is_paid_statuses();
		$valid_aov_set = array( 'completed', 'processing', 'on-hold' );
		$pending_set   = array( 'pending', 'on-hold', 'processing' );

		$today_count     = 0;
		$yesterday_count = 0;
		$today_sales     = 0.0;
		$yesterday_sales = 0.0;

		$total30      = 0.0;
		$count30      = 0;
		$prev_total30 = 0.0;
		$prev_count30 = 0;

		$days7_bounds = array();
		for ( $i = 6; $i >= 0; $i-- ) {
			$b              = $this->get_local_day_bounds_utc( $i );
			$days7_bounds[] = array(
				'start'   => $b['start_utc'],
				'end'     => $b['end_utc'],
				'label'   => 0 === $i ? __( 'امروز', 'sefareshban' ) : $this->format_jalali_ts( $b['start_utc'], 'weekday' ),
				'count'   => 0,
				'sales'   => 0.0,
				'pending' => 0,
			);
		}

		$aov_buckets = array();
		for ( $i = 7; $i >= 0; $i-- ) {
			$b_end         = $this->get_local_day_bounds_utc( $i * 4 );
			$b_start       = $this->get_local_day_bounds_utc( min( 29, $i * 4 + 3 ) );
			$aov_buckets[] = array(
				'start' => $b_start['start_utc'],
				'end'   => $b_end['end_utc'],
				'label' => 0 === $i ? __( 'امروز', 'sefareshban' ) : $this->format_jalali_ts( $b_end['end_utc'], 'short' ),
				'total' => 0.0,
				'count' => 0,
			);
		}

		foreach ( $recent_orders as $o ) {
			if ( ! $this->is_real_order( $o ) ) {
				continue;
			}
			$created = $o->get_date_created();
			if ( ! $created ) {
				continue;
			}
			$ts      = $created->getTimestamp();
			$st      = $o->get_status();
			$amt     = (float) $o->get_total();
			$is_paid = in_array( $st, $paid_statuses, true ) || $o->is_paid();

			if ( $ts >= $today_b['start_utc'] && $ts <= $today_b['end_utc'] ) {
				$today_count++;
				if ( $is_paid ) {
					$today_sales += $amt;
				}
			} elseif ( $ts >= $yesterday_b['start_utc'] && $ts <= $yesterday_b['end_utc'] ) {
				$yesterday_count++;
				if ( $is_paid ) {
					$yesterday_sales += $amt;
				}
			}

			if ( $ts >= $days30_b['start_utc'] && $ts <= $today_b['end_utc'] ) {
				if ( in_array( $st, $valid_aov_set, true ) ) {
					$total30 += $amt;
					$count30++;
				}
			} elseif ( $ts >= $days60_b['start_utc'] && $ts < $days30_b['start_utc'] ) {
				if ( in_array( $st, $valid_aov_set, true ) ) {
					$prev_total30 += $amt;
					$prev_count30++;
				}
			}

			if ( $ts >= $days7_bounds[0]['start'] ) {
				foreach ( $days7_bounds as $idx => $db ) {
					if ( $ts >= $db['start'] && $ts <= $db['end'] ) {
						$days7_bounds[ $idx ]['count']++;
						if ( $is_paid ) {
							$days7_bounds[ $idx ]['sales'] += $amt;
						}
						if ( in_array( $st, $pending_set, true ) ) {
							$days7_bounds[ $idx ]['pending']++;
						}
						break;
					}
				}
			}

			if ( $ts >= $days30_b['start_utc'] && in_array( $st, $valid_aov_set, true ) ) {
				foreach ( $aov_buckets as $idx => $ab ) {
					if ( $ts >= $ab['start'] && $ts <= $ab['end'] ) {
						$aov_buckets[ $idx ]['total'] += $amt;
						$aov_buckets[ $idx ]['count']++;
						break;
					}
				}
			}
		}

		$status_counts = $this->compute_all_status_counts();
		$pending_count = ( $status_counts['pending'] ?? 0 ) + ( $status_counts['onhold'] ?? 0 ) + ( $status_counts['processing'] ?? 0 );

		$urgent_cutoff = $now_utc - DAY_IN_SECONDS;
		$urgent_res    = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => array( 'wc-pending', 'wc-on-hold', 'wc-processing' ),
				'date_created' => '<' . $urgent_cutoff,
				'limit'        => 1,
				'paginate'     => true,
				'return'       => 'ids',
			)
		);
		$urgent_count = ( is_object( $urgent_res ) && isset( $urgent_res->total ) ) ? (int) $urgent_res->total : 0;

		$aov30      = $count30 > 0 ? ( $total30 / $count30 ) : 0;
		$prev_aov30 = $prev_count30 > 0 ? ( $prev_total30 / $prev_count30 ) : 0;

		$series7         = array();
		$pending_series7 = array();
		foreach ( $days7_bounds as $db ) {
			$series7[] = array(
				'label' => $db['label'],
				'count' => $db['count'],
				'sales' => $db['sales'],
			);
			$pending_series7[] = array(
				'label' => $db['label'],
				'count' => $db['pending'],
			);
		}

		$aov_series = array();
		foreach ( $aov_buckets as $ab ) {
			$aov_series[] = array(
				'label' => $ab['label'],
				'value' => $ab['count'] > 0 ? ( $ab['total'] / $ab['count'] ) : 0,
			);
		}

		wp_send_json_success(
			array(
				'todayCount'     => $today_count,
				'yesterdayCount' => $yesterday_count,
				'todaySales'     => $today_sales,
				'yesterdaySales' => $yesterday_sales,
				'pendingCount'   => $pending_count,
				'urgentCount'    => $urgent_count,
				'aov30'          => $aov30,
				'prevAov30'      => $prev_aov30,
				'sales30'        => $total30,
				'orders30'       => $count30,
				'totalOrdersAll' => $status_counts['all'] ?? 0,
				'latestOrderId'  => $this->get_latest_order_id(),
				'statusCounts'   => $status_counts,
				'series7'        => $series7,
				'pendingSeries7' => $pending_series7,
				'aovSeries'      => $aov_series,
			)
		);
	}

	public function sefareshban_get_reports() {
		$this->verify();

		$days = absint( $_POST['days'] ?? 30 );
		if ( ! in_array( $days, array( 7, 30, 90, 365 ), true ) ) {
			$days = 30;
		}

		$today_b = $this->get_local_day_bounds_utc( 0 );
		$start_b = $this->get_local_day_bounds_utc( $days - 1 );

		$orders = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'limit'        => 3000,
				'return'       => 'objects',
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => $start_b['start_utc'] . '...' . $today_b['end_utc'],
				'status'       => array_keys( wc_get_order_statuses() ),
			)
		);

		$paid_statuses = wc_get_is_paid_statuses();

		$gross_sales     = 0.0;
		$net_sales       = 0.0;
		$total_shipping  = 0.0;
		$total_discounts = 0.0;
		$total_refunded  = 0.0;
		$paid_orders_cnt = 0;
		$all_orders_cnt  = 0;
		$items_sold      = 0;

		$by_status  = array();
		$by_gateway = array();
		$by_product = array();
		$by_city    = array();

		$bucket_count = min( $days, 14 );
		$bucket_span  = max( 1, (int) ceil( $days / $bucket_count ) );
		$chart_series = array();
		for ( $i = $bucket_count - 1; $i >= 0; $i-- ) {
			$end_ago   = $i * $bucket_span;
			$start_ago = min( $days - 1, ( $i + 1 ) * $bucket_span - 1 );
			if ( $start_ago < $end_ago ) {
				continue;
			}
			$be             = $this->get_local_day_bounds_utc( $end_ago );
			$bs             = $this->get_local_day_bounds_utc( $start_ago );
			$chart_series[] = array(
				'start' => $bs['start_utc'],
				'end'   => $be['end_utc'],
				'label' => 0 === $end_ago ? __( 'امروز', 'sefareshban' ) : $this->format_jalali_ts( $be['end_utc'], 'short' ),
				'sales' => 0.0,
				'count' => 0,
			);
		}

		foreach ( $orders as $o ) {
			if ( ! $this->is_real_order( $o ) ) {
				continue;
			}
			$all_orders_cnt++;
			$st       = $this->normalize_status( $o->get_status() );
			$st_label = $this->status_label( $st );
			$amt      = (float) $o->get_total();
			$refund   = (float) $o->get_total_refunded();

			if ( ! isset( $by_status[ $st ] ) ) {
				$by_status[ $st ] = array(
					'status' => $st,
					'label'  => $st_label,
					'count'  => 0,
					'total'  => 0.0,
				);
			}
			$by_status[ $st ]['count']++;
			$by_status[ $st ]['total'] += $amt;

			$total_refunded += $refund;

			$raw_status = $o->get_status();
			$is_paid    = in_array( $raw_status, $paid_statuses, true ) || $o->is_paid();

			$created = $o->get_date_created();
			$ts      = $created ? $created->getTimestamp() : 0;

			foreach ( $chart_series as $idx => $cs ) {
				if ( $ts >= $cs['start'] && $ts <= $cs['end'] ) {
					$chart_series[ $idx ]['count']++;
					if ( $is_paid ) {
						$chart_series[ $idx ]['sales'] += max( 0, $amt - $refund );
					}
					break;
				}
			}

			if ( $is_paid ) {
				$paid_orders_cnt++;
				$ship = (float) $o->get_shipping_total();
				$disc = (float) $o->get_discount_total();

				$gross_sales     += $amt;
				$net_sales       += max( 0, $amt - $ship - (float) $o->get_total_tax() - $refund );
				$total_shipping  += $ship;
				$total_discounts += $disc;

				$gw_title = $o->get_payment_method_title() ? $o->get_payment_method_title() : __( 'نامشخص', 'sefareshban' );
				$gw_key   = $o->get_payment_method() ? $o->get_payment_method() : md5( $gw_title );
				if ( ! isset( $by_gateway[ $gw_key ] ) ) {
					$by_gateway[ $gw_key ] = array(
						'title' => $gw_title,
						'count' => 0,
						'total' => 0.0,
					);
				}
				$by_gateway[ $gw_key ]['count']++;
				$by_gateway[ $gw_key ]['total'] += $amt;

				$city = trim( $o->get_shipping_city() ? $o->get_shipping_city() : $o->get_billing_city() );
				if ( '' !== $city ) {
					if ( ! isset( $by_city[ $city ] ) ) {
						$by_city[ $city ] = array(
							'city'  => $city,
							'count' => 0,
							'total' => 0.0,
						);
					}
					$by_city[ $city ]['count']++;
					$by_city[ $city ]['total'] += $amt;
				}

				foreach ( $o->get_items() as $item ) {
					$pid        = is_callable( array( $item, 'get_product_id' ) ) ? (int) $item->get_product_id() : 0;
					$pname      = $item->get_name();
					$pkey       = $pid ? 'p_' . $pid : 'n_' . md5( $pname );
					$qty        = (int) $item->get_quantity();
					$ltot       = (float) $o->get_line_total( $item, false, false );
					$items_sold += $qty;

					if ( ! isset( $by_product[ $pkey ] ) ) {
						$by_product[ $pkey ] = array(
							'id'    => $pid,
							'name'  => $pname,
							'qty'   => 0,
							'total' => 0.0,
						);
					}
					$by_product[ $pkey ]['qty']   += $qty;
					$by_product[ $pkey ]['total'] += $ltot;
				}
			}
		}

		$status_list = array_values( $by_status );
		usort( $status_list, function( $a, $b ) { return $b['count'] <=> $a['count']; } );

		$gateway_list = array_values( $by_gateway );
		usort( $gateway_list, function( $a, $b ) { return $b['total'] <=> $a['total']; } );

		$product_list = array_values( $by_product );
		usort( $product_list, function( $a, $b ) { return $b['total'] <=> $a['total']; } );
		$product_list = array_slice( $product_list, 0, 8 );

		$city_list = array_values( $by_city );
		usort( $city_list, function( $a, $b ) { return $b['count'] <=> $a['count']; } );
		$city_list = array_slice( $city_list, 0, 6 );

		$chart_out = array();
		foreach ( $chart_series as $cs ) {
			$chart_out[] = array(
				'label' => $cs['label'],
				'sales' => round( $cs['sales'] ),
				'count' => $cs['count'],
			);
		}

		$aov = $paid_orders_cnt > 0 ? ( $gross_sales / $paid_orders_cnt ) : 0;

		wp_send_json_success(
			array(
				'days'            => $days,
				'allOrdersCount'  => $all_orders_cnt,
				'paidOrdersCount' => $paid_orders_cnt,
				'grossSales'      => $gross_sales,
				'netSales'        => $net_sales,
				'shippingTotal'   => $total_shipping,
				'discountTotal'   => $total_discounts,
				'refundedTotal'   => $total_refunded,
				'itemsSold'       => $items_sold,
				'aov'             => $aov,
				'chart'           => $chart_out,
				'byStatus'        => $status_list,
				'byGateway'       => $gateway_list,
				'topProducts'     => $product_list,
				'topCities'       => $city_list,
			)
		);
	}

	private function build_orders_query_args( $params ) {
		global $wpdb;

		$status          = sanitize_text_field( $params['status'] ?? 'all' );
		$search          = sanitize_text_field( $params['search'] ?? '' );
		$sort_key        = sanitize_text_field( $params['sort_key'] ?? 'date' );
		$sort_dir        = sanitize_text_field( $params['sort_dir'] ?? 'desc' );
		$date_from       = sanitize_text_field( $params['date_from'] ?? '' );
		$date_to         = sanitize_text_field( $params['date_to'] ?? '' );
		$payment_method  = sanitize_text_field( $params['payment_method'] ?? '' );
		$shipping_filter = sanitize_text_field( $params['shipping_filter'] ?? '' );
		$state_filter    = sanitize_text_field( $params['state_filter'] ?? '' );
		$city_filter     = sanitize_text_field( $params['city_filter'] ?? '' );
		$only_with_note  = ! empty( $params['only_with_note'] ) && '0' !== (string) $params['only_with_note'];
		$only_delayed    = ! empty( $params['only_delayed'] ) && '0' !== (string) $params['only_delayed'];
		$min_total       = isset( $params['min_total'] ) && '' !== trim( (string) $params['min_total'] ) ? (float) $this->normalize_digits( $params['min_total'] ) : null;
		$max_total       = isset( $params['max_total'] ) && '' !== trim( (string) $params['max_total'] ) ? (float) $this->normalize_digits( $params['max_total'] ) : null;

		$args = array(
			'return' => 'objects',
			'type'   => 'shop_order',
		);

		if ( 'trash' === $status ) {
			$args['status'] = 'trash';
		} elseif ( $status && 'all' !== $status ) {
			$wc_st          = $this->wc_status( $status );
			$args['status'] = $this->is_hpos_enabled() ? array( 'wc-' . $wc_st, $wc_st ) : array( 'wc-' . $wc_st );
		} else {
			$args['status'] = array_keys( wc_get_order_statuses() );
		}

		// Delayed filter (+24h in pending/on-hold/processing)
		$from_ts = $this->parse_date_filter_utc( $date_from, false );
		$to_ts   = $this->parse_date_filter_utc( $date_to, true );

		if ( $only_delayed ) {
			$delayed_cutoff = time() - DAY_IN_SECONDS;
			$to_ts          = $to_ts ? min( $to_ts, $delayed_cutoff ) : $delayed_cutoff;
			if ( 'all' === $status ) {
				$args['status'] = $this->is_hpos_enabled()
					? array( 'wc-pending', 'pending', 'wc-on-hold', 'on-hold', 'wc-processing', 'processing' )
					: array( 'wc-pending', 'wc-on-hold', 'wc-processing' );
			} elseif ( ! in_array( $status, array( 'pending', 'onhold', 'processing' ), true ) ) {
				$this->intersect_order_ids( $args, array() );
			}
		}

		if ( $from_ts && $to_ts ) {
			if ( $from_ts > $to_ts ) {
				$this->intersect_order_ids( $args, array() );
			} else {
				$args['date_created'] = $from_ts . '...' . $to_ts;
			}
		} elseif ( $from_ts ) {
			$args['date_created'] = '>=' . $from_ts;
		} elseif ( $to_ts ) {
			$args['date_created'] = '<=' . $to_ts;
		}

		if ( '' !== $payment_method ) {
			$args['payment_method'] = $payment_method;
		}

		if ( '' !== $state_filter ) {
			$args['billing_state'] = $state_filter;
		}

		if ( '' !== $city_filter ) {
			$args['billing_city'] = $city_filter;
		}

		// Shipping method filter via woocommerce_order_items table (works identically in HPOS & Legacy)
		if ( '' !== $shipping_filter && isset( $wpdb ) ) {
			$items_tbl = $wpdb->prefix . 'woocommerce_order_items';
			$ship_ids  = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT DISTINCT order_id FROM {$items_tbl} WHERE order_item_type = 'shipping' AND order_item_name LIKE %s ORDER BY order_id DESC LIMIT 3000", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					'%' . $wpdb->esc_like( $shipping_filter ) . '%'
				)
			);
			$this->intersect_order_ids( $args, is_array( $ship_ids ) ? $ship_ids : array() );
		}

		// Customer note filter (HPOS & Legacy compatible)
		if ( $only_with_note && isset( $wpdb ) ) {
			if ( $this->is_hpos_enabled() ) {
				$orders_tbl = $wpdb->prefix . 'wc_orders';
				$note_ids   = $wpdb->get_col( "SELECT id FROM {$orders_tbl} WHERE type = 'shop_order' AND customer_note IS NOT NULL AND TRIM(customer_note) != '' ORDER BY id DESC LIMIT 3000" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			} else {
				$note_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND TRIM(post_excerpt) != '' ORDER BY ID DESC LIMIT 3000" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			}
			$this->intersect_order_ids( $args, is_array( $note_ids ) ? $note_ids : array() );
		}

		// Sort
		$order = ( 'asc' === strtolower( $sort_dir ) ) ? 'ASC' : 'DESC';
		if ( 'id' === $sort_key ) {
			$args['orderby'] = 'ID';
		} elseif ( 'total' === $sort_key ) {
			if ( $this->is_hpos_enabled() ) {
				$args['orderby'] = 'total';
			} else {
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_order_total'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			}
		} else {
			$args['orderby'] = 'date';
		}
		$args['order'] = $order;

		if ( '' !== $search ) {
			$this->apply_search_to_args( $args, $search, $status );
		}

		if ( null !== $min_total || null !== $max_total ) {
			if ( $this->is_hpos_enabled() ) {
				$fq = array( 'relation' => 'AND' );
				if ( null !== $min_total ) {
					$fq[] = array(
						'field'   => 'total_amount',
						'value'   => $min_total,
						'compare' => '>=',
						'type'    => 'NUMERIC',
					);
				}
				if ( null !== $max_total ) {
					$fq[] = array(
						'field'   => 'total_amount',
						'value'   => $max_total,
						'compare' => '<=',
						'type'    => 'NUMERIC',
					);
				}
				$args['field_query'] = $fq;
			} else {
				$mq = array( 'relation' => 'AND' );
				if ( null !== $min_total ) {
					$mq[] = array(
						'key'     => '_order_total',
						'value'   => $min_total,
						'compare' => '>=',
						'type'    => 'NUMERIC',
					);
				}
				if ( null !== $max_total ) {
					$mq[] = array(
						'key'     => '_order_total',
						'value'   => $max_total,
						'compare' => '<=',
						'type'    => 'NUMERIC',
					);
				}
				$args['meta_query'] = $mq; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			}
		}

		return $args;
	}

	public function sefareshban_get_orders() {
		$this->verify();

		$page     = max( 1, absint( $_POST['page'] ?? 1 ) );
		$per_page = absint( $_POST['per_page'] ?? 8 );
		if ( ! in_array( $per_page, array( 8, 12, 20, 50, 100 ), true ) ) {
			$per_page = 8;
		}
		$sort_key = sanitize_text_field( wp_unslash( $_POST['sort_key'] ?? 'date' ) );
		$sort_dir = sanitize_text_field( wp_unslash( $_POST['sort_dir'] ?? 'desc' ) );

		$raw_params = wp_unslash( $_POST );
		$args       = $this->build_orders_query_args( $raw_params );

		$args['limit']    = $per_page;
		$args['paged']    = $page;
		$args['paginate'] = true;

		$result = wc_get_orders( $args );
		$total  = is_object( $result ) && isset( $result->total ) ? (int) $result->total : 0;
		$orders = is_object( $result ) && isset( $result->orders ) ? $result->orders : array();

		$max_pages = max( 1, (int) ceil( $total / $per_page ) );
		if ( $page > $max_pages && $total > 0 ) {
			$page          = $max_pages;
			$args['paged'] = $page;
			$result        = wc_get_orders( $args );
			$orders        = is_object( $result ) && isset( $result->orders ) ? $result->orders : array();
		}

		$rows       = array();
		$cust_cache = array();
		foreach ( $orders as $order ) {
			if ( ! $this->is_real_order( $order ) ) {
				continue;
			}
			$rows[] = $this->order_to_row( $order, $cust_cache );
		}

		if ( 'name' === $sort_key ) {
			usort(
				$rows,
				function( $a, $b ) use ( $sort_dir ) {
					$cmp = strcmp( $a['name'], $b['name'] );
					return ( 'asc' === strtolower( $sort_dir ) ) ? $cmp : -$cmp;
				}
			);
		}

		$status_counts = $this->compute_all_status_counts();

		wp_send_json_success(
			array(
				'rows'          => $rows,
				'total'         => $total,
				'page'          => $page,
				'perPage'       => $per_page,
				'statusCounts'  => $status_counts,
				'latestOrderId' => $this->get_latest_order_id(),
			)
		);
	}

	public function sefareshban_get_order() {
		$this->verify();
		$id = absint( $_POST['order_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'شناسه سفارش نامعتبر است.', 'sefareshban' ) ), 400 );
		}
		$order = wc_get_order( $id );
		if ( ! $this->is_real_order( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'سفارش یافت نشد.', 'sefareshban' ) ), 404 );
		}
		wp_send_json_success( array( 'order' => $this->order_to_detail( $order ) ) );
	}

	public function sefareshban_get_orders_batch() {
		$this->verify();
		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'هیچ سفارشی انتخاب نشده است.', 'sefareshban' ) ), 400 );
		}
		$ids = array_slice( $ids, 0, 50 );
		$out = array();
		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			if ( $this->is_real_order( $order ) ) {
				$out[] = $this->order_to_detail( $order );
			}
		}
		wp_send_json_success( array( 'orders' => $out ) );
	}

	public function sefareshban_update_status() {
		$this->verify();
		$id     = absint( $_POST['order_id'] ?? 0 );
		$status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );
		if ( ! $id || ! $status ) {
			wp_send_json_error( array( 'message' => __( 'اطلاعات ارسالی ناقص است.', 'sefareshban' ) ), 400 );
		}
		$order = wc_get_order( $id );
		if ( ! $this->is_real_order( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'سفارش یافت نشد.', 'sefareshban' ) ), 404 );
		}
		$wc_status = $this->wc_status( $status );
		$allowed   = array_keys( wc_get_order_statuses() );
		if ( ! in_array( 'wc-' . $wc_status, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'وضعیت انتخاب‌شده معتبر نیست.', 'sefareshban' ) ), 400 );
		}
		if ( 'trash' === $order->get_status() ) {
			$this->restore_order_item( $order );
			$order = wc_get_order( $id );
		}
		$order->update_status( $wc_status, __( 'تغییر وضعیت از طریق سفارش‌بان', 'sefareshban' ), true );
		wp_send_json_success(
			array(
				'order' => $this->order_to_detail( wc_get_order( $id ) ),
			)
		);
	}

	public function sefareshban_add_note() {
		$this->verify();
		$id            = absint( $_POST['order_id'] ?? 0 );
		$note          = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
		$customer_note = ! empty( $_POST['customer_note'] ) && '0' !== $_POST['customer_note'] ? 1 : 0;
		if ( ! $id || '' === $note ) {
			wp_send_json_error( array( 'message' => __( 'متن یادداشت نمی‌تواند خالی باشد.', 'sefareshban' ) ), 400 );
		}
		$order = wc_get_order( $id );
		if ( ! $this->is_real_order( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'سفارش یافت نشد.', 'sefareshban' ) ), 404 );
		}
		$order->add_order_note( $note, $customer_note, true );
		wp_send_json_success(
			array(
				'order' => $this->order_to_detail( wc_get_order( $id ) ),
			)
		);
	}

	public function sefareshban_delete_note() {
		$this->verify();
		$order_id = absint( $_POST['order_id'] ?? 0 );
		$note_id  = absint( $_POST['note_id'] ?? 0 );
		if ( ! $order_id || ! $note_id ) {
			wp_send_json_error( array( 'message' => __( 'شناسه یادداشت نامعتبر است.', 'sefareshban' ) ), 400 );
		}
		$order = wc_get_order( $order_id );
		if ( ! $this->is_real_order( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'سفارش یافت نشد.', 'sefareshban' ) ), 404 );
		}
		if ( function_exists( 'wc_delete_order_note' ) ) {
			wc_delete_order_note( $note_id );
		}
		wp_send_json_success(
			array(
				'order' => $this->order_to_detail( wc_get_order( $order_id ) ),
			)
		);
	}

	public function sefareshban_update_order_details() {
		$this->verify();
		$id = absint( $_POST['order_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'شناسه سفارش نامعتبر است.', 'sefareshban' ) ), 400 );
		}
		$order = wc_get_order( $id );
		if ( ! $this->is_real_order( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'سفارش یافت نشد.', 'sefareshban' ) ), 404 );
		}

		$first_name     = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
		$last_name      = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
		$phone          = sanitize_text_field( $this->normalize_digits( wp_unslash( $_POST['phone'] ?? '' ) ) );
		$email          = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$state          = sanitize_text_field( wp_unslash( $_POST['state'] ?? '' ) );
		$city           = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );
		$address_1      = sanitize_textarea_field( wp_unslash( $_POST['address_1'] ?? '' ) );
		$address_2      = sanitize_text_field( wp_unslash( $_POST['address_2'] ?? '' ) );
		$postcode       = sanitize_text_field( $this->normalize_digits( wp_unslash( $_POST['postcode'] ?? '' ) ) );
		$customer_note  = sanitize_textarea_field( wp_unslash( $_POST['customer_note'] ?? '' ) );
		$transaction_id = sanitize_text_field( $this->normalize_digits( wp_unslash( $_POST['transaction_id'] ?? '' ) ) );

		$addr_payload = array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'phone'      => $phone,
			'email'      => $email,
			'country'    => $order->get_billing_country() ? $order->get_billing_country() : 'IR',
			'state'      => $state,
			'city'       => $city,
			'address_1'  => $address_1,
			'address_2'  => $address_2,
			'postcode'   => $postcode,
		);

		$order->set_address( $addr_payload, 'billing' );
		unset( $addr_payload['email'], $addr_payload['phone'] );
		$order->set_address( $addr_payload, 'shipping' );

		if ( method_exists( $order, 'set_customer_note' ) ) {
			$order->set_customer_note( $customer_note );
		}
		if ( method_exists( $order, 'set_transaction_id' ) ) {
			$order->set_transaction_id( $transaction_id );
		}

		$order->save();
		$order->add_order_note( __( 'مشخصات گیرنده و نشانی سفارش از طریق صفحه اختصاصی سفارش‌بان ویرایش شد.', 'sefareshban' ), 0, true );

		wp_send_json_success(
			array(
				'order' => $this->order_to_detail( wc_get_order( $id ) ),
			)
		);
	}

	public function sefareshban_save_tracking() {
		$this->verify();
		$id              = absint( $_POST['order_id'] ?? 0 );
		$code            = sanitize_text_field( $this->normalize_digits( wp_unslash( $_POST['tracking_code'] ?? '' ) ) );
		$carrier         = sanitize_text_field( wp_unslash( $_POST['carrier'] ?? 'پست پیشتاز' ) );
		$notify_customer = ! empty( $_POST['notify_customer'] ) && '0' !== (string) $_POST['notify_customer'];
		$mark_completed  = ! empty( $_POST['mark_completed'] ) && '0' !== (string) $_POST['mark_completed'];

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'شناسه سفارش نامعتبر است.', 'sefareshban' ) ), 400 );
		}
		$order = wc_get_order( $id );
		if ( ! $this->is_real_order( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'سفارش یافت نشد.', 'sefareshban' ) ), 404 );
		}

		$order->update_meta_data( '_sefareshban_tracking_code', $code );
		$order->update_meta_data( '_sefareshban_tracking_carrier', $carrier );
		$order->update_meta_data( '_tracking_code', $code );
		$order->save();

		if ( '' !== $code ) {
			$msg = sprintf(
				/* translators: 1: carrier name, 2: tracking code */
				__( 'مرسوله سفارش شما از طریق «%1$s» ارسال شد. کد رهگیری مرسوله: %2$s', 'sefareshban' ),
				$carrier ? $carrier : __( 'پست', 'sefareshban' ),
				$code
			);
			$order->add_order_note( $msg, $notify_customer ? 1 : 0, true );
		}

		if ( $mark_completed && 'completed' !== $order->get_status() ) {
			$order->update_status( 'completed', __( 'تکمیل خودکار سفارش پس از ثبت کد رهگیری در سفارش‌بان', 'sefareshban' ), true );
		}

		wp_send_json_success(
			array(
				'order' => $this->order_to_detail( wc_get_order( $id ) ),
			)
		);
	}

	public function sefareshban_create_order() {
		$this->verify();

		$customer_name   = sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) );
		$phone           = sanitize_text_field( $this->normalize_digits( wp_unslash( $_POST['phone'] ?? '' ) ) );
		$email           = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$state           = sanitize_text_field( wp_unslash( $_POST['state'] ?? '' ) );
		$city            = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );
		$address         = sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) );
		$postcode        = sanitize_text_field( $this->normalize_digits( wp_unslash( $_POST['postcode'] ?? '' ) ) );
		$customer_note   = sanitize_textarea_field( wp_unslash( $_POST['customer_note'] ?? '' ) );
		$payment_method  = sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? '' ) );
		$status          = sanitize_text_field( wp_unslash( $_POST['status'] ?? 'processing' ) );
		$shipping_title  = sanitize_text_field( wp_unslash( $_POST['shipping_title'] ?? 'ارسال پستی' ) );
		$shipping_cost   = max( 0.0, (float) $this->normalize_digits( wp_unslash( $_POST['shipping_cost'] ?? '0' ) ) );
		$discount_amount = max( 0.0, (float) $this->normalize_digits( wp_unslash( $_POST['discount_amount'] ?? '0' ) ) );
		$items_raw       = wp_unslash( $_POST['items_json'] ?? '[]' );
		$items_list      = json_decode( $items_raw, true );

		if ( ! is_array( $items_list ) || empty( $items_list ) ) {
			wp_send_json_error( array( 'message' => __( 'لطفاً حداقل یک محصول به سفارش اضافه کنید.', 'sefareshban' ) ), 400 );
		}
		if ( '' === trim( $customer_name ) && '' === trim( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'وارد کردن نام یا شماره موبایل مشتری الزامی است.', 'sefareshban' ) ), 400 );
		}

		$wc_status = $this->wc_status( $status );
		$allowed   = array_keys( wc_get_order_statuses() );
		if ( ! in_array( 'wc-' . $wc_status, $allowed, true ) ) {
			$wc_status = 'processing';
		}

		$order = wc_create_order();
		if ( is_wp_error( $order ) || ! $order ) {
			wp_send_json_error( array( 'message' => __( 'خطا در ایجاد سفارش جدید.', 'sefareshban' ) ), 500 );
		}

		$added_count = 0;
		foreach ( $items_list as $it ) {
			$pid = absint( $it['product_id'] ?? 0 );
			$qty = max( 1, absint( $it['qty'] ?? 1 ) );
			if ( ! $pid ) {
				continue;
			}
			$prod = wc_get_product( $pid );
			if ( $prod ) {
				$order->add_product( $prod, $qty );
				$added_count++;
			}
		}

		if ( 0 === $added_count ) {
			$order->delete( true );
			wp_send_json_error( array( 'message' => __( 'محصولات انتخاب‌شده معتبر نیستند.', 'sefareshban' ) ), 400 );
		}

		// Split full name into first_name and last_name
		$name_parts = preg_split( '/\s+/u', trim( $customer_name ), 2 );
		$first_name = $name_parts[0] ?? '';
		$last_name  = $name_parts[1] ?? '';

		$addr_data = array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'email'      => $email,
			'phone'      => $phone,
			'country'    => 'IR',
			'state'      => $state,
			'city'       => $city,
			'address_1'  => $address,
			'postcode'   => $postcode,
		);
		$order->set_address( $addr_data, 'billing' );
		$order->set_address( $addr_data, 'shipping' );

		if ( '' !== $email ) {
			$existing_user = get_user_by( 'email', $email );
			if ( $existing_user ) {
				$order->set_customer_id( $existing_user->ID );
			}
		}

		if ( $shipping_cost > 0 || '' !== $shipping_title ) {
			$ship_item = new WC_Order_Item_Shipping();
			$ship_item->set_method_title( '' !== $shipping_title ? $shipping_title : __( 'هزینه ارسال', 'sefareshban' ) );
			$ship_item->set_total( $shipping_cost );
			$order->add_item( $ship_item );
		}

		if ( $discount_amount > 0 ) {
			$fee_item = new WC_Order_Item_Fee();
			$fee_item->set_name( __( 'تخفیف دستی', 'sefareshban' ) );
			$fee_item->set_amount( -$discount_amount );
			$fee_item->set_total( -$discount_amount );
			$fee_item->set_tax_status( 'none' );
			$order->add_item( $fee_item );
		}

		if ( '' !== $payment_method ) {
			$order->set_payment_method( $payment_method );
			if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
				$gws = WC()->payment_gateways()->payment_gateways();
				if ( isset( $gws[ $payment_method ] ) ) {
					$order->set_payment_method_title( $gws[ $payment_method ]->get_title() );
				}
			}
		}

		if ( '' !== $customer_note ) {
			$order->set_customer_note( $customer_note );
		}

		$order->set_created_via( 'admin' );
		$order->calculate_totals( false );
		$order->set_status( $wc_status, __( 'ثبت سریع سفارش از داخل سفارش‌بان', 'sefareshban' ), true );
		$order->save();

		wp_send_json_success(
			array(
				'order' => $this->order_to_detail( wc_get_order( $order->get_id() ) ),
			)
		);
	}

	public function sefareshban_create_refund() {
		$this->verify();

		$id      = absint( $_POST['order_id'] ?? 0 );
		$amount  = (float) $this->normalize_digits( wp_unslash( $_POST['amount'] ?? '0' ) );
		$reason  = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
		$restock = ! empty( $_POST['restock'] ) && '0' !== (string) $_POST['restock'];

		if ( ! $id || $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'مبلغ استرداد باید بیشتر از صفر باشد.', 'sefareshban' ) ), 400 );
		}

		$order = wc_get_order( $id );
		if ( ! $this->is_real_order( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'سفارش یافت نشد.', 'sefareshban' ) ), 404 );
		}

		$max_refundable = (float) $order->get_remaining_refund_amount();
		if ( $amount > $max_refundable + 0.01 ) {
			wp_send_json_error( array( 'message' => __( 'مبلغ واردشده بیشتر از سقف قابل استرداد سفارش است.', 'sefareshban' ) ), 400 );
		}

		$line_items = array();
		if ( $restock && abs( $amount - $max_refundable ) < 1 ) {
			foreach ( $order->get_items() as $item_id => $item ) {
				$refunded_qty = abs( (int) $order->get_qty_refunded_for_item( $item_id ) );
				$rem_qty      = max( 0, (int) $item->get_quantity() - $refunded_qty );
				if ( $rem_qty > 0 ) {
					$line_items[ $item_id ] = array(
						'qty'          => $rem_qty,
						'refund_total' => 0,
						'refund_tax'   => array(),
					);
				}
			}
		}

		$refund = wc_create_refund(
			array(
				'amount'         => min( $amount, $max_refundable ),
				'reason'         => $reason,
				'order_id'       => $id,
				'line_items'     => $line_items,
				'refund_payment' => false,
				'restock_items'  => $restock,
			)
		);

		if ( is_wp_error( $refund ) ) {
			wp_send_json_error( array( 'message' => $refund->get_error_message() ), 400 );
		}

		$note_txt = sprintf(
			/* translators: 1: refund amount, 2: optional reason */
			__( 'استرداد وجه به مبلغ %1$s ثبت شد%2$s', 'sefareshban' ),
			number_format_i18n( $amount ),
			'' !== $reason ? ' (' . $reason . ')' : ''
		);
		$order->add_order_note( $note_txt, 0, true );

		wp_send_json_success(
			array(
				'order' => $this->order_to_detail( wc_get_order( $id ) ),
			)
		);
	}

	private function restore_order_item( $order ) {
		try {
			$data_store = WC_Data_Store::load( 'order' );
			if ( $data_store && is_callable( array( $data_store, 'untrash_order' ) ) ) {
				$data_store->untrash_order( $order );
				return true;
			}
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}

		if ( ! $this->is_hpos_enabled() && function_exists( 'wp_untrash_post' ) ) {
			wp_untrash_post( $order->get_id() );
		}

		$reloaded = wc_get_order( $order->get_id() );
		if ( $reloaded && 'trash' === $reloaded->get_status() ) {
			$prev = (string) $reloaded->get_meta( '_wp_trash_meta_status', true );
			$prev = preg_replace( '/^wc-/', '', $prev );
			$reloaded->set_status( '' !== $prev ? $prev : 'on-hold' );
			$reloaded->save();
		}
		return true;
	}

	public function sefareshban_bulk_action() {
		$this->verify();
		$bulk = sanitize_text_field( wp_unslash( $_POST['bulk'] ?? '' ) );
		$ids  = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		$ids  = array_filter( $ids );
		if ( empty( $ids ) || ! $bulk ) {
			wp_send_json_error( array( 'message' => __( 'درخواست نامعتبر است.', 'sefareshban' ) ), 400 );
		}

		$updated = 0;
		if ( 'status' === $bulk ) {
			$status    = sanitize_text_field( wp_unslash( $_POST['status'] ?? 'processing' ) );
			$wc_status = $this->wc_status( $status );
			$allowed   = array_keys( wc_get_order_statuses() );
			if ( ! in_array( 'wc-' . $wc_status, $allowed, true ) ) {
				wp_send_json_error( array( 'message' => __( 'وضعیت نامعتبر است.', 'sefareshban' ) ), 400 );
			}
			foreach ( $ids as $id ) {
				$order = wc_get_order( $id );
				if ( $this->is_real_order( $order ) ) {
					if ( 'trash' === $order->get_status() ) {
						$this->restore_order_item( $order );
						$order = wc_get_order( $id );
					}
					$order->update_status( $wc_status, __( 'تغییر گروهی وضعیت از طریق سفارش‌بان', 'sefareshban' ), true );
					$updated++;
				}
			}
		} elseif ( 'trash' === $bulk ) {
			foreach ( $ids as $id ) {
				$order = wc_get_order( $id );
				if ( $this->is_real_order( $order ) ) {
					$order->delete( false );
					$updated++;
				}
			}
		} elseif ( 'restore' === $bulk ) {
			foreach ( $ids as $id ) {
				$order = wc_get_order( $id );
				if ( $this->is_real_order( $order ) ) {
					$this->restore_order_item( $order );
					$updated++;
				}
			}
		} elseif ( 'delete_permanently' === $bulk ) {
			foreach ( $ids as $id ) {
				$order = wc_get_order( $id );
				if ( $this->is_real_order( $order ) ) {
					$order->delete( true );
					$updated++;
				}
			}
		}

		wp_send_json_success( array( 'updated' => $updated ) );
	}

	private function escape_csv_cell( $val ) {
		$str = (string) $val;
		if ( '' !== $str && in_array( $str[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $str;
		}
		return $str;
	}

	public function sefareshban_export_csv() {
		$this->verify();

		$raw_params      = wp_unslash( $_POST );
		$export_format   = sanitize_text_field( $raw_params['export_format'] ?? 'standard' );
		$selected_cols   = isset( $raw_params['columns'] ) && is_array( $raw_params['columns'] ) ? array_map( 'sanitize_text_field', $raw_params['columns'] ) : array();
		$args            = $this->build_orders_query_args( $raw_params );
		$args['limit']   = 5000;
		$args['orderby'] = 'date';
		$args['order']   = 'DESC';

		if ( ! empty( $raw_params['ids'] ) && is_array( $raw_params['ids'] ) ) {
			$sel_ids = array_values( array_filter( array_map( 'absint', $raw_params['ids'] ) ) );
			if ( ! empty( $sel_ids ) ) {
				$this->intersect_order_ids( $args, $sel_ids );
			}
		}

		$orders = wc_get_orders( $args );
		$rows   = array();
		$count  = 0;

		if ( 'postal' === $export_format ) {
			// Format optimized for Iranian Postal / Logistics Panels (Tapin, Postex, Padro, Tipax)
			$rows[] = array(
				'شماره سفارش',
				'نام و نام خانوادگی گیرنده',
				'شماره موبایل',
				'استان',
				'شهر',
				'آدرس کامل گیرنده',
				'کد پستی',
				'نوع پرداخت',
				'مبلغ وصولی در محل (COD)',
				'ارزش کل سفارش',
				'تعداد اقلام',
				'شرح محتویات مرسوله',
				'توضیحات خریدار',
				'روش ارسال انتخابی',
				'کد رهگیری فعلی',
			);

			foreach ( $orders as $order ) {
				if ( ! $this->is_real_order( $order ) ) {
					continue;
				}
				$count++;
				$s_country = $order->get_shipping_country() ?: $order->get_billing_country();
				$state_raw = $order->get_shipping_state() ?: $order->get_billing_state();
				$state_lbl = $this->state_name( $s_country, $state_raw );
				$city_lbl  = $order->get_shipping_city() ?: $order->get_billing_city();
				$addr1     = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
				$addr2     = $order->get_shipping_address_2() ?: $order->get_billing_address_2();
				$full_addr = implode( '، ', array_filter( array( $state_lbl, $city_lbl, $addr1, $addr2 ) ) );
				$postcode  = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
				$recv_name = trim( $order->get_formatted_shipping_full_name() ) ?: ( trim( $order->get_formatted_billing_full_name() ) ?: __( 'مهمان', 'sefareshban' ) );

				$qty        = 0;
				$item_names = array();
				foreach ( $order->get_items() as $item ) {
					$iq           = (int) $item->get_quantity();
					$qty         += $iq;
					$item_names[] = $item->get_name() . ' (' . $iq . ')';
				}

				$is_cod   = ( 'cod' === strtolower( (string) $order->get_payment_method() ) );
				$cod_amt  = $is_cod ? (float) $order->get_total() : 0;
				$tracking = $this->get_order_tracking( $order );

				$rows[] = array(
					$order->get_order_number(),
					$this->escape_csv_cell( $recv_name ),
					$this->escape_csv_cell( $order->get_billing_phone() ),
					$this->escape_csv_cell( $state_lbl ),
					$this->escape_csv_cell( $city_lbl ),
					$this->escape_csv_cell( $full_addr ),
					$this->escape_csv_cell( $postcode ),
					$is_cod ? 'پرداخت در محل (پس‌کرایه)' : 'پرداخت آنلاین (پیش‌کرایه)',
					$cod_amt,
					$order->get_total(),
					$qty,
					$this->escape_csv_cell( implode( ' | ', $item_names ) ),
					$this->escape_csv_cell( $order->get_customer_note() ),
					$this->escape_csv_cell( $order->get_shipping_method() ),
					$this->escape_csv_cell( $tracking['code'] ),
				);
			}
		} else {
			$col_defs = array(
				'order_number'  => 'شماره سفارش',
				'name'          => 'مشتری',
				'phone'         => 'تلفن',
				'email'         => 'ایمیل',
				'state'         => 'استان',
				'city'          => 'شهر',
				'address'       => 'آدرس',
				'postcode'      => 'کد پستی',
				'date'          => 'تاریخ ثبت',
				'status'        => 'وضعیت',
				'payment'       => 'روش پرداخت',
				'shipping'      => 'روش ارسال',
				'tracking'      => 'کد رهگیری',
				'items'         => 'اقلام سفارش',
				'qty'           => 'تعداد اقلام',
				'shipping_cost' => 'هزینه ارسال',
				'discount'      => 'تخفیف',
				'total'         => 'مبلغ کل',
				'customer_note' => 'یادداشت مشتری',
			);

			if ( empty( $selected_cols ) ) {
				$selected_cols = array( 'order_number', 'name', 'phone', 'email', 'city', 'date', 'status', 'payment', 'qty', 'total' );
			} else {
				$selected_cols = array_values( array_intersect( array_keys( $col_defs ), $selected_cols ) );
				if ( empty( $selected_cols ) ) {
					$selected_cols = array_keys( $col_defs );
				}
			}

			$header = array();
			foreach ( $selected_cols as $ck ) {
				$header[] = $col_defs[ $ck ];
			}
			$rows[] = $header;

			foreach ( $orders as $order ) {
				if ( ! $this->is_real_order( $order ) ) {
					continue;
				}
				$count++;
				$date      = $this->format_date_parts( $order );
				$s_country = $order->get_shipping_country() ?: $order->get_billing_country();
				$state_raw = $order->get_shipping_state() ?: $order->get_billing_state();
				$state_lbl = $this->state_name( $s_country, $state_raw );
				$city_lbl  = $order->get_shipping_city() ?: $order->get_billing_city();
				$addr1     = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
				$addr2     = $order->get_shipping_address_2() ?: $order->get_billing_address_2();
				$full_addr = implode( '، ', array_filter( array( $state_lbl, $city_lbl, $addr1, $addr2 ) ) );
				$postcode  = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
				$tracking  = $this->get_order_tracking( $order );

				$qty        = 0;
				$item_names = array();
				foreach ( $order->get_items() as $item ) {
					$iq           = (int) $item->get_quantity();
					$qty         += $iq;
					$item_names[] = $item->get_name() . ' ×' . $iq;
				}

				$val_map = array(
					'order_number'  => $order->get_order_number(),
					'name'          => $this->escape_csv_cell( trim( $order->get_formatted_billing_full_name() ) ?: __( 'مهمان', 'sefareshban' ) ),
					'phone'         => $this->escape_csv_cell( $order->get_billing_phone() ),
					'email'         => $this->escape_csv_cell( $order->get_billing_email() ),
					'state'         => $this->escape_csv_cell( $state_lbl ),
					'city'          => $this->escape_csv_cell( $city_lbl ),
					'address'       => $this->escape_csv_cell( $full_addr ),
					'postcode'      => $this->escape_csv_cell( $postcode ),
					'date'          => $date['date'] . ' ' . $date['time'],
					'status'        => $this->status_label( $order->get_status() ),
					'payment'       => $this->escape_csv_cell( $order->get_payment_method_title() ),
					'shipping'      => $this->escape_csv_cell( $order->get_shipping_method() ),
					'tracking'      => $this->escape_csv_cell( $tracking['code'] ),
					'items'         => $this->escape_csv_cell( implode( ' | ', $item_names ) ),
					'qty'           => $qty,
					'shipping_cost' => $order->get_shipping_total(),
					'discount'      => $order->get_discount_total(),
					'total'         => $order->get_total(),
					'customer_note' => $this->escape_csv_cell( $order->get_customer_note() ),
				);

				$line = array();
				foreach ( $selected_cols as $ck ) {
					$line[] = $val_map[ $ck ] ?? '';
				}
				$rows[] = $line;
			}
		}

		$fp = fopen( 'php://temp', 'r+' );
		fwrite( $fp, "\xEF\xBB\xBF" );
		foreach ( $rows as $r ) {
			fputcsv( $fp, $r );
		}
		rewind( $fp );
		$csv = stream_get_contents( $fp );
		fclose( $fp );

		$prefix = ( 'postal' === $export_format ) ? 'sefareshban-postal-' : 'sefareshban-orders-';
		wp_send_json_success(
			array(
				'csv'      => base64_encode( $csv ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'filename' => $prefix . gmdate( 'Ymd-His' ) . '.csv',
				'count'    => $count,
			)
		);
	}

	public function sefareshban_get_customers() {
		$this->verify();

		$search   = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		$search   = trim( $this->normalize_digits( $search ) );
		$page     = max( 1, absint( $_POST['page'] ?? 1 ) );
		$per_page = absint( $_POST['per_page'] ?? 20 );
		if ( ! in_array( $per_page, array( 12, 20, 50, 100 ), true ) ) {
			$per_page = 20;
		}

		$orders = wc_get_orders(
			array(
				'type'    => 'shop_order',
				'limit'   => 1000,
				'return'  => 'objects',
				'orderby' => 'date',
				'order'   => 'DESC',
				'status'  => array_keys( wc_get_order_statuses() ),
			)
		);

		$map = array();
		foreach ( $orders as $order ) {
			if ( ! $this->is_real_order( $order ) ) {
				continue;
			}

			$cid   = (int) $order->get_customer_id();
			$email = strtolower( trim( (string) $order->get_billing_email() ) );
			$phone = trim( (string) $order->get_billing_phone() );

			if ( $cid > 0 ) {
				$key = 'u_' . $cid;
			} elseif ( '' !== $email ) {
				$key = 'e_' . $email;
			} elseif ( '' !== $phone ) {
				$key = 'p_' . $phone;
			} else {
				$key = 'g_' . $order->get_id();
			}

			$name = trim( $order->get_formatted_billing_full_name() );
			if ( '' === $name ) {
				$name = trim( $order->get_formatted_shipping_full_name() );
			}
			$city    = $order->get_billing_city() ? $order->get_billing_city() : $order->get_shipping_city();
			$date_pt = $this->format_date_parts( $order );

			if ( ! isset( $map[ $key ] ) ) {
				$map[ $key ] = array(
					'customerId' => $cid,
					'name'       => '' !== $name ? $name : __( 'مهمان', 'sefareshban' ),
					'email'      => $order->get_billing_email(),
					'phone'      => $phone,
					'city'       => $city,
					'orders'     => 0,
					'total'      => 0.0,
					'lastOrder'  => $date_pt['date'],
					'editUrl'    => $cid > 0 ? get_edit_user_link( $cid ) : '',
				);
			}

			$st = $order->get_status();
			if ( ! in_array( $st, array( 'cancelled', 'failed', 'trash' ), true ) ) {
				$map[ $key ]['total'] += (float) $order->get_total();
			}
			$map[ $key ]['orders'] += 1;

			if ( '' === $map[ $key ]['phone'] && '' !== $phone ) {
				$map[ $key ]['phone'] = $phone;
			}
			if ( '' === $map[ $key ]['city'] && '' !== $city ) {
				$map[ $key ]['city'] = $city;
			}
			if ( '' === $map[ $key ]['email'] && '' !== $email ) {
				$map[ $key ]['email'] = $order->get_billing_email();
			}
		}

		if ( count( $map ) < 200 ) {
			$wc_users = get_users(
				array(
					'role__in' => array( 'customer' ),
					'number'   => 200,
					'orderby'  => 'registered',
					'order'    => 'DESC',
				)
			);
			foreach ( $wc_users as $u ) {
				$ukey = 'u_' . $u->ID;
				if ( isset( $map[ $ukey ] ) ) {
					continue;
				}
				$fname = trim( get_user_meta( $u->ID, 'billing_first_name', true ) . ' ' . get_user_meta( $u->ID, 'billing_last_name', true ) );
				if ( '' === $fname ) {
					$fname = $u->display_name;
				}
				$map[ $ukey ] = array(
					'customerId' => $u->ID,
					'name'       => $fname ? $fname : __( 'کاربر', 'sefareshban' ),
					'email'      => $u->user_email,
					'phone'      => (string) get_user_meta( $u->ID, 'billing_phone', true ),
					'city'       => (string) get_user_meta( $u->ID, 'billing_city', true ),
					'orders'     => (int) wc_get_customer_order_count( $u->ID ),
					'total'      => (float) wc_get_customer_total_spent( $u->ID ),
					'lastOrder'  => '—',
					'editUrl'    => get_edit_user_link( $u->ID ),
				);
			}
		}

		$all_rows = array_values( $map );

		if ( '' !== $search ) {
			$pattern  = '/' . preg_quote( $search, '/' ) . '/iu';
			$all_rows = array_values(
				array_filter(
					$all_rows,
					function( $c ) use ( $pattern ) {
						return ( 1 === preg_match( $pattern, (string) $c['name'] ) )
							|| ( 1 === preg_match( $pattern, (string) $c['email'] ) )
							|| ( 1 === preg_match( $pattern, (string) $c['phone'] ) )
							|| ( 1 === preg_match( $pattern, (string) $c['city'] ) );
					}
				)
			);
		}

		usort(
			$all_rows,
			function( $a, $b ) {
				if ( $b['orders'] !== $a['orders'] ) {
					return $b['orders'] <=> $a['orders'];
				}
				return $b['total'] <=> $a['total'];
			}
		);

		$total  = count( $all_rows );
		$offset = ( $page - 1 ) * $per_page;
		$rows   = array_slice( $all_rows, $offset, $per_page );

		wp_send_json_success(
			array(
				'rows'    => $rows,
				'total'   => $total,
				'page'    => $page,
				'perPage' => $per_page,
			)
		);
	}

	public function sefareshban_get_products() {
		$this->verify();

		$search   = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		$search   = trim( $this->normalize_digits( $search ) );
		$page     = max( 1, absint( $_POST['page'] ?? 1 ) );
		$per_page = absint( $_POST['per_page'] ?? 20 );
		if ( ! in_array( $per_page, array( 8, 12, 20, 50, 100 ), true ) ) {
			$per_page = 20;
		}

		$only_published = ! empty( $_POST['only_published'] );

		$args = array(
			'limit'    => $per_page,
			'page'     => $page,
			'paginate' => true,
			'status'   => $only_published ? array( 'publish' ) : array( 'publish', 'draft', 'private', 'pending' ),
			'orderby'  => 'date',
			'order'    => 'DESC',
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$result   = wc_get_products( $args );
		$total    = is_object( $result ) && isset( $result->total ) ? (int) $result->total : 0;
		$products = is_object( $result ) && isset( $result->products ) ? $result->products : array();

		if ( '' !== $search && 1 === $page && function_exists( 'wc_get_product_id_by_sku' ) ) {
			$sku_id = wc_get_product_id_by_sku( $search );
			if ( $sku_id ) {
				$exists = false;
				foreach ( $products as $ep ) {
					if ( $ep->get_id() === (int) $sku_id ) {
						$exists = true;
						break;
					}
				}
				if ( ! $exists ) {
					$sku_prod = wc_get_product( $sku_id );
					if ( $sku_prod ) {
						array_unshift( $products, $sku_prod );
						$total++;
					}
				}
			}
		}

		$rows = array();
		foreach ( $products as $product ) {
			if ( ! is_object( $product ) ) {
				continue;
			}
			$img_id = $product->get_image_id();
			$img    = '';
			if ( $img_id ) {
				$src = wp_get_attachment_image_src( $img_id, 'thumbnail' );
				$img = $src ? $src[0] : '';
			}
			$rows[] = array(
				'id'           => $product->get_id(),
				'name'         => $product->get_name(),
				'sku'          => $product->get_sku(),
				'price'        => (float) $product->get_price(),
				'regularPrice' => (float) $product->get_regular_price(),
				'salePrice'    => '' !== $product->get_sale_price() ? (float) $product->get_sale_price() : null,
				'stock'        => $product->managing_stock() ? (int) $product->get_stock_quantity() : null,
				'stockStatus'  => $product->get_stock_status(),
				'status'       => $product->get_status(),
				'total_sales'  => (int) $product->get_total_sales(),
				'img'          => $img,
				'editUrl'      => get_edit_post_link( $product->get_id(), 'raw' ),
				'viewUrl'      => get_permalink( $product->get_id() ),
			);
		}

		wp_send_json_success(
			array(
				'rows'    => $rows,
				'total'   => $total,
				'page'    => $page,
				'perPage' => $per_page,
			)
		);
	}

	public function sefareshban_get_settings() {
		$this->verify();
		$opts = Sefareshban_Admin::instance()->get_saved_settings();
		wp_send_json_success( array( 'settings' => $opts ) );
	}

	public function sefareshban_save_settings() {
		$this->verify();
		$per_page = (int) ( $_POST['per_page'] ?? 8 );
		if ( ! in_array( $per_page, array( 8, 12, 20, 50 ), true ) ) {
			$per_page = 8;
		}
		$auto_refresh    = ! empty( $_POST['auto_refresh'] ) && '0' !== (string) $_POST['auto_refresh'] ? 1 : 0;
		$sound_alert     = isset( $_POST['sound_alert'] ) ? ( ! empty( $_POST['sound_alert'] ) && '0' !== (string) $_POST['sound_alert'] ? 1 : 0 ) : 1;
		$compact         = ! empty( $_POST['compact'] ) && '0' !== (string) $_POST['compact'] ? 1 : 0;
		$replace_wc_menu = isset( $_POST['replace_wc_menu'] ) ? ( ! empty( $_POST['replace_wc_menu'] ) && '0' !== (string) $_POST['replace_wc_menu'] ? 1 : 0 ) : 1;
		$sender_phone    = sanitize_text_field( wp_unslash( $_POST['sender_phone'] ?? '' ) );
		$sender_address  = sanitize_textarea_field( wp_unslash( $_POST['sender_address'] ?? '' ) );

		$opts = array(
			'per_page'        => $per_page,
			'auto_refresh'    => $auto_refresh,
			'sound_alert'     => $sound_alert,
			'compact'         => $compact,
			'replace_wc_menu' => $replace_wc_menu,
			'sender_phone'    => $sender_phone,
			'sender_address'  => $sender_address,
		);
		update_option( 'sefareshban_settings', $opts );
		wp_send_json_success( array( 'settings' => $opts ) );
	}
}
