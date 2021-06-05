<?php
/**
 * The main FooSales config file of the plugin
 *
 * @link https://www.foosales.com
 * @since 1.0.0
 * @package foosales
 */

/**
 * This class contains properties used throughout the plugin.
 *
 * @since 1.0.0
 * @package foosales
 */
class FooSales_Config {
	/**
	 * The plugin version.
	 *
	 * @since 1.0.0
	 * @var string $plugin_version The current plugin version.
	 */
	public $plugin_version;

	/**
	 * The plugin class path.
	 *
	 * @since 1.0.0
	 * @var string $class_path The current plugin class path.
	 */
	public $class_path;

	/**
	 * The plugin template path.
	 *
	 * @since 1.0.0
	 * @var string $template_path The current plugin template path.
	 */
	public $template_path;

	/**
	 * The plugin scripts path.
	 *
	 * @since 1.0.0
	 * @var string $scripts_path The current plugin scripts path.
	 */
	public $scripts_path;

	/**
	 * The plugin styles path.
	 *
	 * @since 1.0.0
	 * @var string $styles_path The current plugin styles path.
	 */
	public $styles_path;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$this->plugin_version = '1.18.0';
		$this->class_path     = plugin_dir_path( __FILE__ ) . 'classes/';
		$this->styles_path    = plugin_dir_url( __FILE__ ) . 'css/';
		$this->helper_path    = plugin_dir_path( __FILE__ ) . 'helpers/';
		$this->scripts_path   = plugin_dir_url( __FILE__ ) . 'js/';
		$this->template_path  = plugin_dir_path( __FILE__ ) . 'templates/';

	}

}
