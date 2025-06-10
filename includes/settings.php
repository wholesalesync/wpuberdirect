<?php
/**
 * Settings for Uber shipping.
 */

defined( 'ABSPATH' ) || exit;

$settings = array(
	'title'      => [
		'title'       => __( 'Method title', 'wc-uber' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'wc-uber' ),
		'default'     => __( 'Uber Direct', 'wc-uber' ),
		'desc_tip'    => true
	],
	'add_fees'      => [
		'title'       => __( 'Additional Fees ($)', 'wc-uber' ),
		'type'        => 'number',
		'description' => __( 'If you set 10, $10 will be added to Uber rate.', 'wc-uber' ),
		'desc_tip'    => true,
		'default' 	  => 0,
		'custom_attributes' => [
			'min'	  => 0,
			'step' 	  => 0.01
		]
	],
    'free_shipping'      => [
        'title'       => __( 'Free shipping', 'wc-uber' ),
        'type'        => 'checkbox',
        'description' => __( 'Allow free shipping.', 'wc-uber' ),
        'desc_tip'    => true
    ],
    'free_shipping_cart_total'      => [
        'title'       => __( 'Cart total', 'wc-uber' ),
        'type'        => 'number',
        'description' => __( 'Minimum cart total for free shipping.', 'wc-uber' ),
        'desc_tip'    => true,
        'default' 	  => 0
    ]
);

return $settings;
