<?php
/**
 * Plugin Name:     Cart Link Generator
 * Plugin URI:
 * Description:     This plugin gave you option to generete a cart link for future or refer to any one.
 * Author:          Govind Namdev
 * Author URI:
 * Text Domain:     cart-link
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Cart_Link_Generator
 */

/**
 * Check if WooCommerce is active before proceeding.
 */
function is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Activation hook.
 */
function cart_link_generator_activate() {
	if ( ! is_woocommerce_active() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( 'Cart Link Generator requires WooCommerce to be activated. Plugin deactivated.' );
	}
}
register_activation_hook( __FILE__, 'cart_link_generator_activate' );

/**
 * Deactivation hook.
 */
function cart_link_generator_deactivate() {
	// Clean up tasks if needed.
}
register_deactivation_hook( __FILE__, 'cart_link_generator_deactivate' );

/**
 * Function to encrypt the cart link.
 *
 * @param string $link The link to encrypt.
 * @return string The encrypted link
 */
function encrypt_cart_link( $link ) {
	$encrypted_link = base64_encode( $link );
	return $encrypted_link;
}

/**
 * Function to decrypt the cart link.
 *
 * @param string $link The link to decrypt.
 * @return string The encrypted link
 */
function decrypt_cart_link( $link ) {
	$decrypted_link = base64_decode( $link );
	return $decrypted_link;
}

/**
 * Function to generate the encrypted cart link.
 */
function generate_encrypted_cart_link() {
	$cart_items = WC()->cart->get_cart();
	$cart_url   = wc_get_cart_url();

	// Create an array to store product IDs and quantities.
	$product_data = array();

	foreach ( $cart_items as $cart_item_key => $cart_item ) {
		$product_id = $cart_item['product_id'];
		$quantity   = $cart_item['quantity'];

		// Store product ID and quantity in the array.
		$product_data[] = array(
			'product_id' => $product_id,
			'quantity'   => $quantity,
		);
	}

	// Encode the product data in JSON format.
	$product_data_json = wp_json_encode( $product_data );

	// Add the product data to the cart URL as a query parameter.
	$cart_url = add_query_arg( 'cart_data', encrypt_cart_link( $product_data_json ), $cart_url );

	// Encrypt the cart URL.
	$encrypted_cart_url = esc_url( $cart_url );

	return $encrypted_cart_url;
}

/**
 * Function to send the cart link via email.
 *
 * @param string $user_email The user email.
 * @param string $cart_link The cart link.
 * @return void
 */
function send_cart_link_email( $user_email, $cart_link ) {
	$subject = 'Your Cart Link';
	$message = 'Here is your cart link: ' . $cart_link;
	$headers = 'From: Your Store <noreply@example.com>';

	wp_mail( $user_email, $subject, $message, $headers );
}

/**
 * Function to display the button on the cart page.
 */
function display_generate_link_button() {
	if ( is_cart() ) {
		$generate_cart_link = generate_encrypted_cart_link();
		$cart_url           = wc_get_cart_url();
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$user_email   = $current_user->user_email;

			echo '<a href="' . esc_url( $cart_url ) . '?generate_cart_link=true" class="button">Generate Cart Link and Email</a>';

			if ( isset( $_GET['generate_cart_link'] ) ) {
				send_cart_link_email( $user_email, $generate_cart_link );
				echo '<p>Email sent with cart link.</p>';
			}
		} else {
			echo '<a href="' . esc_url( $generate_cart_link ) . '" class="button">Generate Cart Link</a>';
			echo '<p>You are not logged in. Please copy this link & share it via email.</p>';
		}
	}
}
add_action( 'woocommerce_cart_actions', 'display_generate_link_button' );


/**
 * Function to process the cart_data parameter.
 */
function process_cart_data() {
	if ( isset( $_GET['cart_data'] ) ) {
		// Decrypt the cart data.
		$decrypted_data = decrypt_cart_link( wp_unslash( $_GET['cart_data'] ) );

		// Parse the JSON data.
		$product_data = json_decode( $decrypted_data, true );

		// Loop through the products and add them to the cart.
		foreach ( $product_data as $item ) {
			$product_id = $item['product_id'];
			$quantity   = $item['quantity'];

			WC()->cart->add_to_cart( $product_id, $quantity );
		}

		// Redirect to the checkout page.
		$checkout_url = wc_get_checkout_url();
		wp_safe_redirect( $checkout_url );
		exit();
	}
}
add_action( 'template_redirect', 'process_cart_data' );
