<?php
/**
 * REST API class containing initialization of REST API endpoints as well as their callbacks
 *
 * @link    https://www.foosales.com
 * @since   1.0.0
 * @package foosales
 */

/**
 * The REST API-specific functionality of the plugin.
 *
 * @link       https://www.foosales.com
 * @since      1.14.0
 * @package    foosales
 */
class FooSales_REST_API extends WP_REST_Controller {
	/**
	 * The namespace of the REST API.
	 *
	 * @since    1.14.0
	 * @var      string    $api_namespace        The current namespace of the REST API.
	 */
	private $api_namespace;

	/**
	 * The required capability of the REST API.
	 *
	 * @since    1.14.0
	 * @var      string    $required_capability  The current required capability of the REST API.
	 */
	private $required_capability;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.14.0
	 */
	public function __construct() {

		$this->api_namespace       = 'foosales/';
		$this->required_capability = 'publish_foosales';

		add_action( 'rest_api_init', array( $this, 'fsfwc_register_rest_api_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'fsfwc_rest_pre_serve_request' ) );

	}

	/**
	 * Register REST API endpoints with their corresponding callback functions.
	 *
	 * @since 1.14.0
	 */
	public function fsfwc_register_rest_api_routes() {

		$rest_api_endpoints = array(
			'v1' => array(
				'validate'                  => 'GET',
				'connect_data_fetch'        => 'POST',
				'update_product'            => 'POST',
				'create_order'              => 'POST',
				'sync_offline_changes'      => 'POST',
				'cancel_order'              => 'POST',
				'refund_order'              => 'POST',
				'create_update_customer'    => 'POST',
				'get_coupon_code_discounts' => 'POST',
			),
			'v2' => array(
				'validate'                            => 'GET',
				'connect_data_fetch'                  => 'POST',
				'update_product'                      => 'POST',
				'create_order'                        => 'POST',
				'sync_offline_changes'                => 'POST',
				'cancel_order'                        => 'POST',
				'refund_order'                        => 'POST',
				'create_update_customer'              => 'POST',
				'get_coupon_code_discounts'           => 'POST',
				'get_data_updates'                    => 'POST',
				'create_square_payment'               => 'POST',
				'webhook_square'                      => 'POST',
				'generate_square_device_code'         => 'POST',
				'get_square_device_pair_status'       => 'POST',
				'create_square_terminal_checkout'     => 'POST',
				'get_square_terminal_checkout_status' => 'POST',
				'create_square_terminal_refund'       => 'POST',
				'get_square_terminal_refund_status'   => 'POST',
			),
		);

		foreach ( $rest_api_endpoints as $version => $endpoints ) {

			foreach ( $endpoints as $endpoint => $method ) {

				$namespace = $this->api_namespace . $version;

				$callback = 'fsfwc_rest_callback_' . $endpoint;

				if ( method_exists( $this, $callback . '_' . $version ) ) {
					$callback .= '_' . $version;

					register_rest_route(
						$namespace,
						'/' . $endpoint,
						array(
							array(
								'methods'             => $method,
								'callback'            => array(
									$this,
									$callback,
								),
								'permission_callback' => '__return_true',
							),
						)
					);
				} else {
					register_rest_route(
						$namespace,
						'/' . $endpoint,
						array(
							array(
								'methods'             => $method,
								'callback'            => array(
									$this,
									$callback,
								),
								'permission_callback' => '__return_true',
							),
						)
					);
				}
			}
		}

	}

	/**
	 * Add headers to REST pre-serve request.
	 *
	 * @since 1.16.0
	 * @param bool $served Whether the REST request was served or not.
	 *
	 * @return bool
	 */
	public function fsfwc_rest_pre_serve_request( $served ) {

		header( 'Access-Control-Allow-Headers: Username, Password, Content-Type, X-WP-Nonce' );

		return $served;

	}

	/**
	 * Test if the provided credentials are for a valid user with the proper user role.
	 *
	 * @since 1.14.0
	 * @param array $headers The headers received by the REST API request.
	 *
	 * @return array
	 */
	public function fsfwc_is_authorized_rest_user( $headers ) {
		$creds = array();

		// Get username and password from the submitted headers.
		if ( array_key_exists( 'username', $headers ) && array_key_exists( 'password', $headers ) ) {
			$creds['user_login']    = $headers['username'][0];
			$creds['user_password'] = $headers['password'][0];
			$creds['remember']      = false;

			$user = wp_signon( $creds, false );

			if ( is_wp_error( $user ) ) {
				return array(
					'message' => false,
				);
			}

			wp_set_current_user( $user->ID, $user->user_login );

			if ( ! current_user_can( $this->required_capability ) ) {
				return array(
					'message'      => false,
					'invalid_user' => '1',
				);
			}

			return $user;
		} elseif ( array_key_exists( 'x_wp_nonce', $headers ) || array_key_exists( 'x-wp-nonce', $headers ) ) {
			if ( is_user_logged_in() ) {
				if ( current_user_can( $this->required_capability ) ) {
					return wp_get_current_user();
				} else {
					return array(
						'message'      => false,
						'invalid_user' => '1',
					);
				}
			} else {
				return array(
					'message' => false,
				);
			}
		} else {
			return array(
				'message' => false,
			);
		}
	}

	/**
	 * Validate that FooSales is being accessed via the plugin
	 *
	 * @since 1.16.1
	 * @param WP_REST_Request $request The REST API request object.
	 */
	public function fsfwc_rest_callback_validate( WP_REST_Request $request ) {
		return true;
	}

	/**
	 * Connect and fetch data.
	 *
	 * @since 1.14.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_connect_data_fetch( WP_REST_Request $request ) {

		$output = array( 'message' => false );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {

			wp_raise_memory_limit();

			set_time_limit( 0 );

			$output['message'] = true;

			$user  = $authorize_result;
			$chunk = $request->get_param( 'param2' );

			$output['data'] = fsfwc_fetch_chunk( $user, $chunk );

		} else {

			return $authorize_result;

		}

		return $output;
	}

	/**
	 * Update product data.
	 *
	 * @since 1.16.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_update_product( WP_REST_Request $request ) {

		$output = array( 'status' => 'error' );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {
			$result = fsfwc_do_update_product( json_decode( $request->get_param( 'param2' ), true ) );

			$updated_product  = $result['updated_product'];
			$sale_product_ids = $result['sale_product_ids'];

			$output = array(
				'status'           => 'success',
				'product'          => $updated_product,
				'sale_product_ids' => $sale_product_ids,
			);

		}

		return $output;
	}

	/**
	 * Update product data - LEGACY - v1.
	 *
	 * @since 1.16.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_update_product_v1( WP_REST_Request $request ) {

		$output = array( 'status' => 'error' );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {

			$success = fsfwc_do_update_product_v1(
				array(
					$request->get_param( 'param2' ),
					$request->get_param( 'param3' ),
					$request->get_param( 'param4' ),
					$request->get_param( 'param5' ),
					$request->get_param( 'param6' ),
				)
			);

			if ( $success ) {

				$output['status'] = 'success';

			}
		}

		return $output;
	}

	/**
	 * Create a new order.
	 *
	 * @since 1.16.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_create_order( WP_REST_Request $request ) {

		$output = array( 'status' => 'error' );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {
			$order_date                  = $request->get_param( 'param2' );
			$payment_method_key          = $request->get_param( 'param3' );
			$coupons                     = $request->get_param( 'param4' );
			$order_items                 = $request->get_param( 'param5' );
			$order_customer              = $request->get_param( 'param6' );
			$order_note                  = $request->get_param( 'param7' );
			$order_note_send_to_customer = $request->get_param( 'param8' );
			$attendee_details            = $request->get_param( 'param9' );
			$square_order_id             = $request->get_param( 'param10' );
			$user_id                     = $request->get_param( 'param11' );

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

			$output['status'] = 'success';
			$output['order']  = fsfwc_do_get_single_order( $new_order );

		}

		return $output;

	}

	/**
	 * Synchronize offline changes.
	 *
	 * @since 1.16.0
	 * @param WP_REST_Request $request The REST API request object.
	 */
	public function fsfwc_rest_callback_sync_offline_changes( WP_REST_Request $request ) {

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {
			$offline_changes = json_decode( $request->get_param( 'param2' ), true );

			fsfwc_do_sync_offline_changes( $offline_changes );
		} else {
			echo wp_json_encode( $authorize_result );
		}

		exit;
	}

	/**
	 * Synchronize offline changes - LEGACY - v1.
	 *
	 * @since 1.16.0
	 * @param WP_REST_Request $request The REST API request object.
	 */
	public function fsfwc_rest_callback_sync_offline_changes_v1( WP_REST_Request $request ) {

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {
			$offline_changes = json_decode( $request->get_param( 'param2' ), true );

			fsfwc_do_sync_offline_changes_v1( $offline_changes );
		} else {
			echo wp_json_encode( $authorize_result );
		}

		exit;
	}

	/**
	 * Cancel order.
	 *
	 * @since 1.16.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_cancel_order( WP_REST_Request $request ) {

		$output = array( 'status' => 'error' );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {

			if ( fsfwc_do_cancel_order( $request->get_param( 'param2' ), (bool) $request->get_param( 'param3' ) ) ) {

				$output['status'] = 'success';

			}
		}

		return $output;
	}

	/**
	 * Refund order.
	 *
	 * @since 1.16.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_refund_order( WP_REST_Request $request ) {

		$output = array( 'status' => 'error' );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {

			$order_id       = $request->get_param( 'param2' );
			$refunded_items = json_decode( stripslashes( $request->get_param( 'param3' ) ), true );

			$refund_result = fsfwc_do_refund_order( $order_id, $refunded_items );
			$wc_order      = $refund_result['order'];

			$output['status'] = 'success';
			$output['order']  = fsfwc_do_get_single_order( $wc_order );

			if ( ! empty( $refund_result['square_refund'] ) ) {

				$output['square_refund'] = $refund_result['square_refund'];

				if ( ! empty( $refund_result['square_terminal_refund'] ) ) {
					$output['square_terminal_refund'] = $refund_result['square_terminal_refund'];
				}
			}
		}

		return $output;

	}

	/**
	 * Create or update customer.
	 *
	 * @since 1.16.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_create_update_customer( WP_REST_Request $request ) {

		$output = array( 'status' => 'error' );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {

			$customer_details = json_decode( stripslashes( $request->get_param( 'param2' ) ), true );

			$output = fsfwc_do_create_update_customer( $customer_details );

		}

		return $output;

	}

	/**
	 * Get coupon code discounts.
	 *
	 * @since 1.16.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_get_coupon_code_discounts( WP_REST_Request $request ) {

		$output = array( 'status' => 'error' );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {

			$coupons     = json_decode( stripslashes( $request->get_param( 'param2' ) ), true );
			$order_items = json_decode( stripslashes( $request->get_param( 'param3' ) ), true );

			$output = fsfwc_do_get_coupon_code_discounts( $coupons, $order_items );

		}

		return $output;

	}

	/**
	 * Get data updates.
	 *
	 * @since 1.16.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_get_data_updates( WP_REST_Request $request ) {

		$output = array( 'status' => 'error' );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {

			$last_checked_timestamp = json_decode( stripslashes( $request->get_param( 'param2' ) ), true );

			$output = fsfwc_do_get_data_updates( $last_checked_timestamp );

		}

		return $output;

	}

	/**
	 * Create a Square manual payment.
	 *
	 * @since 1.16.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_create_square_payment( WP_REST_Request $request ) {

		$output = array( 'status' => 'error' );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {

			$payment_data = json_decode( $request->get_param( 'payment_data' ), true );

			$output = fsfwc_do_create_square_payment( $payment_data );

		}

		return $output;

	}

	/**
	 * Webhook for Square to send various device and checkout status updates.
	 *
	 * @since 1.17.0
	 * @global wpdb $wpdb
	 * @param WP_REST_Request $request The REST API request object.
	 */
	public function fsfwc_rest_callback_webhook_square( WP_REST_Request $request ) {

		global $wpdb;

		$event_type  = $request->get_param( 'type' );
		$square_data = $request->get_param( 'data' )['object'];

		if ( 'device.code.paired' === $event_type ) {

			if ( ! empty( $square_data['device_code'] ) ) {

				$device_code = $square_data['device_code'];
				$table_name  = $wpdb->prefix . 'foosales_square_devices';

				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table_name,
					array(
						'device_id' => $device_code['device_id'],
						'status'    => $device_code['status'],
					),
					array(
						'device_code_id' => $device_code['id'],
					)
				);

			}
		} elseif ( 'terminal.checkout.updated' === $event_type ) {

			if ( ! empty( $square_data['checkout'] ) ) {

				$checkout_data = $square_data['checkout'];
				$table_name    = $wpdb->prefix . 'foosales_square_checkouts';

				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table_name,
					array(
						'status'     => $checkout_data['status'],
						'updated_at' => $checkout_data['updated_at'],
						'payment_id' => $checkout_data['payment_ids'][0],
					),
					array(
						'checkout_id' => $checkout_data['id'],
					)
				);
			}
		} elseif ( 'terminal.refund.updated' === $event_type ) {

			if ( ! empty( $square_data['refund'] ) ) {

				$refund_data = $square_data['refund'];
				$table_name  = $wpdb->prefix . 'foosales_square_refunds';

				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table_name,
					array(
						'status'     => $refund_data['status'],
						'updated_at' => $refund_data['updated_at'],
					),
					array(
						'refund_id' => $refund_data['id'],
					)
				);
			}
		}

	}

	/**
	 * Generate a Square device code.
	 *
	 * @since 1.17.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_generate_square_device_code( WP_REST_Request $request ) {

		$output = array( 'status' => 'error' );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {
			$square_location = json_decode( $request->get_param( 'param2' ), true );

			$output = fsfwc_do_generate_square_device_code( $square_location );
		}

		return $output;
	}

	/**
	 * Get the pair status of a Square device.
	 *
	 * @since 1.17.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_get_square_device_pair_status( WP_REST_Request $request ) {

		$output = array( 'status' => 'error' );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {
			$square_device_id = $request->get_param( 'param2' );

			$output = fsfwc_do_get_square_device_pair_status( $square_device_id );
		}

		return $output;
	}

	/**
	 * Create a Square terminal checkout request.
	 *
	 * @since 1.17.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_create_square_terminal_checkout( WP_REST_Request $request ) {

		$output = array( 'status' => 'error' );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {
			$checkout_data = json_decode( $request->get_param( 'param2' ), true );

			$output = fsfwc_do_create_square_terminal_checkout( $checkout_data );
		}

		return $output;
	}

	/**
	 * Get a Square terminal checkout status.
	 *
	 * @since 1.17.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_get_square_terminal_checkout_status( WP_REST_Request $request ) {

		$output = array( 'status' => 'error' );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {
			$checkout_id = $request->get_param( 'param2' );

			$output = fsfwc_do_get_square_terminal_checkout_status( $checkout_id );
		}

		return $output;
	}

	/**
	 * Create a Square terminal refund request.
	 *
	 * @since 1.17.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_create_square_terminal_refund( WP_REST_Request $request ) {

		$output = array( 'status' => 'error' );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {
			$refund_data = json_decode( $request->get_param( 'param2' ), true );

			$output = fsfwc_do_create_square_terminal_refund( $refund_data );
		}

		return $output;
	}

	/**
	 * Get a Square terminal refund status.
	 *
	 * @since 1.17.0
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array
	 */
	public function fsfwc_rest_callback_get_square_terminal_refund_status( WP_REST_Request $request ) {

		$output = array( 'status' => 'error' );

		$authorize_result = $this->fsfwc_is_authorized_rest_user( $request->get_headers() );

		if ( $authorize_result && is_object( $authorize_result ) && is_a( $authorize_result, 'WP_User' ) ) {
			$refund_id = $request->get_param( 'param2' );

			$output = fsfwc_do_get_square_terminal_refund_status( $refund_id );
		}

		return $output;
	}
}
