<?php

use WC_Uber\Uber as Uber;

class Uber_Ajax {
	public function __construct() {
		if ( wp_doing_ajax() ) {
			add_action( 'wp_ajax_create_delivery', [ $this, 'create_delivery_handler' ] );

			add_action( 'wp_ajax_cancel_delivery', [ $this, 'cancel_delivery_handler' ] );

			add_action( 'wp_ajax_dash_cancel_delivery', [ $this, 'dash_cancel_delivery_handler' ] );

			add_action( 'wp_ajax_working_hours', [ $this, 'working_hours_handler' ] );

			add_action( 'wp_ajax_get_deliveries', [ $this, 'get_deliveries_handler' ] );

			add_action( 'wp_ajax_uber_schedule', [ $this, 'uber_schedule_handler' ] );
			add_action( 'wp_ajax_nopriv_uber_schedule', [ $this, 'uber_schedule_handler' ] );

			add_action( 'wp_ajax_remove_datetime', [ $this, 'remove_datetime_handler' ] );
			add_action( 'wp_ajax_nopriv_remove_datetime', [ $this, 'remove_datetime_handler' ] );
		}
	}

	/**
	 * @throws Exception
	 */
	public function create_delivery_handler() {
		check_ajax_referer('uber_ajax_nonce', 'security');
		$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
		$item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
		assert($order_id > 0);
		assert($item_id > 0);
		if (!$order_id) {
			wp_send_json([
				'status'  => 'error',
				'message' => 'order_id wasn\'t passed.'
			]);
		}
		if (!$item_id) {
			wp_send_json([
				'status'  => 'error',
				'message' => 'item_id wasn\'t passed.'
			]);
		}
		$merchant_timezone = get_option( 'uber_merchant_timezone' );
		if ( $merchant_timezone === false ) {
			wp_send_json( [
				'status'  => 'error',
				'message' => 'Specify Merchant time zone in Uber shipping settings.'
			] );
		}
		if ( ! $merchant_phone = get_option( 'uber_merchant_phone' ) ) {
			wp_send_json( [
				'status'  => 'error',
				'message' => 'Merchant Phone is missed in Uber shipping settings.'
			] );
		}
		if ( ! $customer_id = get_option( 'uber_customer_id' ) ) {
			wp_send_json( [
				'status'  => 'error',
				'message' => 'Uber Customer ID is missed in Uber shipping settings.'
			] );
		}

		$timezones   = [
			'America/New_York',
			'America/Chicago',
			'America/Denver',
			'America/Phoenix',
			'America/Los_Angeles',
			'America/Anchorage',
			'America/Adak',
			'Pacific/Honolulu'
		];
		$tz          = new DateTimeZone( $timezones[ $merchant_timezone ] );
		$now         = new DateTime( 'now', $tz );
		$time_offset = $tz->getOffset( $now ) / - 1;
		$timezone    = 'GMT-' . $time_offset / 3600;

		$order          = wc_get_order( $order_id );
		$shipping_items = $order->get_items( [ 'shipping' ] );
		$product_items  = $order->get_items( [ 'line_item' ] );
		if ( is_wp_error( $product_items ) ) {
			wp_send_json([
				'status' => 'error',
				'message' => 'Error retrieving product items.'
			]);
		}
		$item = $shipping_items[ $item_id ];

		$delivery_id = $item->get_meta( 'Delivery id' );

		if ( $_POST['force'] === 'false' && ! empty( $delivery_id ) ) {
			wp_send_json( [
				'status'  => 'success',
				'message' => "Delivery for this order already exists ($delivery_id). Confirm to create one more delivery."
			] );
		}

		$url = 'https://api.uber.com/v1/customers/' . $customer_id . '/deliveries';

		// Pickup address
		$pickup_address = '';
		if ( WC()->countries->get_base_address() ) {
			$pickup_address .= WC()->countries->get_base_address();
		}
		if ( WC()->countries->get_base_address_2() ) {
			$pickup_address .= ' ' . WC()->countries->get_base_address_2();
		}
		if ( WC()->countries->get_base_city() ) {
			$pickup_address .= ' ' . WC()->countries->get_base_city();
		}
		if ( WC()->countries->get_base_state() ) {
			$pickup_address .= ' ' . WC()->countries->get_base_state();
		}
		if ( WC()->countries->get_base_postcode() ) {
			$pickup_address .= ' ' . WC()->countries->get_base_postcode();
		}
		if ( WC()->countries->get_base_country() ) {
			$pickup_address .= ' ' . WC()->countries->get_base_country();
		}

		// Dropoff address
		$dropoff_address = '';
		if ( $order->get_shipping_address_1() ) {
			if ( $order->get_shipping_address_1() ) {
				$dropoff_address .= $order->get_shipping_address_1();
			}
			if ( $order->get_shipping_address_2() ) {
				$dropoff_address .= ' ' . $order->get_shipping_address_2();
			}
			if ( $order->get_shipping_city() ) {
				$dropoff_address .= ' ' . $order->get_shipping_city();
			}
			if ( $order->get_shipping_state() ) {
				$dropoff_address .= ' ' . $order->get_shipping_state();
			}
			if ( $order->get_shipping_postcode() ) {
				$dropoff_address .= ' ' . $order->get_shipping_postcode();
			}
			if ( $order->get_shipping_country() ) {
				$dropoff_address .= ' ' . $order->get_shipping_country();
			}
			$phone = $order->get_meta( '_shipping_phone' );
		} else {
			if ( $order->get_billing_address_1() ) {
				$dropoff_address .= $order->get_billing_address_1();
			}
			if ( $order->get_billing_address_2() ) {
				$dropoff_address .= ' ' . $order->get_billing_address_2();
			}
			if ( $order->get_billing_city() ) {
				$dropoff_address .= ' ' . $order->get_billing_city();
			}
			if ( $order->get_billing_state() ) {
				$dropoff_address .= ' ' . $order->get_billing_state();
			}
			if ( $order->get_billing_postcode() ) {
				$dropoff_address .= ' ' . $order->get_billing_postcode();
			}
			if ( $order->get_billing_country() ) {
				$dropoff_address .= ' ' . $order->get_billing_country();
			}
			$phone = $order->get_billing_phone();
		}

		$phone = $order->get_billing_phone();

		$args_body = [
			'dropoff_address'      => $dropoff_address,
			'dropoff_name'         => $dropoff_address,
			'dropoff_phone_number' => preg_replace( '/[^0-9]/', '', $phone ),
			'pickup_address'       => $pickup_address,
			'pickup_name'          => $pickup_address,
			'pickup_phone_number'  => $merchant_phone,
			'manifest'             => $item->get_meta( 'Items' )
		];

		if ( isset( $_POST['requires_id'] ) ) {
			$args_body['requires_id'] = true;
		}
		if ( isset( $_POST['requires_signature'] ) ) {
			$args_body['requires_dropoff_signature'] = true;
		}

		$args_body['quote_id'] = (int) $item->get_meta( 'Quote id' ) ?: '';

		if ( isset( $_POST['pickup_date'] ) && isset( $_POST['pickup_time'] ) ) {
			$pickup_human_date  = $_POST['pickup_date'] . ' ' . $_POST['pickup_time'];
			$pickup_ready_dt    = date( 'c', strtotime( $pickup_human_date ) + $time_offset );
			$pickup_deadline_dt = date( 'c', strtotime( $pickup_human_date ) + $time_offset + 20 * 60 );

			$args_body['pickup_ready_dt']    = $pickup_ready_dt;
			$args_body['pickup_deadline_dt'] = $pickup_deadline_dt;
		}

		foreach ( $product_items as $pr_item_id => $pr_item ) {
			$args_body['manifest']         .= $pr_item->get_name() . ' x ' . $pr_item->get_quantity() . "\n";
			$args_body['manifest_items'][] = [
				'name'     => $pr_item->get_name(),
				'quantity' => $pr_item->get_quantity(),
				'size'     => 'small'
			];
		}
		if ( $order_notes = $order->get_customer_order_notes() ) {
			$args_body['dropoff_notes'] = $order_notes;
		}

		$args = [
			'timeout' => 10,
			'headers' => [
				'Authorization' => 'Bearer ' . Uber::getToken(),
				'Content-type'  => 'application/json'
			],
			'body'    => json_encode( $args_body )
		];

		Uber::log( $args_body, 'request ', 'create_delivery' );
		$response = wp_remote_post( $url, $args );
		Uber::log( $response, 'response ', 'create_delivery' );
		if ( is_wp_error( $response ) ) {
			wp_send_json( [
				'status'  => 'error',
				'message' => $response->get_error_code() . ' ' . $response->get_error_message()
			] );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( $response['response']['code'] === 200 ) {
			$additional_fees = Uber::FEE;
			$shipping_zones  = WC_Shipping_Zones::get_zones();
			$instance_id     = 0;
			foreach ( $shipping_zones as $zone ) {
				foreach ( $zone['shipping_methods'] as $method ) {
					if ( 'uber' === $method->id ) {
						$instance_id = $method->instance_id;
						break 2;
					}
				}
			}
			if ( $instance_id ) {
				$option_name              = 'woocommerce_uber_' . $instance_id . '_settings';
				$shipping_method_settings = get_option( $option_name );
				if ( isset( $shipping_method_settings['add_fees'] ) ) {
					$additional_fees += $shipping_method_settings['add_fees'] * 100;
				}
			}
			if ( $orders_found = $item->get_meta( 'Deliveries count' ) ) {
				$item->update_meta_data( 'Deliveries count', $orders_found + 1 );
			} else {
				$item->update_meta_data( 'Deliveries count', 1 );
			}
			foreach ( $body as $key => $value ) {
				if ( $key === 'created' ||
				     $key === 'updated' ||
				     $key === 'pickup_ready' ||
				     $key === 'pickup_deadline' ||
				     $key === 'dropoff_ready' ||
				     $key === 'dropoff_deadline' ||
				     $key === 'pickup_eta' ||
				     $key === 'dropoff_eta'
				) {
					$time  = strtotime( $value ) - $time_offset;
					$value = date( 'F j, Y, g:i a', $time ) . ' (' . $timezone . ')';
				} elseif ( $key === 'fee' ) {
					$value = '$' . ( $value + $additional_fees ) / 100;
				} elseif ( $key === 'courier_imminent' ) {
					$value = $value ? 'Yes' : 'No';
				} elseif ( $key === 'live_mode' ) {
					$value = $value ? 'Yes' : 'No';
				} elseif ( $key === 'id' ) {
					update_post_meta( $order_id, 'uber_delivery_id', $value );
				} elseif ( $key === 'currency' ) {
					continue;
				}
				$key = 'Delivery ' . $key;
				$item->update_meta_data( $key, $value );
			}
			$item->save_meta_data();

			if ( $item->get_meta( 'Free shipping' ) === 'Yes' ) {
				$item->set_total( 0 );
			} else {
				$item->set_total( ( $body->fee + $additional_fees ) / 100 );
			}

			$item->calculate_taxes();
			$item->save();
			$order->calculate_totals();
			$order->save();
			// Get HTML to return.
			ob_start();
			include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-items.php';
			$items_html = ob_get_clean();

			ob_start();
			$notes = wc_get_order_notes( [ 'order_id' => $order_id ] );
			include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-notes.php';
			$notes_html = ob_get_clean();
			wp_send_json( [
				'status'      => 'success',
				'html'        => $items_html,
				'notes_html'  => $notes_html,
				'delivery_id' => $item->get_meta( 'Delivery id' ),
				'icon'        => WC_Uber\Uber::$icon
			] );
		} else {
			$args = [
				'status'  => 'error',
				'message' => $body->message ?? $response['body']
			];
			if ( strpos( $body->message, 'manifest' ) !== false ) {
				$args['message'] = 'Please add products to the order.';
			}
			if ( $params = $body->params ) {
				if ( count( (array) $params ) > 2 ) {
					$args['message'] .= '<br>Seems like you haven\'t created an order yet. Please do it before creating delivery.<br>The following parameters are missing:';
				}

				$error_details = '<br>';
				foreach ( (array) $params as $key => $value ) {
					$error_details .= $value . '<br>';
				}
				$args['message'] .= $error_details;
			}
			wp_send_json( $args );
		}
		die;
	}

	public function working_hours_handler() {
		check_ajax_referer('uber_ajax_nonce', 'security');
		$day = isset($_POST['day']) ? sanitize_text_field($_POST['day']) : '';
		$is_checked = isset($_POST['is_checked']) ? $_POST['is_checked'] : '';
		assert(!empty($day));
		if ($is_checked !== 'true') {
			wp_send_json([
				'status' => 'success',
				'html'   => ''
			]);
		}

		$time_format = [
			'12:00 am',
			'12:30 am',
			'1:00 am',
			'1:30 am',
			'2:00 am',
			'2:30 am',
			'3:00 am',
			'3:30 am',
			'4:00 am',
			'4:30 am',
			'5:00 am',
			'5:30 am',
			'6:00 am',
			'6:30 am',
			'7:00 am',
			'7:30 am',
			'8:00 am',
			'8:30 am',
			'9:00 am',
			'9:30 am',
			'10:00 am',
			'10:30 am',
			'11:00 am',
			'11:30 am',
			'12:00 pm',
			'12:30 pm',
			'1:00 pm',
			'1:30 pm',
			'2:00 pm',
			'2:30 pm',
			'3:00 pm',
			'3:30 pm',
			'4:00 pm',
			'4:30 pm',
			'5:00 pm',
			'5:30 pm',
			'6:00 pm',
			'6:30 pm',
			'7:00 pm',
			'7:30 pm',
			'8:00 pm',
			'8:30 pm',
			'9:00 pm',
			'9:30 pm',
			'10:00 pm',
			'10:30 pm',
			'11:00 pm',
			'11:30 pm',
			'12:00 am'
		];

		ob_start();
		?>
        <select name="<?php echo $day; ?>_hours_start" id="<?php echo $day; ?>_hours_start">
			<?php foreach ( $time_format as $time ): ?>
                <option value="<?php echo $time; ?>"<?php if ( get_option( $day . '_hours_start' ) === $time ) {
					echo ' selected';
				} ?>><?php echo $time; ?></option>
			<?php endforeach; ?>
        </select>
        <select name="<?php echo $day; ?>_hours_end" id="<?php echo $day; ?>_hours_end">
			<?php foreach ( $time_format as $time ): ?>
                <option value="<?php echo $time; ?>"<?php if ( get_option( $day . '_hours_end' ) === $time ) {
					echo ' selected';
				} ?>><?php echo $time; ?></option>
			<?php endforeach; ?>
        </select>
		<?php
		$html = ob_get_clean();

		wp_send_json( [
			'status' => 'success',
			'html'   => $html
		] );
	}

	public function uber_schedule_handler() {
		check_ajax_referer('uber_ajax_nonce', 'security');
		$date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
		$time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
		$address_1 = isset($_POST['address_1']) ? sanitize_text_field($_POST['address_1']) : '';
		$address_2 = isset($_POST['address_2']) ? sanitize_text_field($_POST['address_2']) : '';
		$city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
		$state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
		$postcode = isset($_POST['postcode']) ? sanitize_text_field($_POST['postcode']) : '';
		$country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
		assert(!empty($date));
		assert(!empty($time));
		if (!$date) {
			wp_send_json([
				'status'  => 'error',
				'message' => 'date wasn\'t passed.'
			]);
		}
		if (!$time) {
			wp_send_json([
				'status'  => 'error',
				'message' => 'time wasn\'t passed.'
			]);
		}

		$merchant_timezone = get_option( 'uber_merchant_timezone' );
		$timezones         = [
			'America/New_York',
			'America/Chicago',
			'America/Denver',
			'America/Phoenix',
			'America/Los_Angeles',
			'America/Anchorage',
			'America/Adak',
			'Pacific/Honolulu'
		];
		$tz                = new DateTimeZone( $timezones[ $merchant_timezone ] );
		$now               = new DateTime( 'now', $tz );
		$time_offset       = $tz->getOffset( $now ) / - 1;
		$timezone          = 'GMT-' . $time_offset / 3600;

		$human_date  = $date . ' ' . $time;
		$datetime    = date( 'c', strtotime( $human_date ) + $time_offset );
		$customer_id = get_option( 'uber_customer_id' );

		// Pickup address
		$store_address   = WC()->countries->get_base_address();
		$store_address_2 = WC()->countries->get_base_address_2();
		$store_city      = WC()->countries->get_base_city();
		$store_postcode  = WC()->countries->get_base_postcode();
		$store_state     = WC()->countries->get_base_state();
		$store_country   = WC()->countries->get_base_country();
		$pickup_address  = '';
		if ( $store_address ) {
			$pickup_address .= $store_address;
		}
		if ( $store_address_2 ) {
			$pickup_address .= ' ' . $store_address_2;
		}
		if ( $store_city ) {
			$pickup_address .= ' ' . $store_city;
		}
		if ( $store_state ) {
			$pickup_address .= ' ' . $store_state;
		}
		if ( $store_postcode ) {
			$pickup_address .= ' ' . $store_postcode;
		}
		if ( $store_country ) {
			$pickup_address .= ' ' . $store_country;
		}

		// Dropoff address
		$dropoff_address = '';
		if ( $address_1 ) {
			$dropoff_address .= $address_1;
		}
		if ( $address_2 ) {
			$dropoff_address .= ' ' . $address_2;
		}
		if ( $city ) {
			$dropoff_address .= ' ' . $city;
		}
		if ( $state ) {
			$dropoff_address .= ' ' . $state;
		}
		if ( $postcode ) {
			$dropoff_address .= ' ' . $postcode;
		}
		if ( $country ) {
			$dropoff_address .= ' ' . $country;
		}

		$url       = 'https://api.uber.com/v1/customers/' . $customer_id . '/delivery_quotes';
		$args_body = [
			'dropoff_address'     => $dropoff_address,
			'pickup_address'      => $pickup_address,
			'dropoff_deadline_dt' => $datetime
		];
		$args      = [
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bearer ' . Uber::getToken(),
				'Content-type'  => 'application/json'
			],
			'body'    => json_encode( $args_body )
		];
		Uber::log( $args_body, 'request ', 'schedule_quote' );
		$response = wp_remote_post( $url, $args );
		Uber::log( $response['body'], 'response ', 'schedule_quote' );
		if ( is_wp_error( $response ) ) {
			wp_send_json( [
				'status'  => 'error',
				'message' => $response->get_error_code() . ' ' . $response->get_error_message()
			] );
		}

		$body = json_decode( $response['body'] );
		if ( $response['response']['code'] !== 200 ) {
			if ( isset( $body->kind ) && $body->kind === 'error' ) {
				wp_send_json( [
					'status'  => 'error',
					'message' => $body->message
				] );
			} else {
				wp_send_json( [
					'status'  => 'error',
					'message' => $response['response']['message']
				] );
			}

		}

		WC()->session->set( 'uber_datetime', $datetime );
		WC()->session->set( 'uber_human_date', $human_date );
		wp_send_json( [
			'status'     => 'success',
			'human_date' => $human_date
		] );
	}

	public function remove_datetime_handler() {
		check_ajax_referer('uber_ajax_nonce', 'security');
		WC()->session->__unset('uber_datetime');
		WC()->session->__unset('uber_human_date');
	}

	public function cancel_delivery_handler() {
		check_ajax_referer('uber_ajax_nonce', 'security');
		$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
		$delivery_id = isset($_POST['delivery_id']) ? sanitize_text_field($_POST['delivery_id']) : '';
		$item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
		assert($order_id > 0);
		assert(!empty($delivery_id));
		assert($item_id > 0);
		if (!$order_id) {
			wp_send_json([
				'status'  => 'error',
				'message' => 'order_id wasn\'t passed.'
			]);
		}
		if (!$delivery_id) {
			wp_send_json([
				'status'  => 'error',
				'message' => 'delivery_id wasn\'t passed.'
			]);
		}
		if (!$item_id) {
			wp_send_json([
				'status'  => 'error',
				'message' => 'item_id wasn\'t passed.'
			]);
		}
		if ( ! $customer_id = get_option( 'uber_customer_id' ) ) {
			wp_send_json( [
				'status'  => 'error',
				'message' => 'Uber Customer ID is missed in Uber shipping settings.'
			] );
		}

		$url  = 'https://api.uber.com/v1/customers/' . $customer_id . '/deliveries/' . $delivery_id . '/cancel';
		$args = [
			'timeout' => 10,
			'headers' => [
				'Authorization' => 'Bearer ' . Uber::getToken(),
				'Content-type'  => 'application/json'
			],
			'body'    => '{}'
		];
		Uber::log( $url, 'request ', 'cancel_delivery' );
		Uber::log( $args, 'request ', 'cancel_delivery' );
		$response = wp_remote_post( $url, $args );
		Uber::log( $response, 'response ', 'cancel_delivery' );
		if ( is_wp_error( $response ) ) {
			wp_send_json( [
				'status'  => 'error',
				'message' => $response->get_error_code() . ' ' . $response->get_error_message()
			] );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( $response['response']['code'] === 200 ) {
			$item         = new WC_Order_Item_Shipping( $item_id );
			$orders_found = $item->get_meta( 'Deliveries count' );
			if ( $orders_found > 1 ) {
				$item->update_meta_data( 'Deliveries count', $orders_found - 1 );
			} else {
				$item->delete_meta_data( 'Deliveries count', 0 );
			}

			foreach ( $item->get_meta_data() as $meta_data ) {
				if ( strpos( $meta_data->__get( 'key' ), 'Delivery' ) !== false ) {
					$item->delete_meta_data( $meta_data->__get( 'key' ) );
				}
			}
			$order = wc_get_order( $order_id );
			$item->save_meta_data();
			$item->set_total( $body->fee / 100 );
			$item->calculate_taxes();
			$item->save();
			$order->calculate_totals();
			$order->save();
			// Get HTML to return.
			ob_start();
			include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-items.php';
			$items_html = ob_get_clean();

			ob_start();
			$notes = wc_get_order_notes( [ 'order_id' => $order_id ] );
			include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-notes.php';
			$notes_html = ob_get_clean();
			wp_send_json( [
				'status'     => 'success',
				'html'       => $items_html,
				'notes_html' => $notes_html
			] );
		} else {
			$args = [
				'status'  => 'error',
				'message' => $body->message ?? $response['body']
			];
			if ( $params = $body->params ) {
				$error_details = '<br>';
				foreach ( (array) $params as $key => $value ) {
					$error_details .= $value . '<br>';
				}
				$args['message'] .= $error_details;
			}
			wp_send_json( $args );
		}
	}

	public function get_deliveries_handler() {
		check_ajax_referer('uber_ajax_nonce', 'security');
		$customer_id = isset($_POST['customer_id']) ? sanitize_text_field($_POST['customer_id']) : '';
		$filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
		assert(!empty($customer_id));
		assert(!empty($filter));
		if (!$customer_id) {
			wp_send_json_error([
				'message' => 'customer_id was not passed.'
			]);
		}
		if (!$filter) {
			wp_send_json_error([
				'message' => 'filter was not passed.'
			]);
		}

		$query_args = [
			'filter' => $filter,
			'limit'  => 100,
		];

		$url  = 'https://api.uber.com/v1/customers/' . $customer_id . '/deliveries?' . http_build_query( $query_args );
		$args = [
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bearer ' . Uber::getToken(),
				'Content-type'  => 'application/json'
			]
		];

		$response = wp_remote_get( $url, $args );
		$body     = json_decode( $response['body'] );

		ob_start();
		if ( $body->data ) {
			$this->store_address   = WC()->countries->get_base_address();
			$this->store_address_2 = WC()->countries->get_base_address_2();
			$this->store_city      = WC()->countries->get_base_city();
			$this->store_postcode  = WC()->countries->get_base_postcode();
			$this->store_state     = WC()->countries->get_base_state();
			$this->store_country   = WC()->countries->get_base_country();
			$pickup_address        = '';
			if ( $this->store_address ) {
				$pickup_address .= $this->store_address;
			}
			if ( $this->store_address_2 ) {
				$pickup_address .= ' ' . $this->store_address_2;
			}
			if ( $this->store_city ) {
				$pickup_address .= ' ' . $this->store_city;
			}
			if ( $this->store_state ) {
				$pickup_address .= ' ' . $this->store_state;
			}
			if ( $this->store_postcode ) {
				$pickup_address .= ' ' . $this->store_postcode;
			}
			if ( $this->store_country ) {
				$pickup_address .= ' ' . $this->store_country;
			}

			foreach ( $body->data as $delivery ):
				if ( $pickup_address !== $delivery->pickup->name ) {
					continue;
				}
				?>
                <tr>
                    <td>
						<?php
						$allow_cancel = [ 'pending', 'pickup', 'pickup_complete', 'ongoing' ];
						echo $delivery->id;
						if ( in_array( $filter, $allow_cancel ) ) {
							echo '<button class="cancel-delivery button button-primary" data-id="' . $delivery->id . '">Cancel</button>';
						}
						?>
                    </td>
                    <td><?php echo date( 'm/d/Y h:i a', strtotime( $delivery->created ) ); ?></td>
                    <td><?php echo $delivery->pickup->name; ?></td>
                    <td><?php echo $delivery->dropoff->name; ?></td>
                    <td><?php echo "<a target=\"_blank\" href=\"$delivery->tracking_url\">$delivery->tracking_url</a>"; ?></td>
                    <td><?php echo $delivery->status; ?></td>
                    <td><?php echo '$' . number_format( $delivery->fee / 100, 2 ); ?></td>
                </tr>
			<?php endforeach;
		} else {
			echo '<tr><td colspan="7">No relevant deliveries found...</td></tr>';
		}

		$output = ob_get_clean();
		wp_send_json_success( [
			'html' => $output,
			'data' => $body->data
		] );
	}

	public function dash_cancel_delivery_handler() {
		check_ajax_referer('uber_ajax_nonce', 'security');
		$delivery_id = isset($_POST['delivery_id']) ? sanitize_text_field($_POST['delivery_id']) : '';
		assert(!empty($delivery_id));
		if (!$delivery_id) {
			wp_send_json([
				'status'  => 'error',
				'message' => 'delivery_id wasn\'t passed'
			]);
		}

		$customer_id = get_option( 'uber_customer_id' );

		$url  = 'https://api.uber.com/v1/customers/' . $customer_id . '/deliveries/' . $delivery_id . '/cancel';
		$args = [
			'timeout' => 10,
			'headers' => [
				'Authorization' => 'Bearer ' . Uber::getToken(),
				'Content-type'  => 'application/json'
			],
			'body'    => '{}'
		];
		Uber::log( $args, 'request ', 'cancel_delivery' );
		$response = wp_remote_post( $url, $args );
		Uber::log( $args, 'request ', 'cancel_delivery' );
		if ( $response['response']['code'] !== 200 ) {
			wp_send_json( [
				'status'  => 'error',
				'message' => $response['response']['code'] . ': ' . $response['response']['message']
			] );
		}

		$result = self::getOrderIdByOrderItemMeta( $delivery_id );
		if ( $result->order_item_id ) {
			$item         = new WC_Order_Item_Shipping( $result->order_item_id );
			$orders_found = $item->get_meta( 'Deliveries count' );
			if ( $orders_found > 1 ) {
				$item->update_meta_data( 'Deliveries count', $orders_found - 1 );
			} else {
				$item->delete_meta_data( 'Deliveries count', 0 );
			}
			$item->save_meta_data();
			$item->save();
		}

		wp_send_json( [
			'status'      => 'success',
			'delivery_id' => $result
		] );
	}

	private static function getOrderIdByOrderItemMeta( $delivery_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'woocommerce_order_itemmeta';

		return $wpdb->get_row( "SELECT order_item_id FROM $table WHERE meta_key='Delivery id' AND meta_value='$delivery_id'" );
	}

}

new Uber_Ajax();
