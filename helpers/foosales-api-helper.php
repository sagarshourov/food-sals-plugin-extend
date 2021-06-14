<?php

/**
 * API helper functions used by the REST API and XML-RPC classes.
 *
 * @link    https://www.foosales.com
 * @since   1.0.0
 * @package foosales
 */

/**
 * Get all tax rates.
 *
 * @since 1.14.0
 */
function fsfwc_do_get_all_tax_rates() {

    $wc_tax = new WC_Tax();

    $tax_classes_temp = $wc_tax->get_tax_classes();

    $tax_classes = array_merge(array(''), $tax_classes_temp);

    $tax_rates = array();

    foreach ($tax_classes as $tax_class) {
        $rates = $wc_tax->get_rates_for_tax_class($tax_class);

        foreach ($rates as $rate) {
            $tax_rates[] = array(
                'trid' => $rate->tax_rate_id,
                'trc' => $rate->tax_rate_country,
                'trs' => $rate->tax_rate_state,
                'trn' => $rate->tax_rate_name,
                'trr' => $rate->tax_rate,
                'trp' => $rate->tax_rate_priority,
                'trcm' => $rate->tax_rate_compound,
                'trsh' => $rate->tax_rate_shipping,
                'tro' => $rate->tax_rate_order,
                'trcl' => '' !== $rate->tax_rate_class ? $rate->tax_rate_class : 'standard',
                'trpc' => $rate->postcode_count,
                'trcc' => $rate->city_count,
            );
        }
    }

    return $tax_rates;
}

/**
 * Get payment methods.
 *
 * @since 1.18.0
 * @param bool $admin Specify whether the returned result will only be used for display purposes in the admin area.
 */
function fsfwc_do_get_all_payment_methods($admin = false) {

    $payment_methods = array();

    $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();

    // Loop through Woocommerce available payment gateways.
    foreach ($payment_gateways as $gateway_id => $gateway) {
        if ('foosales_app' === $gateway->availability) {
            $payment_method_key = str_replace('-', '_', $gateway->domain);

            if ($admin) {
                $payment_methods[$payment_method_key] = $gateway->method_title;

                if ('foosales_square_reader' === $payment_method_key) {
                    $payment_methods['foosales_square'] = $gateway->method_title;
                }
            } else {
                $payment_methods[] = array(
                    'pmk' => $payment_method_key,
                    'pmt' => $gateway->method_title,
                );
            }
        }
    }

    return $payment_methods;
}

/**
 * Get product price excluding tax.
 *
 * @since 1.14.0
 * @param WC_Product $product The WooCommerce product.
 * @param array      $args Additional arguments.
 * @param string     $type The type of product.
 */
function fsfwc_get_price_excluding_tax($product, $args = array(), $type = '') {
    $price = $product->get_price();

    if ('regular' === $type) {
        $price = $product->get_regular_price();
    } elseif ('sale' === $type) {
        $price = $product->get_sale_price();
    }

    $args = wp_parse_args(
            $args,
            array(
                'qty' => '',
                'price' => '',
            )
    );

    $price = '' !== $args['price'] ? max(0.0, (float) $args['price']) : $price;
    $qty = '' !== $args['qty'] ? max(0.0, (float) $args['qty']) : 1;

    if ('' === $price) {
        return '';
    } elseif (empty($qty)) {
        return 0.0;
    }

    $line_price = $price * $qty;

    if ($product->is_taxable() && wc_prices_include_tax()) {
        $tax_rates = WC_Tax::get_rates($product->get_tax_class());
        $base_tax_rates = WC_Tax::get_base_tax_rates($product->get_tax_class('unfiltered'));
        $remove_taxes = apply_filters('woocommerce_adjust_non_base_location_prices', true) ? WC_Tax::calc_tax($line_price, $base_tax_rates, true) : WC_Tax::calc_tax($line_price, $tax_rates, true);
        $return_price = $line_price - array_sum($remove_taxes); // Unrounded since we're dealing with tax inclusive prices. Matches logic in cart-totals class. @see adjust_non_base_location_price.
    } else {
        $return_price = $line_price;
    }

    return apply_filters('woocommerce_get_price_excluding_tax', $return_price, $qty, $product);
}

/**
 * Get product price including tax.
 *
 * @since 1.14.0
 * @param WC_Product $product The WooCommerce product.
 * @param string     $type The type of product.
 */
function fsfwc_get_price_including_tax($product, $type = '') {
    $price = $product->get_price();

    if ('regular' === $type) {
        $price = $product->get_regular_price();
    } elseif ('sale' === $type) {
        $price = $product->get_sale_price();
    }

    $qty = 1;

    if ('' === $price) {
        return '';
    } elseif (empty($qty)) {
        return 0.0;
    }

    $line_price = $price * $qty;
    $return_price = $line_price;

    if ($product->is_taxable()) {
        if (!wc_prices_include_tax()) {
            $tax_rates = WC_Tax::get_rates($product->get_tax_class());
            $taxes = WC_Tax::calc_tax($line_price, $tax_rates, false);

            $taxes_total = array_sum($taxes);

            $return_price = $line_price + $taxes_total;
        } else {
            $tax_rates = WC_Tax::get_rates($product->get_tax_class());
            $base_tax_rates = WC_Tax::get_base_tax_rates($product->get_tax_class('unfiltered'));

            /**
             * If the customer is excempt from VAT, remove the taxes here.
             * Either remove the base or the user taxes depending on woocommerce_adjust_non_base_location_prices setting.
             */
            if (!empty(WC()->customer) && WC()->customer->get_is_vat_exempt()) { // @codingStandardsIgnoreLine.
                $remove_taxes = apply_filters('woocommerce_adjust_non_base_location_prices', true) ? WC_Tax::calc_tax($line_price, $base_tax_rates, true) : WC_Tax::calc_tax($line_price, $tax_rates, true);

                $remove_taxes_total = array_sum($remove_taxes);

                $return_price = $line_price - $remove_taxes_total;

                /**
                 * The woocommerce_adjust_non_base_location_prices filter can stop base taxes being taken off when dealing with out of base locations.
                 * e.g. If a product costs 10 including tax, all users will pay 10 regardless of location and taxes.
                 * This feature is experimental @since 2.4.7 and may change in the future. Use at your risk.
                 */
            } elseif ($tax_rates !== $base_tax_rates && apply_filters('woocommerce_adjust_non_base_location_prices', true)) {
                $base_taxes = WC_Tax::calc_tax($line_price, $base_tax_rates, true);
                $modded_taxes = WC_Tax::calc_tax($line_price - array_sum($base_taxes), $tax_rates, false);

                $base_taxes_total = array_sum($base_taxes);
                $modded_taxes_total = array_sum($modded_taxes);

                $return_price = $line_price - $base_taxes_total + $modded_taxes_total;
            }
        }
    }
    return apply_filters('woocommerce_get_price_including_tax', $return_price, $qty, $product);
}

/**
 * Get payment method value from key.
 *
 * @since 1.14.0
 * @param string $payment_method_key The key of the payment method.
 */
function fsfwc_get_payment_method_from_key($payment_method_key = '') {

    if (class_exists('FooSales_Config')) {

        $foosales_config = new FooSales_Config();

        require $foosales_config->helper_path . 'foosales-phrases-helper.php';
    } else {

        require plugin_dir_path(__FILE__) . 'foosaleswc-phrases-helper.php';
    }

    $payment_methods = fsfwc_do_get_all_payment_methods(true);

    return !empty($payment_methods[$payment_method_key]) ? $payment_methods[$payment_method_key] : $foosales_phrases['meta_value_payment_method_' . str_replace('foosales_', '', $payment_method_key)];
}

/**
 * Get payment method key from value.
 *
 * @since 1.14.0
 * @param string $payment_method The payment method.
 */
function fsfwc_get_payment_method_key_from_value($payment_method = '') {
    $payment_method_key = '';

    if ('Cash Payment' === $payment_method) {
        $payment_method_key = 'foosales_cash';
    } elseif ('Card Payment' === $payment_method) {
        $payment_method_key = 'foosales_card';
    } elseif ('Direct Bank Transfer' === $payment_method) {
        $payment_method_key = 'foosales_direct_bank_transfer';
    } elseif ('Check Payment' === $payment_method) {
        $payment_method_key = 'foosales_check_payment';
    } elseif ('Cash on Delivery' === $payment_method) {
        $payment_method_key = 'foosales_cash_on_delivery';
    } elseif ('Square Manual Payment' === $payment_method) {
        $payment_method_key = 'foosales_square_manual';
    } elseif ('Square Terminal Payment' === $payment_method) {
        $payment_method_key = 'foosales_square_terminal';
    } elseif ('Square' === $payment_method || 'Square Reader Payment' === $payment_method) {
        $payment_method_key = 'foosales_square_reader';
    } elseif ('Other Payment Method' === $payment_method) {
        $payment_method_key = 'foosales_other';
    } else {
        $payment_methods = fsfwc_do_get_all_payment_methods(true);

        foreach ($payment_methods as $temp_payment_method_key => $payment_method_value) {
            if ($payment_method_value === $payment_method) {
                $payment_method_key = $temp_payment_method_key;

                break;
            }
        }
    }

    return $payment_method_key;
}

/**
 * Sorting function for product categories.
 *
 * @since 1.14.0
 * @param array $a The one category object to compare.
 * @param array $b The other category object to compare.
 */
function fsfwc_do_compare_categories($a, $b) {
    return strcmp($a['pcn'], $b['pcn']);
}

/**
 * Get all product categories.
 *
 * @since 1.14.0
 */
function fsfwc_do_get_all_product_categories() {

    $cats = get_terms('product_cat');

    $temp_categories = array();

    foreach ($cats as $cat) {
        $category = array();

        $category['pcid'] = (string) $cat->term_id;

        $temp_display_name = '';

        if ($cat->parent > 0) {
            foreach ($cats as $parent_cat) {
                if ($parent_cat->term_id === $cat->parent) {
                    $temp_display_name .= htmlspecialchars_decode($parent_cat->name) . ' - ';

                    break;
                }
            }
        }

        $temp_display_name .= htmlspecialchars_decode($cat->name);

        $category['pcn'] = (string) $temp_display_name;

        $temp_categories[] = $category;

        $category = null;
        $temp_display_name = null;

        unset($category, $temp_display_name);
    }

    uasort($temp_categories, 'fsfwc_do_compare_categories');

    $categories = array();

    foreach ($temp_categories as $key => $category) {
        $categories[] = $category;
    }

    $cats = null;
    $temp_categories = null;

    unset($cats, $temp_categories);

    return $categories;
}

/**
 * Fetch all product images.
 *
 * @since 1.14.0
 */
function fsfwc_do_get_all_product_images() {

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'orderby' => 'title',
        'order' => 'ASC',
        'post_status' => get_option('globalFooSalesProductsStatus', array('publish')),
    );

    if ('cat' === (string) get_option('globalFooSalesProductsToDisplay', '')) {
        $args['tax_query'] = array(// phpcs:ignore WordPress.DB.SlowDBQuery
            array(
                'taxonomy' => 'product_cat',
                'terms' => get_option('globalFooSalesProductCategories'),
                'operator' => 'IN',
            ),
        );
    }

    if ('yes' === (string) get_option('globalFooSalesProductsOnlyInStock', '')) {
        $args['meta_query'] = array(// phpcs:ignore WordPress.DB.SlowDBQuery
            array(
                'key' => '_stock_status',
                'value' => 'instock',
            ),
            array(
                'key' => '_backorders',
                'value' => 'no',
            ),
        );
    }

    $query = new WP_Query($args);

    $args = null;

    unset($args);

    $product_image_data = array();

    $product_image_data['total_product_images'] = $query->post_count . '_total_product_images';
    $product_image_data['product_images'] = array();

    foreach ($query->posts as $post_id) {
        $product_image = preg_replace_callback(
                '/[^\x20-\x7f]/',
                function($match) {
            return rawurlencode($match[0]);
        },
                (string) get_the_post_thumbnail_url($post_id, 'thumbnail')
        );

        $product_image_data['product_images'][] = array(
            'pid' => (string) $post_id,
            'pi' => $product_image,
        );
    }

    $query = null;

    unset($query);

    return $product_image_data;
}

/**
 * Fetch all products.
 *
 * @since 1.14.0
 * @param int $offset The offset from where to start adding fetched products.
 */
function fsfwc_do_get_all_products($offset = 0) {

    $product_data = array();

    $max_products = get_option('globalFooSalesProductsPerPage', '500');

    $args = array(
        'post_type' => 'wpcargo_shipment',
        'posts_per_page' => $max_products,
        'offset' => $offset * $max_products,
        'fields' => 'ids',
        'no_found_rows' => true,
        'orderby' => 'title',
        'order' => 'ASC',
        'post_status' => get_option('globalFooSalesProductsStatus', array('publish')),
    );

    if ('cat' === (string) get_option('globalFooSalesProductsToDisplay', '')) {
        $args['tax_query'] = array(// phpcs:ignore WordPress.DB.SlowDBQuery
            array(
                'taxonomy' => 'product_cat',
                'terms' => get_option('globalFooSalesProductCategories'),
                'operator' => 'IN',
            ),
        );
    }

    if ('yes' === (string) get_option('globalFooSalesProductsOnlyInStock', '')) {
        $args['meta_query'] = array(// phpcs:ignore WordPress.DB.SlowDBQuery
            array(
                'key' => '_stock_status',
                'value' => 'instock',
            ),
            array(
                'key' => '_backorders',
                'value' => 'no',
            ),
        );
    }

    $query = new WP_Query($args);

    $args['posts_per_page'] = -1;
    $args['offset'] = 0;

    $count_query = new WP_Query($args);

    $args = null;

    unset($args);

    $product_data['total_products'] = $count_query->post_count . '_total_products';

    $count_query = null;

    unset($count_query);

    $product_data['sale_product_ids'] = implode(', ', wc_get_product_ids_on_sale());

    $products = array();

    $wc_tax = new WC_Tax();

    $cat_names = array();

    $shop_tax = ( 'yes' === (string) get_option('woocommerce_calc_taxes', '') ) ? (string) get_option('woocommerce_tax_display_shop', '') : 'incl';



    foreach ($query->posts as $post_id) {

        $products[] = fsfwc_do_get_single_product($post_id, $wc_tax, $cat_names, $shop_tax);
    }

    $query = null;
    $cat_names = null;
    $wc_tax = null;

    unset($query, $cat_names, $wc_tax);

    //$product_data['products'] = $products;


    $jayParsedAry = [
        "total_products" => "1_total_products",
        "sale_product_ids" => "",
        "products" => $products,
    ];

    return $jayParsedAry;
}

/**
 * Output single product.
 *
 * @since 1.14.0
 * @param int    $post_id The WordPress post ID.
 * @param WC_Tax $wc_tax The WooCommerce tax object.
 * @param array  $cat_names An array of category names to add to the product if it matches.
 * @param string $shop_tax Whether the shop tax is incl or excl.
 * 
 * 
 * 
 */
function fsfwc_do_get_single_product($post_id, &$wc_tax, &$cat_names, $shop_tax = 'incl') {

    $product_title = (string) htmlspecialchars_decode(get_post_field('post_title', $post_id));

    $product_price = get_post_meta($post_id, 'item_cost', true);



    $product = [
        "pid" => (string) $post_id,
        "pt" => $product_title,
        "pd" => "",
        "ppi" => $product_price,
        "ppe" => $product_price,
        "prpi" => $product_price,
        "prpe" => $product_price,
        "pspi" => "$".$product_price,
        "pspe" => "$".$product_price,
        "pph" => "",
        "ptc" => "standard",
        "ptr" => "",
        "psm" => "",
        "ps" => "",
        "pss" => "instock",
        "psku" => "",
        "psi" => "",
        "pv" => [
        ],
        "pc" => [
            [
                "pcid" => "509",
                "pcn" => "Specials"
            ]
        ]
    ];

    return $product;
}

function fsfwc_do_get_single_products($post_id, &$wc_tax, &$cat_names, $shop_tax = 'incl') {

    $product_data = array();

    $wc_product = wc_get_product($post_id);

    $product_data['pid'] = (string) $post_id;

    $product_title = (string) htmlspecialchars_decode(get_post_field('post_title', $post_id));

    $product_data['pt'] = $product_title;
    $product_data['pd'] = (string) htmlspecialchars_decode(get_post_field('post_content', $post_id));

    $product_data['ppi'] = (string) fsfwc_get_price_including_tax($wc_product);
    $product_data['ppe'] = (string) fsfwc_get_price_excluding_tax($wc_product);

    $product_data['prpi'] = (string) fsfwc_get_price_including_tax($wc_product, 'regular');
    $product_data['prpe'] = (string) fsfwc_get_price_excluding_tax($wc_product, array(), 'regular');

    $product_data['pspi'] = (string) fsfwc_get_price_including_tax($wc_product, 'sale');
    $product_data['pspe'] = (string) fsfwc_get_price_excluding_tax($wc_product, array(), 'sale');
    $product_data['pph'] = (string) $wc_product->get_price_html();

    $product_data['ptc'] = '' !== (string) $wc_product->get_tax_class() ? $wc_product->get_tax_class() : 'standard';

    $tax_rate = 0.0;

    $tax_rates = $wc_tax->get_rates_for_tax_class($wc_product->get_tax_class());

    if (!empty($tax_rates)) {
        $tax_rate_item = reset($tax_rates);

        $tax_rate = (string) $tax_rate_item->tax_rate;

        $tax_rate_item = null;

        unset($tax_rate_item);
    }

    $tax_rates = null;

    unset($tax_rates);

    $product_data['ptr'] = (string) $tax_rate;

    $tax_rate = null;

    unset($tax_rate);

    $product_data['psm'] = $wc_product->get_manage_stock() ? '1' : '0';
    $product_data['ps'] = $wc_product->get_stock_quantity() !== null ? (string) $wc_product->get_stock_quantity() : '0';
    $product_data['pss'] = (string) $wc_product->get_stock_status();
    $product_data['psku'] = (string) $wc_product->get_sku();
    $product_data['psi'] = $wc_product->get_sold_individually() ? '1' : '0';

    $product_variations = array();

    if ($wc_product->is_type('variable')) {

        $atts = $wc_product->get_variation_attributes();

        $attributes = array();

        foreach ($atts as $att_name => $att_val) {
            $attributes[] = $att_name;
        }

        $atts = null;

        unset($atts);

        $variations = $wc_product->get_available_variations();

        $show_attribute_labels = get_option('globalFooSalesProductsShowAttributeLabels', '');

        foreach ($variations as $variation) {

            $product_variation = array();

            $product_variation['pvid'] = (string) $variation['variation_id'];
            $product_variation['pt'] = $product_title;

            $variation_attributes = '';
            $variation_attribute_count = 0;

            foreach ($variation['attributes'] as $variation_attribute_key => $variation_attribute_value) {

                $variation_attribute_label = '';

                if ('yes' === $show_attribute_labels) {
                    $variation_attribute_label = ucfirst($attributes[$variation_attribute_count]) . ': ';
                }

                $variation_attribute_count++;

                $variation_attributes .= $variation_attribute_label . ucfirst($variation_attribute_value);

                if ($variation_attribute_count < count($variation['attributes'])) {
                    $variation_attributes .= ', ';
                }
            }

            $product_variation['pva'] = $variation_attributes;

            $wc_product_variation = wc_get_product($variation['variation_id']);

            $product_variation['ptc'] = (string) $wc_product->get_tax_class() !== '' ? $wc_product_variation->get_tax_class() : 'standard';

            $tax_rate = 0.0;

            $tax_rates = $wc_tax->get_rates_for_tax_class($wc_product_variation->get_tax_class());

            if (!empty($tax_rates)) {
                $tax_rate_item = reset($tax_rates);

                $tax_rate = (float) $tax_rate_item->tax_rate;

                $tax_rate_item = null;

                unset($tax_rate_item);
            }

            $tax_rates = null;

            unset($tax_rates);

            $product_variation['ptr'] = (string) $tax_rate;

            $tax_rate = null;

            unset($tax_rate);

            $product_variation['ppi'] = (string) fsfwc_get_price_including_tax($wc_product_variation);
            $product_variation['ppe'] = (string) fsfwc_get_price_excluding_tax($wc_product_variation);

            $product_variation['prpi'] = (string) fsfwc_get_price_including_tax($wc_product_variation, 'regular');
            $product_variation['prpe'] = (string) fsfwc_get_price_excluding_tax($wc_product_variation, array(), 'regular');

            $product_variation['pspi'] = (string) fsfwc_get_price_including_tax($wc_product_variation, 'sale');
            $product_variation['pspe'] = (string) fsfwc_get_price_excluding_tax($wc_product_variation, array(), 'sale');
            $product_variation['pph'] = (string) $wc_product_variation->get_price_html();

            $product_variation['pi'] = (string) isset($variation['image']['thumb_src']) ? $variation['image']['thumb_src'] : '';

            $variation_manage_stock = $wc_product_variation->get_manage_stock();

            if ('parent' !== $variation_manage_stock) {
                $variation_manage_stock = $variation_manage_stock ? '1' : '0';
            }

            $product_variation['psm'] = $variation_manage_stock;
            $product_variation['ps'] = $wc_product_variation->get_stock_quantity() !== null ? (string) $wc_product_variation->get_stock_quantity() : '0';
            $product_variation['pss'] = (string) $wc_product_variation->get_stock_status();
            $product_variation['psku'] = (string) $wc_product_variation->get_sku();
            $product_variation['psi'] = $wc_product->get_sold_individually() ? '1' : '0';

            $wc_product_variation = null;

            unset($wc_product_variation);

            $product_variations[] = $product_variation;

            $product_variation = null;

            unset($product_variation);
        }
    }

    $product_data['pv'] = $product_variations;

    $wc_product = null;

    unset($wc_product);

    $product_categories = array();

    $cat_ids = wp_get_post_terms($post_id, 'product_cat', array('fields' => 'ids'));

    $last_cat_id = end($cat_ids);

    foreach ($cat_ids as $cat_id) {

        if (empty($cat_names[(string) $cat_id])) {
            $cat = get_term_by('id', $cat_id, 'product_cat');

            $cat_names[(string) $cat_id] = htmlspecialchars_decode($cat->name);

            $cat = null;

            unset($cat);
        }

        $product_categories[] = array(
            'pcid' => (string) $cat_id,
            'pcn' => (string) $cat_names[(string) $cat_id],
        );
    }

    $product_data['pc'] = $product_categories;

    $cat_ids = null;
    $last_cat_id = null;

    unset($cat_ids, $last_cat_id);

    // Check if FooEvents plugin is enabled.
    if (!function_exists('is_plugin_active') || !function_exists('is_plugin_active_for_network')) {

        require_once ABSPATH . '/wp-admin/includes/plugin.php';
    }

    if ('Event' === get_post_meta($post_id, 'WooCommerceEventsEvent', true) && ( is_plugin_active('fooevents/fooevents.php') || is_plugin_active_for_network('fooevents/fooevents.php') )) {

        $event = array();

        $event['cad'] = ( get_post_meta($post_id, 'WooCommerceEventsCaptureAttendeeDetails', true) === 'on' ? '1' : '0' );
        $event['cat'] = ( get_post_meta($post_id, 'WooCommerceEventsCaptureAttendeeTelephone', true) === 'on' ? '1' : '0' );
        $event['cac'] = ( get_post_meta($post_id, 'WooCommerceEventsCaptureAttendeeCompany', true) === 'on' ? '1' : '0' );
        $event['cades'] = ( get_post_meta($post_id, 'WooCommerceEventsCaptureAttendeeDesignation', true) === 'on' ? '1' : '0' );

        $custom_fields = array();

        if (is_plugin_active('fooevents_custom_attendee_fields/fooevents-custom-attendee-fields.php') || is_plugin_active_for_network('fooevents_custom_attendee_fields/fooevents-custom-attendee-fields.php')) {
            $fooevents_custom_attendee_fields = new Fooevents_Custom_Attendee_Fields();

            $fooevents_custom_attendee_fields_options_serialized = get_post_meta($post_id, 'fooevents_custom_attendee_fields_options_serialized', true);
            $fooevents_custom_attendee_fields_options = json_decode($fooevents_custom_attendee_fields_options_serialized, true);
            $fooevents_custom_attendee_fields_options = $fooevents_custom_attendee_fields->correct_legacy_options($fooevents_custom_attendee_fields_options);

            foreach ($fooevents_custom_attendee_fields_options as $key => $field_options) {

                $custom_fields[] = array(
                    'hash' => $key,
                    'label' => $field_options[$key . '_label'],
                    'type' => $field_options[$key . '_type'],
                    'options' => $field_options[$key . '_options'],
                    'req' => 'true' === $field_options[$key . '_req'] ? '1' : '0',
                );
            }
        }

        $event['caf'] = $custom_fields;

        $product_data['fee'] = $event;

        $custom_fields = null;
        $event = null;

        unset($custom_fields, $event);
    }

    return $product_data;
}

/**
 * Fetch all orders.
 *
 * @since 1.14.0
 * @param int $offset The offset from where to start adding fetched orders.
 */
function fsfwc_do_get_all_orders($offset = 0) {

    $order_data = array();

    $args = array(
        'posts_per_page' => -1,
        'post_type' => 'shop_order',
        'post_status' => array('wc-completed', 'wc-cancelled', 'wc-refunded'),
        'fields' => 'ids',
        'no_found_rows' => true,
        'orderby' => 'id',
        'order' => 'DESC',
    );

    $query = new WP_Query($args);

    $args = null;

    unset($args);

    $total_orders = $query->post_count;

    $orders_to_load = (int) get_option('globalFooSalesOrdersToLoad', '100');

    if (!empty($orders_to_load) && $orders_to_load > 0 && $total_orders > $orders_to_load) {
        $total_orders = $orders_to_load;
    }

    $order_data['total_orders'] = $total_orders . '_total_orders';
    $order_data['orders'] = array();

    $max_orders = 200;
    $orders_start = ( $offset * $max_orders ) + 1;
    $orders_end = ( $offset * $max_orders ) + $max_orders;

    if (!empty($orders_to_load) && $orders_to_load > 0) {
        if ($orders_end > $orders_to_load) {
            $orders_end = $orders_to_load;
        }
    }

    $order_count = 0;

    foreach ($query->posts as $post_id) {

        $order_count++;

        if ($order_count < $orders_start) {
            continue;
        }

        $wc_order = wc_get_order($post_id);

        $order_data['orders'][] = fsfwc_do_get_single_order($wc_order);

        $wc_order = null;

        unset($wc_order);

        if ($order_count === $orders_end) {
            break;
        }
    }

    $query = null;

    unset($query);

    return $order_data;
}

/**
 * Fetch all customers.
 *
 * @since 1.14.0
 * @param int $offset The offset from where to start adding fetched customers.
 */
function fsfwc_do_get_all_customers($offset = 0) {

    $customer_data = array();
    $max_users = 1000;

    $args = array(
        'role__in' => array('customer', 'subscriber'),
        'number' => $max_users,
        'offset' => $offset * $max_users,
        'fields' => 'ids',
        'no_found_rows' => true,
        'orderby' => 'user_email',
        'order' => 'ASC',
    );

    $query = new WP_User_Query($args);

    $args['number'] = -1;
    $args['offset'] = 0;

    $count_query = new WP_User_Query($args);

    $args = null;

    unset($args);

    $customer_data['total_customers'] = $count_query->total_users . '_total_customers';

    $count_query = null;

    unset($count_query);

    $customer_data['customers'] = array();

    $customer_ids = $query->get_results();

    foreach ($customer_ids as $customer_id) {

        $customer_data['customers'][] = fsfwc_get_single_customer($customer_id);
    }

    $query = null;

    unset($query);

    return $customer_data;
}

/**
 * Output single customer.
 *
 * @since 1.14.0
 * @param int $id The WordPress post ID of a customer.
 */
function fsfwc_get_single_customer($id) {

    $customer_data = array();

    $customer = get_userdata($id);

    $customer_data['cid'] = (string) $id;
    $customer_data['cfn'] = !empty($customer->first_name) && null !== $customer->first_name ? $customer->first_name : '';
    $customer_data['cln'] = !empty($customer->last_name) && null !== $customer->last_name ? $customer->last_name : '';
    $customer_data['cun'] = !empty($customer->user_login) && null !== $customer->user_login ? $customer->user_login : '';
    $customer_data['ce'] = !empty($customer->user_email) && null !== $customer->user_email ? $customer->user_email : '';

    $customer = null;

    unset($customer);

    $customer_fields = array(
        'cbfn' => 'billing_first_name',
        'cbln' => 'billing_last_name',
        'cbco' => 'billing_company',
        'cba1' => 'billing_address_1',
        'cba2' => 'billing_address_2',
        'cbc' => 'billing_city',
        'cbpo' => 'billing_postcode',
        'cbcu' => 'billing_country',
        'cbs' => 'billing_state',
        'cbph' => 'billing_phone',
        'cbe' => 'billing_email',
        'csfn' => 'shipping_first_name',
        'csln' => 'shipping_last_name',
        'csco' => 'shipping_company',
        'csa1' => 'shipping_address_1',
        'csa2' => 'shipping_address_2',
        'csc' => 'shipping_city',
        'cspo' => 'shipping_postcode',
        'cscu' => 'shipping_country',
        'css' => 'shipping_state',
    );

    $customer_meta = get_user_meta($id);

    foreach ($customer_fields as $customer_key => $meta_key) {
        $val = '';

        if (!empty($customer_meta[$meta_key])) {
            $val = $customer_meta[$meta_key][0];
        }

        $customer_data[$customer_key] = null !== $val ? $val : '';
    }

    return $customer_data;
}

/**
 * Fetch chunked data.
 *
 * @since 1.14.0
 * @param WP_User $user The WordPress user object.
 * @param string  $chunk The specific chunk of data to fetch.
 */
function fsfwc_fetch_chunk($user, $chunk) {

    $data = array();

    if ('store_settings' === $chunk) {

        $data['user'] = $user->data;

        $data['plugin_version'] = apply_filters('fsfwc_current_plugin_version', '');

        $temp_config = null;

        unset($temp_config);

        // Get app settings.
        $data['store_logo_url'] = trim(get_option('globalFooSalesStoreLogoURL', ''));
        $data['store_name'] = trim(get_option('globalFooSalesStoreName', ''));
        $data['order_limit'] = get_option('globalFooSalesOrdersToLoad', 'all');
        $data['receipt_header'] = trim(str_replace("\r\n", '<br />', get_option('globalFooSalesHeaderContent', '')));
        $data['receipt_title'] = trim(get_option('globalFooSalesReceiptTitle', ''));
        $data['receipt_order_number_prefix'] = trim(get_option('globalFooSalesOrderNumberPrefix', ''));
        $data['receipt_product_column_title'] = trim(get_option('globalFooSalesProductColumnTitle', ''));
        $data['receipt_quantity_column_title'] = trim(get_option('globalFooSalesQuantityColumnTitle', ''));
        $data['receipt_price_column_title'] = trim(get_option('globalFooSalesPriceColumnTitle', ''));
        $data['receipt_subtotal_column_title'] = trim(get_option('globalFooSalesSubtotalColumnTitle', ''));
        $data['receipt_inclusive_abbreviation'] = trim(get_option('globalFooSalesInclusiveAbbreviation', ''));
        $data['receipt_exclusive_abbreviation'] = trim(get_option('globalFooSalesExclusiveAbbreviation', ''));
        $data['receipt_discounts_title'] = trim(get_option('globalFooSalesDiscountsTitle', ''));
        $data['receipt_refunds_title'] = trim(get_option('globalFooSalesRefundsTitle', ''));
        $data['receipt_tax_title'] = trim(get_option('globalFooSalesTaxTitle', ''));
        $data['receipt_total_title'] = trim(get_option('globalFooSalesTotalTitle', ''));
        $data['receipt_payment_method'] = trim(get_option('globalFooSalesPaymentMethodTitle', ''));
        $data['receipt_billing_address_title'] = trim(get_option('globalFooSalesBillingAddressTitle', ''));
        $data['receipt_shipping_address_title'] = trim(get_option('globalFooSalesShippingAddressTitle', ''));
        $data['receipt_footer'] = trim(str_replace("\r\n", '<br />', get_option('globalFooSalesFooterContent', '')));
        $data['receipt_show_logo'] = get_option('globalFooSalesReceiptShowLogo', 'yes') === 'yes' ? '1' : '0';

        $square_application_id = get_option('globalFooSalesSquareApplicationID', '');

        $data['square_application_id'] = $square_application_id;

        $square_locations = array();

        if ('' !== $square_application_id) {

            $square_locations_result = fsfwc_get_square_locations();

            if ('success' === $square_locations_result['status']) {

                foreach ($square_locations_result['locations'] as $square_location) {

                    $square_locations[] = array(
                        'id' => $square_location['id'],
                        'name' => $square_location['name'],
                        'status' => $square_location['status'],
                        'currency' => $square_location['currency'],
                    );
                }
            }
        }

        $data['square_locations'] = $square_locations;

        // Check if FooEvents plugin is enabled.
        if (!function_exists('is_plugin_active') || !function_exists('is_plugin_active_for_network')) {

            require_once ABSPATH . '/wp-admin/includes/plugin.php';
        }

        $data['fooevents_active'] = is_plugin_active('fooevents/fooevents.php') || is_plugin_active_for_network('fooevents/fooevents.php') ? '1' : '0';

        $settings = array(
            'c' => get_woocommerce_currency(),
            'cs' => html_entity_decode(get_woocommerce_currency_symbol()),
            'ct' => 'yes' === (string) get_option('woocommerce_calc_taxes', '') ? '1' : '0',
            'pit' => 'yes' === (string) get_option('woocommerce_prices_include_tax', '') ? '1' : '0',
            'ec' => 'yes' === (string) get_option('woocommerce_enable_coupons', '') ? '1' : '0',
        );

        $settings['cpt'] = '1' === $settings['ct'] ? (string) get_option('woocommerce_tax_display_cart', '') : 'incl';
        $settings['spt'] = '1' === $settings['ct'] ? (string) get_option('woocommerce_tax_display_shop', '') : 'incl';
        $settings['dtl'] = '1' === $settings['ct'] ? (string) get_option('woocommerce_default_country', '') : '';
        $settings['ttd'] = '1' === $settings['ct'] ? (string) get_option('woocommerce_tax_total_display', '') : 'single';
        $settings['tbo'] = '1' === $settings['ct'] ? (string) get_option('woocommerce_tax_based_on', '') : 'base';

        $currency_format = html_entity_decode(get_woocommerce_price_format());
        $currency_format = str_replace('%1$s', $settings['cs'], $currency_format);
        $currency_format = str_replace('%2$s', '%@', $currency_format);

        $settings['cf'] = $currency_format;
        $settings['ts'] = wc_get_price_thousand_separator();
        $settings['ds'] = wc_get_price_decimal_separator();

        if ('' === $settings['ds']) {
            $settings['ds'] = '.';
        }

        if ($settings['ts'] === $settings['ds']) {
            $settings['ts'] = ' ';
        }

        $settings['nd'] = (string) wc_get_price_decimals();

        $data['settings'] = $settings;

        $data['categories'] = fsfwc_do_get_all_product_categories();

        $data['payment_methods'] = fsfwc_do_get_all_payment_methods();

        $data['tax_rates'] = fsfwc_do_get_all_tax_rates();
    } elseif (strpos($chunk, 'customers') !== false) {

        $data = fsfwc_do_get_all_customers((int) substr($chunk, strlen('customers')));
    } elseif (strpos($chunk, 'orders') !== false) {

        $data = fsfwc_do_get_all_orders((int) substr($chunk, strlen('orders')));
    } elseif (strpos($chunk, 'products') !== false) {

        $data = fsfwc_do_get_all_products((int) substr($chunk, strlen('products')));
    } elseif ('product_images' === $chunk) {

        $data = fsfwc_do_get_all_product_images();
    }

    return $data;
}

/**
 * Update product.
 *
 * @since 1.14.0
 * @param array $product_params Key/value pairs of product data to update.
 */
function fsfwc_do_update_product($product_params) {

    $product_id = $product_params['pid'];

    try {
        $wc_product = wc_get_product($product_id);

        if (null === $wc_product || false === $wc_product) {
            return false;
        }

        if (isset($product_params['pt'])) {
            $wc_product->set_name($product_params['pt']);
        }

        if (isset($product_params['pp'])) {
            $wc_product->set_price($product_params['pp']);
        }

        if (isset($product_params['prp'])) {
            $wc_product->set_regular_price($product_params['prp']);
        }

        if (isset($product_params['psp'])) {
            $wc_product->set_sale_price($product_params['psp']);
        }

        if (isset($product_params['psku'])) {
            $wc_product->set_sku($product_params['psku']);
        }

        if (isset($product_params['psm'])) {
            $manage_stock = $product_params['psm'];

            if ('parent' !== $manage_stock) {
                $manage_stock = '1' === $product_params['psm'];
            }

            $wc_product->set_manage_stock($manage_stock);
        }

        if (isset($product_params['ps'])) {
            wc_update_product_stock($wc_product, $product_params['ps']);
        }

        if (isset($product_params['pss'])) {
            $wc_product->set_stock_status($product_params['pss']);
        }

        if (isset($product_params['psi'])) {
            $wc_product->set_sold_individually('1' === $product_params['psi']);
        }

        $wc_product->save();
    } catch (Exception $e) {
        return false;
    }

    $wc_tax = new WC_Tax();

    $cat_names = array();

    $shop_tax = ( (string) get_option('woocommerce_calc_taxes', '') === 'yes' ) ? (string) get_option('woocommerce_tax_display_shop', '') : 'incl';

    if ('variation' === $wc_product->get_type()) {
        $product_id = $wc_product->get_parent_id();
    }

    $updated_product = fsfwc_do_get_single_product($product_id, $wc_tax, $cat_names, $shop_tax);
    $sale_product_ids = implode(',', wc_get_product_ids_on_sale());

    return array(
        'updated_product' => $updated_product,
        'sale_product_ids' => $sale_product_ids,
    );
}

/**
 * Update product - LEGACY - v1.
 *
 * @since 1.16.0
 * @param array $product_params An array of product values to update.
 */
function fsfwc_do_update_product_v1($product_params) {
    try {
        $wc_product = wc_get_product($product_params[0]);

        if (null === $wc_product || false === $wc_product) {
            return false;
        }

        $wc_product->set_price($product_params[1]);
        $wc_product->set_regular_price($product_params[2]);
        $wc_product->set_sale_price($product_params[3]);

        $wc_product->save();

        wc_update_product_stock($wc_product, $product_params[4]);
    } catch (Exception $e) {
        return false;
    }

    return true;
}

/**
 * Create a new WooCommerce order.
 *
 * @since 1.14.0
 * @param array $order_data Key/value pairs containing order data needed to create a new WooCommerce order.
 */
function fsfwc_do_create_new_order($order_data) {

    if (class_exists('FooSales_Config')) {

        $foosales_config = new FooSales_Config();

        require $foosales_config->helper_path . 'foosales-phrases-helper.php';
    } else {

        require plugin_dir_path(__FILE__) . 'foosaleswc-phrases-helper.php';
    }

    // Check if FooEvents plugin is enabled.
    $is_fooevents_enabled = false;

    if (!function_exists('is_plugin_active') || !function_exists('is_plugin_active_for_network')) {

        require_once ABSPATH . '/wp-admin/includes/plugin.php';
    }

    if (is_plugin_active('fooevents/fooevents.php') || is_plugin_active_for_network('fooevents/fooevents.php')) {
        $is_fooevents_enabled = true;
    }

    WC()->frontend_includes();
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
    WC()->customer = new WC_Customer(0, true);
    WC()->cart = new WC_Cart();

    $order_date = $order_data[0];
    $payment_method_key = $order_data[1];
    $coupons = json_decode(stripslashes($order_data[2]), true);
    $order_items = json_decode(stripslashes($order_data[3]), true);
    $order_customer = json_decode(stripslashes($order_data[4]), true);
    $order_note = urldecode($order_data[5]);
    $order_note_send_to_customer = (int) $order_data[6];
    $attendee_details = json_decode(stripslashes($order_data[7]), true);
    $square_order_id = $order_data[8];
    $user_id = $order_data[9];

    $order = wc_create_order();

    $order->set_date_created((int) $order_date);



    // Order date.
    wp_update_post(
            array(
                'ID' => $order->get_id(),
                'post_date' => gmdate('Y-m-d H:i:s', (int) $order_date),
            )
    );

    // Payment method.
    add_post_meta($order->get_id(), '_foosales_order_source', 'foosales_app', true);
    add_post_meta($order->get_id(), '_foosales_payment_method', $payment_method_key, true);

    //add_post_meta($order->get_id(), '_foosales_order_items', $order_items, true);

    $payment_method = fsfwc_get_payment_method_from_key($payment_method_key);

    add_post_meta($order->get_id(), $foosales_phrases['meta_key_order_payment_method'], $payment_method, true);

    // User ID.
    if (!empty($user_id) && (int) $user_id > 0) {

        add_post_meta($order->get_id(), '_foosales_user_id', $user_id, true);
    }

    if (!empty($square_order_id) && in_array(
                    $payment_method_key,
                    array(
                        'foosales_square',
                        'foosales_square_manual',
                        'foosales_square_terminal',
                        'foosales_square_reader',
                    ),
                    true
            )) {

        add_post_meta($order->get_id(), '_foosales_square_order_id', $square_order_id, true);

        $square_order_result = fsfwc_get_square_order($square_order_id);

        if ('success' === $square_order_result['status']) {

            $square_order = $square_order_result['order'];

            if (!empty($square_order['tenders']) && count($square_order['tenders']) === 1) {

                add_post_meta($order->get_id(), '_foosales_square_order_auto_refund', '1', true);
            }
        }
    }

    $order_has_event = false;

    // Order items.
    foreach ($order_items as $order_item) {
        $wc_product = wc_get_product($order_item['pid']);

        $line_total = $order_item['oilst'];

        $product_args = array(
            'total' => $line_total,
            'subtotal' => $line_total,
        );

        if ($is_fooevents_enabled) {
            if (false === $order_has_event) {
                $event_product_id = $order_item['pid'];

                if ($wc_product->get_type() === 'variation') {
                    $event_product_id = $wc_product->get_parent_id();
                }

                if ('Event' === get_post_meta($event_product_id, 'WooCommerceEventsEvent', true)) {
                    $order_has_event = true;
                }
            }

            $variation_id = 0;
            $attributes = array();

            if ('variation' === $wc_product->get_type()) {
                $variation_id = $order_item['pid'];
                $attributes = $wc_product->get_attributes();
            }

            WC()->cart->add_to_cart($order_item['pid'], $order_item['oiq'], $variation_id, $attributes);
        }
        $product_title = (string) htmlspecialchars_decode(get_post_field('post_title', $order_item['pid']));

        $product_weight = get_post_meta($order_item['pid'], 'weight', true);

        $product = new WC_Product();
        $product->set_name($product_title . ' (' . $product_weight . ' lbs)');
        $order->add_product($product, $order_item['oiq'], $product_args);



        $product_args = null;

        unset($product_args);
    }

    // Order customer.
    if ('' === $order_customer['cid'] && !empty($order_customer['ce'])) {
        // First create new customer.
        $create_result = fsfwc_do_create_update_customer($order_customer);

        if ('success' === $create_result['status']) {

            $order_customer['cid'] = $create_result['cid'];
        }
    }

    if ('' !== $order_customer['cid'] && (int) $order_customer['cid'] > 0) {
        if ($is_fooevents_enabled) {
            WC()->customer = new WC_Customer((int) $order_customer['cid'], true);

            if (trim($order_customer['cbfn']) !== '') {
                WC()->customer->set_billing_first_name($order_customer['cbfn']);
            } else {
                WC()->customer->set_billing_first_name($order_customer['cfn']);
            }

            if (trim($order_customer['cbln']) !== '') {
                WC()->customer->set_billing_last_name($order_customer['cbln']);
            } else {
                WC()->customer->set_billing_last_name($order_customer['cln']);
            }

            WC()->customer->set_billing_company($order_customer['cbco']);
            WC()->customer->set_billing_address_1($order_customer['cba1']);
            WC()->customer->set_billing_address_2($order_customer['cba2']);
            WC()->customer->set_billing_city($order_customer['cbc']);
            WC()->customer->set_billing_postcode($order_customer['cbpo']);
            WC()->customer->set_billing_country($order_customer['cbcu']);
            WC()->customer->set_billing_state($order_customer['cbs']);
            WC()->customer->set_billing_phone($order_customer['cbph']);
            WC()->customer->set_billing_email($order_customer['cbe']);

            WC()->customer->set_shipping_first_name($order_customer['csfn']);
            WC()->customer->set_shipping_last_name($order_customer['csln']);
            WC()->customer->set_shipping_company($order_customer['csco']);
            WC()->customer->set_shipping_address_1($order_customer['csa1']);
            WC()->customer->set_shipping_address_2($order_customer['csa2']);
            WC()->customer->set_shipping_city($order_customer['csc']);
            WC()->customer->set_shipping_postcode($order_customer['cspo']);
            WC()->customer->set_shipping_country($order_customer['cscu']);
            WC()->customer->set_shipping_state($order_customer['css']);
        }

        $order->set_customer_id((int) $order_customer['cid']);

        if (trim($order_customer['cbfn']) !== '') {
            $order->set_billing_first_name($order_customer['cbfn']);
        } else {
            $order->set_billing_first_name($order_customer['cfn']);
        }

        if (trim($order_customer['cbln']) !== '') {
            $order->set_billing_last_name($order_customer['cbln']);
        } else {
            $order->set_billing_last_name($order_customer['cln']);
        }

        $order->set_billing_company($order_customer['cbco']);
        $order->set_billing_address_1($order_customer['cba1']);
        $order->set_billing_address_2($order_customer['cba2']);
        $order->set_billing_city($order_customer['cbc']);
        $order->set_billing_postcode($order_customer['cbpo']);
        $order->set_billing_country($order_customer['cbcu']);
        $order->set_billing_state($order_customer['cbs']);
        $order->set_billing_phone($order_customer['cbph']);
        $order->set_billing_email($order_customer['cbe']);

        $order->set_shipping_first_name($order_customer['csfn']);
        $order->set_shipping_last_name($order_customer['csln']);
        $order->set_shipping_company($order_customer['csco']);
        $order->set_shipping_address_1($order_customer['csa1']);
        $order->set_shipping_address_2($order_customer['csa2']);
        $order->set_shipping_city($order_customer['csc']);
        $order->set_shipping_postcode($order_customer['cspo']);
        $order->set_shipping_country($order_customer['cscu']);
        $order->set_shipping_state($order_customer['css']);
    }

    if ($is_fooevents_enabled && $order_has_event) {
        if (!empty($attendee_details)) {
            foreach ($attendee_details as $key => $val) {
                $_POST[$key] = sanitize_text_field($val);
            }
        }

        $fooevents_config = new FooEvents_Config();

        // Require CheckoutHelper.
        require_once $fooevents_config->classPath . 'checkouthelper.php'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
        $fooevents_checkout_helper = new FooEvents_Checkout_Helper($fooevents_config);

        $fooevents_checkout_helper->woocommerce_events_process($order->get_id());

        WC()->session->destroy_session();
        WC()->cart->empty_cart();
    }

    $order->calculate_totals();

    if (!empty($coupons)) {
        foreach ($coupons as $coupon) {
            $order->apply_coupon(new WC_Coupon($coupon));
        }
    }

    $order->set_status('completed', '', false);

    $order->set_date_completed((int) $order_date);
    $order->set_date_paid((int) $order_date);

    if ('' !== $order_note) {
        $order->add_order_note($order_note, $order_note_send_to_customer);
    }

    $order->save();

    return $order;
}

/**
 * Output a single order.
 *
 * @since 1.14.0
 * @param WC_Order $wc_order The WooCommerce order object to get.
 */
function fsfwc_do_get_single_order(&$wc_order) {

    if (class_exists('FooSales_Config')) {

        $foosales_config = new FooSales_Config();

        require $foosales_config->helper_path . 'foosales-phrases-helper.php';
    } else {

        require plugin_dir_path(__FILE__) . 'foosaleswc-phrases-helper.php';
    }

    $single_order = array();

    $single_order['oid'] = (string) $wc_order->get_id();
    $single_order['od'] = (string) strtotime($wc_order->get_date_created());
    $single_order['os'] = (string) $wc_order->get_status();
    $single_order['ost'] = (string) $wc_order->get_subtotal();
    $single_order['odt'] = (string) $wc_order->get_discount_total();
    $single_order['ot'] = (string) $wc_order->get_total();
    $single_order['ott'] = (string) $wc_order->get_total_tax();

    $tax_totals = $wc_order->get_tax_totals();
    $tax_lines = array();

    foreach ($tax_totals as $tax_key => $tax_total) {

        $tax_lines[] = array(
            'tax_total' => (string) $tax_total->amount,
            'label' => (string) $tax_total->label,
        );
    }

    $single_order['otl'] = $tax_lines;

    $tax_lines = null;
    $tax_totals = null;

    unset($tax_lines, $tax_totals);

    $payment_method_key = (string) get_post_meta($wc_order->get_id(), '_foosales_payment_method', true);

    if ('' === $payment_method_key) {

        $payment_method = (string) get_post_meta($wc_order->get_id(), $foosales_phrases['meta_key_order_payment_method'], true);

        if ('' === $payment_method) {
            $payment_method = (string) get_post_meta($wc_order->get_id(), 'Order Payment Method', true);
        }

        $payment_method_key = fsfwc_get_payment_method_key_from_value($payment_method);

        $payment_method = null;

        unset($payment_method);

        add_post_meta($wc_order->get_id(), '_foosales_payment_method', $payment_method_key, true);
    } elseif ('foosales_square' === $payment_method_key) {

        $payment_method_key = 'foosales_square_reader';

        update_post_meta($wc_order->get_id(), '_foosales_payment_method', $payment_method_key, true);
    }

    $single_order['opmk'] = '' === $payment_method_key ? 'foosales_other' : $payment_method_key;

    $order_source = (string) get_post_meta($wc_order->get_id(), '_foosales_order_source', true);

    $single_order['fo'] = 'foosales_app' === $order_source ? '1' : '0';

    $single_order['soid'] = (string) get_post_meta($wc_order->get_id(), '_foosales_square_order_id', true);
    $single_order['oud'] = (string) get_post_meta($wc_order->get_id(), '_foosales_user_id', true);

    $payment_method_key = null;

    unset($payment_method_key);

    $single_order['ort'] = (string) ( '' === $wc_order->get_total_refunded() ? '0' : $wc_order->get_total_refunded() );

    $order_refunds = $wc_order->get_refunds();

    $order_refund_items = array();

    foreach ($order_refunds as $order_refund) {
        $refund_items = $order_refund->get_items();

        foreach ($refund_items as $refund_item) {
            $order_item_id = '';

            $meta_data = $refund_item->get_meta_data();

            foreach ($meta_data as $meta_data_item) {
                if ('_refunded_item_id' === $meta_data_item->key) {
                    $order_item_id = (string) $meta_data_item->value;

                    break;
                }
            }

            if (empty($order_refund_items[$order_item_id])) {
                $order_refund_items[$order_item_id] = array(
                    'qty' => 0,
                    'total' => 0,
                );
            }

            $order_refund_items[$order_item_id]['qty'] += abs($refund_item->get_quantity());
            $order_refund_items[$order_item_id]['total'] += abs($refund_item['total']) + abs($refund_item['total_tax']);
        }
    }

    $order_refunds = null;

    $customer_data = array(
        'cid' => '',
        'cfn' => '',
        'cln' => '',
        'cun' => '',
        'ce' => '',
        'cbfn' => '',
        'cbln' => '',
        'cbco' => '',
        'cba1' => '',
        'cba2' => '',
        'cbc' => '',
        'cbpo' => '',
        'cbcu' => '',
        'cbs' => '',
        'cbph' => '',
        'cbe' => '',
        'csfn' => '',
        'csln' => '',
        'csco' => '',
        'csa1' => '',
        'csa2' => '',
        'csc' => '',
        'cspo' => '',
        'cscu' => '',
        'css' => '',
    );

    if ($wc_order->get_customer_id() > 0) {

        $customer_data = fsfwc_get_single_customer($wc_order->get_customer_id());
    }

    $single_order['oc'] = $customer_data;

    $customer_data = null;

    unset($customer_data);

    $order_items = array();

    $wc_order_items = $wc_order->get_items();

    foreach ($wc_order_items as $wc_order_item) {

        $order_item = array();

        $product_id = $wc_order_item['product_id'];

        if ((int) $wc_order_item['variation_id'] > 0) {
            $product_id = (int) $wc_order_item['variation_id'];
        }

        $order_item['oiid'] = (string) $wc_order_item->get_id();
        $order_item['oin'] = (string) $wc_order_item->get_name();
        $order_item['oipid'] = (string) $product_id;
        $order_item['oivid'] = (string) $wc_order_item['variation_id'];
        $order_item['oivpid'] = (string) $wc_order_item['product_id'];
        $order_item['oilst'] = (string) $wc_order_item['line_subtotal'];
        $order_item['oilstt'] = (string) $wc_order_item['line_subtotal_tax'];
        $order_item['oiltx'] = (string) $wc_order_item['total_tax'];
        $order_item['oiltl'] = (string) $wc_order_item['total'];
        $order_item['oiq'] = (string) $wc_order_item['qty'];
        $order_item['oitcid'] = (!empty($wc_order_item['taxes']['total']) ? (string) array_keys($wc_order_item['taxes']['total'])[0] : '0' );

        $refunded_quantity = '0';
        $refunded_total = '0';

        if (!empty($order_refund_items[(string) $wc_order_item->get_id()])) {
            $refunded_quantity = (string) $order_refund_items[(string) $wc_order_item->get_id()]['qty'];
            $refunded_total = (string) $order_refund_items[(string) $wc_order_item->get_id()]['total'];
        }

        $order_item['oirq'] = (string) $refunded_quantity;
        $order_item['oirt'] = (string) $refunded_total;

        $product_id = null;

        unset($product_id);

        $order_items[] = $order_item;

        $order_item = null;

        unset($order_item);
    }

    $single_order['oi'] = $order_items;

    $wc_order_items = null;
    $order_refund_items = null;

    unset($wc_order_items, $order_refunds, $order_refund_items);

    $coupon_lines = array();

    $coupons = $wc_order->get_items('coupon');

    if (!empty($coupons)) {

        foreach ($coupons as $coupon) {

            $coupon_lines[] = array(
                'oclc' => $coupon->get_code(),
                'ocld' => $coupon->get_discount(),
                'ocldt' => $coupon->get_discount_tax(),
            );
        }
    }

    $single_order['ocl'] = $coupon_lines;

    return $single_order;
}

/**
 * Synchronize offline data.
 *
 * @since 1.16.0
 * @param array $offline_changes An array containing key/value pairs of other arrays containing offline changes.
 */
function fsfwc_do_sync_offline_changes($offline_changes = array()) {
    fsfwc_do_sync_offline_changes_v1($offline_changes);
}

/**
 * Synchronize offline data - LEGACY - v1.
 *
 * @since 1.16.0
 * @param array $offline_changes An array containing key/value pairs of other arrays containing offline changes.
 */
function fsfwc_do_sync_offline_changes_v1($offline_changes = array()) {
    $result = array(
        'status' => 'success',
    );

    $new_order_ids = array();
    $new_orders = array();
    $cancelled_order_ids = array();

    $last_offline_change = end($offline_changes);

    foreach ($offline_changes as $offline_change) {
        if (!empty($offline_change['update_product'])) {

            $update_product_params = $offline_change['update_product']['FooSalesProductParams'];

            if (is_array($update_product_params)) {

                fsfwc_do_update_product_v1($update_product_params);
            } else {

                fsfwc_do_update_product(json_decode($update_product_params, true));
            }

            $response = array();

            $response['ocid'] = $offline_change['update_product']['ocid'];

            echo wp_json_encode($response);

            flush();
        } elseif (!empty($offline_change['create_order'])) {
            $order_params = $offline_change['create_order'];
            $temp_id = $order_params['temp_id'];

            $new_order = fsfwc_do_create_new_order(
                    array(
                        $order_params['date'],
                        $order_params['payment_method_key'],
                        wp_json_encode($order_params['coupons']),
                        wp_json_encode($order_params['items']),
                        wp_json_encode($order_params['customer']),
                        $order_params['order_note'],
                        $order_params['order_note_send_to_customer'],
                        wp_json_encode($order_params['attendee_details']),
                        $order_params['square_order_id'],
                        $order_params['user_id'],
                    )
            );

            $new_order_ids[] = array(
                'temp_id' => (string) $temp_id,
                'oid' => (string) $new_order->get_id(),
            );

            $order_items = $new_order->get_items();

            $new_orders[(string) $temp_id] = array();

            $response = array();

            $response[(string) $temp_id] = array();

            foreach ($order_items as $order_item) {
                $new_orders[(string) $temp_id][] = array(
                    'oiid' => (string) $order_item->get_id(),
                    'oipid' => (string) $order_item['product_id'],
                );

                $response[(string) $temp_id][] = array(
                    'oiid' => (string) $order_item->get_id(),
                    'oipid' => (string) $order_item['product_id'],
                );
            }

            $response['ocid'] = $offline_change['create_order']['ocid'];

            $response['newOrderID'] = array(
                'temp_id' => (string) $temp_id,
                'oid' => (string) $new_order->get_id(),
            );

            echo wp_json_encode($response);

            flush();
        } elseif (!empty($offline_change['cancel_order'])) {
            $cancel_order_params = $offline_change['cancel_order'];
            $temp_id = '';
            $cancel_id = $cancel_order_params['oid'];

            if (strpos($cancel_id, '_') !== false) {
                $temp_id = $cancel_id;

                foreach ($new_order_ids as $new_order_id) {
                    if ($new_order_id['temp_id'] === $cancel_id) {
                        $cancel_id = $new_order_id['oid'];
                    }
                }
            }

            fsfwc_do_cancel_order($cancel_id, (bool) $cancel_order_params['restock']);

            $cancelled_order_ids[] = array(
                'temp_id' => (string) $temp_id,
                'oid' => (string) $cancel_id,
                'restock' => $cancel_order_params['restock'],
            );

            $response = array();

            $response['ocid'] = $offline_change['cancel_order']['ocid'];

            echo wp_json_encode($response);

            flush();
        } elseif (!empty($offline_change['refund_order'])) {
            $refund_order_params = $offline_change['refund_order'];
            $temp_id = $refund_order_params['oid'];
            $order_id = $refund_order_params['oid'];
            $refunded_items = json_decode(stripslashes($refund_order_params['refundedItems']), true);

            foreach ($refunded_items as &$refunded_item) {
                if (!empty($new_orders[$temp_id])) {
                    foreach ($new_orders[$temp_id] as $new_order_item) {
                        if ($new_order_item['oipid'] === $refunded_item['oipid']) {
                            $refunded_item['oiid'] = $new_order_item['oiid'];

                            break;
                        }
                    }
                }
            }

            foreach ($new_order_ids as $new_order_id) {
                if ($new_order_id['temp_id'] === $temp_id) {
                    $order_id = $new_order_id['oid'];

                    break;
                }
            }

            $refund_result = fsfwc_do_refund_order($order_id, $refunded_items);
            $refunded_order = $refund_result['order'];

            $response = array();

            $response['ocid'] = $offline_change['refund_order']['ocid'];

            if (!empty($refund_result['square_refund'])) {

                if ('error' === $refund_result['square_refund']) {

                    $result['square_refund'] = 'error';
                }
            }

            echo wp_json_encode($response);

            flush();
        }

        if ($offline_change !== $last_offline_change) {
            echo '|';

            flush();
        }
    }

    echo 'FooSalesResponse:';

    flush();

    echo wp_json_encode($result);
}

/**
 * Cancel a WooCommerce order.
 *
 * @since 1.14.0
 * @param int     $order_id The WooCommerce order ID.
 * @param boolean $restock Whether or not the items should be restocked.
 */
function fsfwc_do_cancel_order($order_id, $restock) {
    try {
        $wc_order = wc_get_order($order_id);

        if (false === $wc_order) {
            return false;
        }

        $refund = wc_create_refund(
                array(
                    'order_id' => $order_id,
                    'amount' => $wc_order->get_total() - $wc_order->get_total_refunded(),
                )
        );

        if (false === $restock) {
            $wc_order_items = $wc_order->get_items();

            foreach ($wc_order_items as $wc_order_item) {
                $wc_product = wc_get_product($wc_order_item['product_id']);

                if ($wc_product->get_manage_stock()) {
                    wc_update_product_stock($wc_product, $wc_order_item->get_quantity(), 'decrease');
                }

                $wc_product = null;

                unset($wc_product);
            }
        }

        if ($wc_order->update_status('cancelled', '', false)) {
            return true;
        } else {
            return false;
        }
    } catch (Exception $e) {
        return false;
    }

    return false;
}

/**
 * Refund a WooCommerce order and restock items.
 *
 * @since 1.14.0
 * @global wpdb $wpdb
 * @param int   $order_id The WooCommerce order ID.
 * @param array $refunded_items Items to be refunded.
 */
function fsfwc_do_refund_order($order_id, $refunded_items) {

    if (class_exists('FooSales_Config')) {

        $foosales_config = new FooSales_Config();

        require $foosales_config->helper_path . 'foosales-phrases-helper.php';
    } else {

        require plugin_dir_path(__FILE__) . 'foosaleswc-phrases-helper.php';
    }

    global $wpdb;

    $wc_order = wc_get_order($order_id);

    $refund_args = array(
        'order_id' => $order_id,
    );

    $refund_total = 0.0;

    $line_items = array();
    $restock_items = array();

    foreach ($refunded_items as $refunded_item) {
        $refund_total += (float) $refunded_item['refund_total'] + (!empty($refunded_item['refund_tax']) ? (float) $refunded_item['refund_tax'] : 0.0 );

        if ((int) $refunded_item['restock_qty'] > 0) {
            $restock_items[(string) $refunded_item['oipid']] = $refunded_item['restock_qty'];
        }

        $line_item = array(
            'qty' => $refunded_item['qty'],
            'refund_total' => $refunded_item['refund_total'],
        );

        if (!empty($refunded_item['refund_tax']) && !empty($refunded_item['refund_tax_class'])) {
            $line_item['refund_tax'] = array(
                $refunded_item['refund_tax_class'] => $refunded_item['refund_tax'],
            );
        }

        $line_items[$refunded_item['oiid']] = $line_item;

        unset($line_item);
    }

    if (round($wc_order->get_total()) === round($refund_total)) {
        $wc_order->update_status('refunded', '', false);
    } else {
        $refund_args['amount'] = $refund_total;
        $refund_args['line_items'] = $line_items;

        $refund = wc_create_refund($refund_args);
    }

    foreach ($restock_items as $product_id => $quantity) {
        $wc_product = wc_get_product($product_id);

        wc_update_product_stock($wc_product, $quantity, 'increase');

        unset($wc_product);
    }

    $payment_method_key = get_post_meta($order_id, '_foosales_payment_method', true);

    $result = array();

    if (in_array(
                    $payment_method_key,
                    array(
                        'foosales_square',
                        'foosales_square_manual',
                        'foosales_square_terminal',
                        'foosales_square_reader',
                    ),
                    true
            )) {

        $square_order_auto_refund = get_post_meta($order_id, '_foosales_square_order_auto_refund', true) === '1';

        if ($square_order_auto_refund) {

            $square_order_id = get_post_meta($order_id, '_foosales_square_order_id', true);

            $refund_result = fsfwc_refund_square_order($square_order_id, $refund_total);

            $result['square_refund'] = $refund_result['status'];

            if ('success' !== $result['square_refund'] && 'foosales_square_terminal' === $payment_method_key) {
                if ('CARD_PRESENCE_REQUIRED' === $result['square_refund']) {
                    // Refund via Terminal.
                    $checkout = $wpdb->get_row(// phpcs:ignore WordPress.DB.DirectDatabaseQuery
                            $wpdb->prepare(
                                    "SELECT * FROM {$wpdb->prefix}foosales_square_checkouts WHERE order_id = %s",
                                    $square_order_id
                            ),
                            'ARRAY_A'
                    );

                    if (!empty($checkout)) {

                        $refund_data = array(
                            'refund' => array(
                                'amount_money' => array(
                                    'amount' => (int) round($refund_total * 100),
                                    'currency' => $checkout['currency'],
                                ),
                                'payment_id' => $checkout['payment_id'],
                                'device_id' => $checkout['device_id'],
                                'reason' => sprintf($foosales_phrases['description_square_terminal_refund_reason'], $order_id),
                            ),
                        );

                        $create_refund_result = fsfwc_do_create_square_terminal_refund($refund_data);

                        if ('success' === $create_refund_result['status']) {
                            $result['square_refund'] = 'terminal_refund';
                            $result['square_terminal_refund'] = $create_refund_result['square_terminal_refund'];
                        }
                    }
                }
            }
        } else {

            $result['square_refund'] = 'error';
        }
    }

    $result['order'] = $wc_order;

    return $result;
}

/**
 * Creates a new customer or updates the customer's details if they exist.
 *
 * @since 1.14.0
 * @param array $customer_details Key/value pairs of customer data to create or update a customer.
 */
function fsfwc_do_create_update_customer($customer_details = array()) {

    $result = array('status' => 'error');

    $customer_id = $customer_details['cid'];

    if ('' === $customer_id) {
        // New customer.
        $args = array(
            'search' => $customer_details['ce'],
            'search_columns' => array('user_email'),
        );

        $query = new WP_User_Query($args);

        if (!empty($query->results)) {
            $result['message'] = 'Email exists';

            return $result;
        }
    }

    $customer_fields = array(
        'cbfn' => 'billing_first_name',
        'cbln' => 'billing_last_name',
        'cbco' => 'billing_company',
        'cba1' => 'billing_address_1',
        'cba2' => 'billing_address_2',
        'cbc' => 'billing_city',
        'cbpo' => 'billing_postcode',
        'cbcu' => 'billing_country',
        'cbs' => 'billing_state',
        'cbph' => 'billing_phone',
        'cbe' => 'billing_email',
        'csfn' => 'shipping_first_name',
        'csln' => 'shipping_last_name',
        'csco' => 'shipping_company',
        'csa1' => 'shipping_address_1',
        'csa2' => 'shipping_address_2',
        'csc' => 'shipping_city',
        'cspo' => 'shipping_postcode',
        'cscu' => 'shipping_country',
        'css' => 'shipping_state',
    );

    if ('' === $customer_id) {
        // New customer.
        $random_password = wp_generate_password(12, false);
        $customer_id = wp_create_user($customer_details['ce'], $random_password, $customer_details['ce']);

        add_post_meta($customer_id, '_foosales_user_source', 'foosales_app', true);
    }

    $customer_id = wp_update_user(
            array(
                'ID' => $customer_id,
                'user_email' => $customer_details['ce'],
                'first_name' => $customer_details['cfn'],
                'last_name' => $customer_details['cln'],
                'role' => 'customer',
            )
    );

    if (is_wp_error($customer_id)) {

        $result['message'] = 'Unknown';
    } else {

        foreach ($customer_fields as $key => $meta_key) {

            update_user_meta($customer_id, $meta_key, $customer_details[$key]);
        }

        $result['status'] = 'success';
        $result['cid'] = (string) $customer_id;
    }

    return $result;
}

/**
 * Gets the discount of a given coupon code for the current cart.
 *
 * @since 1.14.0
 * @param array $coupons An array of coupon codes to apply to the order.
 * @param array $order_items The cart items which will be used to obtain the discounts.
 */
function fsfwc_do_get_coupon_code_discounts($coupons = array(), $order_items = array()) {

    $output = array('status' => 'error');

    WC()->frontend_includes();
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
    WC()->customer = new WC_Customer(0, true);
    WC()->cart = new WC_Cart();

    $order = wc_create_order();

    // Order date.
    wp_update_post(
            array(
                'ID' => $order->get_id(),
                'post_date' => gmdate('Y-m-d H:i:s', time()),
            )
    );

    // Order items.
    foreach ($order_items as $order_item) {
        $line_total_excl = $order_item['oilst'];

        $product_args = array(
            'totals' => array(
                'subtotal' => $line_total_excl,
                'total' => $line_total_excl,
            ),
        );

        $wc_product = wc_get_product($order_item['pid']);

        $order->add_product($wc_product, $order_item['oiq'], $product_args);

        $product_args = null;

        unset($product_args);
    }

    $order->calculate_totals();

    if (!empty($coupons)) {
        foreach ($coupons as $coupon) {
            $coupon_result = $order->apply_coupon(new WC_Coupon($coupon));

            if (is_wp_error($coupon_result)) {
                $order->delete(true);

                $output['message'] = html_entity_decode(wp_strip_all_tags($coupon_result->get_error_message()));

                return $output;
            }
        }
    }

    $output['status'] = 'success';
    $output['discounted_order'] = fsfwc_do_get_single_order($order);

    $output['discounts'] = array();

    $coupons = $order->get_items('coupon');

    if (!empty($coupons)) {
        foreach ($coupons as $coupon) {
            $output['discounts'][] = array(
                'coupon' => $coupon->get_code(),
                'discount' => $coupon->get_discount(),
                'discount_tax' => $coupon->get_discount_tax(),
            );
        }
    }

    WC()->session->destroy_session();
    WC()->cart->empty_cart();

    $order->delete(true);

    $order = null;

    unset($order);

    return $output;
}

/**
 * Gets the products and orders that were updated since the provided timestamp and return
 * the products' new prices and stock quantities and new and/or updated orders.
 *
 * @since 1.15.0
 * @param int $last_checked_timestamp The timestamp of the last time updates were fetched by the app.
 */
function fsfwc_do_get_data_updates($last_checked_timestamp = 0) {

    if (0 === $last_checked_timestamp) {
        $last_checked_timestamp = time();
    }

    $timestamp_offset = ( 30 * 60 );
    $last_checked_timestamp -= $timestamp_offset;

    $output = array(
        'status' => 'success',
        'ts_now' => gmdate('c', time()),
        'ts_var' => gmdate('c', $last_checked_timestamp + $timestamp_offset),
        'ts_set' => gmdate('c', $last_checked_timestamp),
    );

    // Get product updates.
    $product_args = array(
        'post_type' => 'product',
        'date_query' => array(
            'column' => 'post_modified',
            'after' => gmdate('c', $last_checked_timestamp),
        ),
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'orderby' => 'title',
        'order' => 'ASC',
        'post_status' => get_option('globalFooSalesProductsStatus', array('publish')),
    );

    if ('cat' === (string) get_option('globalFooSalesProductsToDisplay', '')) {
        $product_args['tax_query'] = array(// phpcs:ignore WordPress.DB.SlowDBQuery
            array(
                'taxonomy' => 'product_cat',
                'terms' => get_option('globalFooSalesProductCategories'),
                'operator' => 'IN',
            ),
        );
    }

    $products_query = new WP_Query($product_args);

    $wc_tax = new WC_Tax();

    $cat_names = array();

    $shop_tax = ( (string) get_option('woocommerce_calc_taxes', '') === 'yes' ) ? (string) get_option('woocommerce_tax_display_shop', '') : 'incl';

    $product_updates = array();

    foreach ($products_query->posts as $post_id) {

        $updated_product = fsfwc_do_get_single_product($post_id, $wc_tax, $cat_names, $shop_tax);

        $product_image = preg_replace_callback(
                '/[^\x20-\x7f]/',
                function($match) {
            return rawurlencode($match[0]);
        },
                (string) get_the_post_thumbnail_url($post_id, 'thumbnail')
        );

        $updated_product['pi'] = $product_image;

        $product_updates[] = $updated_product;
    }

    $output['product_updates'] = $product_updates;

    // Get updated sale product IDs.
    $output['sale_product_ids'] = implode(',', wc_get_product_ids_on_sale());

    // Get order updates.
    $order_args = array(
        'posts_per_page' => -1,
        'post_type' => 'shop_order',
        'post_status' => array('wc-completed', 'wc-cancelled', 'wc-refunded'),
        'date_query' => array(
            'column' => 'post_modified',
            'after' => gmdate('c', $last_checked_timestamp),
        ),
        'meta_query' => array(// phpcs:ignore WordPress.DB.SlowDBQuery
            'relation' => 'OR',
            array(
                'key' => '_foosales_order_source',
                'value' => 'foosales_app',
            ),
            array(
                'key' => 'Order Source',
                'value' => 'FooSales app',
            ),
        ),
        'fields' => 'ids',
        'no_found_rows' => true,
        'orderby' => 'id',
        'order' => 'DESC',
    );

    $order_query = new WP_Query($order_args);

    $order_updates = array();

    foreach ($order_query->posts as $post_id) {

        $wc_order = wc_get_order($post_id);

        $order_updates[] = fsfwc_do_get_single_order($wc_order);

        $wc_order = null;

        unset($wc_order);
    }

    $output['order_updates'] = $order_updates;

    return $output;
}

/**
 * Check that the user has permission to access FooSales.
 *
 * @since 1.14.0
 * @param WP_User $user The WordPress user object.
 *
 * @return bool
 */
function fsfwc_checkroles($user) {

    return user_can($user, 'publish_foosales');
}

/**
 * Get Square order.
 *
 * @since 1.15.0
 * @param string $square_order_id The ID of the Square order.
 *
 * @return array
 */
function fsfwc_get_square_order($square_order_id = '') {

    $result = array('status' => 'error');

    $square_access_token = get_option('globalFooSalesSquareAccessToken');

    if ('' === $square_access_token || '' === $square_order_id) {
        return $result;
    }

    $response = wp_remote_get(
            'https://connect.squareup.com/v2/orders/' . $square_order_id,
            array(
                'method' => 'GET',
                'timeout' => 30,
                'redirection' => 10,
                'httpversion' => '1.1',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $square_access_token,
                    'Content-type' => 'application/json',
                ),
            )
    );

    if (is_wp_error($response)) {
        return $result;
    } else {
        $response_array = json_decode($response['body'], true);

        if (false !== $response_array) {

            if (!empty($response_array['order'])) {

                $order = $response_array['order'];

                $result['status'] = 'success';

                $result['order'] = $order;
            } else {

                return $result;
            }
        } else {

            return $result;
        }
    }

    return $result;
}

/**
 * Refund a Square order.
 *
 * @since 1.15.0
 * @param string $square_order_id The ID of the Square order.
 * @param double $amount The amount in cents to refund to the original payment card.
 *
 * @return array
 */
function fsfwc_refund_square_order($square_order_id = '', $amount = 0) {

    $result = array('status' => 'error');

    $square_access_token = get_option('globalFooSalesSquareAccessToken');

    if ('' === $square_access_token || '' === $square_order_id || 0 === $amount) {
        return $result;
    }

    $order_result = fsfwc_get_square_order($square_order_id);

    if ('success' === $order_result['status']) {

        $order = $order_result['order'];

        if (!empty($order['tenders'])) {

            $payment_id = $order['tenders'][0]['id'];

            $refund_args = array(
                'idempotency_key' => fsfwc_generate_idempotency_string(),
                'payment_id' => $payment_id,
                'amount_money' => array(
                    'currency' => $order['tenders'][0]['amount_money']['currency'],
                    'amount' => (int) round((float) $amount * 100.0),
                ),
            );

            $response = wp_remote_post(
                    'https://connect.squareup.com/v2/refunds',
                    array(
                        'method' => 'POST',
                        'timeout' => 30,
                        'redirection' => 10,
                        'httpversion' => '1.1',
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $square_access_token,
                            'Content-type' => 'application/json',
                        ),
                        'body' => wp_json_encode($refund_args),
                    )
            );

            if (is_wp_error($response)) {
                return $result;
            } else {
                $response_array = json_decode($response['body'], true);

                if (false !== $response_array) {

                    if (!empty($response_array['refund'])) {

                        $result['status'] = 'success';
                    } elseif (!empty($response_array['errors'])) {
                        if (!empty($response_array['errors'][0]['detail'])) {
                            $result['status'] = $response_array['errors'][0]['detail'];
                        }
                    }
                } else {

                    return $result;
                }
            }
        } else {

            $result['split_tenders'] = true;
        }
    }

    return $result;
}

/**
 * Get Square locations.
 *
 * @since 1.16.0
 *
 * @return array
 */
function fsfwc_get_square_locations() {

    $result = array('status' => 'error');

    $square_access_token = get_option('globalFooSalesSquareAccessToken');

    if ('' === $square_access_token) {
        return $result;
    }

    $response = wp_remote_get(
            'https://connect.squareup.com/v2/locations',
            array(
                'method' => 'GET',
                'timeout' => 30,
                'redirection' => 10,
                'httpversion' => '1.1',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $square_access_token,
                    'Content-type' => 'application/json',
                ),
            )
    );

    if (is_wp_error($response)) {
        return $result;
    } else {
        $response_array = json_decode($response['body'], true);

        if (false !== $response_array) {

            if (!empty($response_array['locations'])) {

                $result['status'] = 'success';

                $result['locations'] = $response_array['locations'];
            } else {

                return $result;
            }
        } else {

            return $result;
        }
    }

    return $result;
}

/**
 * Create a Square manual payment.
 *
 * @since 1.16.0
 * @param array $payment_data Key/value pairs for the payment data.
 */
function fsfwc_do_create_square_payment($payment_data = array()) {

    $result = array('status' => 'error');

    $square_access_token = get_option('globalFooSalesSquareAccessToken');

    if ('' === $square_access_token || empty($payment_data)) {

        return $result;
    }

    $payment_data['autocomplete'] = true;
    $payment_data['idempotency_key'] = fsfwc_generate_idempotency_string();

    $response = wp_remote_post(
            'https://connect.squareup.com/v2/payments',
            array(
                'method' => 'POST',
                'timeout' => 30,
                'redirection' => 10,
                'httpversion' => '1.1',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $square_access_token,
                    'Content-type' => 'application/json',
                ),
                'body' => wp_json_encode($payment_data),
            )
    );

    if (is_wp_error($response)) {
        return $result;
    } else {
        $response_array = json_decode($response['body'], true);

        if (false !== $response_array) {

            if (!empty($response_array['payment'])) {

                $result['status'] = 'success';

                $result['payment'] = $response_array['payment'];
            } else {

                return $result;
            }
        } else {

            return $result;
        }
    }

    return $result;
}

/**
 * Generate a Square device code.
 *
 * @since 1.17.0
 * @global wpdb $wpdb
 * @param array $square_location Key/value pairs of Square location data.
 */
function fsfwc_do_generate_square_device_code($square_location = array()) {

    global $wpdb;

    $result = array('status' => 'error');

    $square_access_token = get_option('globalFooSalesSquareAccessToken');

    if ('' === $square_access_token || empty($square_location)) {

        return $result;
    }

    $device_code_data = array(
        'idempotency_key' => fsfwc_generate_idempotency_string(),
        'device_code' => array(
            'product_type' => 'TERMINAL_API',
            'location_id' => $square_location['id'],
            'name' => $square_location['name'] . ' - TERMINAL',
        ),
    );

    $response = wp_remote_post(
            'https://connect.squareup.com/v2/devices/codes',
            array(
                'method' => 'POST',
                'timeout' => 30,
                'redirection' => 10,
                'httpversion' => '1.1',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $square_access_token,
                    'Content-type' => 'application/json',
                ),
                'body' => wp_json_encode($device_code_data),
            )
    );

    if (is_wp_error($response)) {
        return $result;
    } else {
        $response_array = json_decode($response['body'], true);

        if (false !== $response_array) {

            if (!empty($response_array['device_code'])) {

                $result['status'] = 'success';

                $result['device_code'] = $response_array['device_code'];

                $table_name = $wpdb->prefix . 'foosales_square_devices';

                $existing_location_row = $wpdb->get_var(// phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        $wpdb->prepare(
                                "SELECT id FROM {$wpdb->prefix}foosales_square_devices WHERE location_id = %s",
                                $square_location['id']
                        )
                );

                $device_data = array(
                    'device_code_id' => $result['device_code']['id'],
                    'code' => $result['device_code']['code'],
                    'location_id' => $result['device_code']['location_id'],
                    'pair_by' => $result['device_code']['pair_by'],
                    'status' => $result['device_code']['status'],
                );

                if (null === $existing_location_row) {

                    // Insert new location device.
                    $wpdb->insert(// phpcs:ignore WordPress.DB.DirectDatabaseQuery
                            $table_name,
                            $device_data
                    );
                } else {

                    // Update existing location device.
                    $wpdb->update(// phpcs:ignore WordPress.DB.DirectDatabaseQuery
                            $table_name,
                            $device_data,
                            array('id' => $existing_location_row)
                    );
                }
            } else {

                return $result;
            }
        } else {

            return $result;
        }
    }

    return $result;
}

/**
 * Get the pair status of a Square device.
 *
 * @since 1.17.0
 * @global wpdb $wpdb
 * @param string $square_device_code_id Square device ID.
 */
function fsfwc_do_get_square_device_pair_status($square_device_code_id = '') {

    global $wpdb;

    $result = array('status' => 'error');

    if ('' === $square_device_code_id) {

        return $result;
    }

    $square_device = $wpdb->get_row(// phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}foosales_square_devices WHERE device_code_id = %s",
                    $square_device_code_id
            ),
            'ARRAY_A'
    );

    if (!empty($square_device)) {

        $result['status'] = $square_device['status'];
        $result['device_id'] = $square_device['device_id'];
    }

    return $result;
}

/**
 * Create a Square terminal checkout request.
 *
 * @since 1.17.0
 * @global wpdb $wpdb
 * @param array $checkout_data Key/value pairs of checkout data.
 */
function fsfwc_do_create_square_terminal_checkout($checkout_data = array()) {

    global $wpdb;

    $result = array('status' => 'error');

    $square_access_token = get_option('globalFooSalesSquareAccessToken');

    if ('' === $square_access_token || empty($checkout_data)) {

        return $result;
    }

    $checkout_data['idempotency_key'] = fsfwc_generate_idempotency_string();

    $response = wp_remote_post(
            'https://connect.squareup.com/v2/terminals/checkouts',
            array(
                'method' => 'POST',
                'timeout' => 30,
                'redirection' => 10,
                'httpversion' => '1.1',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $square_access_token,
                    'Content-type' => 'application/json',
                ),
                'body' => wp_json_encode($checkout_data),
            )
    );

    if (is_wp_error($response)) {
        return $result;
    } else {
        $response_array = json_decode($response['body'], true);

        if (false !== $response_array) {

            if (!empty($response_array['checkout'])) {

                $result['status'] = 'success';

                $result['checkout'] = $response_array['checkout'];

                $checkout_created_timestamp = strtotime($result['checkout']['created_at']);
                $checkout_deadline = gmdate(DATE_RFC3339, $checkout_created_timestamp + ( 5 * 60 ));

                $table_name = $wpdb->prefix . 'foosales_square_checkouts';

                $wpdb->insert(// phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        $table_name,
                        array(
                            'checkout_id' => $result['checkout']['id'],
                            'amount' => (string) ( $result['checkout']['amount_money']['amount'] / 100.0 ),
                            'currency' => $result['checkout']['amount_money']['currency'],
                            'created_at' => $result['checkout']['created_at'],
                            'device_id' => $result['checkout']['device_options']['device_id'],
                            'deadline' => $checkout_deadline,
                            'status' => $result['checkout']['status'],
                            'updated_at' => $result['checkout']['updated_at'],
                        )
                );
            } else {

                return $result;
            }
        } else {

            return $result;
        }
    }

    return $result;
}

/**
 * Get a Square terminal checkout status.
 *
 * @since 1.17.0
 * @global wpdb $wpdb
 * @param string $checkout_id The ID of the Square checkout.
 */
function fsfwc_do_get_square_terminal_checkout_status($checkout_id = '') {

    global $wpdb;

    $result = array('status' => 'error');

    $square_access_token = get_option('globalFooSalesSquareAccessToken');

    if ('' === $square_access_token || '' === $checkout_id) {

        return $result;
    }

    $checkout = $wpdb->get_row(// phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}foosales_square_checkouts WHERE checkout_id = %s",
                    $checkout_id
            ),
            'ARRAY_A'
    );

    if (empty($checkout)) {

        return $result;
    }

    $result['status'] = $checkout['status'];

    if ('COMPLETED' === $checkout['status']) {

        $payment_id = $checkout['payment_id'];

        $response = wp_remote_get(
                'https://connect.squareup.com/v2/payments/' . $payment_id,
                array(
                    'method' => 'GET',
                    'timeout' => 30,
                    'redirection' => 10,
                    'httpversion' => '1.1',
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $square_access_token,
                        'Content-type' => 'application/json',
                    ),
                )
        );

        if (is_wp_error($response)) {
            return $result;
        } else {
            $response_array = json_decode($response['body'], true);

            if (false !== $response_array) {

                if (!empty($response_array['payment'])) {

                    $result['soid'] = $response_array['payment']['order_id'];

                    $table_name = $wpdb->prefix . 'foosales_square_checkouts';

                    $wpdb->update(// phpcs:ignore WordPress.DB.DirectDatabaseQuery
                            $table_name,
                            array(
                                'order_id' => $result['soid'],
                            ),
                            array(
                                'payment_id' => $payment_id,
                            )
                    );
                } else {

                    return $result;
                }
            } else {

                return $result;
            }
        }
    }

    return $result;
}

/**
 * Create a Square terminal refund request.
 *
 * @since 1.17.0
 * @global wpdb $wpdb
 * @param array $refund_data Key/value pairs of checkout data.
 */
function fsfwc_do_create_square_terminal_refund($refund_data = array()) {

    global $wpdb;

    $result = array('status' => 'error');

    $square_access_token = get_option('globalFooSalesSquareAccessToken');

    if ('' === $square_access_token || empty($refund_data)) {

        return $result;
    }

    $refund_data['idempotency_key'] = fsfwc_generate_idempotency_string();

    $response = wp_remote_post(
            'https://connect.squareup.com/v2/terminals/refunds',
            array(
                'method' => 'POST',
                'timeout' => 30,
                'redirection' => 10,
                'httpversion' => '1.1',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $square_access_token,
                    'Content-type' => 'application/json',
                ),
                'body' => wp_json_encode($refund_data),
            )
    );

    if (is_wp_error($response)) {
        return $result;
    } else {
        $response_array = json_decode($response['body'], true);

        if (false !== $response_array) {

            if (!empty($response_array['refund'])) {

                $result['status'] = 'success';

                $result['square_terminal_refund'] = $response_array['refund'];

                $refund_created_timestamp = strtotime($response_array['refund']['created_at']);
                $refund_deadline = gmdate(DATE_RFC3339, $refund_created_timestamp + ( 5 * 60 ));

                $table_name = $wpdb->prefix . 'foosales_square_refunds';

                $refund_db_data = array(
                    'refund_id' => $response_array['refund']['id'],
                    'amount' => (string) ( $response_array['refund']['amount_money']['amount'] / 100.0 ),
                    'currency' => $response_array['refund']['amount_money']['currency'],
                    'device_id' => $response_array['refund']['device_id'],
                    'deadline' => $refund_deadline,
                    'payment_id' => $response_array['refund']['payment_id'],
                    'order_id' => $response_array['refund']['order_id'],
                    'status' => $response_array['refund']['status'],
                    'created_at' => $response_array['refund']['created_at'],
                    'updated_at' => $response_array['refund']['updated_at'],
                );

                $insert_result = $wpdb->insert(// phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        $table_name,
                        $refund_db_data
                );
            } else {

                return $result;
            }
        } else {

            return $result;
        }
    }

    return $result;
}

/**
 * Get a Square terminal refund status.
 *
 * @since 1.17.0
 * @global wpdb $wpdb
 * @param string $refund_id The ID of the Square refund.
 */
function fsfwc_do_get_square_terminal_refund_status($refund_id = '') {

    global $wpdb;

    $result = array('status' => 'error');

    $square_access_token = get_option('globalFooSalesSquareAccessToken');

    if ('' === $square_access_token || '' === $refund_id) {

        return $result;
    }

    $refund = $wpdb->get_row(// phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}foosales_square_refunds WHERE refund_id = %s",
                    $refund_id
            ),
            'ARRAY_A'
    );

    if (empty($refund)) {

        return $result;
    }

    $result['status'] = $refund['status'];

    return $result;
}

/**
 * Generate an idempotency string.
 *
 * @since 1.16.0
 *
 * @return string
 */
function fsfwc_generate_idempotency_string() {
    return fsfwc_generate_random_string(8) . '-' . fsfwc_generate_random_string(4) . '-' . fsfwc_generate_random_string(4) . '-' . fsfwc_generate_random_string(4) . '-' . fsfwc_generate_random_string(12);
}

/**
 * Generate a random string.
 *
 * @since 1.15.0
 * @param int $length The length of the randomly generated string.
 *
 * @return string
 */
function fsfwc_generate_random_string($length = 4) {

    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $characters_length = strlen($characters);
    $random_string = '';

    for ($i = 0; $i < $length; $i++) {

        $random_string .= $characters[wp_rand(0, $characters_length - 1)];
    }

    return $random_string;
}
