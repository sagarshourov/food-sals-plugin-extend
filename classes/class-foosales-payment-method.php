<?php
/**
 * Payment Method class that adds a new payment method to WooCommerce Payment methods.
 *
 * @link    https://www.foosales.com
 * @since   1.16.1
 * @package foosales
 */

/**
 * This class contains payment method funtionality and settings.
 *
 * @link       https://www.foosales.com
 * @since      1.16.1
 *
 * @package    foosales
 */
class FooSales_Payment_Method extends WC_Payment_Gateway {

	/**
	 * The FooSales phrases helper.
	 *
	 * @since    1.16.1
	 * @var array $foosales_phrases The current phrases helper array.
	 */
	private $foosales_phrases;

	/**
	 * The tooltip for enabling/disabling the payment method.
	 *
	 * @since    1.16.1
	 * @var array $enable_disable_label The current tooltip for enabling/disabling the payment method.
	 */
	private $enable_disable_label;

	/**
	 * Static factory method to a create payment method.
	 *
	 * @since 1.16.1
	 * @param array $foosales_payment_method_options Key/value pairs for the payment method ID, title, description and enable/disable label.
	 *
	 * @return FooSales_Payment_Method
	 */
	public static function with_options( $foosales_payment_method_options ) {

		if ( ! isset( $foosales_payment_method_options ) ) {
			return;
		}

		return new self( $foosales_payment_method_options );

	}

	/**
	 * Constructor for the payment method gateway.
	 *
	 * @since 1.16.0
	 * @param array $foosales_payment_method_options Key/value pairs for the payment method ID, title, description and enable/disable label.
	 */
	public function __construct( $foosales_payment_method_options ) {

		$foosales_config = new FooSales_Config();

		// FooSales phrases.
		require $foosales_config->helper_path . 'foosales-phrases-helper.php';

		$this->foosales_phrases     = $foosales_phrases;
		$this->domain               = $foosales_payment_method_options['id'];
		$this->id                   = $foosales_payment_method_options['id'];
		$this->has_fields           = false;
		$this->method_description   = $foosales_payment_method_options['description'];
		$this->enable_disable_label = $foosales_payment_method_options['enable_disable_label'];

		if ( $this->get_option( 'title' ) !== '' ) :
			$this->method_title = $this->get_option( 'title' );
		else :
			$this->method_title = $foosales_payment_method_options['title'];
		endif;

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title        = $this->foosales_phrases['label_payment_method_pos_only'];
		$this->availability = 'foosales_app';
		$this->description  = $this->get_option( 'description' );

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

	}

	/**
	 * Initialise Gateway Settings the 'Other Payment Method' Form Fields.
	 *
	 * @since 1.16.0
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'   => $this->foosales_phrases['title_payment_method_enable_disable'],
				'type'    => 'checkbox',
				'label'   => $this->enable_disable_label,
				'default' => 'yes',
			),
			'title'   => array(
				'title'       => $this->foosales_phrases['title_payment_method_custom_title'],
				'type'        => 'text',
				'description' => $this->foosales_phrases['title_payment_method_custom_title_tooltip'],
				'default'     => '',
				'desc_tip'    => true,
			),
		);
	}
}
