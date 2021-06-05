<?php
/**
 * The main FooSales class file of the plugin
 *
 * @link https://www.foosales.com
 * @since 1.16.0
 * @package foosales
 */

/**
 * This class contains all initialization for the plugin.
 *
 * @since 1.0.0
 * @package foosales
 */
class FooSales {
	/**
	 * The main FooSales config.
	 *
	 * @since 1.0.0
	 * @var FooSales_Config $foosales_config The current FooSales config.
	 */
	private $foosales_config;

	/**
	 * The FooSales phrases helper.
	 *
	 * @since    1.16.1
	 * @var array $foosales_phrases The current phrases helper array.
	 */
	private $foosales_phrases;

	/**
	 * The REST API.
	 *
	 * @since    1.14.0
	 * @var FooSales_REST_API $class_rest_api The current REST API class.
	 */
	private $class_rest_api;

	/**
	 * The XML-RPC API.
	 *
	 * @since    1.14.0
	 * @var FooSales_XMLRPC $class_xmlrpc The current XML-RPC API class.
	 */
	private $class_xmlrpc;

	/**
	 * FooSales payment method IDs.
	 *
	 * @since    1.14.0
	 * @var array $foosales_payment_method_ids An array of the current FooSales payment method IDs.
	 */
	private $foosales_payment_method_ids;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		add_action( 'init', array( $this, 'plugin_init' ) );

		add_action( 'admin_notices', array( $this, 'check_woocommerce' ) );

		add_action( 'admin_init', array( $this, 'register_settings_options' ) );
		add_action( 'admin_init', array( &$this, 'assign_admin_caps' ) );
		add_action( 'admin_init', array( $this, 'foosales_register_scripts' ) );
		add_action( 'admin_init', array( $this, 'foosales_register_styles' ) );
		add_action( 'admin_init', array( $this, 'foosales_register_importer' ) );
		add_action( 'admin_menu', array( $this, 'foosales_pos_menu' ) );
		add_action( 'admin_head', array( $this, 'foosales_pos_submenu_target' ), 1001 );

		add_filter( 'plugin_action_links_foosales/foosales.php', array( $this, 'add_action_links' ) );
		add_filter( 'woocommerce_email_enabled_new_order', array( $this, 'foosales_conditionally_send_wc_email' ), 10, 2 );

		add_filter( 'parse_query', array( $this, 'foosales_filter_order_results' ) );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'foosales_order_column' ), 20 );
		add_action( 'restrict_manage_posts', array( $this, 'foosales_filter_orders' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'foosales_order_column_content' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'foosales_order_meta_general' ) );

		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'fsfwc_conditional_payment_gateways' ), 10, 1 );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'fsfwc_add_payment_options_class' ) );
		add_filter( 'plugins_loaded', array( $this, 'fsfwc_payment_methods_init' ), 0 );
		add_filter( 'fsfwc_current_plugin_version', array( $this, 'fsfwc_current_plugin_version' ) );

	}

	/**
	 * Register plugin scripts.
	 *
	 * @since 1.0.0
	 */
	public function foosales_register_scripts() {

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-tooltip' );

		if ( isset( $_GET['page'] ) && 'foosales-settings' === $_GET['page'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_enqueue_media();
		}

		wp_enqueue_script( 'foosales-scripts', $this->foosales_config->scripts_path . 'foosales-admin.js', array( 'jquery', 'jquery-ui-tooltip' ), $this->foosales_config->plugin_version, true );

	}

	/**
	 * Register plugin styles.
	 *
	 * @since 1.0.0
	 */
	public function foosales_register_styles() {

		wp_enqueue_style( 'foosales-styles', $this->foosales_config->styles_path . 'foosales-admin.css', array(), $this->foosales_config->plugin_version );

	}

	/**
	 * Register FooSales options.
	 *
	 * @since 1.16.0
	 */
	public function register_settings_options() {

		register_setting( 'foosales-settings-products', 'globalFooSalesProductsToDisplay' );
		register_setting( 'foosales-settings-products', 'globalFooSalesProductCategories' );
		register_setting( 'foosales-settings-products', 'globalFooSalesProductsStatus' );
		register_setting( 'foosales-settings-products', 'globalFooSalesProductsOnlyInStock' );
		register_setting( 'foosales-settings-products', 'globalFooSalesProductsPerPage' );
		register_setting( 'foosales-settings-products', 'globalFooSalesProductsShowAttributeLabels' );

		register_setting( 'foosales-settings-orders', 'globalFooSalesOrdersToLoad' );
		register_setting( 'foosales-settings-orders', 'globalFooSalesDisableNewOrderEmails' );

		register_setting( 'foosales-settings-receipts', 'globalFooSalesStoreLogoURL' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesStoreName' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesHeaderContent' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesReceiptTitle' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesOrderNumberPrefix' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesProductColumnTitle' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesQuantityColumnTitle' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesPriceColumnTitle' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesSubtotalColumnTitle' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesInclusiveAbbreviation' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesExclusiveAbbreviation' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesDiscountsTitle' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesRefundsTitle' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesTaxTitle' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesTotalTitle' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesPaymentMethodTitle' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesBillingAddressTitle' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesShippingAddressTitle' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesFooterContent' );
		register_setting( 'foosales-settings-receipts', 'globalFooSalesReceiptShowLogo' );

		register_setting( 'foosales-settings-integration', 'globalFooSalesSquareApplicationID' );
		register_setting( 'foosales-settings-integration', 'globalFooSalesSquareAccessToken' );

	}

	/**
	 * Display and processes the FooSales Settings page.
	 *
	 * @since 1.16.0
	 */
	public function display_settings_page() {

		if ( ! current_user_can( 'publish_foosales' ) ) {
			wp_die( esc_html( $this->foosales_phrases['error_insufficient_permissions'] ) );
		}

		if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'is_plugin_active_for_network' ) ) {

			require_once ABSPATH . '/wp-admin/includes/plugin.php';

		}

		$active_tab = '';

		if ( isset( $_GET['tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification

			$active_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		} else {

			$active_tab = 'general';

		}

		// General settings.
		$foosales_url       = site_url();
		$foosales_url_info  = wp_parse_url( $foosales_url );
		$foosales_clean_url = $foosales_url_info['host'];

		$data = array(
			'passphrase' => '84671ded20ea778edd234f7e1cb3dcc1',
			'channel'    => 'foosales-plugin',
			'url'        => $foosales_url,
		);

		$result = wp_remote_post(
			'https://www.foosales.com/wp-json/foosales-accounts/v0/url/',
			array(
				'method'      => 'POST',
				'timeout'     => 30,
				'redirection' => 10,
				'httpversion' => '1.1',
				'body'        => $data,
			)
		);

		if ( is_wp_error( $result ) ) {

			$foosales_status = 'static';

		} else {

			$result = $result['body'];

			$obj = json_decode( $result, true );

			if ( true === $obj['connect'] ) {

				$foosales_connect = 'true';

			} else {

				$foosales_connect = 'false';

			}

			$foosales_renewal = $obj['renewal'];
			$foosales_status  = $obj['status'];

		}

		// Products settings.
		// Get all product categories.
		$taxonomy     = 'product_cat';
		$orderby      = 'name';
		$show_count   = 1; // 1 for yes, 0 for no.
		$pad_counts   = 0; // 1 for yes, 0 for no.
		$hierarchical = 1; // 1 for yes, 0 for no.
		$title        = '';
		$empty        = 0;

		$args = array(
			'taxonomy'     => $taxonomy,
			'orderby'      => $orderby,
			'show_count'   => $show_count,
			'pad_counts'   => $pad_counts,
			'hierarchical' => $hierarchical,
			'title_li'     => $title,
			'hide_empty'   => $empty,
		);

		$all_categories = get_categories( $args );

		$cat_options = array();

		foreach ( $all_categories as $cat ) {

			if ( 0 === $cat->category_parent ) {

				$category_id = $cat->term_id;

				$cat_options[ $cat->term_id ] = $cat->name;

				$args2 = array(
					'taxonomy'     => $taxonomy,
					'child_of'     => 0,
					'parent'       => $category_id,
					'orderby'      => $orderby,
					'show_count'   => $show_count,
					'pad_counts'   => $pad_counts,
					'hierarchical' => $hierarchical,
					'title_li'     => $title,
					'hide_empty'   => $empty,
				);

				$sub_cats = get_categories( $args2 );

				if ( $sub_cats ) {
					foreach ( $sub_cats as $sub_category ) {
						$cat_options[ $sub_category->term_id ] = '   - ' . $sub_category->name;
					}
				}
			}
		}

		$products_to_display = get_option( 'globalFooSalesProductsToDisplay', 'all' );
		$product_categories  = get_option( 'globalFooSalesProductCategories', array() );

		$status_options = array(
			'any'     => esc_html( $this->foosales_phrases['option_product_status_any'] ),
			'publish' => esc_html( $this->foosales_phrases['option_product_status_published'] ),
			'pending' => esc_html( $this->foosales_phrases['option_product_status_pending'] ),
			'draft'   => esc_html( $this->foosales_phrases['option_product_status_draft'] ),
			'future'  => esc_html( $this->foosales_phrases['option_product_status_future'] ),
			'private' => esc_html( $this->foosales_phrases['option_product_status_private'] ),
		);

		$products_status        = get_option( 'globalFooSalesProductsStatus', array( 'publish' ) );
		$products_only_in_stock = get_option( 'globalFooSalesProductsOnlyInStock', '' );

		if ( empty( $products_status ) ) {
			$products_status = array( 'publish' );
		}

		$products_per_page_array = array(
			'10',
			'20',
			'30',
			'40',
			'50',
			'100',
			'200',
			'300',
			'400',
			'500',
		);

		$products_per_page              = get_option( 'globalFooSalesProductsPerPage', '500' );
		$products_show_attribute_labels = get_option( 'globalFooSalesProductsShowAttributeLabels', '' );

		// Orders settings.
		$order_limit_array = array(
			'all'  => esc_html( $this->foosales_phrases['option_order_limit_all'] ),
			'10'   => '10',
			'20'   => '20',
			'30'   => '30',
			'40'   => '40',
			'50'   => '50',
			'100'  => '100',
			'150'  => '150',
			'200'  => '200',
			'250'  => '250',
			'300'  => '300',
			'350'  => '350',
			'400'  => '400',
			'450'  => '450',
			'500'  => '500',
			'1000' => '1000',
		);

		$orders_to_load           = get_option( 'globalFooSalesOrdersToLoad', '100' );
		$disable_new_order_emails = get_option( 'globalFooSalesDisableNewOrderEmails', '' );

		// Receipts settings.
		$store_logo_url         = get_option( 'globalFooSalesStoreLogoURL' );
		$store_name             = get_option( 'globalFooSalesStoreName' );
		$header_content         = get_option( 'globalFooSalesHeaderContent' );
		$receipt_title          = get_option( 'globalFooSalesReceiptTitle' );
		$order_number_prefix    = get_option( 'globalFooSalesOrderNumberPrefix' );
		$product_column_title   = get_option( 'globalFooSalesProductColumnTitle' );
		$quantity_column_title  = get_option( 'globalFooSalesQuantityColumnTitle' );
		$price_column_title     = get_option( 'globalFooSalesPriceColumnTitle' );
		$subtotal_column_title  = get_option( 'globalFooSalesSubtotalColumnTitle' );
		$inclusive_abbreviation = get_option( 'globalFooSalesInclusiveAbbreviation' );
		$exclusive_abbreviation = get_option( 'globalFooSalesExclusiveAbbreviation' );
		$discounts_title        = get_option( 'globalFooSalesDiscountsTitle' );
		$refunds_title          = get_option( 'globalFooSalesRefundsTitle' );
		$tax_title              = get_option( 'globalFooSalesTaxTitle' );
		$total_title            = get_option( 'globalFooSalesTotalTitle' );
		$payment_method_title   = get_option( 'globalFooSalesPaymentMethodTitle' );
		$billing_address_title  = get_option( 'globalFooSalesBillingAddressTitle' );
		$shipping_address_title = get_option( 'globalFooSalesShippingAddressTitle' );
		$footer_content         = get_option( 'globalFooSalesFooterContent' );
		$receipt_show_logo      = get_option( 'globalFooSalesReceiptShowLogo', 'yes' );

		// Integration settings.
		$square_application_id = get_option( 'globalFooSalesSquareApplicationID' );
		$square_access_token   = get_option( 'globalFooSalesSquareAccessToken' );

		$foosales_phrases = $this->foosales_phrases;

		require_once $this->foosales_config->template_path . 'template-foosales-settings.php';

	}

	/**
	 *  Initialize plugin and helpers.
	 *
	 * @since 1.14.0
	 */
	public function plugin_init() {

		// Main FooSales config.
		$this->foosales_config = new FooSales_Config();

		// FooSales phrases.
		require $this->foosales_config->helper_path . 'foosales-phrases-helper.php';
		$this->foosales_phrases = $foosales_phrases;

		// API helper methods.
		require_once $this->foosales_config->helper_path . 'foosales-api-helper.php';

		// FooSales_REST_API class.
		require_once $this->foosales_config->class_path . 'class-foosales-rest-api.php';
		$this->class_rest_api = new FooSales_REST_API();

		// FooSales_XMLRPC class.
		require_once $this->foosales_config->class_path . 'class-foosales-xmlrpc.php';
		$this->class_xmlrpc = new FooSales_XMLRPC();

		$this->add_foosales_db_tables();

	}

	/**
	 * Add additional database tables.
	 *
	 * @since 1.17.0
	 */
	public function add_foosales_db_tables() {

		// Square Terminal Devices DB table.
		$foosales_db_square_devices = get_option( 'foosales_db_square_devices', '' );

		if ( $this->foosales_config->plugin_version !== $foosales_db_square_devices ) {
			$this->add_foosales_db_square_devices();
		}

		// Square Terminal Checkouts DB table.
		$foosales_db_square_checkouts = get_option( 'foosales_db_square_checkouts', '' );

		if ( $this->foosales_config->plugin_version !== $foosales_db_square_checkouts ) {
			$this->add_foosales_db_square_checkouts();
		}

		// Square Terminal Refunds DB table.
		$foosales_db_square_refunds = get_option( 'foosales_db_square_refunds', '' );

		if ( $this->foosales_config->plugin_version !== $foosales_db_square_refunds ) {
			$this->add_foosales_db_square_refunds();
		}

	}

	/**
	 * Add database table for Square devices.
	 *
	 * @since 1.17.0
	 */
	public function add_foosales_db_square_devices() {

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'foosales_square_devices';

		$sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            device_code_id VARCHAR(50) NOT NULL,
            device_id VARCHAR(50) NOT NULL,
            code VARCHAR(50) NOT NULL,
            location_id VARCHAR(50) NOT NULL,
            pair_by VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'foosales_db_square_devices', $this->foosales_config->plugin_version );

	}

	/**
	 * Add database table for Square Checkouts.
	 *
	 * @since 1.17.0
	 */
	public function add_foosales_db_square_checkouts() {

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'foosales_square_checkouts';

		$sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            checkout_id VARCHAR(50) NOT NULL,
            amount VARCHAR(50) NOT NULL,
            currency VARCHAR(10) NOT NULL,
            device_id VARCHAR(50) NOT NULL,
            deadline VARCHAR(50) NOT NULL,
            payment_id VARCHAR(50) NOT NULL DEFAULT '',
            order_id VARCHAR(50) NOT NULL DEFAULT '',
			status VARCHAR(50) NOT NULL,
			created_at VARCHAR(50) NOT NULL,
			updated_at VARCHAR(50) NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'foosales_db_square_checkouts', $this->foosales_config->plugin_version );

	}

	/**
	 * Add database table for Square Refunds.
	 *
	 * @since 1.17.0
	 */
	public function add_foosales_db_square_refunds() {

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'foosales_square_refunds';

		$sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            refund_id VARCHAR(50) NOT NULL,
            amount VARCHAR(50) NOT NULL,
            currency VARCHAR(10) NOT NULL,
            device_id VARCHAR(50) NOT NULL,
            deadline VARCHAR(50) NOT NULL,
            payment_id VARCHAR(50) NOT NULL DEFAULT '',
            order_id VARCHAR(50) NOT NULL DEFAULT '',
			status VARCHAR(50) NOT NULL,
			created_at VARCHAR(50) NOT NULL,
			updated_at VARCHAR(50) NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'foosales_db_square_refunds', $this->foosales_config->plugin_version );

	}

	/**
	 * Checks if WooCommerce is active.
	 *
	 * @since 1.0.0
	 */
	public function check_woocommerce() {

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->output_notices( array( esc_html( $this->foosales_phrases['notice_woocommerce_not_active'] ) ) );
		}
	}

	/**
	 * Outputs notices to screen.
	 *
	 * @since 1.0.0
	 * @param array $notices An array of notices to output.
	 */
	private function output_notices( $notices ) {

		foreach ( $notices as $notice ) {
			echo '<div class="updated"><p>' . esc_attr( $notice ) . '</p></div>';
		}
	}

	/**
	 * Assign admin capabilities.
	 *
	 * @since 1.0.0
	 */
	public function assign_admin_caps() {

		$role = get_role( 'administrator' );

		$role->add_cap( 'publish_foosales' );
	}

	/**
	 * Add action links to the plugin listing.
	 *
	 * @since 1.0.0
	 * @param array $links The array of action links displayed on the plugin listing.
	 */
	public function add_action_links( $links ) {

		$link_settings = '<a href="' . admin_url( 'admin.php?page=foosales-settings' ) . '">' . esc_html( $this->foosales_phrases['button_settings'] ) . '</a>';

		array_unshift( $links, $link_settings );

		return $links;

	}

	/**
	 * Filter WooCommerce orders listing based on FooSales filter selection.
	 *
	 * @since 1.10.0
	 * @param WP_Query $query The WooCommerce order results query.
	 */
	public function foosales_filter_order_results( $query ) {

		global $pagenow;
		$foosales_filter = '';

		if ( is_admin() && 'edit.php' === $pagenow && isset( $_GET['foosales_filter'] ) && '' !== sanitize_text_field( wp_unslash( $_GET['foosales_filter'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$foosales_filter        = sanitize_text_field( wp_unslash( $_GET['foosales_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$payment_method_options = array(
				'pos-cash'                 => 'foosales_cash',
				'pos-card'                 => 'foosales_card',
				'pos-direct-bank-transfer' => 'foosales_direct_bank_transfer',
				'pos-check-payment'        => 'foosales_check_payment',
				'pos-cash-on-delivery'     => 'foosales_cash_on_delivery',
				'pos-square-manual'        => 'foosales_square_manual',
				'pos-square-terminal'      => 'foosales_square_terminal',
				'pos-square-reader'        => 'foosales_square_reader',
				'pos-other'                => 'foosales_other',
			);

			if ( in_array( $foosales_filter, array_keys( $payment_method_options ), true ) ) {
				if ( 'pos-square-reader' === $foosales_filter ) {
					$query->query_vars['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
						'relation' => 'OR',
						array(
							'key'     => '_foosales_payment_method',
							'value'   => 'foosales_square_reader',
							'compare' => '=',
						),
						array(
							'key'     => '_foosales_payment_method',
							'value'   => 'foosales_square',
							'compare' => '=',
						),
					);
				} else {
					$query->query_vars['meta_key']   = '_foosales_payment_method'; // phpcs:ignore WordPress.DB.SlowDBQuery
					$query->query_vars['meta_value'] = $payment_method_options[ $foosales_filter ]; // phpcs:ignore WordPress.DB.SlowDBQuery
				}
			} else {
				switch ( $foosales_filter ) {
					case 'pos-all':
						// All FooSales payments.
						$query->query_vars['meta_key']   = '_foosales_order_source'; // phpcs:ignore WordPress.DB.SlowDBQuery
						$query->query_vars['meta_value'] = 'foosales_app'; // phpcs:ignore WordPress.DB.SlowDBQuery
						break;
					case 'pos-none':
						// Online orders only.
						$query->query_vars['meta_key']     = '_foosales_order_source'; // phpcs:ignore WordPress.DB.SlowDBQuery
						$query->query_vars['meta_compare'] = 'NOT EXISTS';
						$query->query_vars['meta_value']   = 'foosales_app'; // phpcs:ignore WordPress.DB.SlowDBQuery
						break;
				}
			}
		}
	}

	/**
	 * Adds FooSales drop down filter selection to the WooCommerce orders listing.
	 *
	 * @since 1.10.0
	 */
	public function foosales_filter_orders() {

		global $wpdb, $post_type;

		if ( 'shop_order' === $post_type ) {

			$foosales_filter = '';

			if ( isset( $_GET['foosales_filter'] ) && '' !== $_GET['foosales_filter'] ) { // phpcs:ignore WordPress.Security.NonceVerification
				$foosales_filter = sanitize_text_field( wp_unslash( $_GET['foosales_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}
			?>
			<select name="foosales_filter">
				<option value=""><?php echo esc_html( $this->foosales_phrases['filter_all_orders'] ); ?></option>

				<option value="pos-all"
				<?php
				if ( 'pos-all' === $foosales_filter ) {
					echo 'selected';  }
				?>
				><?php echo esc_html( $this->foosales_phrases['filter_pos_only'] ); ?></option>

				<option value="pos-none"
				<?php
				if ( 'pos-none' === $foosales_filter ) {
					echo 'selected';  }
				?>
				><?php echo esc_html( $this->foosales_phrases['filter_online_only'] ); ?></option>

				<?php
					$payment_methods        = fsfwc_do_get_all_payment_methods( true );
					$payment_method_options = array(
						'pos-cash'                 => 'foosales_cash',
						'pos-card'                 => 'foosales_card',
						'pos-direct-bank-transfer' => 'foosales_direct_bank_transfer',
						'pos-check-payment'        => 'foosales_check_payment',
						'pos-cash-on-delivery'     => 'foosales_cash_on_delivery',
						'pos-square-manual'        => 'foosales_square_manual',
						'pos-square-terminal'      => 'foosales_square_terminal',
						'pos-square-reader'        => 'foosales_square_reader',
						'pos-other'                => 'foosales_other',
					);

					foreach ( $payment_method_options as $payment_method_option_value => $payment_method_key ) {
						?>
						<option value="<?php echo esc_attr( $payment_method_option_value ); ?>"
						<?php
						if ( $payment_method_option_value === $foosales_filter ) {
							echo 'selected';  }
						?>
						><?php echo esc_html( ! empty( $payment_methods[ $payment_method_key ] ) ? 'POS ' . $payment_methods[ $payment_method_key ] : $this->foosales_phrases[ 'filter_pos_' . str_replace( 'foosales_', '', $payment_method_key ) ] ); ?></option>
						<?php
					}
					?>
			</select>
			<?php
		}
	}

	/**
	 * Add POS column to WooCommerce orders listing.
	 *
	 * @since 1.10.0
	 * @param array $columns The columns that show on the order list.
	 */
	public function foosales_order_column( $columns ) {
		$columns['foosales_column_type'] = 'POS';

		return $columns;
	}

	/**
	 * Add payment method indicator to POS column on WooCommerce orders listing.
	 *
	 * @since 1.10.0
	 * @param string $column The column for which content should be added.
	 */
	public function foosales_order_column_content( $column ) {
		global $post;

		if ( 'foosales_column_type' === $column ) {

			$foosales_order_type = get_post_meta( $post->ID, '_foosales_payment_method', true );

			$payment_methods            = fsfwc_do_get_all_payment_methods( true );
			$payment_method_key_classes = array(
				'foosales_cash'                 => 'foosales_type_cash',
				'foosales_card'                 => 'foosales_type_card',
				'foosales_direct_bank_transfer' => 'foosales_type_direct_bank_transfer',
				'foosales_check_payment'        => 'foosales_type_check_payment',
				'foosales_cash_on_delivery'     => 'foosales_type_cash_on_delivery',
				'foosales_square_manual'        => 'foosales_type_square',
				'foosales_square_terminal'      => 'foosales_type_square',
				'foosales_square'               => 'foosales_type_square',
				'foosales_square_reader'        => 'foosales_type_square',
				'foosales_other'                => 'foosales_type_other',
			);

			$payment_method_mark_label = $this->foosales_phrases['label_order_payment_method_online'];
			$payment_method_mark_class = 'foosales_type_online';

			if ( in_array( $foosales_order_type, array_keys( $payment_methods ), true ) ) {
				$payment_method_mark_label = $payment_methods[ $foosales_order_type ];
				$payment_method_mark_class = $payment_method_key_classes[ $foosales_order_type ];
			} elseif ( in_array( $foosales_order_type, array_keys( $payment_method_key_classes ), true ) ) {
				$payment_method_mark_label = $this->foosales_phrases[ 'label_order_payment_method_' . str_replace( 'foosales_', '', $foosales_order_type ) ];
				$payment_method_mark_class = $payment_method_key_classes[ $foosales_order_type ];
			}

			echo '<mark class="order-status ' . esc_attr( $payment_method_mark_class ) . '"><span>';
			echo esc_html( $payment_method_mark_label );
			echo '</span></mark>';
		}
	}

	/**
	 * Add payment method to WooCommerce order details screen.
	 *
	 * @since 1.15.0
	 * @param WC_Order $order The WooCommerce order to which payment method meta should be added.
	 */
	public function foosales_order_meta_general( $order ) {

		$foosales_source             = get_post_meta( $order->get_id(), '_foosales_order_source', true );
		$foosales_payment_method     = get_post_meta( $order->get_id(), '_foosales_payment_method', true );
		$foosales_payment_method_pub = get_post_meta( $order->get_id(), 'Order Payment Method', true );

		if ( 'foosales_app' === $foosales_source ) {

			echo "<br class='clear' />";
			echo '<h3>' . esc_html( $this->foosales_phrases['title_payment_details'] ) . '</h3>';
			echo '<p>';

			$payment_methods      = fsfwc_do_get_all_payment_methods( true );
			$found_payment_method = ! empty( $payment_methods[ $foosales_payment_method ] ) ? $payment_methods[ $foosales_payment_method ] : $this->foosales_phrases[ 'description_payment_details_' . str_replace( 'foosales_', '', $foosales_payment_method ) ];

			echo esc_html( $found_payment_method );

			echo ' <em>' . esc_html( $this->foosales_phrases['label_payment_via_foosales_pos'] ) . '</em>';

			if ( in_array(
				$foosales_payment_method,
				array(
					'foosales_square',
					'foosales_square_manual',
					'foosales_square_terminal',
					'foosales_square_reader',
				),
				true
			) ) {

				$square_order_id = get_post_meta( $order->get_id(), '_foosales_square_order_id', true );

				if ( '' !== $square_order_id ) {

					$square_auto_refund = get_post_meta( $order->get_id(), '_foosales_square_order_auto_refund', true );

					echo '<br/>';

					if ( '' === $square_auto_refund ) {

						echo esc_html( $this->foosales_phrases['description_square_split_tenders'] ) . ' ';

					}

					echo '<a href="https://squareup.com/dashboard/sales/transactions/' . esc_attr( $square_order_id ) . '" target="_blank">' . esc_html( $this->foosales_phrases['button_view_square_transaction'] ) . '</a>';

				}
			}

			echo '</p>';
		} else {
			echo "<br class='clear' />&nbsp;";
		}
	}

	/**
	 * Set the point of sale admin submenu target
	 *
	 * @since 1.18.0
	 */
	public function foosales_pos_submenu_target() {
		?>
		<script type="text/javascript">
			jQuery(document).ready( function( $) {
				$( '#toplevel_page_foosales-settings ul li:nth-child(3) a' ).attr( 'target', '_blank' );
			} );
		</script>
		<?php
	}

	/**
	 * Adds the FooSales import sub menu page.
	 *
	 * @since 1.12.0
	 */
	public function add_foosales_import_page() {

		$foosales_phrases = $this->foosales_phrases;

		require_once $this->foosales_config->template_path . 'template-foosales-import.php';

	}

	/**
	 * Redirect to the import sub menu page.
	 *
	 * @since 1.12.0
	 */
	public function redirect_to_import() {

		wp_safe_redirect( admin_url( 'admin.php?page=foosales-import' ) );

		exit;

	}

	/**
	 * Adds submenu to WooCommerce admin menu.
	 *
	 * @since 1.0.0
	 */
	public function foosales_pos_menu() {

		add_menu_page(
			null,
			$this->foosales_phrases['title_foosales'],
			'manage_options',
			'foosales-settings',
			array( $this, 'display_settings_page' ),
			'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+PCFET0NUWVBFIHN2ZyBQVUJMSUMgIi0vL1czQy8vRFREIFNWRyAxLjEvL0VOIiAiaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkIj48c3ZnIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIHZpZXdCb3g9IjAgMCA1NCAzNCIgdmVyc2lvbj0iMS4xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4bWw6c3BhY2U9InByZXNlcnZlIiB4bWxuczpzZXJpZj0iaHR0cDovL3d3dy5zZXJpZi5jb20vIiBzdHlsZT0iZmlsbC1ydWxlOmV2ZW5vZGQ7Y2xpcC1ydWxlOmV2ZW5vZGQ7c3Ryb2tlLWxpbmVqb2luOnJvdW5kO3N0cm9rZS1taXRlcmxpbWl0OjI7Ij48Zz48cGF0aCBkPSJNNDguNjk2LDYuNTAzYzAuMzgzLC0xLjI3MyAxLjcyNywtMS45OTUgMi45OTksLTEuNjEyYzEuMjcyLDAuMzgzIDEuOTk0LDEuNzI3IDEuNjExLDIuOTk5bC01LjAxLDE2LjY1Yy0wLjM4MywxLjI3MiAtMS43MjcsMS45OTQgLTIuOTk5LDEuNjExYy0xLjI3MiwtMC4zODMgLTEuOTk0LC0xLjcyNyAtMS42MTEsLTIuOTk5bDUuMDEsLTE2LjY0OVoiIHN0eWxlPSJmaWxsOiNhMGE1YWE7Ii8+PHBhdGggZD0iTTUzLjQ3MSw3LjAxOGMwLC0xLjMyNSAtMS4wNzcsLTIuNDAyIC0yLjQwMiwtMi40MDJsLTMzLjgzMSwwYy0xLjMyNiwwIC0yLjQwMiwxLjA3NyAtMi40MDIsMi40MDJjMCwxLjMyNiAxLjA3NiwyLjQwMyAyLjQwMiwyLjQwM2wzMy44MzEsMGMxLjMyNSwwIDIuNDAyLC0xLjA3NyAyLjQwMiwtMi40MDNaIiBzdHlsZT0iZmlsbDojYTBhNWFhOyIvPjxwYXRoIGQ9Ik00OC41OTgsMjMuMjk3YzAuMzUsLTEuMjgyIC0xLjg5OSwtMS43NjcgLTMuMjI4LC0xLjc2N2wtMjIuOTMyLDBjLTEuMzI5LDAgLTIuNDY4LDEuMDggLTIuNDA4LDIuNDA4YzAuMDU3LDEuMjQ2IDAuNDk2LDIuMTg4IDIuNDA4LDIuNDA3bDIyLjkzMiwwYzIuNDksLTAuMDA0IDIuNzk5LC0xLjQ3MyAzLjIyOCwtMy4wNDhaIiBzdHlsZT0iZmlsbDojYTBhNWFhOyIvPjxwYXRoIGQ9Ik0xOS41MSw2LjUyNGMtMC4zOTEsLTEuMjk3IC0xLjc2MiwtMi4wMzQgLTMuMDU5LC0xLjY0M2MtMS4yOTgsMC4zOSAtMi4wMzUsMS43NjEgLTEuNjQ0LDMuMDU5bDQuOTYxLDE2LjQ4NWMwLjM5MSwxLjI5OCAxLjc2MSwyLjAzNCAzLjA1OSwxLjY0NGMxLjI5OCwtMC4zOTEgMi4wMzQsLTEuNzYxIDEuNjQ0LC0zLjA1OWwtNC45NjEsLTE2LjQ4NloiIHN0eWxlPSJmaWxsOiNhMGE1YWE7Ii8+PHBhdGggZD0iTTguOTg4LDIuNDA1YzAsLTEuMzI3IDAuMDQ3LC0yLjQyNCAtMS42NSwtMi40MDVsLTYuNjU1LDBjLTAuOTExLDAgLTAuOTExLDQuODExIDAsNC44MTFsNi42NTUsMGMwLjkxMSwwIDEuNjUsLTEuMDc4IDEuNjUsLTIuNDA2WiIgc3R5bGU9ImZpbGw6I2EwYTVhYTsiLz48cGF0aCBkPSJNMTYuMTQ2LDMzLjg0N2MtMC44NzQsLTAuMTUyIC0xLjYzMiwtMC43OCAtMS45MDQsLTEuNjg2bC04LjczOSwtMjkuMDM4Yy0wLjM4NCwtMS4yNzggMC4xOCwtMi41ODkgMS40NTcsLTIuOTc0YzEuMjc4LC0wLjM4NCAyLjc4OCwwLjMwMyAzLjE3MiwxLjU4MWw3LjMwOSwyNC4yODVjMS4wMzMsMy4wMzYgMS41OTUsMy4wNjQgNS43MTksMy4wNjljMC4wMjYsMCAyNC41NjEsMC4wMTEgMjQuNTYxLDAuMDExYzAuOTYzLDAgMC45NjMsNC44NCAwLDQuODRjMCwwIC0zMS4zOTQsLTAuMDMxIC0zMS41NzUsLTAuMDg4WiIgc3R5bGU9ImZpbGw6I2EwYTVhYTsiLz48L2c+PC9zdmc+',
			'55.8'
		);

		add_submenu_page( 'foosales-settings', $this->foosales_phrases['title_foosales_settings'], $this->foosales_phrases['menu_settings'], 'edit_posts', 'foosales-settings', array( $this, 'display_settings_page' ) );
		add_submenu_page( 'foosales-settings', $this->foosales_phrases['menu_point_of_sale'], $this->foosales_phrases['menu_point_of_sale'], 'manage_options', 'https://web.foosales.com' );
		add_submenu_page( 'foosales-settings', $this->foosales_phrases['title_foosales_import'], $this->foosales_phrases['menu_import'], 'manage_options', 'foosales-import', array( $this, 'add_foosales_import_page' ) );

	}

	/**
	 * Register FooSales importer.
	 *
	 * @since 1.12.0
	 */
	public function foosales_register_importer() {

		register_importer( 'foosales-import', $this->foosales_phrases['title_foosales_import'], $this->foosales_phrases['description_foosales_import'], array( $this, 'redirect_to_import' ) );

	}

	/**
	 * Conditionally disable new order emails for orders captured in FooSales.
	 *
	 * @since 1.15.0
	 * @param bool     $whether_enabled Whether the sending of new order admin emails is enabled or not.
	 * @param WC_Order $order The WooCommerce order to check.
	 */
	public function foosales_conditionally_send_wc_email( $whether_enabled, $order ) {

		if ( ! empty( $order ) && 'foosales_app' === get_post_meta( $order->get_id(), '_foosales_order_source', true ) ) {

			$disable_new_order_emails = 'yes' === get_option( 'globalFooSalesDisableNewOrderEmails', '' );

			if ( $disable_new_order_emails ) {

				return false;

			}
		}

		return $whether_enabled;

	}

	/**
	 * Add POS payment methods to the WooCommerce Payment Methods
	 *
	 * @since 1.16.1
	 */
	public function fsfwc_payment_methods_init() {

		$foosales_config = new FooSales_Config();

		require_once $foosales_config->class_path . 'class-foosales-payment-method.php';

		$this->foosales_payment_method_ids = array(
			'cash',
			'card',
			'direct-bank-transfer',
			'check-payment',
			'cash-on-delivery',
			'square-manual',
			'square-terminal',
			'square-reader',
			'other',
		);

	}

	/**
	 * Add the POS payment methods.
	 *
	 * @since 1.16.1
	 * @param array $methods An array of payment methods.
	 */
	public function fsfwc_add_payment_options_class( $methods ) {

		foreach ( $this->foosales_payment_method_ids as $foosales_payment_method_id ) {

			$foosales_payment_method_options = array(
				'id'                   => 'foosales-' . $foosales_payment_method_id,
				'title'                => $this->foosales_phrases[ 'title_payment_method_' . str_replace( '-', '_', $foosales_payment_method_id ) ],
				'description'          => $this->foosales_phrases[ 'description_payment_method_' . str_replace( '-', '_', $foosales_payment_method_id ) ],
				'enable_disable_label' => $this->foosales_phrases[ 'label_enable_disable_' . str_replace( '-', '_', $foosales_payment_method_id ) ],
			);

			// Custom descriptions for Square payment methods.
			if ( strpos( $foosales_payment_method_id, 'square' ) === 0 ) {
				$foosales_payment_method_options['description'] = sprintf( $foosales_payment_method_options['description'], '<a href="' . admin_url( 'admin.php?page=foosales-settings&tab=integration' ) . '">', '&nbsp;&rarr;</a>' );
			}

			$methods[] = FooSales_Payment_Method::with_options( $foosales_payment_method_options );

			unset( $foosales_payment_method_options );
		}

		return $methods;

	}

	/**
	 * Remove POS payment methods from checkout
	 *
	 * @since 1.16.1
	 * @param array $available_gateways list of gateways used by WooCommerce.
	 */
	public function fsfwc_conditional_payment_gateways( $available_gateways ) {

		if ( is_checkout() ) {
			foreach ( $this->foosales_payment_method_ids as $foosales_payment_method_id ) {

				unset( $available_gateways[ 'foosales-' . $foosales_payment_method_id ] );

			}
		}

		return $available_gateways;
	}

	/**
	 * Filter callback to output the current plugin version in the API helper file.
	 *
	 * @since 1.16.2
	 * @param string $empty A string value that is not used to determine the plugin version.
	 */
	public function fsfwc_current_plugin_version( $empty ) {

		$temp_config = new FooSales_Config();

		return (string) $temp_config->plugin_version;

	}

}

new FooSales();
