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
		add_action( 'init', array( &$this, 'remove_data'));
		add_action( 'manage_posts_custom_column', array( &$this, 'custom_column'), 10, 2);
		add_filter( 'manage_edit-verified-phone_columns', array( &$this, 'column_display'));
		add_filter( 'post_row_actions', array( &$this, 'remove_row_actions'), 10, 1 );
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
	public static function add_phone($id_user,$phone){
		//set user
	    $post = array(
	        'post_title' => $phone,
	        'post_author' => $id_user,
	        'post_status' => 'publish',
	        'post_type' => 'verified-phone',
	        'post_name' => sanitize_title( date("dmYhis")),
	        'comment_status' => 'closed'
	    );
	    $post_ID = wp_insert_post( $post );
		return $post_ID;
	}

}
new RingCaptchaPhones;