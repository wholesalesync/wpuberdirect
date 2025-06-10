<?php
namespace WC_Uber;
require_once plugin_dir_path(__FILE__) . 'includes/class-debug-logger.php';
if (!defined('WINSCODE_DEBUG')) define('WINSCODE_DEBUG', true);
/**
 * Plugin Name:       Uber Direct for WooCommerce
 * Plugin URI:        https://idelivernear.me
 * Description:       Uber Direct is transforming the way goods move around cities by enabling anyone to have anything delivered on-demand.
 * Version:           2.3.5
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            iDelivernear.me
 * Author URI:        https://idelivernear.me
 * Text Domain:       wc-uber
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */


class Uber {
	public $version;
	public $api_settings;
	public $error_notices;
	public static $icon;
	public static $small_icon;
	public static $plugin_url;
	public static $plugin_path;
	public static $notification_image;
	public static $notification_text = 'You must be at least 21 years of age to purchase from our store. A valid ID or passport will be checked upon EACH AND EVERY delivery. If we are unable to complete your order because we cannot verify that you are of legal age, you will be charged a NON-REFUNDABLE 50% restocking fee and will not receive your order. Thank you for your cooperation. If you have any questions please do not hesitate to call us.';

	const FEE = 0;

	public function __construct() {
		$this->version = '2.1';

		self::$icon               = plugins_url( 'assets/img/uber.png', __FILE__ );
		self::$small_icon         = plugins_url( 'assets/img/uber-small.png', __FILE__ );
		self::$plugin_url         = plugin_dir_url( __FILE__ );
		self::$plugin_path        = plugin_dir_path( __FILE__ );
		self::$notification_image = self::$plugin_url . 'assets/img/id-verification.gif';

		add_action( 'plugins_loaded', [ $this, 'init_wc_uber' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'uber_admin_enqueue_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'uber_wp_enqueue_scripts' ] );
		add_action( 'admin_menu', [ $this, 'deliveries_dashboard' ], 100 );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'woocommerce_checkout_order_processed' ], 10, 3 );
		require_once 'includes/class-uber-ajax.php';
		require_once 'includes/class-uber-hooks.php';
		require_once 'includes/class-uber-webhook.php';

		$this->api_settings['merchant_phone']           = [
			'label' => 'Merchant phone',
			'value' => get_option( 'uber_merchant_phone' )
		];
		$this->api_settings['customer_id']              = [
			'label' => 'Customer ID',
			'value' => get_option( 'uber_customer_id' )
		];
		$this->api_settings['client_id']                = [
			'label' => 'Client ID',
			'value' => get_option( 'uber_client_id' )
		];
		$this->api_settings['client_secret']            = [
			'label' => 'Client Secret',
			'value' => get_option( 'uber_client_secret' )
		];
		$this->api_settings['Webhook Signature Secret'] = [
			'label' => 'webhook_signature_secret',
			'value' => get_option( 'uber_webhook_signature_secret' )
		];
	}

	public static function getToken() {
		$key = 'uber_direct_access_token';

		$token = get_transient( $key );

		if ( $token === false ) {
			$client_id     = get_option( 'uber_client_id' );
			$client_secret = get_option( 'uber_client_secret' );
			$url           = 'https://login.uber.com/oauth/v2/token';
			$body_args     = [
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'grant_type'    => 'client_credentials',
				'scope'         => 'eats.deliveries'
			];
			$response      = wp_remote_post( $url, [
				'headers' => [
					'Content-Type' => 'application/x-www-form-urlencoded',
				],
				'body'    => $body_args
			] );
			if ( is_wp_error( $response ) ) {
				return false;
			}
			$body  = json_decode( wp_remote_retrieve_body( $response ), true );
			$token = $body['access_token'];
			if ( is_null( $token ) ) {
				return false;
			}
			set_transient( $key, $token, $body['expires_in'] );
		}

		return $token;
	}

	public function woocommerce_checkout_order_processed( $order_id, $posted_data, $order ) {
		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		if ( count( $chosen_methods ) > 1 ) {
			foreach ( $chosen_methods as $key => $chosen_method ) {
				if ( strpos( $chosen_method, 'uber' ) !== false ) {
					unset( $chosen_methods[ $key ] );
				}
			}
			WC()->session->set( 'chosen_shipping_methods', $chosen_methods );
		}
	}

	public function check_api_settings() {

		foreach ( $this->api_settings as $key => $value ) {
			if ( ! $value['value'] ) {
				$this->error_notices[] = $value['label'];
			}
		}
		if ( $this->error_notices ) {
			add_action( 'admin_notices', [ $this, 'api_settings_notices' ] );
		}

	}

	public function uber_admin_enqueue_scripts() {
		global $post;
		wp_enqueue_script( 'sweetalert2', plugins_url( 'assets/js/sweetalert2.js', __FILE__ ), [ 'jquery' ], $this->version, true );
		wp_enqueue_script( 'timepicker', plugins_url( 'assets/plugins/timepicker/jquery.timepicker.min.js', __FILE__ ), [ 'jquery' ], $this->version, true );
		wp_enqueue_style( 'timepicker', plugins_url( 'assets/plugins/timepicker/jquery.timepicker.min.css', __FILE__ ), [], $this->version );
		wp_enqueue_style( 'uber-admin', plugins_url( 'assets/css/uber-admin.css', __FILE__ ), [], $this->version );
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'uber-deliveries' ) {
			wp_enqueue_script( 'admin-deliveries', plugins_url( 'assets/js/admin-deliveries.js', __FILE__ ), [ 'jquery' ], time(), true );
			wp_localize_script( 'admin-deliveries', 'deliveries', [
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'customer_id' => $this->api_settings['customer_id']['value'],
				'api_key'     => $this->api_settings['api_key']['value'],
				'nonce'       => wp_create_nonce('uber_ajax_nonce')
			] );
		} elseif ( $post ) {
			wp_enqueue_script( 'uber-admin', plugins_url( 'assets/js/uber-admin.js', __FILE__ ), [
				'jquery',
				'sweetalert2',
				'timepicker'
			], $this->version, true );
			wp_localize_script( 'uber-admin', 'uber', [
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'order_id'       => $post->ID,
				'merchant_phone' => $this->api_settings['merchant_phone']['value']
			] );
		}

	}

	public function uber_wp_enqueue_scripts() {
		wp_enqueue_script( 'sweetalert2', plugins_url( 'assets/js/sweetalert2.js', __FILE__ ), [ 'jquery' ], $this->version, true );
		wp_enqueue_script( 'magnific-popup', plugins_url( 'assets/plugins/magnific-popup/jquery.magnific-popup.min.js', __FILE__ ), [ 'jquery' ], $this->version, true );
		wp_enqueue_style( 'magnific-popup', plugins_url( 'assets/plugins/magnific-popup/magnific-popup.css', __FILE__ ), [], $this->version );
		wp_enqueue_script( 'datepicker', plugins_url( 'assets/plugins/datepicker/jquery-ui.js', __FILE__ ), [ 'jquery' ], $this->version, true );
		wp_enqueue_style( 'datepicker', plugins_url( 'assets/plugins/datepicker/jquery-ui.css', __FILE__ ), [], $this->version );
		wp_enqueue_script( 'timepicker', plugins_url( 'assets/plugins/timepicker/jquery.timepicker.min.js', __FILE__ ), [ 'jquery' ], $this->version, true );
		wp_enqueue_style( 'timepicker', plugins_url( 'assets/plugins/timepicker/jquery.timepicker.min.css', __FILE__ ), [], $this->version );

		wp_enqueue_script(
			'uber-frontend',
			plugins_url( 'assets/js/uber-frontend.js', __FILE__ ),
			[ 'jquery', 'magnific-popup', 'datepicker', 'timepicker' ],
			filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/uber-frontend.js' ),
			true
		);
		wp_localize_script( 'uber-frontend', 'uber', [
			'ajax_url'         => admin_url( 'admin-ajax.php' ),
			'verificationImg'  => get_option( 'uber_age_notification_image', self::$notification_image ),
			'verificationText' => get_option( 'uber_age_notification', self::$notification_text ),
			'nonce'            => wp_create_nonce('uber_ajax_nonce')
		] );
		wp_enqueue_style( 'uber-frontend', plugins_url( 'assets/css/uber-frontend.css', __FILE__ ), [], $this->version );
	}

	public function init_wc_uber() {
		if ( class_exists( 'WooCommerce' ) ) {
			add_filter( 'woocommerce_shipping_methods', [ $this, 'uber_shipping_method' ] );
			add_action( 'woocommerce_shipping_init', [ $this, 'uber_shipping_method_init' ] );
			add_action( 'woocommerce_coupon_options', [ $this, 'uber_coupon' ], 10, 2 );
			add_action( 'woocommerce_coupon_options_save', [ $this, 'save_uber_fields' ], 10, 2 );
			$this->check_api_settings();
		} else {
			add_action( 'admin_notices', [ $this, 'uber_notices' ] );
		}

	}

	/**
	 * Save free shipping coupon for Uber
	 */
	public function save_uber_fields( $post_id, $coupon ) {
		if ( isset( $_POST['uber_free_shipping'] ) && $_POST['uber_free_shipping'] === 'yes' ) {
			$coupon->add_meta_data( 'uber_free_shipping', 'yes', true );
		} else {
			$coupon->delete_meta_data( 'uber_free_shipping' );
		}
		$coupon->save();
	}

	/**
	 * Display option free shipping coupon for Uber
	 */
	public function uber_coupon( $coupon_id, $coupon ) {
		woocommerce_wp_checkbox(
			[
				'id'          => 'uber_free_shipping',
				'label'       => __( 'Allow free shipping for Uber', 'wc-uber' ),
				'description' => __( 'Check this box if the coupon grants free shipping.', 'wc-uber' ),
				'value'       => wc_bool_to_string( $coupon->get_meta( 'uber_free_shipping' ) ),
			]
		);

	}

	public function uber_notices() {
		echo '<div class="notice notice-error">
			<p>' . esc_html( __( 'Uber for WooCommerce needs WooCommerce to be active.', 'wc-uber' ) ) . '</p>
		</div>';
	}

	public function api_settings_notices() {
		echo '<div class="notice notice-error">
			<p>' . esc_html( __( 'Following Uber API settings are missing: ', 'wc-uber' ) ) . esc_html( implode( ', ', $this->error_notices ) ) . '</p>
		</div>';
	}

	public function uber_shipping_method( $methods ) {

		$methods['uber'] = 'WC_Uber_Shipping_Method';

		return $methods;

	}

	public function uber_shipping_method_init() {

		require_once( 'includes/class-wc-uber.php' );

	}

	public function deliveries_dashboard() {
		add_submenu_page( 'woocommerce', 'Uber Dashboard', 'Uber Dashboard', 'manage_options', 'uber-deliveries', [
			$this,
			'uber_deliveries_callback'
		] );
	}

	public function uber_deliveries_callback() {
		require_once 'templates/html-uber-deliveries.php';
	}

	public static function log( $data, $prefix = '', $source = '' ) {
		if ( get_option( 'uber_logging' ) === 'yes' ) {
			$logger = wc_get_logger();
			$logger->debug( $prefix . print_r( $data, true ), [ 'source' => 'uber-direct_' . $source ] );
		}
	}
}

new Uber();

require_once plugin_dir_path(__FILE__) . 'includes/class-debug-log-viewer.php';
add_action('admin_menu', ['Winscode_Debug_Log_Viewer', 'admin_menu']);
