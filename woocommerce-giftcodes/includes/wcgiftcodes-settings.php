<?php
	class WC_Giftcodes_Settings {
		
		public function __construct() {			
			// Load includes
			$this->includes();

			add_action( 'admin_menu', array( &$this, 'wcgiftcodes_add_page' ) );
		}
		
		public function includes(){
			include_once('post-type/wc-giftcodes.php');
		}
		
		/**
		 * Add menu page
		 */
		public function wcgiftcodes_add_page() {
			add_submenu_page( 
				'edit.php', 
				__( 'Giftcodes', 'WCGiftcodes' ), 
				__( 'Giftcodes', 'WCGiftcodes' ), 
				'manage_product_terms', 
				'wcgiftcodes_options_page', 
				array( $this, 'wcgiftcodes_options_do_page' )
			);
		}
	}
?>