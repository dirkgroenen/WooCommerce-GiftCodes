<?php
/* 
	Plugin Name: WooCommerce - Gift codes 
	Plugin URI: https://www.bitlabs.nl 
	Version: 1.0 
	Author: Bitlabs
	Author URI: https://www.bitlabs.nl
	Description: Create a database with giftcodes (for example Spotify or iTunes Giftcard Codes) and distribute them on a purchese of a specific article.
	*/  
	
	/**
	 * Check if WooCommerce is active and there isn't a class called WC_Giftcodes_dist
	 **/
	if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		if ( ! class_exists( 'WC_Giftcodes_dist' ) ) {
			/**
			 * Localisation
			 */
			load_plugin_textdomain('wcblgiftcodes', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');
			
			
			/* Start code */
			class WC_Giftcodes_dist {
				
				public function __construct(){
					// Load includes
					$this->includes();
					
					// Load settings
					$this->settings = new WC_Giftcodes_Settings();
					
					// Add giftcode post creator action
					add_action('init', array($this, 'create_giftcodes_post_type'));

					/*
					* Add action that will check the current stock of an article
					* Moments to check: after checkout, on new giftcode add, on new product add, after giftcard provide funcion
					*/
					add_action('giftcodes_add_giftcode', array($this, 'check_product_giftcard_stock'));
					add_action('deleted_post', array($this, 'check_product_giftcard_stock'));
					//add_action('woocommerce_create_product_variation', array($this, 'check_product_giftcard_stock'));
					//add_action('after_provide_giftcode', array($this, 'check_product_giftcard_stock'));
					
					/* Actions that will distribute the codes */
					add_action('woocommerce_order_status_processing', array($this, 'order_changed_status_check'),10,1); //woocommerce_order_status_pending,
					//add_action('woocommerce_payment_complete', array($this, 'order_changed_status_check'),10,1); //woocommerce_order_status_pending
					
					// Show codes on thankyou page hook
					add_action('woocommerce_thankyou', array(&$this, 'order_complete_thank_you_page') );
					add_action('woocommerce_view_order', array(&$this, 'order_complete_thank_you_page') );
					
					// Hook for admin menu
					add_filter( 'views_edit-shop_giftcodes', array(&$this, 'giftcodes_menu_refresh_stock_button') );
					add_action( 'woocommerce_api_wc_giftcodes_dist', array( $this, 'refresh_products_stock' ) );
				
					register_activation_hook( __FILE__, array( 'WCGiftcodes_Settings', 'default_settings' ) );
				}
				
				/**
				 * Load additional classes and functions
				 */
				public function includes() {
					include_once( 'includes/wcgiftcodes-settings.php' );
				}
				
				
				/*
				* Add button to custom post type page
				*/
				public function giftcodes_menu_refresh_stock_button($views){
					$refreshlink = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'wc_giftcodes_dist', home_url( '/' ) ) );
				
					$views['giftcodes-update-stock'] = '<a href="'.$refreshlink.'" id="update-product-stock" type="button"  class="button" title="'.__("Update products with giftcard stock", "wcblgiftcodes").'" style="margin:5px">'.__("Update product stock", "wcblgiftcodes").'</a>';
					return $views;
				}
				
				/*
				* Refresh the product stock
				*/
				public function refresh_products_stock(){
					$this->check_product_giftcard_stock();
					header('Location: /wp-admin/edit.php?post_type=shop_giftcodes');
				}
				
				/**
				* Create new post type and page in admin menu
				*/				
				public function create_giftcodes_post_type() {
					register_post_type( 'shop_giftcodes',
						array(
							'labels' => array(
								'name' => __( 'Giftcard codes', 'wcblgiftcodes' ),
								'singular_name' => __( 'Giftcard codes', 'wcblgiftcodes' ),
								'add_new'            => _x( 'Add New', 'code', 'wcblgiftcodes' ),
								'add_new_item'       => __( 'Add New Code', 'wcblgiftcodes' ),
								'edit_item'          => __( 'Edit Code', 'wcblgiftcodes' ),
								'new_item'           => __( 'New Code', 'wcblgiftcodes' ),
								'view_item'          => __( 'View Code', 'wcblgiftcodes' ),
								'search_items'       => __( 'Search Codes', 'wcblgiftcodes' ),
								'not_found'          => __( 'No codes found', 'wcblgiftcodes' ),
								'not_found_in_trash' => __( 'No codes found in the Trash', 'wcblgiftcodes' )
							),
						'has_archive' => true,
						'show_ui' => true,
						'show_in_menu' => 'woocommerce',
						'supports' => array( 'title' ),
						)
					);
				}
				
				/*
				* Change the stock according to the amount of available giftcodes
				*/
				function check_product_giftcard_stock(){
					global $woocommerce;	
					$this->logger = $woocommerce->logger();
					$this->logger->add("giftcodes", "Check giftcard stock");
					
					$all_products = get_posts(array('post_type' => 'product_variation', 'posts_per_page' => -1));
					$new_stock = array();
					
					/* Check the giftcodes per product*/
					foreach($all_products as $product){
						$product = get_product($product->ID);
						$product->set_stock_status("instock");
						
						$product_id = $product->variation_id;
						$product_stock = $product->total_stock;
						
						// Add product to array
						$new_stock[$product_id] = 0;
						
						/* Check if there is a giftcard code */
						$giftcode_list = get_posts(array('post_type' => 'shop_giftcodes', 'post_status' => 'publish', 'posts_per_page' => -1));
						foreach($giftcode_list as $giftcode){							
							$code_product_id = get_post_custom_values('_wcgc_product_id', $giftcode->ID);
							if($code_product_id[0] == $product_id){
								
								$code_sold = get_post_custom_values('_wcgc_sold', $giftcode->ID);							
							
								// Check if the code isn't already sold, if it isn't: increase product stock in array
								if($code_sold[0] != 'on'){
									$new_stock[$product_id]++;
								}
							}
						}
					}
					
					/* Change product stock */
					foreach($new_stock as $id => $stock){
						// Change stock
						$product = get_product($id);
						$product->set_stock($stock);
						
						$this->logger->add("giftcodes", "Set giftcard stock [".$stock."] for ".$product->id);
					}
				}	

				/*
				* Function that returns an available code for the given product
				* @param1: product
				* @return: code
				*/
				function get_next_avail_giftcode( $product_id, $order_id ){	
						
					/* Get first available code */
					foreach(get_posts(array('post_type' => 'shop_giftcodes', 'post_status' => 'publish', 'posts_per_page' => -1)) as $giftcode){							
						$code_product_id = get_post_custom_values('_wcgc_product_id', $giftcode->ID);
						
						if($code_product_id[0] == $product_id){
							$code_sold = get_post_custom_values('_wcgc_sold', $giftcode->ID);							

							// Check if the code isn't already sold, if it isn't: increase product stock in array
							if($code_sold[0] != 'on'){

								// Get the code and set the code_sold status to on
								$code = $giftcode->post_title;
								update_post_meta($giftcode->ID, '_wcgc_sold', 'on');
								update_post_meta($giftcode->ID, '_wcgc_sold_to', $order_id);
								
								// Stop the foreach loop
								break;
							}
						}
					}
					return $code;
				}
				
				/*
				* Run when the status of an order changed
				*/
				public function order_changed_status_check( $order_id ){
					$this->order_finished_provide_code($order_id);
				}
				
				/**
				* Run when an order was payed
				*/
				public function order_finished_provide_code( $order_id ){
					global $woocommerce;	
					$this->logger = $woocommerce->logger();
					
					// Get the order object and save it in $order
					$order = new WC_Order($order_id);
					
					$this->logger->add("giftcodes", "Provide code for order ".$order_id);
					
					// Check the status of the order. Status has to be 'pending'
					if($order_id != 0){						
						$giftcodes = array();
						
						// Get the products and loop through them
						$items = $order->get_items();
						$this->logger->add( 'giftcodes', 'Products: ' . print_r( $items, true ) );
						
						foreach($items as $item){
							for($y = 0;$y < $item['qty'];$y++){
								$product = $order->get_product_from_item($item);
								$product_variation = $product->variation_id;
									
								// Distribute the code
								$giftcode = $this->get_next_avail_giftcode($product_variation, $order_id);
								$giftcodes[($y+1).' '.woocommerce_get_formatted_product_name($product)] = $giftcode;
								
								update_post_meta( $order->id, 'Giftcard code '.$product->get_sku(). " #".$y, $giftcode );
								$this->logger->add("giftcodes", "Code found for ".$product->get_sku().": ". $giftcode);
							}
						}
						
						// Add note to order that will show the codes for the products
						$notebody = "<p>".__("We have just added the codes to your order:", 'wcblgiftcodes')."</p>";
						foreach($giftcodes as $name => $code){
							$notebody .= $name.": <b>".$code."</b><br/>";
						}
						
						$order->add_order_note($notebody, 1);
						update_post_meta( $order->id, 'Provided codes', implode('|',$giftcodes) );
											
						// Set status to completed
						if(count($giftcodes) > 0){
							$order->update_status('completed', "Giftcodes provided");
							update_post_meta( $order->id, 'Giftcard provided', 'yes');
						}
						
						do_action("after_provide_giftcode");
					}
				}
				
				/*
				* Show codes on the thank you page
				*/
				public function order_complete_thank_you_page($orderid){
					$order = new WC_Order($orderid);	
					if(in_array( $order->status, array( 'completed' ) )){
						// Get the products and loop through them
						$items = $order->get_items();
						$giftcodes = explode('|', get_post_meta($orderid, 'Provided codes', true));
						
						if(count($giftcodes) > 0){
							?>
							<p><?php _e( 'Your codes are:', 'wcblgiftcodes' ); ?></p>
							<?php
						
							$x = 0;
							foreach($items as $item){
								for($y = 0;$y < $item['qty'];$y++){
									$product = $order->get_product_from_item($item);
									
									?>
										<ul class="order_details">
											<li class="name">
												<?php _e( 'Product:', 'wcblgiftcodes' ); ?>
												<strong><?php echo woocommerce_get_formatted_product_name($product); ?></strong>
											</li>
											<li class="code">
												<?php _e( 'Code:', 'wcblgiftcodes' ); ?>
												<?php echo $giftcodes[$x]; ?>
											</li>
										</ul>
										<div class="clear"></div>
									<?
									
									// Add one to the x, X is used to get de giftcodes from the array
									$x++;
								}
							}
						}
					}
				}
				
			}
			
			$GLOBALS['wc_giftcodes_dist'] = new WC_Giftcodes_dist();
		}
	}
?>