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
			'sefareshban_get_stats',
			'sefareshban_update_status',
			'sefareshban_add_note',
			'sefareshban_bulk_action',
			'sefareshban_export_csv',
		);
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, $action ) );
		}
	}

	private function verify() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'unauthorized' ), 403 );
		}
		check_ajax_referer( 'sefareshban_admin', 'nonce' );
	}

	private function normalize_status( $status ) {
		$status = str_replace( 'wc-', '', (string) $status );
		if ( 'on-hold' === $status ) {
			return 'onhold';
		}
		return $status;
	}

	private function wc_status( $status ) {
		$status = str_replace( 'wc-', '', (string) $status );
		if ( 'onhold' === $status ) {
			return 'on-hold';
		}
		return $status;
	}

	private function status_label( $status ) {
		$status = $this->wc_status( $status );
		$statuses = wc_get_order_statuses();
		$key      = 'wc-' . $status;
		if ( isset( $statuses[ $key ] ) ) {
			return wp_strip_all_tags( $statuses[ $key ] );
		}
		return $status;
	}

	private function format_money( $amount ) {
		return (float) $amount;
	}

	private function format_date_parts( $order ) {
		$date = $order->get_date_created();
		if ( ! $date ) {
			return array(
				'iso'  => '',
				'date' => '',
				'time' => '',
			);
		}
		$ts = $date->getTimestamp();
		// Use wp_date to respect site locale/calendar plugins.
		return array(
			'iso'  => gmdate( 'Y-m-d\TH:i:s', $ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ),
			'date' => wp_date( 'j F', $ts ),
			'time' => wp_date( 'H:i', $ts ),
		);
	}

	private function order_to_row( $order ) {
		$date = $this->format_date_parts( $order );
		$status = $this->normalize_status( $order->get_status() );
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'n' => $item->get_name(),
				'q' => (int) $item->get_quantity(),
				'p' => (float) $order->get_item_total( $item, false, false ),
				't' => (float) $order->get_line_total( $item, false, false ),
			);
		}
		$qty = 0;
		foreach ( $items as $it ) {
			$qty += $it['q'];
		}

		return array(
			'id'       => $order->get_id(),
			'name'     => trim( $order->get_formatted_billing_full_name() ) ?: __( 'مهمان', 'sefareshban' ),
			'city'     => $order->get_billing_city(),
			'email'    => $order->get_billing_email(),
			'phone'    => $order->get_billing_phone(),
			'iso'      => $date['iso'],
			'date'     => $date['date'],
			'time'     => $date['time'],
			'status'   => $status,
			'statusLabel' => $this->status_label( $status ),
			'pay'      => $order->get_payment_method_title() ?: '—',
			'channel'  => $order->get_created_via() ?: 'web',
			'qty'      => $qty,
			'total'    => (float) $order->get_total(),
			'currency' => $order->get_currency(),
		);
	}

	private function order_to_detail( $order ) {
		$row = $this->order_to_row( $order );
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$img = '';
			if ( $product ) {
				$img_id = $product->get_image_id();
				if ( $img_id ) {
					$src = wp_get_attachment_image_src( $img_id, 'thumbnail' );
					$img = $src ? $src[0] : '';
				}
			}
			$items[] = array(
				'n'   => $item->get_name(),
				'q'   => (int) $item->get_quantity(),
				'p'   => (float) $order->get_item_total( $item, false, false ),
				't'   => (float) $order->get_line_total( $item, false, false ),
				'img' => $img,
				'c'   => mb_substr( $item->get_name(), 0, 1 ),
			);
		}

		$notes = array();
		$wc_notes = wc_get_order_notes( array( 'order_id' => $order->get_id(), 'orderby' => 'date_created', 'order' => 'DESC' ) );
		foreach ( $wc_notes as $n ) {
			$is_system = $n->added_by !== 'admin' && ( strpos( $n->content, 'وضعیت' ) !== false || $n->added_by === 'system' );
			// Better heuristic: customer notes vs private; WC notes don't have perfect author metadata.
			$by = $n->added_by;
			if ( 'system' === strtolower( (string) $by ) || empty( $by ) ) {
				$by = __( 'سیستم', 'sefareshban' );
				$is_system = true;
			}
			$date_created = $n->date_created;
			$at = $date_created ? wp_date( 'j F — H:i', $date_created->getTimestamp() ) : '';
			$notes[] = array(
				't'   => wp_strip_all_tags( $n->content ),
				'by'  => $by,
				'at'  => $at,
				'sys' => (bool) $is_system,
				'customer' => (bool) $n->customer_note,
			);
		}

		$addr_parts = array_filter( array(
			$order->get_billing_state(),
			$order->get_billing_city(),
			$order->get_billing_address_1(),
			$order->get_billing_address_2(),
		) );
		$shipping_addr = array_filter( array(
			$order->get_shipping_state(),
			$order->get_shipping_city(),
			$order->get_shipping_address_1(),
			$order->get_shipping_address_2(),
		) );
		$addr = ! empty( $shipping_addr ) ? implode( '، ', $shipping_addr ) : implode( '، ', $addr_parts );

		$row['addr']     = $addr;
		$row['items']    = $items;
		$row['ship']     = (float) $order->get_shipping_total();
		$row['disc']     = (float) $order->get_discount_total();
		$row['subtotal'] = (float) $order->get_subtotal();
		$row['notes']    = $notes;
		$edit_url = $order->get_edit_order_url();
		$row['editUrl']  = $edit_url ? add_query_arg( 'sefareshban_native', '1', $edit_url ) : '';
		$row['orderCount'] = $this->get_customer_order_count( $order );
		return $row;
	}

	private function get_customer_order_count( $order ) {
		$customer_id = $order->get_customer_id();
		if ( $customer_id ) {
			$count = wc_get_customer_order_count( $customer_id );
			return (int) $count;
		}
		$email = $order->get_billing_email();
		if ( $email && function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders( array( 'billing_email' => $email, 'return' => 'ids', 'limit' => -1 ) );
			return is_array( $orders ) ? count( $orders ) : 0;
		}
		return 0;
	}

	public function sefareshban_get_stats() {
		$this->verify();

		$now        = current_time( 'timestamp' );
		$today_start = strtotime( 'today', $now );
		$today_end   = strtotime( 'tomorrow', $now ) - 1;
		$yesterday_start = strtotime( 'yesterday', $now );
		$yesterday_end   = $today_start - 1;

		$today_orders = wc_get_orders( array(
			'limit'        => -1,
			'return'       => 'objects',
			'date_created' => $today_start . '...' . $today_end,
			'status'       => array_keys( wc_get_order_statuses() ),
		) );
		$yesterday_orders = wc_get_orders( array(
			'limit'        => -1,
			'return'       => 'objects',
			'date_created' => $yesterday_start . '...' . $yesterday_end,
			'status'       => array_keys( wc_get_order_statuses() ),
		) );

		$today_count = count( $today_orders );
		$yesterday_count = count( $yesterday_orders );
		$today_sales = 0;
		foreach ( $today_orders as $o ) {
			if ( $o->is_paid() ) {
				$today_sales += (float) $o->get_total();
			}
		}
		$yesterday_sales = 0;
		foreach ( $yesterday_orders as $o ) {
			if ( $o->is_paid() ) {
				$yesterday_sales += (float) $o->get_total();
			}
		}

		$pending_statuses = array( 'wc-pending', 'wc-on-hold', 'wc-processing' );
		$pending_orders = wc_get_orders( array(
			'limit'  => -1,
			'return' => 'objects',
			'status' => $pending_statuses,
		) );
		$pending_count = count( $pending_orders );
		$urgent_count  = 0;
		foreach ( $pending_orders as $o ) {
			$created = $o->get_date_created();
			if ( $created && ( $now - $created->getTimestamp() ) > DAY_IN_SECONDS ) {
				$urgent_count++;
			}
		}

		$days30_start = strtotime( '-29 days', $today_start );
		$orders30 = wc_get_orders( array(
			'limit'        => -1,
			'return'       => 'objects',
			'date_created' => $days30_start . '...' . $today_end,
			'status'       => array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending' ),
		) );
		$total30 = 0;
		foreach ( $orders30 as $o ) {
			$total30 += (float) $o->get_total();
		}
		$aov30 = count( $orders30 ) ? $total30 / count( $orders30 ) : 0;

		// 7-day series.
		$series7 = array();
		$pending_series7 = array();
		$pending_statuses_set = array( 'pending', 'on-hold', 'processing' );
		for ( $i = 6; $i >= 0; $i-- ) {
			$day_start = strtotime( '-' . $i . ' days', $today_start );
			$day_end   = strtotime( '+1 day', $day_start ) - 1;
			$day_orders = wc_get_orders( array(
				'limit'        => -1,
				'return'       => 'objects',
				'date_created' => $day_start . '...' . $day_end,
				'status'       => array_keys( wc_get_order_statuses() ),
			) );
			$sales = 0;
			$pending_count_day = 0;
			foreach ( $day_orders as $o ) {
				if ( $o->is_paid() ) {
					$sales += (float) $o->get_total();
				}
				if ( in_array( $o->get_status(), $pending_statuses_set, true ) ) {
					$pending_count_day++;
				}
			}
			$label = wp_date( 'D', $day_start );
			$series7[] = array(
				'label' => $label,
				'count' => count( $day_orders ),
				'sales' => $sales,
			);
			$pending_series7[] = array(
				'label' => $label,
				'count' => $pending_count_day,
			);
		}

		// AOV series (8 buckets over 30 days, ~4 days each) for simplicity.
		$aov_series = array();
		for ( $i = 7; $i >= 0; $i-- ) {
			$bucket_end = strtotime( '-' . ( $i * 4 ) . ' days', $today_end );
			$bucket_start = strtotime( '-3 days', strtotime( 'today', $bucket_end ) );
			if ( $bucket_start < $days30_start ) {
				$bucket_start = $days30_start;
			}
			$b_orders = wc_get_orders( array(
				'limit'        => -1,
				'return'       => 'objects',
				'date_created' => $bucket_start . '...' . $bucket_end,
				'status'       => array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending' ),
			) );
			$b_total = 0;
			foreach ( $b_orders as $o ) {
				$b_total += (float) $o->get_total();
			}
			$aov_series[] = array(
				'label' => wp_date( 'j M', $bucket_end ),
				'value' => count( $b_orders ) ? $b_total / count( $b_orders ) : 0,
			);
		}

		wp_send_json_success( array(
			'todayCount'      => $today_count,
			'yesterdayCount'  => $yesterday_count,
			'todaySales'      => $today_sales,
			'yesterdaySales'  => $yesterday_sales,
			'pendingCount'    => $pending_count,
			'urgentCount'     => $urgent_count,
			'aov30'           => $aov30,
			'series7'         => $series7,
			'pendingSeries7'  => $pending_series7,
			'aovSeries'       => $aov_series,
		) );
	}

	public function sefareshban_get_orders() {
		$this->verify();

		$page     = max( 1, absint( $_POST['page'] ?? 1 ) );
		$per_page = absint( $_POST['per_page'] ?? 20 );
		if ( ! in_array( $per_page, array( 8, 12, 20, 50, 100 ), true ) ) {
			$per_page = 20;
		}
		$status = sanitize_text_field( wp_unslash( $_POST['status'] ?? 'all' ) );
		$search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );
		$sort_key = sanitize_text_field( wp_unslash( $_POST['sort_key'] ?? 'date' ) );
		$sort_dir = sanitize_text_field( wp_unslash( $_POST['sort_dir'] ?? 'desc' ) );

		$args = array(
			'limit'   => $per_page,
			'paged'   => $page,
			'return'  => 'objects',
			'type'    => 'shop_order',
		);

		// Status filter.
		if ( $status && 'all' !== $status ) {
			$args['status'] = $this->wc_status( $status );
		}

		// Sort.
		$orderby = 'date';
		if ( 'total' === $sort_key ) {
			$orderby = 'total';
		} elseif ( 'name' === $sort_key ) {
			// WC doesn't natively sort by billing name well; we'll fallback to date and sort in-memory for the page? Better keep simple: date.
			$orderby = 'date';
		} elseif ( 'date' === $sort_key ) {
			$orderby = 'date';
		}
		$args['orderby'] = $orderby;
		$args['order']   = ( 'asc' === strtolower( $sort_dir ) ) ? 'ASC' : 'DESC';

		// Search.
		if ( $search !== '' ) {
			// If numeric, try order ID.
			if ( ctype_digit( $search ) ) {
				$args['includes'] = array( absint( $search ) );
			} else {
				// wc_get_orders supports 's' in newer WC; also try 'search'.
				$args['search'] = '*' . $search . '*';
			}
		}

		$orders = wc_get_orders( $args );

		// Get total count. wc_get_orders with paginate gives total.
		$count_args = $args;
		$count_args['paginate'] = true;
		$count_args['limit']    = $per_page;
		$count_args['paged']    = $page;
		$result = wc_get_orders( $count_args );
		$total  = is_object( $result ) && isset( $result->total ) ? (int) $result->total : count( $orders );
		$orders = is_object( $result ) && isset( $result->orders ) ? $result->orders : $orders;

		$rows = array();
		foreach ( $orders as $order ) {
			$rows[] = $this->order_to_row( $order );
		}

		// If sort by name requested, sort in-memory for current page (acceptable).
		if ( 'name' === $sort_key ) {
			usort( $rows, function( $a, $b ) use ( $sort_dir ) {
				$cmp = strcmp( $a['name'], $b['name'] );
				return ( 'asc' === strtolower( $sort_dir ) ) ? $cmp : -$cmp;
			} );
		}

		// Status counts for chips.
		$status_counts = array( 'all' => 0 );
		$all_statuses  = wc_get_order_statuses();
		foreach ( $all_statuses as $k => $label ) {
			$slug = $this->normalize_status( $k );
			$status_counts[ $slug ] = 0;
		}
		// Count per status (cheap enough with wc_orders_count if available).
		$total_all = 0;
		foreach ( $all_statuses as $k => $label ) {
			$slug = $this->normalize_status( $k );
			$wc_slug = $this->wc_status( $slug );
			$count = 0;
			if ( function_exists( 'wc_orders_count' ) ) {
				$count = (int) wc_orders_count( $wc_slug );
			} else {
				$count = (int) wp_count_posts( 'shop_order' )->{"wc-$wc_slug"} ?? 0;
			}
			$status_counts[ $slug ] = $count;
			$total_all += $count;
		}
		$status_counts['all'] = $total_all;

		wp_send_json_success( array(
			'rows'         => $rows,
			'total'        => $total,
			'page'         => $page,
			'perPage'      => $per_page,
			'statusCounts' => $status_counts,
		) );
	}

	public function sefareshban_get_order() {
		$this->verify();
		$id = absint( $_POST['order_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'invalid_id' ), 400 );
		}
		$order = wc_get_order( $id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'not_found' ), 404 );
		}
		wp_send_json_success( array( 'order' => $this->order_to_detail( $order ) ) );
	}

	public function sefareshban_update_status() {
		$this->verify();
		$id     = absint( $_POST['order_id'] ?? 0 );
		$status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );
		if ( ! $id || ! $status ) {
			wp_send_json_error( array( 'message' => 'invalid' ), 400 );
		}
		$order = wc_get_order( $id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'not_found' ), 404 );
		}
		$wc_status = $this->wc_status( $status );
		$allowed = array_keys( wc_get_order_statuses() );
		if ( ! in_array( 'wc-' . $wc_status, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => 'invalid_status' ), 400 );
		}
		$order->update_status( $wc_status, __( 'تغییر وضعیت از طریق سفارش‌بان', 'sefareshban' ), true );
		wp_send_json_success( array(
			'order' => $this->order_to_detail( wc_get_order( $id ) ),
		) );
	}

	public function sefareshban_add_note() {
		$this->verify();
		$id   = absint( $_POST['order_id'] ?? 0 );
		$note = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
		if ( ! $id || $note === '' ) {
			wp_send_json_error( array( 'message' => 'invalid' ), 400 );
		}
		$order = wc_get_order( $id );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'not_found' ), 404 );
		}
		$current_user = wp_get_current_user();
		$author = $current_user ? $current_user->display_name : __( 'مدیر', 'sefareshban' );
		wc_create_order_note( $id, $note, 0, true );
		// wc_create_order_note doesn't set added_by nicely for our display; but good enough.
		wp_send_json_success( array(
			'order' => $this->order_to_detail( wc_get_order( $id ) ),
		) );
	}

	public function sefareshban_bulk_action() {
		$this->verify();
		$bulk = sanitize_text_field( wp_unslash( $_POST['bulk'] ?? '' ) );
		$ids  = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		$ids  = array_filter( $ids );
		if ( empty( $ids ) || ! $bulk ) {
			wp_send_json_error( array( 'message' => 'invalid' ), 400 );
		}

		$updated = 0;
		if ( 'status' === $bulk ) {
			$status = sanitize_text_field( wp_unslash( $_POST['status'] ?? 'processing' ) );
			$wc_status = $this->wc_status( $status );
			foreach ( $ids as $id ) {
				$order = wc_get_order( $id );
				if ( $order ) {
					$order->update_status( $wc_status, __( 'تغییر گروهی وضعیت از طریق سفارش‌بان', 'sefareshban' ), true );
					$updated++;
				}
			}
		} elseif ( 'trash' === $bulk ) {
			foreach ( $ids as $id ) {
				$order = wc_get_order( $id );
				if ( $order ) {
					$order->delete( false ); // trash
					$updated++;
				}
			}
		}

		wp_send_json_success( array( 'updated' => $updated ) );
	}

	public function sefareshban_export_csv() {
		$this->verify();
		$status = sanitize_text_field( wp_unslash( $_POST['status'] ?? 'all' ) );
		$search = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );

		$args = array(
			'limit'  => -1,
			'return' => 'objects',
			'type'   => 'shop_order',
			'orderby' => 'date',
			'order'   => 'DESC',
		);
		if ( $status && 'all' !== $status ) {
			$args['status'] = $this->wc_status( $status );
		}
		if ( $search !== '' ) {
			if ( ctype_digit( $search ) ) {
				$args['includes'] = array( absint( $search ) );
			} else {
				$args['search'] = '*' . $search . '*';
			}
		}
		$orders = wc_get_orders( $args );

		$rows = array();
		$rows[] = array( 'شماره', 'مشتری', 'ایمیل', 'تلفن', 'شهر', 'تاریخ', 'وضعیت', 'روش پرداخت', 'تعداد اقلام', 'مبلغ' );
		foreach ( $orders as $order ) {
			$date = $this->format_date_parts( $order );
			$qty = 0;
			foreach ( $order->get_items() as $item ) {
				$qty += (int) $item->get_quantity();
			}
			$rows[] = array(
				$order->get_id(),
				trim( $order->get_formatted_billing_full_name() ),
				$order->get_billing_email(),
				$order->get_billing_phone(),
				$order->get_billing_city(),
				$date['date'] . ' ' . $date['time'],
				$this->status_label( $order->get_status() ),
				$order->get_payment_method_title(),
				$qty,
				$order->get_total(),
			);
		}

		$fp = fopen( 'php://temp', 'r+' );
		// BOM for Excel UTF-8
		fwrite( $fp, "\xEF\xBB\xBF" );
		foreach ( $rows as $r ) {
			fputcsv( $fp, $r );
		}
		rewind( $fp );
		$csv = stream_get_contents( $fp );
		fclose( $fp );

		wp_send_json_success( array(
			'csv'      => base64_encode( $csv ),
			'filename' => 'woo-orders-' . gmdate( 'Ymd-His' ) . '.csv',
			'count'    => count( $orders ),
		) );
	}
}
