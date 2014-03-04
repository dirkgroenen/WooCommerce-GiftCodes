<?php
	/*
	* Setup the array with input fields
	*/
	function init_post_meta_fields() {
		global $wcgiftcodes_meta_fields;
		global $prefix;
		
		/* Get all products and variations */
		$all_products = array();
		$products_list = search_products('spot', array('product', 'product_variation'));					
		foreach ( $products_list as $key => $product_name) {
			$all_products[$key] = array('label' => $product_name, 'value' => $key);
		}
		
		/* Create the array that will be the source for all elements inside the custom box */	
		$prefix = '_wcgc_';  
		$wcgiftcodes_meta_fields = array(  
			array(  
				'label'=> 'Product link',  
				'desc'  => 'Select the product which is linked with this code.',  
				'id'    => $prefix.'product_id',  
				'type'  => 'select',  
				'options' => $all_products
			),
			array(  
				'label'=> 'Sold',  
				'desc'  => 'Check this if the code is sold. Default: not checked!',  
				'id'    => $prefix.'sold',  
				'type'  => 'checkbox'
			)  
		); 	
	}
	add_action('init', 'init_post_meta_fields');
		
	/**
	* Change the columns on the Post type page
	*/
	function change_giftcodes_columns( $cols ) {
		$cols = array(
			'cb'       => '<input type="checkbox" />',
			'title'      => __( 'Giftcard code', 'WCGiftcodes' ),
			'product' => __( 'Product', 'WCGiftcodes' ),
			'sold'     => __( 'Sold', 'WCGiftcodes' ),
			'sold_to'     => __( 'Sold to order', 'WCGiftcodes' ),
		);
		
		return $cols;
	}
	add_filter( 'manage_shop_giftcodes_posts_columns', 'change_giftcodes_columns' );

	/**
	* Fill the custom columns with content
	*/
	function custom_columns( $column, $post_id ) {
		global $prefix;
		
		switch ( $column ) {
			case "product":
				$product = get_post_meta( $post_id, $prefix.'product_id', true);
				echo woocommerce_get_formatted_product_name( get_product($product) );
				break;
			case "sold":
				echo (get_post_meta( $post_id, $prefix.'sold', true) != 'on') ? __('Not sold' , 'WCGiftcodes') : '<span style="color: red;">'.__('Sold' , 'WCGiftcodes')."</span>" ;
				break;
			case "sold_to":
				echo "<i>#".get_post_meta( $post_id, $prefix.'sold_to', true)."</i>";
				break;
		}
	}
	add_action( "manage_posts_custom_column", "custom_columns", 10, 2 );
	
	/*
	* Make some columns sortable
	*/
	function sortable_columns() {
	  return array(
		'product' => 'product',
		'title' => 'title',
		'sold' => 'sold',
		'sold_to' => 'sold_to'
	  );
	}
	add_filter( "manage_edit-shop_giftcodes_sortable_columns", "sortable_columns" );

	/*
	* Change the 'Enter new title' text on the post page
	*/
	function title_text_input( $title ){
		global $post;
		if ( $post->post_type == 'shop_giftcodes' ) return __( 'Enter giftcard code', 'wcgiftcodes' );
		return $title;
	}
	add_filter( 'enter_title_here', 'title_text_input' );
	
	
	/*
	* Add custom fields to the post type edit page
	* Define the custom meta box
	*/
	function add_custom_meta_box() {  
		add_meta_box(  
			'wcgiftcodes_meta_box', // $id  
			'Settings giftcode', // $title   
			'show_giftcode_meta_box', // $callback  
			'shop_giftcodes', // $page  
			'normal', // $context  
			'high'); // $priority  
	}  
	add_action('add_meta_boxes', 'add_custom_meta_box');  
	
	/*
	* The Callback that will show all elements inside the custom meta box
	*/ 
	function show_giftcode_meta_box() {  
		global $wcgiftcodes_meta_fields, $post;  	

		// Use nonce for verification  
		echo '<input type="hidden" name="giftcodes_meta_box_nonce" value="'.wp_create_nonce(basename(__FILE__)).'" />';  
		  
		// Begin the field table and loop  
		echo '<table class="form-table">';  
		foreach ($wcgiftcodes_meta_fields as $field) {  
			// get value of this field if it exists for this post  
			$meta = get_post_meta($post->ID, $field['id'], true);  

			// begin a table row with  
			echo '<tr> 
					<th><label for="'.$field['id'].'">'.$field['label'].'</label></th> 
					<td>';  
					
					switch($field['type']) {  
						case 'text':  
							echo '<input type="text" name="'.$field['id'].'" id="'.$field['id'].'" value="'.$meta.'" size="30" /> 
								<br /><span class="description">'.$field['desc'].'</span>';  
						break;    
						case 'textarea':  
							echo '<textarea name="'.$field['id'].'" id="'.$field['id'].'" cols="60" rows="4">'.$meta.'</textarea> 
								<br /><span class="description">'.$field['desc'].'</span>';  
						break;  
						case 'checkbox':  
							echo '<input type="checkbox" name="'.$field['id'].'" id="'.$field['id'].'" ',$meta ? ' checked="checked"' : '','/> 
								<label for="'.$field['id'].'">'.$field['desc'].'</label>';  
						break;  
						case 'select':  
							echo '<select name="'.$field['id'].'" id="'.$field['id'].'">';  
							foreach ($field['options'] as $option) {  
								echo '<option', $meta == $option['value'] ? ' selected="selected"' : '', ' value="'.$option['value'].'">'.$option['label'].'</option>';  
							}  
							echo '</select><br /><span class="description">'.$field['desc'].'</span>';  
						break;  
					} 
					
			echo '</td></tr>';  
		} // end foreach  
		echo '</table>'; // end table  
	}  
	
	/*
	* Remove the quick edit
	*/	
	function remove_quick_edit( $actions ) {
		global $post;
		if( $post->post_type == 'shop_giftcodes' ) {
			unset($actions['inline hide-if-no-js']);
		}
		return $actions;
	}
	add_filter('post_row_actions','remove_quick_edit',10,2);
		
	
	/*
	* Save the custom post data
	*/
	function save_giftcode_meta( $post_id, $post ){
		global $wcgiftcodes_meta_fields;  
		
		// verify nonce  
		if (!isset($_POST['giftcodes_meta_box_nonce']) || !wp_verify_nonce(($_POST['giftcodes_meta_box_nonce']), basename(__FILE__)))   
			return $post_id;  
			
		// Get the post type object
		$post_type = get_post_type_object( $post->post_type ); 
			
		/* Check if the current user has permission to edit the post. */
		if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
			return $post_id;
			
		/* Loop through the fields and save the data */
		foreach ($wcgiftcodes_meta_fields as $field) {  
			$meta_value = get_post_meta($post_id, $field['id'], true);  
			$new_meta_value = $_POST[$field['id']]; 
			$meta_key = $field['id'];
			
			/* If the new meta value does not match the old value, update it. When it doesn't exist: create a new post meta */
			if ( $new_meta_value && $new_meta_value != $meta_value || '' == $meta_value ){
				update_post_meta( $post_id, $meta_key, $new_meta_value );
			}
			
			/* If there is no new meta value but an old value exists, delete it. */
			elseif ( '' == $new_meta_value && $meta_value ){
				delete_post_meta( $post_id, $meta_key, $meta_value );
			}
		} 
		
		// Register action
		do_action("giftcodes_add_giftcode");
	}
	add_action('save_post', 'save_giftcode_meta', 10, 2);  
	
	
	/*
	* Function (copied from the woocommerce code) that searches for products and all variations)
	*/
	function search_products( $term = '', $post_types = array('product') ) {

		$term = (string) stripslashes(strip_tags($term));

		if (empty($term)) return;

		if ( is_numeric( $term ) ) {

			$args = array(
				'post_type'			=> $post_types,
				'post_status'	 	=> 'publish',
				'posts_per_page' 	=> -1,
				'post__in' 			=> array(0, $term),
				'fields'			=> 'ids'
			);

			$args2 = array(
				'post_type'			=> $post_types,
				'post_status'	 	=> 'publish',
				'posts_per_page' 	=> -1,
				'post_parent' 		=> $term,
				'fields'			=> 'ids'
			);

			$args3 = array(
				'post_type'			=> $post_types,
				'post_status' 		=> 'publish',
				'posts_per_page' 	=> -1,
				'meta_query' 		=> array(
					array(
					'key' 	=> '_sku',
					'value' => $term,
					'compare' => 'LIKE'
					)
				),
				'fields'			=> 'ids'
			);

			$posts = array_unique(array_merge( get_posts( $args ), get_posts( $args2 ), get_posts( $args3 ) ));

		} else {

			$args = array(
				'post_type'			=> $post_types,
				'post_status' 		=> 'publish',
				'posts_per_page' 	=> -1,
				's' 				=> $term,
				'fields'			=> 'ids'
			);

			$args2 = array(
				'post_type'			=> $post_types,
				'post_status' 		=> 'publish',
				'posts_per_page' 	=> -1,
				'meta_query' 		=> array(
					array(
					'key' 	=> '_sku',
					'value' => $term,
					'compare' => 'LIKE'
					)
				),
				'fields'			=> 'ids'
			);

			$posts = array_unique(array_merge( get_posts( $args ), get_posts( $args2 ) ));

		}

		$found_products = array();

		if ( $posts ) foreach ( $posts as $post ) {

			$product = get_product( $post );

			$found_products[ $post ] = woocommerce_get_formatted_product_name( $product );

		}

		return $found_products;
	}
?>