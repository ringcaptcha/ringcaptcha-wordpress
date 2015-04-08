<?php
/**
* 
*/
class RingCaptchaPhones
{
	const sluglang = 'rc_';	
	
	function __construct()
	{
		add_action( 'init', array( &$this, 'post_type'), 0 );
		add_action( 'admin_menu', array( &$this,'menu'));
		add_action( 'admin_head', array( &$this, 'columns_width'));
		add_action( 'init', array( &$this, 'remove_data'));
		add_action( 'manage_posts_custom_column', array( &$this, 'custom_column'), 10, 2);
		add_filter( 'manage_edit-verified-phone_columns', array( &$this, 'column_display'));
		add_filter( 'post_row_actions', array( &$this, 'remove_row_actions'), 10, 1 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ));
		add_action( 'save_post', array( $this, 'save_meta_box_data' ));
	}

	function post_type() {

		$labels = array(
			'name'                => __( 'Verified Phones', self::sluglang ),
			'singular_name'       => __( 'Verified Phone', self::sluglang ),
			'menu_name'           => __( 'Verified Phones', self::sluglang ),
			'parent_item_colon'   => __( 'Parent Item:', self::sluglang ),
			'all_items'           => __( 'All Verified Phones', self::sluglang ),
			'view_item'           => __( 'View Verified Phones', self::sluglang ),
			'add_new_item'        => __( 'Add new', self::sluglang ),
			'add_new'             => __( 'Add new', self::sluglang ),
			'edit_item'           => __( 'Edit', self::sluglang ),
			'update_item'         => __( 'Update', self::sluglang ),
			'search_items'        => __( 'Search', self::sluglang ),
			'not_found'           => __( 'Not found', self::sluglang ),
			'not_found_in_trash'  => __( 'Not found in trash', self::sluglang ),
		);
		$args = array(
			'label'               => __( 'Verified Phones', self::sluglang ),
			'description'         => __( 'Verified Phones', self::sluglang ),
			'labels'              => $labels,
			'supports'            => array('title','author'),
			'menu_icon'        	  => 'dashicons-phone',
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 5,
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'page',
		);
		register_post_type( 'verified-phone', $args );
	}

	function menu()
	{
		global $submenu;
		// replace my_type with the name of your post type
		unset($submenu['edit.php?post_type=verified-phone'][10]);
		if (isset($_GET['post_type']) && $_GET['post_type'] == 'verified-phone') {
		    echo '<style type="text/css">#favorite-actions, .add-new-h2, .tablenav { display:none; }</style>';
		}
		//remove_meta_box( 'submitdiv', 'verified-phone', 'side' );
	}

	function columns_width() {
		if($_GET['post_type'] == 'verified-phone'){			
		    echo '<style type="text/css">';
		    echo '.column-number { text-align: center; width:30px !important; overflow:hidden }';
		    echo '.column-title { text-align: center !important; width:140px !important; overflow:hidden }';
		    echo '</style>';
		}
	}
	function remove_data(){
		remove_post_type_support( 'verified-phone', 'editor' );
	}

	function custom_column( $column, $post_id ) {
		global $post;
		switch ( $column ) {
			case 'modified':
				$m_orig	= get_post_field( 'post_modified', $post_id, 'raw' );
				$m_stamp	= strtotime( $m_orig );
				$modified	= date('n/j/y @ g:i a', $m_stamp );
				$modr_id	= get_post_meta( $post_id, '_edit_last', true );
				$auth_id	= get_post_field( 'post_author', $post_id, 'raw' );
				$user_id	= !empty( $modr_id ) ? $modr_id : $auth_id;
				$user_info	= get_userdata( $user_id );
				echo '<p class="mod-date">';
				echo '<em>'.$modified.'</em><br />';
				echo __( 'By', self::sluglang ).'<strong> <a href="'.get_edit_user_link($user_id).'">'.$user_info->display_name.'</a><strong>';
				echo '</p>';
			break;
			case 'transaction':
				echo get_post_meta($post_id,'transaction',true);
			break;
			case 'geolocation':
				echo get_post_meta($post_id,'geolocation',true);
			break;
			case 'phone_type':
				echo get_post_meta($post_id,'phone_type',true);
			break;
			case 'carrier_name':
				echo get_post_meta($post_id,'carrier_name',true);
			break;
			case 'device_name':
				echo get_post_meta($post_id,'device_name',true);
			break;
			case 'isp_name':
				echo get_post_meta($post_id,'isp_name',true);
			break;
	        case 'number':
	            echo $post->ID;
	        break;
		// end all case breaks
		}
	}
	function column_display( $columns ) {
		$columns = array();
		$columns['number'] = __( '#', self::sluglang );
		$columns['title'] = __( 'Phone', self::sluglang );
		$columns["transaction"] = __( 'Transaction', self::sluglang );
		$columns["geolocation"] = __( 'Geolocation', self::sluglang );
		$columns["phone_type"] = __( 'Phone Type', self::sluglang );
		$columns["carrier_name"] = __( 'Carrier Name', self::sluglang );
		$columns["device_name"] = __( 'Device Name', self::sluglang );
		$columns["isp_name"] = __( 'ISP Name', self::sluglang );
		$columns['modified'] = __( 'Registered', self::sluglang );
		return $columns;
	} 	
	function remove_row_actions( $actions )
	{
	    if( get_post_type() === 'verified-phone' )
	        unset( $actions['view'] );
	        unset( $actions['trash'] );
	        unset( $actions['inline hide-if-no-js'] );
	    return $actions;
	}

	/**
	 * add_meta_box
	 */
	public function add_meta_box(){
	 
		add_meta_box( 'ringcaptcha_metabox',  __( 'Phone Data', self::sluglang ), array( $this, 'display_meta_form' ), 'verified-phone', 'advanced', 'high' );
	}
	 
	/**
	 * display_meta_form	
	 */
	 
	public function display_meta_form( $post ) {
	 
		wp_nonce_field( 'ringcaptcha_metabox', 'ringcaptcha_metabox_nonce' );
	 
		$transaction  = get_post_meta( $post->ID, 'transaction', true );
		$geolocation  = get_post_meta( $post->ID, 'geolocation', true );
		$phone_type  = get_post_meta( $post->ID, 'phone_type', true );
		$carrier_name  = get_post_meta( $post->ID, 'carrier_name', true );
		$device_name  = get_post_meta( $post->ID, 'device_name', true );
		$isp_name  = get_post_meta( $post->ID, 'isp_name', true );

		echo '<div class="wrap">';
		echo '<label for="transaction">' . __( 'Transaction', self::sluglang ) . '</label> <br/>';
		echo '<input class="text" type="text" id="transaction" name="transaction" value="' . esc_attr( $transaction ) . '"   />';
		echo '</div>';

		echo '<div class="wrap">';
		echo '<label for="geolocation">' . __( 'Geolocation', self::sluglang ) . '</label> <br/>';
		echo '<input class="text" type="text" id="geolocation" name="geolocation" value="' . esc_attr( $geolocation ) . '"   />';
		echo '</div>';

		echo '<div class="wrap">';
		echo '<label for="phone_type">' . __( 'Phone Type', self::sluglang ) . '</label> <br/>';
		echo '<input class="text" type="text" id="phone_type" name="phone_type" value="' . esc_attr( $phone_type ) . '"   />';
		echo '</div>';

		echo '<div class="wrap">';
		echo '<label for="carrier_name">' . __( 'Carrier Name', self::sluglang ) . '</label> <br/>';
		echo '<input class="text" type="text" id="carrier_name" name="carrier_name" value="' . esc_attr( $carrier_name ) . '"   />';
		echo '</div>';

		echo '<div class="wrap">';
		echo '<label for="device_name">' . __( 'Device Name', self::sluglang ) . '</label> <br/>';
		echo '<input class="text" type="text" id="device_name" name="device_name" value="' . esc_attr( $device_name ) . '"   />';
		echo '</div>';

		echo '<div class="wrap">';
		echo '<label for="isp_name">' . __( 'ISP Name', self::sluglang ) . '</label> <br/>';
		echo '<input class="text" type="text" id="isp_name" name="isp_name" value="' . esc_attr( $isp_name ) . '"   />';
		echo '</div>';
	}

	/**
	 * save_meta_box_data
	 */
	 
	public function save_meta_box_data( $post_id ){
	 
	    if ( ! isset( $_POST['ringcaptcha_metabox_nonce'] ) ) {
		  return;
	    }
	 
	    if ( ! wp_verify_nonce( $_POST['ringcaptcha_metabox_nonce'], 'ringcaptcha_metabox' ) ) {
		   return;
	    }
	 
	    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		  return;
	    }
	 
	    if ( isset( $_POST['post_type'] ) && $_POST['post_type'] == 'verified-phone' ) {
	            if ( ! current_user_can( 'edit_page', $post_id ) ) {
			     return;
		    }
	    } else {
	            if ( ! current_user_can( 'edit_post', $post_id ) ) {
			     return;
		    }
	    }

		if ( isset( $_POST['transaction'] ) ) {
			update_post_meta( $post_id, 'transaction', sanitize_text_field( $_POST['transaction'] ));
		}
		if ( isset( $_POST['geolocation'] ) ) {
			update_post_meta( $post_id, 'geolocation', sanitize_text_field( $_POST['geolocation'] ));
		}
		if ( isset( $_POST['phone_type'] ) ) {
			update_post_meta( $post_id, 'phone_type', sanitize_text_field( $_POST['phone_type'] ));
		}
		if ( isset( $_POST['carrier_name'] ) ) {
			update_post_meta( $post_id, 'carrier_name', sanitize_text_field( $_POST['carrier_name'] ));
		}
		if ( isset( $_POST['device_name'] ) ) {
			update_post_meta( $post_id, 'device_name', sanitize_text_field( $_POST['device_name'] ));
		}
		if ( isset( $_POST['isp_name'] ) ) {
			update_post_meta( $post_id, 'isp_name', sanitize_text_field( $_POST['isp_name'] ));
		}
	}
	public static function add_phone($id_user,$data){
		//set user
	    $post = array(
	        'post_title' => $data['phone_number'],
	        'post_author' => $id_user,
	        'post_status' => 'publish',
	        'post_type' => 'verified-phone',
	        'post_name' => sanitize_title( date("dmYhis")),
	        'comment_status' => 'closed'
	    );
	    $post_ID = wp_insert_post( $post );

	    update_post_meta($post_ID, "transaction", $data["transaction"]);
	    update_post_meta($post_ID, "geolocation", $data["geolocation"]);
	    update_post_meta($post_ID, "phone_type", $data["phone_type"]);
	    update_post_meta($post_ID, "carrier_name", $data["carrier_name"]);
	    update_post_meta($post_ID, "device_name", $data["device_name"]);
	    update_post_meta($post_ID, "isp_name", $data["isp_name"]);

		return $post_ID;
	}

}
new RingCaptchaPhones;