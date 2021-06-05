<?php
/**
 * HTML template for all FooSales settings
 *
 * @link    https://www.foosales.com
 * @since   1.16.0
 * @package foosales
 */

?>
<div class="wrap" id="foosales-settings-page">
	<h1 class="wp-heading-inline"><?php echo esc_html( $foosales_phrases['title_foosales_settings'] ); ?></h1>
	<h2 class="nav-tab-wrapper">
		<a href="?page=foosales-settings&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $foosales_phrases['title_general'] ); ?></a>
		<a href="?page=foosales-settings&tab=products" class="nav-tab <?php echo 'products' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $foosales_phrases['title_products'] ); ?></a>
		<a href="?page=foosales-settings&tab=orders" class="nav-tab <?php echo 'orders' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $foosales_phrases['title_orders'] ); ?></a>
		<a href="?page=foosales-settings&tab=receipts" class="nav-tab <?php echo 'receipts' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $foosales_phrases['title_receipts'] ); ?></a>
		<a href="admin.php?page=wc-settings&tab=checkout" class="nav-tab"><?php echo esc_html( $foosales_phrases['title_payment_methods'] ); ?></a>
		<a href="?page=foosales-settings&tab=integration" class="nav-tab <?php echo 'integration' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $foosales_phrases['title_integration'] ); ?></a>
	</h2>
	<?php if ( 'general' !== $active_tab ) : ?>
	<form method="post" action="options.php">
	<?php endif; ?>
		<table class="form-table foosales-settings">
			<?php if ( 'general' === $active_tab ) : ?>
				<?php settings_fields( 'foosales-settings-general' ); ?>
				<?php do_settings_sections( 'foosales-settings-general' ); ?>
				<tr valign="top">
					<th scope="row" colspan="2">
						<div class="foosales-introduction">
							<?php if ( 'expired-plan' === $foosales_status ) : ?>
								<div class="foosales-connect">
									<div class="foosales-connection-status foosales-connection-error">
										<div><strong><?php echo esc_html( $foosales_phrases['label_expired_plan'] ); ?>: <?php echo esc_attr( $foosales_clean_url ); ?> <span class="dashicons dashicons-editor-unlink"></span></strong></div>
									</div>
									<h3><?php echo esc_html( $foosales_phrases['title_plan_inactive'] ); ?></h3>
									<p><?php echo esc_html( $foosales_phrases['description_plan_inactive'] ); ?></p>
									<a href="https://www.foosales.com/pricing/" class="button button-primary button-hero"><?php echo esc_html( $foosales_phrases['button_plans_pricing'] ); ?></a>
								</div>
							<?php elseif ( 'connected-plan' === $foosales_status ) : ?>
								<div class="foosales-connect">
									<div class="foosales-connection-status">
										<div><?php echo esc_html( $foosales_phrases['label_connected'] ); ?>: <strong><?php echo esc_attr( $foosales_clean_url ); ?></strong> <span class="dashicons dashicons-admin-links"></span></div>
									</div>
									<h3><?php echo esc_html( $foosales_phrases['title_connected'] ); ?></h3>
									<p><?php echo esc_html( $foosales_phrases['description_connected'] ); ?></p>
									<a href="https://www.foosales.com/pricing/" class="button button-primary button-hero"><?php echo esc_html( $foosales_phrases['button_addons'] ); ?></a>
								</div>
							<?php elseif ( 'expired-trial' === $foosales_status ) : ?>
								<div class="foosales-connect">
									<div class="foosales-connection-status foosales-connection-error">
										<div><strong><?php echo esc_html( $foosales_phrases['label_expired_free_trial'] ); ?>: <?php echo esc_attr( $foosales_clean_url ); ?> <span class="dashicons dashicons-editor-unlink"></span></strong></div>
									</div>
									<h3><?php echo esc_html( $foosales_phrases['title_expired_free_trial'] ); ?></h3>
									<p><?php echo esc_html( $foosales_phrases['description_expired_free_trial'] ); ?></p>
									<a href="https://www.foosales.com/pricing/" class="button button-primary button-hero"><?php echo esc_html( $foosales_phrases['button_plans_pricing'] ); ?></a>
								</div>
							<?php elseif ( 'active-trial' === $foosales_status ) : ?>
								<div class="foosales-connect">
									<div class="foosales-connection-status">
										<div><?php echo esc_html( $foosales_phrases['label_active_free_trial'] ); ?>: <strong><?php echo esc_attr( $foosales_clean_url ); ?></strong> <span class="dashicons dashicons-admin-links"></span></div>
									</div>
									<h3><?php echo esc_html( $foosales_phrases['title_active_free_trial'] ); ?></h3>
									<p><?php echo esc_html( $foosales_phrases['description_free_trial'] ); ?></p>
									<a href="https://www.foosales.com/pricing/" class="button button-primary button-hero"><?php echo esc_html( $foosales_phrases['button_plans_pricing'] ); ?></a>
								</div>
							<?php elseif ( 'static' === $foosales_status ) : ?>
								<div class="foosales-connect">
									<h3><?php echo esc_html( $foosales_phrases['title_static_account'] ); ?></h3>
									<p> <?php echo sprintf( esc_html( $foosales_phrases['description_static_account'] ), '<u>', '</u>' ); ?> </p>
									<a href="https://www.foosales.com/pricing/" class="button button-primary button-hero"><?php echo esc_html( $foosales_phrases['button_plans_pricing'] ); ?></a>
									<a href="https://www.foosales.com/my-account/" class="button button-primary button-hero"><?php echo esc_html( $foosales_phrases['button_my_account'] ); ?></a>
								</div>
							<?php elseif ( 'not-found' === $foosales_status ) : ?>
								<div class="foosales-connect">
									<div class="foosales-connection-status foosales-connection-warning">
										<div><?php echo esc_html( $foosales_phrases['label_domain_not_found'] ); ?>: <strong><?php echo esc_attr( $foosales_clean_url ); ?></strong> <span class="dashicons dashicons-admin-links"></span></div>
									</div>
									<h3><?php echo esc_html( $foosales_phrases['title_domain_not_found'] ); ?></h3>
									<p><?php echo esc_html( $foosales_phrases['description_domain_not_found'] ); ?></p>
									<form method="get" action="https://www.foosales.com/signup/" novalidate="novalidate">
										<input name="store" type="hidden" id="store" value="<?php echo esc_attr( $foosales_url ); ?>" class="regular-text" /> <input type="submit" name="submit" id="submit" class="button button-primary button-hero" value="<?php echo esc_attr( $foosales_phrases['button_link_store'] ); ?>"  />
									</form>
								</div>
							<?php endif; ?>
							<div class="foosales-download">
								<h3><?php echo esc_html( $foosales_phrases['title_download_apps'] ); ?></h3>
								<p><?php echo sprintf( esc_html( $foosales_phrases['description_download_apps'] ), '<a href="https://web.foosales.com" target="_blank">', '</a>' ); ?></p>
								<a href="https://www.foosales.com/downloads/ipad-app/" class="button" target="_blank"><?php echo esc_html( $foosales_phrases['button_ipad_app'] ); ?></a> <a href="https://www.foosales.com/downloads/android-app/" class="button" target="_blank"><?php echo esc_html( $foosales_phrases['button_android_app'] ); ?></a> <a href="https://web.foosales.com/" class="button" target="_blank"><?php echo esc_html( $foosales_phrases['button_web_app'] ); ?></a>
							</div>
						</div>
					</th>
				</tr>
			<?php endif; ?>

			<?php if ( 'products' === $active_tab ) : ?>
				<?php settings_fields( 'foosales-settings-products' ); ?>
				<?php do_settings_sections( 'foosales-settings-products' ); ?>
				<tr valign="top">
					<th scope="row" colspan="2">
						<p><?php echo esc_html( $foosales_phrases['description_product_settings'] ); ?></p>
					</th>
				</tr>
				<tr valign="top">
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_products_to_display'] ); ?></th>
					<td>
						<fieldset>
							<ul>
								<li><label><input type="radio" name="globalFooSalesProductsToDisplay" value="all" <?php echo ( 'all' === $products_to_display || empty( $products_to_display ) ) ? 'CHECKED' : ''; ?>> <?php echo esc_html( $foosales_phrases['radio_show_all_categories'] ); ?></label></li>
								<li><label><input type="radio" name="globalFooSalesProductsToDisplay" value="cat" <?php echo ( 'cat' === $products_to_display ) ? 'CHECKED' : ''; ?>> <?php echo esc_html( $foosales_phrases['radio_specific_categories'] ); ?>:</label></li>
							</ul>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th></th>
					<td>
						<select name="globalFooSalesProductCategories[]" id="globalFooSalesProductCategories" class="text" multiple="multiple" <?php echo ( 'all' === $products_to_display || empty( $products_to_display ) ) ? 'disabled="disabled"' : ''; ?> style="<?php echo ( count( $cat_options ) < 5 ) ? 'height:100px' : 'height:200px'; ?>">
							<?php
							foreach ( $cat_options as $category_id => $category_value ) {
								?>
								<option value="<?php echo esc_attr( $category_id ); ?>" <?php echo ! empty( $product_categories ) && in_array( (string) $category_id, $product_categories, true ) ? 'SELECTED' : ''; ?>><?php echo esc_attr( $category_value ); ?></option>
								<?php
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top"><?php echo esc_html( $foosales_phrases['setting_title_product_status'] ); ?></th>
					<td valign="top">
						<select name="globalFooSalesProductsStatus[]" id="globalFooSalesProductsStatus" class="text" multiple="multiple" size="<?php echo count( $status_options ); ?>" style="overflow:hidden;">
							<?php
							foreach ( $status_options as $status_option => $status_value ) {
								?>
								<option value="<?php echo esc_attr( $status_option ); ?>" <?php echo ! empty( $products_status ) && in_array( $status_option, $products_status, true ) ? 'SELECTED' : ''; ?>><?php echo esc_attr( $status_value ); ?></option>
								<?php
							}
							?>
						</select>
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_product_status_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_products_per_page'] ); ?></th>
					<td>
						<select name="globalFooSalesProductsPerPage" id="globalFooSalesProductsPerPage" class="text">
							<?php
							foreach ( $products_per_page_array as $products_per_page_amount ) {
								?>
								<option <?php echo ( ( ! empty( $products_per_page ) && $products_per_page === $products_per_page_amount ) || ( empty( $products_per_page ) && '500' === $products_per_page_amount ) ) ? 'SELECTED' : ''; ?>><?php echo esc_attr( $products_per_page_amount ); ?></option>
								<?php
							}
							?>
						</select>
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_products_per_page_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_show_products_in_stock'] ); ?></th>
					<td>
						<label><input type="checkbox" name="globalFooSalesProductsOnlyInStock" value="yes" <?php echo ( ! empty( $products_only_in_stock ) && 'yes' === $products_only_in_stock ) ? 'CHECKED' : ''; ?>></label>
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['checkbox_show_products_in_stock_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_show_attribute_labels'] ); ?></th>
					<td>
						<label><input type="checkbox" name="globalFooSalesProductsShowAttributeLabels" value="yes" <?php echo ( ! empty( $products_show_attribute_labels ) && 'yes' === $products_show_attribute_labels ) ? 'CHECKED' : ''; ?>></label>
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['checkbox_show_attribute_labels_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
			<?php endif; ?>

			<?php if ( 'orders' === $active_tab ) : ?>
				<?php settings_fields( 'foosales-settings-orders' ); ?>
				<?php do_settings_sections( 'foosales-settings-orders' ); ?>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_orders_to_load'] ); ?></th>
					<td>
						<select name="globalFooSalesOrdersToLoad" id="globalFooSalesOrdersToLoad" class="text">
							<?php
							foreach ( $order_limit_array as $order_limit ) {
								?>
								<option <?php echo ( ( ! empty( $orders_to_load ) && $orders_to_load === $order_limit ) || ( empty( $orders_to_load ) && '100' === $order_limit ) ) ? 'SELECTED' : ''; ?>><?php echo esc_attr( $order_limit ); ?></option>
								<?php
							}
							?>
						</select>
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_orders_to_load_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_disable_new_order_emails'] ); ?></th>
					<td>
						<label><input type="checkbox" name="globalFooSalesDisableNewOrderEmails" value="yes" <?php echo ( ! empty( $disable_new_order_emails ) && 'yes' === $disable_new_order_emails ) ? 'CHECKED' : ''; ?>></label>
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['checkbox_disable_new_order_emails_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
			<?php endif; ?>

			<?php if ( 'receipts' === $active_tab ) : ?>
				<?php settings_fields( 'foosales-settings-receipts' ); ?>
				<?php do_settings_sections( 'foosales-settings-receipts' ); ?>
				<tr valign="top">
					<th scope="row" colspan="2">
						<p><?php echo esc_html( $foosales_phrases['description_receipt_settings'] ); ?></p>
					</th>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_store_logo'] ); ?></th>
					<td>
						<input id="globalFooSalesStoreLogoURL" class="text uploadfield" type="text" size="40" name="globalFooSalesStoreLogoURL" value="<?php echo esc_attr( $store_logo_url ); ?>" />
						<span class="uploadbox"><input class="upload_image_button_foosales button" type="button" value="<?php echo esc_attr( $foosales_phrases['button_upload_store_logo'] ); ?>"><a href="#" class="upload_reset_foosales"><?php echo esc_html( $foosales_phrases['button_clear_store_logo'] ); ?></a></span>
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_store_logo_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_store_name'] ); ?></th>
					<td>
						<input id="globalFooSalesStoreName" class="text" type="text" size="40" name="globalFooSalesStoreName" value="<?php echo esc_attr( $store_name ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_store_name_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_header_content'] ); ?></th>
					<td valign="top">
						<textarea id="globalFooSalesHeaderContent" class="text" name="globalFooSalesHeaderContent"><?php echo esc_attr( $header_content ); ?></textarea>
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_header_content_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_receipt_title'] ); ?></th>
					<td>
						<input id="globalFooSalesReceiptTitle" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_receipt_title'] ); ?>" class="text" type="text" size="40" name="globalFooSalesReceiptTitle" value="<?php echo esc_attr( $receipt_title ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_receipt_title_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_order_number_prefix'] ); ?></th>
					<td>
						<input id="globalFooSalesOrderNumberPrefix" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_order_number_prefix'] ); ?>" class="text" type="text" size="40" name="globalFooSalesOrderNumberPrefix" value="<?php echo esc_attr( $order_number_prefix ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_order_number_prefix_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_product_column_title'] ); ?></th>
					<td>
						<input id="globalFooSalesProductColumnTitle" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_product_column_title'] ); ?>" class="text" type="text" size="40" name="globalFooSalesProductColumnTitle" value="<?php echo esc_attr( $product_column_title ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_product_column_title_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_quantity_column_title'] ); ?></th>
					<td>
						<input id="globalFooSalesQuantityColumnTitle" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_quantity_column_title'] ); ?>" class="text" type="text" size="40" name="globalFooSalesQuantityColumnTitle" value="<?php echo esc_attr( $quantity_column_title ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_quantity_column_title_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_price_column_title'] ); ?></th>
					<td>
						<input id="globalFooSalesPriceColumnTitle" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_price_column_title'] ); ?>" class="text" type="text" size="40" name="globalFooSalesPriceColumnTitle" value="<?php echo esc_attr( $price_column_title ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_price_column_title_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_subtotal_column_title'] ); ?></th>
					<td>
						<input id="globalFooSalesSubtotalColumnTitle" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_subtotal_column_title'] ); ?>" class="text" type="text" size="40" name="globalFooSalesSubtotalColumnTitle" value="<?php echo esc_attr( $subtotal_column_title ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_subtotal_column_title_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_incl_abbreviation'] ); ?></th>
					<td>
						<input id="globalFooSalesInclusiveAbbreviation" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_incl_abbreviation'] ); ?>" class="text" type="text" size="40" name="globalFooSalesInclusiveAbbreviation" value="<?php echo esc_attr( $inclusive_abbreviation ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_incl_abbreviation_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_excl_abbreviation'] ); ?></th>
					<td>
						<input id="globalFooSalesExclusiveAbbreviation" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_excl_abbreviation'] ); ?>" class="text" type="text" size="40" name="globalFooSalesExclusiveAbbreviation" value="<?php echo esc_attr( $exclusive_abbreviation ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_excl_abbreviation_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_discounts_title'] ); ?></th>
					<td>
						<input id="globalFooSalesDiscountsTitle" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_discounts_title'] ); ?>" class="text" type="text" size="40" name="globalFooSalesDiscountsTitle" value="<?php echo esc_attr( $discounts_title ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_discounts_title_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_refunds_title'] ); ?></th>
					<td>
						<input id="globalFooSalesRefundsTitle" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_refunds_title'] ); ?>" class="text" type="text" size="40" name="globalFooSalesRefundsTitle" value="<?php echo esc_attr( $refunds_title ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_refunds_title_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_tax_title'] ); ?></th>
					<td>
						<input id="globalFooSalesTaxTitle" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_tax_title'] ); ?>" class="text" type="text" size="40" name="globalFooSalesTaxTitle" value="<?php echo esc_attr( $tax_title ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_tax_title_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_total_title'] ); ?></th>
					<td>
						<input id="globalFooSalesTotalTitle" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_total_title'] ); ?>" class="text" type="text" size="40" name="globalFooSalesTotalTitle" value="<?php echo esc_attr( $total_title ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_total_title_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_payment_method_title'] ); ?></th>
					<td>
						<input id="globalFooSalesPaymentMethodTitle" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_payment_method_title'] ); ?>" class="text" type="text" size="40" name="globalFooSalesPaymentMethodTitle" value="<?php echo esc_attr( $payment_method_title ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_payment_method_title_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_billing_address_title'] ); ?></th>
					<td>
						<input id="globalFooSalesBillingAddressTitle" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_billing_address_title'] ); ?>" class="text" type="text" size="40" name="globalFooSalesBillingAddressTitle" value="<?php echo esc_attr( $billing_address_title ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_billing_address_title_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_shipping_address_title'] ); ?></th>
					<td>
						<input id="globalFooSalesShippingAddressTitle" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_shipping_address_title'] ); ?>" class="text" type="text" size="40" name="globalFooSalesShippingAddressTitle" value="<?php echo esc_attr( $shipping_address_title ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_shipping_address_title_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_footer_content'] ); ?></th>
					<td valign="top">
						<textarea id="globalFooSalesFooterContent" class="text" name="globalFooSalesFooterContent"><?php echo esc_attr( $footer_content ); ?></textarea>
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_footer_content_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_foosales_logo'] ); ?></th>
					<td>
						<label><input type="checkbox" name="globalFooSalesReceiptShowLogo" value="yes" <?php echo ( ! empty( $receipt_show_logo ) && 'yes' === $receipt_show_logo ) ? 'CHECKED' : ''; ?>></label>
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['checkbox_title_foosales_logo_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
			<?php endif; ?>

			<?php if ( 'integration' === $active_tab ) : ?>
				<?php settings_fields( 'foosales-settings-integration' ); ?>
				<?php do_settings_sections( 'foosales-settings-integration' ); ?>
				<tr valign="top">
					<th scope="row" colspan="2">
						<h2><?php echo esc_html( $foosales_phrases['title_square_payments'] ); ?></h2>
						<p><?php echo sprintf( esc_html( $foosales_phrases['description_square_payments'] ), '<a href="https://www.foosales.com/features/hardware/#square-payments" target="_blank">', '</a>' ) . '<br/>' . esc_html( $foosales_phrases['description_need_help_square_payments'] ) . ' <a href="https://help.foosales.com/docs/topics/payments/square-payment-integration/" target="_blank">' . esc_html( $foosales_phrases['button_click_here'] ) . '</a>'; ?></p>
					</th>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_square_application_id'] ); ?></th>
					<td>
						<input id="globalFooSalesSquareApplicationID" class="text" type="text" size="40" name="globalFooSalesSquareApplicationID" value="<?php echo esc_attr( $square_application_id ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_square_application_id_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( $foosales_phrases['setting_title_square_access_token'] ); ?></th>
					<td>
						<input id="globalFooSalesSquareAccessToken" class="text" placeholder="<?php echo esc_attr( $foosales_phrases['placeholder_square_access_token'] ); ?>" type="password" size="40" name="globalFooSalesSquareAccessToken" value="<?php echo esc_attr( $square_access_token ); ?>" />
						<img class="help_tip foosales-tooltip" title="<?php echo esc_attr( $foosales_phrases['setting_title_square_access_token_tooltip'] ); ?>" src="<?php echo esc_attr( plugins_url() ); ?>/woocommerce/assets/images/help.png" height="16" width="16" />
					</td>
				</tr>
			<?php endif; ?>
		</table>
	<?php if ( 'general' !== $active_tab ) : ?>
		<?php submit_button(); ?>
	</form>
	<?php endif; ?>
</div>
