<?php
/**
 * Plugin Name: FooSales — Point of Sale (POS) for WooCommerce
 * Description: FooSales POS is a point of sale (POS) system for WooCommerce that turns any computer, iPad or Android tablet into a physical retail platform. FooSales POS apps connect to your WooCommerce store using the FooSales POS plugin and make it possible to sell your products in person while accepting payments using Square hardware and the Square point of sale (Square POS) app.
 * Version: 1.18.0
 * Author: FooSales
 * Author URI: https://www.foosales.com
 * Developer: FooSales
 * Developer URI: https://www.foosales.com
 * Plugin URI: https://www.foosales.com
 * Text Domain: foosales
 *
 * WC requires at least: 3.9.0
 * WC tested up to: 5.2.2
 *
 * Copyright: 2009-2021 Grenade Technologies.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package foosales
 */

// Include main FooSales config file.
require_once 'class-foosales-config.php';
$foosales_config = new FooSales_Config();

// Include main FooSales class file.
require_once $foosales_config->class_path . 'class-foosales.php';

/**
 * Redirect to Settings upon plugin activation.
 *
 * @since 1.0.0
 * @param string $plugin A reference to this plugin.
 */
function fsfwc_activation_redirect( $plugin ) {

	if ( plugin_basename( __FILE__ ) === $plugin ) {

		$role = get_role( 'administrator' );

		$role->add_cap( 'publish_foosales' );

		wp_safe_redirect( admin_url( 'admin.php?page=foosales-settings' ) );

		exit;

	}

}

add_action( 'activated_plugin', 'fsfwc_activation_redirect' );

/**
 * Uninstall FooSales.
 *
 * @since 1.16.0
 */
function fsfwc_uninstall() {

	delete_option( 'globalFooSalesProductsToDisplay' );
	delete_option( 'globalFooSalesProductCategories' );
	delete_option( 'globalFooSalesProductsStatus' );
	delete_option( 'globalFooSalesProductsOnlyInStock' );
	delete_option( 'globalFooSalesProductsPerPage' );
	delete_option( 'globalFooSalesProductsShowAttributeLabels' );

	delete_option( 'globalFooSalesOrdersToLoad' );
	delete_option( 'globalFooSalesDisableNewOrderEmails' );

	delete_option( 'globalFooSalesStoreLogoURL' );
	delete_option( 'globalFooSalesStoreName' );
	delete_option( 'globalFooSalesHeaderContent' );
	delete_option( 'globalFooSalesReceiptTitle' );
	delete_option( 'globalFooSalesOrderNumberPrefix' );
	delete_option( 'globalFooSalesProductColumnTitle' );
	delete_option( 'globalFooSalesQuantityColumnTitle' );
	delete_option( 'globalFooSalesPriceColumnTitle' );
	delete_option( 'globalFooSalesSubtotalColumnTitle' );
	delete_option( 'globalFooSalesInclusiveAbbreviation' );
	delete_option( 'globalFooSalesExclusiveAbbreviation' );
	delete_option( 'globalFooSalesDiscountsTitle' );
	delete_option( 'globalFooSalesRefundsTitle' );
	delete_option( 'globalFooSalesTaxTitle' );
	delete_option( 'globalFooSalesTotalTitle' );
	delete_option( 'globalFooSalesPaymentMethodTitle' );
	delete_option( 'globalFooSalesBillingAddressTitle' );
	delete_option( 'globalFooSalesShippingAddressTitle' );
	delete_option( 'globalFooSalesFooterContent' );
	delete_option( 'globalFooSalesReceiptShowLogo' );

	delete_option( 'globalFooSalesSquareApplicationID' );
	delete_option( 'globalFooSalesSquareAccessToken' );

}

register_uninstall_hook( __FILE__, 'fsfwc_uninstall' );

/**
 * Remove admin capabilities.
 *
 * @since 1.0.0
 */
function fsfwc_remove_admin_caps() {

	$delete_caps = array(
		'publish_foosales',
	);

	global $wp_roles;

	foreach ( $delete_caps as $cap ) {

		foreach ( array_keys( $wp_roles->roles ) as $role ) {

			$wp_roles->remove_cap( $role, $cap );

		}
	}
}

register_deactivation_hook( __FILE__, 'fsfwc_remove_admin_caps' );





