<?php

use WC_Uber\Uber;

class Uber_Hooks {
	public function __construct() {
		add_action( 'woocommerce_after_shipping_rate', [ $this, 'schedule_shipping' ], 10, 2 );
		add_filter( 'woocommerce_get_sections_shipping', [ $this, 'uber_shipping_tab' ] );
		add_filter( 'woocommerce_get_settings_shipping', [ $this, 'uber_shipping_settings' ], 10, 2 );
		add_filter( 'woocommerce_checkout_fields', [ $this, 'uber_override_checkout_fields' ] );
		add_filter( 'woocommerce_admin_shipping_fields', [ $this, 'uber_admin_override_checkout_fields' ] );
		add_action( 'woocommerce_order_details_after_order_table', [ $this, 'display_tracking_url' ], 10, 1 );
		add_action( 'woocommerce_settings_tabs_shipping', [ $this, 'display_working_days' ] );
		add_action( 'woocommerce_settings_saved', [ $this, 'save_uber_global_settings' ], 100 );
		add_action( 'woocommerce_before_checkout_form', [ $this, 'merchant_schedule' ] );
		add_action( 'woocommerce_new_order', [ $this, 'save_schedule_datetime' ], 10, 1 );
		add_action( 'woocommerce_after_checkout_validation', [
			$this,
			'woocommerce_after_checkout_validation'
		], 10, 2 );
	}

	public function woocommerce_after_checkout_validation( $data, $errors ) {
		if ( strpos( $data['shipping_method'][0], 'uber' ) !== false ) {
			// Required fields for Uber delivery
			$required_fields = array(
				'address_1' => 'Street address',
				'city' => 'City',
				'state' => 'State',
				'postcode' => 'ZIP code'
			);
			
			foreach ( $required_fields as $field => $label ) {
				if ( empty( $data['shipping_' . $field] ) ) {
					$errors->add( 'shipping', sprintf( __( '%s is required for Uber delivery.', 'wc-uber' ), $label ) );
				}
			}
		}
	}

	public function schedule_shipping( $method, $index ) {
		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
//        var_dump($chosen_methods);
		if ( $method->get_method_id() === 'uber' && in_array( $method->get_id(), $chosen_methods ) ) {
			if ( WC()->session->get( 'uber_available' ) === 'yes' ) {
				$time_available = $this->time_available();
				$human_date     = WC()->session->get( 'uber_human_date' );
				if ( is_checkout() ): ?>
                    <div class="schedule-block">
                        <span>When To Deliver</span><br>
                        <div class="schedule-switcher">
							<?php if ( $time_available ): ?>
                                <button class="asap<?php if ( ! $human_date ) {
									echo ' active';
								} ?>">ASAP
                                </button>
							<?php endif; ?>
                            <button class="schedule<?php if ( $human_date ) {
								echo ' active';
							} ?>">
								<?php
								if ( $human_date ) {
									echo $human_date;
								} else {
									echo 'Schedule';
								}
								?>
                            </button>
                        </div>
                    </div>
				<?php endif;
			} else {
				$customer        = WC()->session->get( 'customer' );
				$checkout_fields = WC()->checkout->get_checkout_fields();
				$address_fields  = [ 'country', 'address_1', 'address_2', 'city', 'state', 'postcode' ];
				$error           = false;
				foreach ( $address_fields as $address_field ) {
					if ( $checkout_fields['billing'][ 'billing_' . $address_field ]['required'] && empty( $customer[ $address_field ] ) ) {
						$error = true;
						echo '<span class="uber-alert">Please provide address';
						break;
					}
				}
				if ( ! $error ) {
					echo '<span class="uber-alert">Not available to this location</span>';
				}
			}

		}
	}

	public function uber_shipping_tab( $sections ) {
		$sections['uber_global'] = __( 'Uber Direct', 'wc-uber' );

		return $sections;
	}

	public function uber_shipping_settings( $settings, $current_section ) {

		if ( $current_section == 'uber_global' ) {
			$uber_settings   = [];
			$uber_settings[] = [
				'name'  => __( 'Uber Settings', 'wc-uber' ),
				'type'  => 'title',
				'desc'  => __( 'The following options are used to configure Uber API settings.', 'wc-uber' ),
				'id'    => 'uber_heading',
				'class' => 'antondrob'
			];
			$uber_settings[] = [
				'name'    => __( 'Merchant time zone', 'wc-uber' ),
				'id'      => 'uber_merchant_timezone',
				'type'    => 'select',
				'options' => [
					'Time zone in Washington, DC (GMT-4)',
					'Central Daylight Time, Chicago (GMT-5)',
					'Mountain Daylight Time, Denver (GMT-6)',
					'Mountain Standard Time, Phoenix (GMT-7)',
					'Pacific Daylight Time, Los Angeles (GMT-7)',
					'Alaska Daylight Time, Anchorage (GMT-8)',
					'Hawaii-Aleutian Standard Time, Honolulu (GMT-10)'
				],
				'default' => 0
			];
			$uber_settings[] = [
				'name'     => __( 'Merchant phone', 'wc-uber' ),
				'id'       => 'uber_merchant_phone',
				'type'     => 'tel',
				'desc_tip' => __( 'This phone will be passed as pickup phone to Uber request.', 'wc-uber' ),
			];
			$uber_settings[] = [
				'name'     => __( 'Customer ID', 'wc-uber' ),
				'id'       => 'uber_customer_id',
				'type'     => 'text',
				'desc_tip' => __( 'Get Customer ID from Uber developer dashboard.', 'wc-uber' ),
			];
			$uber_settings[] = [
				'name'     => __( 'Client ID', 'wc-uber' ),
				'id'       => 'uber_client_id',
				'type'     => 'text',
				'desc_tip' => __( 'Get Client ID from Uber developer dashboard.', 'wc-uber' ),
			];
			$uber_settings[] = [
				'name'     => __( 'Client secret', 'wc-uber' ),
				'id'       => 'uber_client_secret',
				'type'     => 'text',
				'desc_tip' => __( 'Get Client secret from Uber developer dashboard.', 'wc-uber' ),
			];
			$uber_settings[] = [
				'name'     => __( 'Webhook Signature Secret', 'wc-uber' ),
				'id'       => 'uber_webhook_signature_secret',
				'type'     => 'text',
				'desc_tip' => __( 'Get Webhook Signature Secret from Uber developer dashboard.', 'wc-uber' ),
				'desc'     => __( 'Save the following url <u>' . site_url( 'wp-json/uber/v2/webhook/</u> to Uber Direct Dashboard.' ), 'wc-uber' )
			];
			$uber_settings[] = [
				'name'     => __( 'Age notification image', 'wc-uber' ),
				'id'       => 'uber_age_notification_image',
				'type'     => 'url',
				'default'  => Uber::$notification_image,
				'desc_tip' => __( 'Notification pops up in the cart and checkout when Uber delivery is chosen.', 'wc-uber' ),
			];
			$uber_settings[] = [
				'name'              => __( 'Age notification text', 'wc-uber' ),
				'id'                => 'uber_age_notification',
				'type'              => 'textarea',
				'custom_attributes' => [
					'rows' => 6
				],
				'default'           => Uber::$notification_text,
				'desc_tip'          => __( 'Notification pops up in the cart and checkout when Uber delivery is chosen.', 'wc-uber' ),
			];
			$uber_settings[] = [
				'name'     => __( 'Enable logging', 'wc-uber' ),
				'id'       => 'uber_logging',
				'type'     => 'checkbox',
				'desc_tip' => __( 'If enabled all Uber Direct requests/responses will be recorded in WooCommerce Log system.', 'wc-uber' ),
			];
			$uber_settings[] = [
				'type' => 'sectionend',
				'id'   => 'uber_global'
			];

			return $uber_settings;
		} else {
			return $settings;
		}

	}

	public function uber_override_checkout_fields( $fields ) {
		$fields['shipping']['shipping_phone'] = [
			'label'    => __( 'Phone', 'wc-uber' ),
			'required' => true,
			'class'    => [ 'form-row-wide' ],
			'clear'    => true
		];

		return $fields;
	}


	public function uber_admin_override_checkout_fields( $fields ) {
		$fields['phone'] = [
			'label' => __( 'Phone', 'wc-uber' ),
			'show'  => false,
		];

		return $fields;
	}


	public function display_tracking_url( $order ) {
		if ( $shipping_methods = $order->get_shipping_methods() ) {
			if ( $order->has_shipping_method( 'uber' ) ) {
				$has_urls = false;
				foreach ( $order->get_shipping_methods() as $shipping_method ) {
					if ( $shipping_method->get_meta( 'Delivery tracking_url' ) ) {
						$has_urls = true;
						break;
					}
				}
				if ( $has_urls ): ?>
                    <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                        <tbody>
                        <tr>
                            <th>Delivery tracking urls:</th>
                            <td>
                                <?php foreach ( $shipping_methods as $item_id => $shipping_method ):
                                    if ( $shipping_method->get_method_id() === 'uber' ): ?>
                                        <?php echo '<p><a target="_blank" href="' . esc_url($shipping_method->get_meta( 'Delivery tracking_url' )) . '">' . esc_html($shipping_method->get_meta( 'Delivery tracking_url' )) . '</a></p>'; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        </tbody>
                    </table>
				<?php endif;
			}
		}
	}


	public function display_working_days() {
		if ( isset( $_GET['section'] ) && $_GET['section'] === 'uber_global' ) {
			$days        = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];
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
				'11:59 pm'
			];
			?>
            <table class="form-table">
                <tbody>
				<?php foreach ( $days as $day ): ?>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="uber_production_account_id"><?php echo ucfirst( $day ); ?></label>
                        </th>
                        <td class="forminp forminp-text">
                            <input name="uber_<?php echo $day; ?>_enable" class="uber_day_enable"
                                   data-day="<?php echo $day; ?>" id="uber_<?php echo $day; ?>_enable"
                                   type="checkbox"<?php if ( get_option( 'uber_' . $day . '_enable' ) === 'on' ) {
								echo ' checked';
							} ?>>
                            <div class="working-hours">
								<?php if ( get_option( 'uber_' . $day . '_enable' ) === 'on' ): ?>
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
								<?php endif; ?>
                            </div>
                        </td>
                    </tr>
				<?php endforeach; ?>
                </tbody>
            </table>
			<?php
		}

	}

	public function save_uber_global_settings() {
		if ( isset( $_GET['page'], $_GET['tab'], $_GET['section'] ) && ( $_GET['page'] === 'wc-settings' && $_GET['tab'] === 'shipping' && $_GET['section'] === 'uber_global' ) ) {
			$days        = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];
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
				'11:59 pm'
			];
			$output      = [];
			foreach ( $days as $day ) {
				if ( isset( $_POST[ 'uber_' . $day . '_enable' ] ) && ! empty( $_POST[ 'uber_' . $day . '_enable' ] ) ) {
					update_option( 'uber_' . $day . '_enable', $_POST[ 'uber_' . $day . '_enable' ] );
				} else {
					delete_option( 'uber_' . $day . '_enable' );
				}

				if ( isset( $_POST[ $day . '_hours_start' ] ) ) {
					update_option( $day . '_hours_start', $_POST[ $day . '_hours_start' ] );
				} else {
					delete_option( $day . '_hours_start' );
				}

				if ( isset( $_POST[ $day . '_hours_end' ] ) ) {
					update_option( $day . '_hours_end', $_POST[ $day . '_hours_end' ] );
				} else {
					delete_option( $day . '_hours_end' );
				}
				$output[ $day ] = [
					'start' => get_option( $day . '_hours_start' ),
					'end'   => get_option( $day . '_hours_end' )
				];
			}

			if ( $output ) {
				update_option( 'merchant_schedule', json_encode( $output ) );
			} else {
				delete_option( 'merchant_schedule' );
			}
		}
	}

	public function time_available() {
		$day = strtolower( current_time( 'l' ) );
		if ( get_option( 'uber_' . $day . '_enable' ) !== 'on' ) {
			return false;
		}
		$current_date = current_time( 'd.m.Y' );
		$saved_start  = get_option( $day . '_hours_start' );
		$saved_end    = get_option( $day . '_hours_end' );
		$open_time    = strtotime( $current_date . ' ' . $saved_start );

		if ( $saved_start === '12:00 am' && $saved_start === $saved_end ) {
			$close_time = strtotime( $current_date . ' ' . $saved_end ) + 86400;
		} else {
			$close_time = strtotime( $current_date . ' ' . $saved_end );
		}

		$current_time = strtotime( current_time( 'd.m.Y H:i:s' ) );

		if ( $current_time > $open_time && $current_time < $close_time ) {
			return true;
		} else {
			return false;
		}
	}

	public function merchant_schedule() {
		$day          = strtolower( current_time( 'l' ) );
		$saved_start  = get_option( $day . '_hours_start' );
		$current_date = current_time( 'd.m.Y' );

		$open_time    = [
			'unix'  => strtotime( $current_date . ' ' . $saved_start ),
			'human' => $saved_start
		];
		$current_time = [
			'unix'  => strtotime( current_time( 'd.m.Y H:i:s' ) ),
			'human' => current_time( 'g:i a' )
		];

		if ( $merchant_schedule = get_option( 'merchant_schedule' ) ): ?>
            <script type="text/javascript">
                var merchant_schedule = <?php echo $merchant_schedule; ?>;
                var current_day = '<?php echo $day; ?>';
                var current_time = '<?php echo json_encode( $current_time ); ?>';
                var open_time = '<?php echo json_encode( $open_time ); ?>';
            </script>
		<?php endif;
	}

	public function save_schedule_datetime( $order_id ) {
		if ( is_checkout() ){
            WC()->session->__unset('uber_datetime');
            WC()->session->__unset('uber_human_date');
        }
	}
}

new Uber_Hooks();
