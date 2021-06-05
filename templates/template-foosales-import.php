<?php
/**
 * HTML template for the FooSales importer that imports offline changes made in the FooSales app
 *
 * @link    https://www.foosales.com
 * @since   1.12.0
 * @package foosales
 */

?>
<div class="wrap">
	<h1><?php echo esc_html( $foosales_phrases['title_foosales_import'] ); ?></h1>
	<?php
	if ( isset( $_FILES['foosales-import'] ) && check_admin_referer( 'foosales-import' ) ) {
		if ( isset( $_FILES['foosales-import']['error'] ) && $_FILES['foosales-import']['error'] > 0 ) {
			wp_die( esc_html( $foosales_phrases['error_xml_import'] ) );
		} else {
			$file_name       = isset( $_FILES['foosales-import']['name'] ) ? sanitize_text_field( wp_unslash( $_FILES['foosales-import']['name'] ) ) : '';
			$file_name_split = explode( '.', $file_name );
			$file_ext        = strtolower( end( $file_name_split ) );

			if ( 'xml' === $file_ext ) {
				WP_Filesystem();

				global $wp_filesystem;

				libxml_use_internal_errors( true );

				$xml_data = $wp_filesystem->get_contents( sanitize_text_field( wp_unslash( isset( $_FILES['foosales-import']['tmp_name'] ) ? $_FILES['foosales-import']['tmp_name'] : '' ) ) );
				$xml      = simplexml_load_string( $xml_data );

				if ( false === $xml || 'foosales_changes' !== $xml->getName() ) {
					echo "<div class='notice notice-error is-dismissible'><p>" . esc_html( $foosales_phrases['description_unable_to_read_xml'] ) . '</p></div>';
					?>
					<p>
					<?php
					foreach ( libxml_get_errors() as $import_error ) {
						printf(
							'<strong>%s %s, %s %s:</strong> <em>%s</em><br/>',
							esc_html( $foosales_phrases['label_line'] ),
							esc_html( $import_error->line ),
							esc_html( $foosales_phrases['label_column'] ),
							esc_html( $import_error->column ),
							esc_html( $import_error->message )
						);
					}
					?>
					</p>
					<?php
				} else {
					$total_changes = count( $xml->children() );

					if ( $total_changes > 0 ) {
						$foosales_offline_changes = array();
						$offline_changes          = get_option( 'globalFooSalesOfflineChanges' );

						if ( false !== $offline_changes ) {
							$foosales_offline_changes = json_decode( get_option( 'globalFooSalesOfflineChanges' ), true );
						}
						?>
						<h3><?php echo esc_html( $foosales_phrases['title_importing_offline_changes'] ); ?></h3>
						<?php
						ob_start();

						printf(
							"%s: %d\n\n",
							esc_html( $foosales_phrases['label_offline_changes_imported'] ),
							esc_html( $total_changes )
						);

						$change_count = 1;
						$failure      = false;

						foreach ( $xml->children() as $offline_change ) {
							$offline_change_id   = $offline_change->offline_change_id->__toString();
							$offline_change_type = $offline_change->offline_change_type->__toString();

							$already_imported = in_array( $offline_change_id, $foosales_offline_changes, true ) !== false;

							$import_title  = '';
							$import_result = '';

							if ( 'update_product' === $offline_change_type ) {
								$product_id            = 0;
								$product_price         = 0;
								$product_regular_price = 0;
								$product_sale_price    = 0;
								$product_stock         = 0;

								if ( isset( $offline_change->product_data ) ) {
									$update_product_params = json_decode( $offline_change->product_data->__toString(), true );

									$product_id            = $update_product_params['pid'];
									$product_price         = $update_product_params['pp'];
									$product_regular_price = $update_product_params['prp'];
									$product_sale_price    = $update_product_params['psp'];
									$product_stock         = $update_product_params['ps'];
								} else {
									$product_id            = $offline_change->product_id->__toString();
									$product_price         = $offline_change->product_price->__toString();
									$product_regular_price = $offline_change->product_regular_price->__toString();
									$product_sale_price    = $offline_change->product_sale_price->__toString();
									$product_stock         = $offline_change->product_stock->__toString();
								}

								$import_title = esc_html( $foosales_phrases['label_update_product'] ) . '"' . (string) htmlspecialchars_decode( get_post_field( 'post_title', $product_id ) ) . '" (' . $product_id . ')';

								if ( $already_imported ) {
									$import_result = esc_html( $foosales_phrases['label_update_product_skipped'] ) . "\n";
								} else {
									$foosales_offline_changes[] = $offline_change_id;

									$update_product_result = false;

									if ( isset( $offline_change->product_data ) ) {
										$update_product_params = json_decode( $offline_change->product_data->__toString(), true );

										$update_product_result = fsfwc_do_update_product( $update_product_params );
									} else {
										$update_product_params = array(
											$product_id,
											$product_price,
											$product_regular_price,
											$product_sale_price,
											$product_stock,
										);

										$update_product_result = fsfwc_do_update_product_v1( $update_product_params );
									}

									if ( false !== $update_product_result ) {
										$import_result .= "\n";
										$import_result .= "\t- " . esc_html( $foosales_phrases['label_update_product_set_price'] ) . ': ' . wp_strip_all_tags( wc_price( $product_price ) ) . "\n";
										$import_result .= "\t- " . esc_html( $foosales_phrases['label_update_product_set_regular_price'] ) . ': ' . wp_strip_all_tags( wc_price( $product_regular_price ) ) . "\n";
										$import_result .= "\t- " . esc_html( $foosales_phrases['label_update_product_set_sale_price'] ) . ': ' . wp_strip_all_tags( wc_price( $product_sale_price ) ) . "\n";
										$import_result .= "\t- " . esc_html( $foosales_phrases['label_update_product_set_stock'] ) . ': ' . $product_stock . "\n";

									} else {
										$import_result = 'failed';

										$failure = true;
									}
								}
							} elseif ( 'cancel_order' === $offline_change_type ) {
								$order_id = $offline_change->order_id->__toString();
								$restock  = (bool) $offline_change->return_stock->__toString();

								$import_title = esc_html( $foosales_phrases['label_cancel_order'] ) . ' #' . $order_id;

								if ( $already_imported ) {
									$import_result = esc_html( $foosales_phrases['label_cancel_order_skipped'] ) . "\n";
								} else {
									$success = fsfwc_do_cancel_order( $order_id, $restock );

									if ( $success ) {
										$foosales_offline_changes[] = $offline_change_id;

										$import_result .= "\n";
										$import_result .= "\t- " . esc_html( $foosales_phrases['label_cancel_order_set_status'] ) . "\n";

										if ( $restock ) {
											$import_result .= "\t- " . esc_html( $foosales_phrases['label_cancel_order_restocked_items'] ) . "\n";
										}
									} else {
										$import_result = 'failed';

										$failure = true;
									}
								}
							} elseif ( 'refund_order' === $offline_change_type ) {
								$order_id = $offline_change->order_id->__toString();
								$restock  = (bool) $offline_change->return_stock->__toString();

								$import_title = esc_html( $foosales_phrases['label_refund_order'] ) . ' #' . $order_id;

								if ( $already_imported ) {
									$import_result = esc_html( $foosales_phrases['label_refund_order_skipped'] ) . "\n";
								} else {
									$refunded_items = array();

									$import_result .= "\n";

									$total_refunded = 0.0;

									foreach ( $offline_change->refunded_items->children() as $refunded_item ) {

										$refunded_item = array(
											'refund_total' => $refunded_item->refund_total->__toString(),
											'refund_tax'   => $refunded_item->refund_tax->__toString(),
											'refund_tax_class' => $refunded_item->refund_tax_class->__toString(),
											'oipid'        => $refunded_item->order_item_product_id->__toString(),
											'qty'          => $refunded_item->quantity->__toString(),
											'restock_qty'  => $refunded_item->restock_quantity->__toString(),
											'oiid'         => $refunded_item->order_item_id->__toString(),
										);

										$total_refunded += (float) $refunded_item['refund_total'] + (float) $refunded_item['refund_tax'];

										$wc_product = wc_get_product( $refunded_item['oipid'] );

										$import_result .= "\t- " . $refunded_item['qty'] . ' x "' . $wc_product->get_title() . '", ' . wp_strip_all_tags( wc_price( (float) $refunded_item['refund_total'] + (float) $refunded_item['refund_tax'] ) ) . ', ' . $refunded_item['restock_qty'] . ' ' . esc_html( $foosales_phrases['label_refund_order_restocked'] ) . "\n";

										$refunded_items[] = $refunded_item;
									}

									$import_result .= "\t- " . esc_html( $foosales_phrases['label_refund_order_total_refunded'] ) . ': ' . wp_strip_all_tags( wc_price( $total_refunded ) ) . "\n";

									$refund_result = fsfwc_do_refund_order( $order_id, $refunded_items );
									$wc_order      = $refund_result['order'];

									if ( ! empty( $refund_result['square_refund'] ) ) {

										if ( 'success' === $refund_result['square_refund'] ) {

											$import_result .= "\t\t* " . esc_html( $foosales_phrases['label_refund_order_square_refunded_success'] ) . "\n";

										} else {

											$import_result .= "\t\t* " . esc_html( $foosales_phrases['label_refund_order_square_refunded_fail'] ) . "\n";

										}
									}

									$success = ! empty( $wc_order );

									if ( $success ) {
										$foosales_offline_changes[] = $offline_change_id;
									} else {
										$import_result = 'failed';

										$failure = true;
									}
								}
							} elseif ( 'create_order' === $offline_change_type ) {
								$import_title = esc_html( $foosales_phrases['label_create_order'] );

								if ( $already_imported ) {
									$import_result = esc( $foosales_phrases['label_create_order_skipped'] ) . "\n";
								} else {
									$import_result .= "\n";

									$order_items = array();

									foreach ( $offline_change->order_items->children() as $order_item ) {
										$order_item = array(
											'oilst' => $order_item->line_subtotal->__toString(),
											'oiq'   => $order_item->quantity->__toString(),
											'pid'   => $order_item->product_id->__toString(),
											'oiltl' => $order_item->line_total->__toString(),
										);

										$order_items[] = $order_item;

										$wc_product = wc_get_product( $order_item['pid'] );

										$product_title = $wc_product->get_title();

										if ( 'variation' === $wc_product->get_type() ) {
											$product_title .= ' (';

											$atts = $wc_product->get_attributes();

											$att_values = array();

											foreach ( $atts as $att_name => $att_value ) {
												$att_values[] = $att_value;
											}

											$product_title .= implode( ', ', $att_values ) . ')';
										}

										$import_result .= "\t- " . $order_item['oiq'] . ' x "' . $product_title . '", ' . wp_strip_all_tags( wc_price( $order_item['oiltl'] ) ) . "\n";
									}

									$order_items_json = wp_json_encode( $order_items );

									$order_customer = array(
										'cid' => '',
									);

									if ( isset( $offline_change->customer ) ) {
										$order_customer = array(
											'cid' => $offline_change->customer->id->__toString(),
											'cfn' => isset( $offline_change->customer->first_name ) ? trim( $offline_change->customer->first_name->__toString() ) : '',
											'cln' => isset( $offline_change->customer->last_name ) ? trim( $offline_change->customer->last_name->__toString() ) : '',
											'ce'  => isset( $offline_change->customer->email ) ? trim( $offline_change->customer->email->__toString() ) : '',
										);

										$import_result .= "\t- " . esc_html( $foosales_phrases['label_create_order_set_customer'] ) . ': ' . $order_customer['cfn'] . ' ' . $order_customer['cln'] . ' ( ' . $order_customer['cid'] . ' ) - ' . $order_customer['ce'] . "\n";

										if ( isset( $offline_change->customer->billing_address ) ) {
											$import_result .= "\t- " . esc_html( $foosales_phrases['label_create_order_set_billing_address'] ) . "\n";

											$order_customer['cbfn'] = isset( $offline_change->customer->billing_address->first_name ) ? trim( $offline_change->customer->billing_address->first_name->__toString() ) : '';
											$order_customer['cbln'] = isset( $offline_change->customer->billing_address->last_name ) ? trim( $offline_change->customer->billing_address->last_name->__toString() ) : '';
											$order_customer['cbco'] = isset( $offline_change->customer->billing_address->company ) ? trim( $offline_change->customer->billing_address->company->__toString() ) : '';
											$order_customer['cba1'] = isset( $offline_change->customer->billing_address->address_1 ) ? trim( $offline_change->customer->billing_address->address_1->__toString() ) : '';
											$order_customer['cba2'] = isset( $offline_change->customer->billing_address->address_2 ) ? trim( $offline_change->customer->billing_address->address_2->__toString() ) : '';
											$order_customer['cbc']  = isset( $offline_change->customer->billing_address->city ) ? trim( $offline_change->customer->billing_address->city->__toString() ) : '';
											$order_customer['cbpo'] = isset( $offline_change->customer->billing_address->post_code ) ? trim( $offline_change->customer->billing_address->post_code->__toString() ) : '';
											$order_customer['cbcu'] = isset( $offline_change->customer->billing_address->country ) ? trim( $offline_change->customer->billing_address->country->__toString() ) : '';
											$order_customer['cbs']  = isset( $offline_change->customer->billing_address->state ) ? trim( $offline_change->customer->billing_address->state->__toString() ) : '';
											$order_customer['cbph'] = isset( $offline_change->customer->billing_address->phone ) ? trim( $offline_change->customer->billing_address->phone->__toString() ) : '';
											$order_customer['cbe']  = isset( $offline_change->customer->billing_address->email ) ? trim( $offline_change->customer->billing_address->email->__toString() ) : '';
										}

										if ( isset( $offline_change->customer->shipping_address ) ) {
											$import_result .= "\t- " . esc_html( $foosales_phrases['label_create_order_set_shipping_address'] ) . "\n";

											$order_customer['csfn'] = isset( $offline_change->customer->shipping_address->first_name ) ? trim( $offline_change->customer->shipping_address->first_name->__toString() ) : '';
											$order_customer['csln'] = isset( $offline_change->customer->shipping_address->last_name ) ? trim( $offline_change->customer->shipping_address->last_name->__toString() ) : '';
											$order_customer['csco'] = isset( $offline_change->customer->shipping_address->company ) ? trim( $offline_change->customer->shipping_address->company->__toString() ) : '';
											$order_customer['csa1'] = isset( $offline_change->customer->shipping_address->address_1 ) ? trim( $offline_change->customer->shipping_address->address_1->__toString() ) : '';
											$order_customer['csa2'] = isset( $offline_change->customer->shipping_address->address_2 ) ? trim( $offline_change->customer->shipping_address->address_2->__toString() ) : '';
											$order_customer['csc']  = isset( $offline_change->customer->shipping_address->city ) ? trim( $offline_change->customer->shipping_address->city->__toString() ) : '';
											$order_customer['cspo'] = isset( $offline_change->customer->shipping_address->post_code ) ? trim( $offline_change->customer->shipping_address->post_code->__toString() ) : '';
											$order_customer['cscu'] = isset( $offline_change->customer->shipping_address->country ) ? trim( $offline_change->customer->shipping_address->country->__toString() ) : '';
											$order_customer['css']  = isset( $offline_change->customer->shipping_address->state ) ? trim( $offline_change->customer->shipping_address->state->__toString() ) : '';
										}
									}

									$order_customer_json = wp_json_encode( $order_customer );

									$order_attendee_details = array();

									if ( isset( $offline_change->attendee_details ) ) {
										foreach ( $offline_change->attendee_details->children() as $attendee_detail ) {
											$element_name = substr( $attendee_detail->getName(), strlen( 'attendee_' ) );

											$order_attendee_details[ $element_name ] = $attendee_detail->__toString();
										}
									}

									$order_attendee_details_json = wp_json_encode( $order_attendee_details );

									$order_params = array(
										$offline_change->date->__toString(),
										$offline_change->payment_method->__toString(),
										'[]',
										$order_items_json,
										$order_customer_json,
										$offline_change->order_note->__toString(),
										$offline_change->order_note_send_to_customer->__toString(),
										$order_attendee_details_json,
										$offline_change->square_order_id->__toString(),
										$offline_change->user_id->__toString(),
									);

									$import_result .= "\t- " . esc_html( $foosales_phrases['label_create_order_set_order_date'] ) . ': ' . gmdate( 'Y-m-d H:i:s', (int) $offline_change->date->__toString() ) . "\n";
									$import_result .= "\t- " . esc_html( $foosales_phrases['label_create_order_set_payment_method'] ) . ': "' . fsfwc_get_payment_method_from_key( $offline_change->payment_method->__toString() ) . "\"\n";

									$imported_order = fsfwc_do_create_new_order( $order_params );

									$success = ! empty( $imported_order ) && false !== $imported_order;

									if ( $success ) {
										$import_result .= "\t- " . esc_html( $foosales_phrases['label_create_order_total'] ) . ': ' . wp_strip_all_tags( wc_price( $imported_order->get_total() ) ) . "\n";

										$foosales_offline_changes[] = $offline_change_id;
									} else {
										$import_result = 'failed';

										$failure = true;
									}
								}
							}

							printf(
								"%d/%d %s: %s\n",
								esc_html( $change_count++ ),
								esc_html( $total_changes ),
								esc_html( $import_title ),
								esc_html( $import_result )
							);
						}

						update_option( 'globalFooSalesOfflineChanges', wp_json_encode( $foosales_offline_changes ) );

						$import_output = ob_get_contents();

						ob_get_clean();
						?>
					<textarea id="foosales_import_log" class="widefat" rows="10" readonly><?php echo esc_html( $import_output ); ?></textarea>
					<p style="text-align:right;"><button class="button button-primary" href="javascript:void(0);" id="foosales_import_log_copy_button"><?php echo esc_html( $foosales_phrases['button_copy_to_clipboard'] ); ?></button></p>
						<?php
						if ( $failure ) {
							echo "<div class='notice notice-error is-dismissible'><p>" . esc_html( $foosales_phrases['description_problem_importing'] ) . '</p></div>';
						} else {
							echo "<div class='notice notice-success is-dismissible'><p>" . esc_html( $foosales_phrases['description_all_changes_imported'] ) . '</p></div>';
						}
					} else {
						echo "<div class='notice notice-error is-dismissible'><p>" . esc_html( $foosales_phrases['description_no_changes_found'] ) . '</p></div>';
					}
				}
			} else {
				echo "<div class='notice notice-error is-dismissible'><p>" . esc_html( $foosales_phrases['description_invalid_file_format'] ) . '</p></div>';
			}
		}
		?>
		<hr />
		<?php
	}
	?>
	<p><?php echo esc_html( $foosales_phrases['description_import_intro'] ); ?></p>
	<p><?php echo esc_html( $foosales_phrases['description_import_xml'] ); ?></p>
	<form enctype="multipart/form-data" id="foosales-import-upload-form" method="post" class="wp-upload-form" action="">
		<p>
			<?php
				wp_nonce_field( 'foosales-import' );

				$bytes = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
				$size  = size_format( $bytes );

				printf(
					'<label for="upload">%s:</label> (%s)',
					esc_html( $foosales_phrases['label_select_xml'] ),
					sprintf( esc_html( $foosales_phrases['label_maximum_size'] ) . ': %s', esc_html( $size ) )
				);
				?>
			<input type="file" id="upload" name="foosales-import" size="25" accept=".xml" />
		</p>
		<?php submit_button( esc_html( $foosales_phrases['button_upload_import'] ), 'primary' ); ?>
	</form>
</div>
<style type="text/css">
	img#foosales_importing_spinner {
		vertical-align:text-bottom;
		margin-left:1em;
	}
</style>
<script type="text/javascript">
	jQuery( 'form#foosales-import-upload-form' ).submit(function(e) {
		jQuery( 'form#foosales-import-upload-form input#submit' ).attr( 'value', '<?php echo esc_html( $foosales_phrases['button_importing_wait'] ); ?>' ).prop( 'disabled', true).parent().append( '<img src="<?php echo esc_attr( get_admin_url() ); ?>images/loading.gif" id="foosales_importing_spinner" />' );
	} );

	jQuery( 'button#foosales_import_log_copy_button' ).click(function() {
		var copyButton = jQuery(this );

		copyButton.prop( 'disabled', true );

		jQuery( 'textarea#foosales_import_log' ).select();

		document.execCommand( 'copy' );

		copyButton.text( '<?php echo esc_html( $foosales_phrases['button_copied'] ); ?>' );

		setTimeout(function() {
			copyButton.text( '<?php echo esc_html( $foosales_phrases['button_copy_to_clipboard'] ); ?>' );

			copyButton.prop( 'disabled', false );
		}, 1000 );
	} );
</script>
