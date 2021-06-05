<?php
/**
 * XML-RPC class containing initialization of XML-RPC methods as well as their callbacks.
 *
 * @link    https://www.foosales.com
 * @since   1.0.0
 * @package foosales
 */

/**
 * The XML-RPC API-specific functionality of the plugin.
 *
 * @link       https://www.foosales.com
 * @since      1.0.0
 * @package    foosales
 */
class FooSales_XMLRPC {
	/**
	 * The FooSales phrases helper.
	 *
	 * @since    1.16.1
	 * @var array $foosales_phrases The current phrases helper array.
	 */
	private $foosales_phrases;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		if ( class_exists( 'FooSales_Config' ) ) {

			$foosales_config = new FooSales_Config();

			require $foosales_config->helper_path . 'foosales-phrases-helper.php';

		} elseif ( file_exists( plugin_dir_path( __FILE__ ) . 'helpers/foosaleswc-phrases-helper.php' ) ) {

			require plugin_dir_path( __FILE__ ) . 'helpers/foosaleswc-phrases-helper.php';

		}

		$this->foosales_phrases = $foosales_phrases;

		add_action( 'admin_notices', array( $this, 'check_xmlrpc_enabled' ) );

	}

	/**
	 * Check whether XML-RPC is enabled.
	 *
	 * @since 1.0.0
	 */
	public function check_xmlrpc_enabled() {

		$xmlrpc_enabled = false;
		$enabled        = get_option( 'enable_xmlrpc' );

		if ( $enabled ) {

			$xmlrpc_enabled = true;

		} else {

			global $wp_version;

			if ( version_compare( $wp_version, '3.5', '>=' ) ) {

				$xmlrpc_enabled = true;

			} else {

				$xmlrpc_enabled = false;

			}
		}

		if ( ! $xmlrpc_enabled ) {

			$this->output_notices( array( esc_html( $this->foosales_phrases['notice_xmlrpc_not_enabled'] ) ) );

		}

	}

	/**
	 * Output admin notices.
	 *
	 * @since 1.0.0
	 * @param array $notices An array of notices to output.
	 */
	private function output_notices( $notices ) {

		foreach ( $notices as $notice ) {

			echo '<div class="updated"><p>' . esc_attr( $notice ) . '</p></div>';

		}

	}
}

/**
 * Test if is valid user with proper user role.
 *
 * @since 1.0.0
 * @global wp_xmlrpc_server $wp_xmlrpc_server
 * @param array $args The arguments received by the XML-RPC request.
 *
 * @return WP_User
 */
function fsfwc_authorize_xmlrpc_user( $args ) {
	global $wp_xmlrpc_server;

	$wp_xmlrpc_server->escape( $args );

	$username = $args[0];
	$password = $args[1];
	$user     = '';

	$user = $wp_xmlrpc_server->login( $username, $password );

	if ( false === $user ) {
		$output['message'] = false;

		echo wp_json_encode( $output );

		exit;
	} else {
		if ( ! fsfwc_checkroles( $user ) ) {
			$output['message']      = false;
			$output['invalid_user'] = '1';

			echo wp_json_encode( $output );

			exit;
		}
	}

	return $user;
}

/**
 * Tests whether or not XML-RPC is accessible.
 *
 * @since 1.0.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_test_access( $args ) {
	echo 'FooSales success';

	exit;
}

/**
 * Checks connection details and if successful, fetches all data.
 *
 * @since 1.0.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_connect_data_fetch( $args ) {
	$user = fsfwc_authorize_xmlrpc_user( $args );

	wp_raise_memory_limit();

	set_time_limit( 0 );

	$output = array( 'message' => true );

	$chunk = $args[2];

	$output['data'] = fsfwc_fetch_chunk( $user, $chunk );

	echo wp_json_encode( $output );

	exit;
}

/**
 * Update product data.
 *
 * @since 1.0.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_update_product( $args ) {
	$user = fsfwc_authorize_xmlrpc_user( $args );

	$response = array( 'status' => 'error' );

	$product_data = json_decode( $args[2], true );

	if ( null === $product_data || false === $product_data ) {

		$success = fsfwc_do_update_product_v1(
			array(
				$args[2],
				$args[3],
				$args[4],
				$args[5],
				$args[6],
			)
		);

		if ( $success ) {

			$response['status'] = 'success';

		}
	} else {

		$result = fsfwc_do_update_product( $product_data );

		$updated_product  = $result['updated_product'];
		$sale_product_ids = $result['sale_product_ids'];

		$response['status']           = 'success';
		$response['product']          = $updated_product;
		$response['sale_product_ids'] = $sale_product_ids;

	}

	echo wp_json_encode( $response );

	exit;
}

/**
 * Create a new order.
 *
 * @since 1.0.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_create_order( $args ) {

	$user = fsfwc_authorize_xmlrpc_user( $args );

	$order_date                  = $args[2];
	$payment_method_key          = $args[3];
	$coupons                     = $args[4];
	$order_items                 = $args[5];
	$order_customer              = $args[6];
	$order_note                  = $args[7];
	$order_note_send_to_customer = $args[8];
	$attendee_details            = $args[9];
	$square_order_id             = $args[10];
	$user_id                     = $args[11];

	$new_order = fsfwc_do_create_new_order(
		array(
			$order_date,
			$payment_method_key,
			$coupons,
			$order_items,
			$order_customer,
			$order_note,
			$order_note_send_to_customer,
			$attendee_details,
			$square_order_id,
			$user_id,
		)
	);

	$response = array(
		'status' => 'success',
		'order'  => fsfwc_do_get_single_order( $new_order ),
	);

	echo wp_json_encode( $response );

	exit;

}

/**
 * Sync offline data.
 *
 * @since 1.0.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_sync_offline_changes( $args ) {
	$user = fsfwc_authorize_xmlrpc_user( $args );

	$offline_changes = json_decode( stripslashes( $args[2] ), true );

	fsfwc_do_sync_offline_changes( $offline_changes );
}

/**
 * Cancels an order, refunds the total and restocks if specified.
 *
 * @since 1.0.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_cancel_order( $args ) {

	$user = fsfwc_authorize_xmlrpc_user( $args );

	$response = array( 'status' => 'error' );

	if ( fsfwc_do_cancel_order( $args[2], (bool) $args[3] ) ) {
		$response['status'] = 'success';
	}

	echo wp_json_encode( $response );

	exit;

}

/**
 * Refunds items of an order and restocks specified quantities.
 *
 * @since 1.0.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_refund_order( $args ) {
	$user = fsfwc_authorize_xmlrpc_user( $args );

	$response = array( 'status' => 'error' );

	$order_id       = $args[2];
	$refunded_items = json_decode( stripslashes( $args[3] ), true );

	$refund_result = fsfwc_do_refund_order( $order_id, $refunded_items );
	$wc_order      = $refund_result['order'];

	$response['status'] = 'success';
	$response['order']  = fsfwc_do_get_single_order( $wc_order );

	if ( ! empty( $refund_result['square_refund'] ) ) {

		$response['square_refund'] = $refund_result['square_refund'];

		if ( ! empty( $refund_result['square_terminal_refund'] ) ) {
			$response['square_terminal_refund'] = $refund_result['square_terminal_refund'];
		}
	}

	echo wp_json_encode( $response );

	exit;

}

/**
 * Create or update a customer.
 *
 * @since 1.0.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_create_update_customer( $args ) {
	$user = fsfwc_authorize_xmlrpc_user( $args );

	$customer_details = json_decode( stripslashes( $args[2] ), true );

	$response = fsfwc_do_create_update_customer( $customer_details );

	echo wp_json_encode( $response );

	exit;
}

/**
 * Get the coupon discount.
 *
 * @since 1.0.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_get_coupon_code_discounts( $args ) {
	$user = fsfwc_authorize_xmlrpc_user( $args );

	$coupons     = json_decode( stripslashes( $args[2] ), true );
	$order_items = json_decode( stripslashes( $args[3] ), true );

	$response = fsfwc_do_get_coupon_code_discounts( $coupons, $order_items );

	echo wp_json_encode( $response );

	exit;
}

/**
 * Get the data updates.
 *
 * @since 1.16.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_get_data_updates( $args ) {
	$user = fsfwc_authorize_xmlrpc_user( $args );

	$last_checked_timestamp = json_decode( stripslashes( $args[2] ), true );

	$response = fsfwc_do_get_data_updates( $last_checked_timestamp );

	echo wp_json_encode( $response );

	exit;
}

/**
 * Generate a Square device code.
 *
 * @since 1.17.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_generate_square_device_code( $args ) {
	$user = fsfwc_authorize_xmlrpc_user( $args );

	$square_location = json_decode( stripslashes( $args[2] ), true );

	$response = fsfwc_do_generate_square_device_code( $square_location );

	echo wp_json_encode( $response );

	exit();
}

/**
 * Create a Square terminal checkout request.
 *
 * @since 1.17.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_create_square_terminal_checkout( $args ) {
	$user = fsfwc_authorize_xmlrpc_user( $args );

	$checkout_data = json_decode( stripslashes( $args[2] ), true );

	$response = fsfwc_do_create_square_terminal_checkout( $checkout_data );

	echo wp_json_encode( $response );

	exit();
}

/**
 * Get a Square terminal checkout status.
 *
 * @since 1.17.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_get_square_terminal_checkout_status( $args ) {
	$user = fsfwc_authorize_xmlrpc_user( $args );

	$checkout_id = $args[2];

	$response = fsfwc_do_get_square_terminal_checkout_status( $checkout_id );

	echo wp_json_encode( $response );

	exit();
}

/**
 * Get the pair status of a Square device.
 *
 * @since 1.17.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_get_square_device_pair_status( $args ) {
	$user = fsfwc_authorize_xmlrpc_user( $args );

	$square_device_id = $args[2];

	$response = fsfwc_do_get_square_device_pair_status( $square_device_id );

	echo wp_json_encode( $response );

	exit();
}

/**
 * Create a Square terminal refund request.
 *
 * @since 1.17.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_create_square_terminal_refund( $args ) {
	$user = fsfwc_authorize_xmlrpc_user( $args );

	$refund_data = json_decode( stripslashes( $args[2] ), true );

	$response = fsfwc_do_create_square_terminal_refund( $refund_data );

	echo wp_json_encode( $response );

	exit();
}

/**
 * Get a Square terminal refund status.
 *
 * @since 1.17.0
 * @param array $args The arguments received by the XML-RPC request.
 */
function fsfwc_get_square_terminal_refund_status( $args ) {
	$user = fsfwc_authorize_xmlrpc_user( $args );

	$refund_id = $args[2];

	$response = fsfwc_do_get_square_terminal_refund_status( $refund_id );

	echo wp_json_encode( $response );

	exit();
}

/**
 * Create new XML-RPC methods.
 *
 * @since 1.0.0
 * @param array $methods The available XML-RPC methods.
 */
function fsfwc_new_xmlrpc_methods( $methods ) {
	$methods['fsfwc.test_access']                         = 'fsfwc_test_access';
	$methods['fsfwc.connect_data_fetch']                  = 'fsfwc_connect_data_fetch';
	$methods['fsfwc.update_product']                      = 'fsfwc_update_product';
	$methods['fsfwc.create_order']                        = 'fsfwc_create_order';
	$methods['fsfwc.sync_offline_changes']                = 'fsfwc_sync_offline_changes';
	$methods['fsfwc.cancel_order']                        = 'fsfwc_cancel_order';
	$methods['fsfwc.refund_order']                        = 'fsfwc_refund_order';
	$methods['fsfwc.create_update_customer']              = 'fsfwc_create_update_customer';
	$methods['fsfwc.get_coupon_code_discounts']           = 'fsfwc_get_coupon_code_discounts';
	$methods['fsfwc.get_data_updates']                    = 'fsfwc_get_data_updates';
	$methods['fsfwc.generate_square_device_code']         = 'fsfwc_generate_square_device_code';
	$methods['fsfwc.get_square_device_pair_status']       = 'fsfwc_get_square_device_pair_status';
	$methods['fsfwc.create_square_terminal_checkout']     = 'fsfwc_create_square_terminal_checkout';
	$methods['fsfwc.get_square_terminal_checkout_status'] = 'fsfwc_get_square_terminal_checkout_status';
	$methods['fsfwc.create_square_terminal_refund']       = 'fsfwc_create_square_terminal_refund';
	$methods['fsfwc.get_square_terminal_refund_status']   = 'fsfwc_get_square_terminal_refund_status';

	return $methods;
}

add_filter( 'xmlrpc_methods', 'fsfwc_new_xmlrpc_methods' );
