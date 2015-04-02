<?php
/*
Plugin Name: RingCaptcha
Plugin URI:  http://www.ringcaptcha.com
Description: Integrates RingCaptcha to Wordpress Forms.
Version:     1.0 
Author:      RingCaptcha
Author URI:  mailto: support@ringcaptcha.com
License:     GPL2
AboutDeveloper: mx.linkedin.com/in/rivenvirus
AboutDeveloperEmail: rivenvirus@gmail.com
/*  

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*--------------------------------------------*
* Includes
*--------------------------------------------*/
include_once("ringcaptcha.php");
include_once("inc/utils.php");
include_once("inc/verified_phones.php");

class WPRingCaptcha{
	/*--------------------------------------------*
	* Constants
	*--------------------------------------------*/
	const name = 'RingCaptcha';
	const slug = 'ringcaptcha';
	const sluglang = 'rc_';	

	public static $phoneNumber;
	public static $phoneData;

	/**
	 * Constructor
	 */
	public function __construct() {	

		$settings = get_option(self::slug); 

	  	add_action( 'init', array( &$this, 'init' ));
		add_action( 'plugins_loaded', array( &$this, 'localization' ));
		add_shortcode( 'ringcaptcha', array( &$this, 'ringcaptcha_shortcode' ));

		if($settings["public_key"]!= "" && $settings["private_key"] != ""){

			if($settings['form_login'] == '1'){			
				add_action( 'login_form', array( &$this, 'ringcaptcha_form' ));
				add_filter( 'wp_authenticate_user', array( &$this, 'ringcaptcha_login' ) ,10,2);
			}

			if($settings['form_lostpassword'] == '1'){			
				add_action( 'lostpassword_form', array( &$this, 'ringcaptcha_form' ));
				add_action( 'lostpassword_post', array( &$this, 'ringcaptcha_lost_password' ), 10, 3);
			}

			if($settings['form_register'] == '1'){			
				add_action( 'register_form', array( &$this, 'ringcaptcha_form' ));
				add_action( 'user_register', array( &$this, 'save_ringcaptcha_profile_field' ));
				add_action( 'registration_errors', array( &$this, 'validate_ringcaptcha_profile_field' ), 10, 3);
				add_action( 'signup_extra_fields', array( &$this, 'ringcaptcha_register' ));
				add_filter( 'add_signup_meta', array( &$this, 'add_signup_meta' ));


				//profile functions
				add_action( 'show_user_profile',array( &$this, 'ringcaptcha_profile_field' ));
				add_action( 'edit_user_profile',array( &$this, 'ringcaptcha_profile_field' ));
				
				add_action( 'personal_options_update', array( &$this, 'save_ringcaptcha_profile_field' ));
				add_action( 'edit_user_profile_update', array( &$this, 'save_ringcaptcha_profile_field' ));
			}

			//scripts
			add_action( 'login_footer',  array( &$this, 'javascript' ));
		}

		/*add_action( 'register_post', array( &$this, 'ringcaptcha_form'), 10, 3 );
		add_filter( 'wpmu_validate_user_signup', array( &$this, 'ringcaptcha_validate_form') );*/
	}	

	function init(){
		if(is_admin()){
			// Settings Page
 			add_action( 'admin_menu',array($this,'register_menu' ));
		}else{
			add_action("wp_enqueue_scripts", array($this,"jquery_enqueue"), 11);
		}		
		if (class_exists('Woocommerce')) {
			// WooCommerce
			add_filter( 'woocommerce_general_settings', array(&$this,'woocommerce_settings' ));
			$woocommerce_active = get_option( 'woocommerce_ringcaptcha_active' );
			//print_r($woocommerce_active);
			if($woocommerce_active == 'yes'){				
				add_filter( 'woocommerce_billing_fields' ,array(&$this,'woocommerce_checkout_fields' ));
				add_action( 'woocommerce_after_checkout_billing_form' ,array(&$this,'woocommerce_ringcaptcha' ));
				add_action( 'woocommerce_checkout_update_order_meta' ,array(&$this,'woocommerce_phone_update' ));
				add_action( 'woocommerce_checkout_process' ,array(&$this,'woocommerce_phone_validate' ));
			}			
		}
	}

	function localization() {
		load_plugin_textdomain(self::sluglang, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

   	function register_menu(){
        add_options_page(self::name, self::name, 'manage_options', self::slug, array(&$this,"settings_page"));
    }	

    function javascript(){
    	if ( in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ) ) ){
    		echo '<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>';
    	}
    	echo '<script type="text/javascript" charset="UTF-8" src="//cdn.ringcaptcha.com/widget/v2/bundle.min.js"></script>';
    	self::javascript_config();
    }

	function jquery_enqueue() {
	   wp_deregister_script('jquery');
	   wp_register_script('jquery', "http" . ($_SERVER['SERVER_PORT'] == 443 ? "s" : "") . "://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js", false, null);
	   wp_enqueue_script('jquery');
	}

    function javascript_config(){
    	$settings = get_option(self::slug);
		if($settings["default_country"] != ""){			
	    	echo '<script type="text/javascript">';
	    	echo '	var country = "'.$settings["default_country"].'";$("[data-widget]").on("ready.rc.widget", function () {$(this).find("[data-country-iso=\''.$settings["default_country"].'\']").click();});';
			echo '</script>';
		}
    }

	function settings_page() {
		$slug = self::slug;
		if(isset($_POST) && !empty($_POST)){
			$settings = array(
			  "public_key" => trim($_POST[self::slug.'_public_key']),
			  "private_key" => trim($_POST[self::slug.'_private_key']),
			  "language" => trim($_POST[self::slug.'_language']),
			  "default_country" => trim($_POST[self::slug.'_default_country']),
			  "form_login" => trim($_POST[self::slug.'_form_login']),
			  "form_register" => trim($_POST[self::slug.'_form_register']),
			  "form_lostpassword" => trim($_POST[self::slug.'_form_lostpassword']),
			  "widget" => trim($_POST[self::slug.'_widget']),
			);

			$message = __('Settings saved', self::sluglang);
			update_option(self::slug, $settings);
		}else{
			$settings = get_option(self::slug);
			if (!$settings){
			  $settings = array(
			    "public_key" => "",
			    "private_key" => "",
			    "language" => substr(WPLANG, 0,2),
			    "widget" => "verification"
			  );
			}
		}

		?>
		<div class="wrap">
        	<h1><?php _e("RingCaptcha", self::sluglang); ?></h1>
			<form method="POST" action="options-general.php?page=<?=self::slug?>" id="<?=self::slug?>_form">
			
				<p><?php _e('Sign up at <a href="http://www.ringcaptcha.com" target="_blank">RingCaptcha</a> website, create a widget with your domain and insert the App and Secret Keys below. ', self::sluglang); ?></p>
			
				<table class="form-table">
					<tr valign="top">
					  <th scope="row" style="width: 100px;">
					    <label for="<?=self::slug?>_public_key">App Key</label>
					  </th>
					  <td>
					    <input type="text" id="<?=self::slug?>_public_key" name="<?=self::slug?>_public_key" value="<?php echo $settings["public_key"]; ?>"  style="width: 50%;" />
					  </td>
					</tr>
					<tr>
					  <th scope="row" style="width: 100px;">
					    <label for="<?=self::slug?>_private_key">Secret Key</label>
					  </th>
					  <td>
					    <input type="text" id="<?=self::slug?>_private_key" name="<?=self::slug?>_private_key" value="<?php echo $settings["private_key"]?>"  style="width: 50%;"/>
					  </td>
					</tr>
					<tr>
					  <th scope="row" style="width: 100px;">
					    <label for="<?=self::slug?>_language"><?php _e('Language'); ?></label>
					  </th>
					  <td>
					    <select id="<?=self::slug?>_language" name="<?=self::slug?>_language" style="width: 50%;">
					    	<?php foreach (RingCaptchaUtils::$languages as $key => $value) {?>
					    		<option value="<?php echo $key;?>" <?php selected($settings["language"],$key);?>><?php echo $value;?></option>
					    	<?php }?>
					    </select>
					  </td>
					</tr>
					<tr>
					  <th scope="row" style="width: 100px;">
					    <label for="<?=self::slug?>_default_country"><?php _e('Default Country');?></label>
					  </th>
					  <td>
					    <select id="<?=self::slug?>_default_country" name="<?=self::slug?>_default_country" style="width: 50%;">
					    	<?php foreach (RingCaptchaUtils::$countries as $key => $value) {?>
					    		<option value="<?php echo $key;?>" <?php selected($settings["default_country"],$key);?>><?php echo $value;?></option>
					    	<?php }?>
					    </select>
					    <input type="hidden" id="<?=self::slug?>_widget" name="<?=self::slug?>_widget" value="verification">	
					  </td>
					</tr>
				</table>	
				<h2><?php _e('Onboarding Shortcode')?></h2>
				<p>You can use the shorcode <strong>[ringcaptcha]</strong> for activate a onboarding widget.</p>
				<p>Also can set the default language and default country with the follow parameters: <strong>[ringcaptcha lang="" country=""]</strong></p>
				<table class="form-table">
				    <tr valign="top">
				      <th><?php _e("Values for 'lang'",self::sluglang )?></th>
				      <th><?php _e("Values for 'country'",self::sluglang )?></th>
				    </tr>
				    <tr>
				    	<td>
				    		<div style="height:200px; overflow-y:scroll;background: #FFF;padding: 10px;border: 1px solid #CCC;">
				    		<?php foreach (RingCaptchaUtils::$languages as $key => $value) {?>
				    			<p><strong><?php echo $key;?></strong> = <?php echo $value;?></p>
				    		<?php }?>
				    		</div>
				    	</td>
				    	<td>
				    		<div style="height:200px; overflow-y:scroll;background: #FFF;padding: 10px;border: 1px solid #CCC;">
				    	    <?php foreach (RingCaptchaUtils::$countries as $key => $value) {?>
				    			<p><strong><?php echo $key;?></strong> = <?php echo $value;?></p>
				    		<?php }?>
				    		</div>
				    	</td>
				    </tr>
				</table>
				<p><strong><?php _e("Example",self::sluglang);?></strong>: [ringcaptcha lang="es" country="ES"]</p>
				<h2><?php _e('Wordpress Forms')?></h2>
				<p>Activate RingCaptcha to verify the user</p>
				<table class="form-table">
				    <tr valign="top">
				      <th scope="row" style="width: 100px;">
				        <label for="<?=self::slug?>_form_login"><?php _e('Login Form',self::sluglang )?></label>
				      </th>	
				      <td>
				        <input type="checkbox" id="<?=self::slug?>_form_login" name="<?=self::slug?>_form_login" value="1" <?=($settings["form_login"] == '1')?'checked':''?> />
				      </td>			      
				    </tr>
				    <tr>
				      <th scope="row" style="width: 100px;">
				        <label for="<?=self::slug?>_form_register"><?php _e('Register Form',self::sluglang )?></label>
				      </th>
				      <td>
				        <input type="checkbox" id="<?=self::slug?>_form_register" name="<?=self::slug?>_form_register" value="1" <?=($settings["form_register"] == '1')?'checked':''?> />
				      </td>
				    </tr>
				    <tr>
				      <th scope="row" style="width: 100px;">
				        <label for="<?=self::slug?>_form_lostpassword"><?php _e('Lost Password Form',self::sluglang );?>	</label>
				      </th>
				      <td>
				        <input type="checkbox" id="<?=self::slug?>_form_lostpassword" name="<?=self::slug?>_form_lostpassword" value="1" <?=($settings["form_lostpassword"] == '1')?'checked':''?> />
				      </td>
				    </tr>
			  </table>
				<p class="submit">
				  <input type="Submit" value="<?php _e("Save Settings", self::sluglang); ?>" class="button-primary settings_savebutton"/>
				</p>
			</form>
		</div>
		<?php
    }
    
	function ringcaptcha_form($pageid = '') {		                  
  		$settings = get_option(self::slug);   
    	echo "<style type='text/css'>#ringcaptcha_widget{display: inline-block;}body.login #login{width: 451px !important;}</style>";    	
		echo '<div data-widget data-app="'.$settings["public_key"].'" data-locale="'.$settings["language"].'" data-mode="'.$settings["widget"].'"></div>';		
    }

	/**
	 * Form Functions
	 */	

	function ringcaptcha_login($user, $password){
		$result = self::ringcaptcha_validate();
		if($result === TRUE){

			RingCaptchaPhones::add_phone($user->ID,self::$phoneData );
		
		  	return $user;
		}else{
			return new WP_Error( 'ring_fail', __( "Phone Verification is required to proceed.", self::sluglang ) );
		}  
	}

	function validate_ringcaptcha_profile_field($errors, $sanitized_user_login, $user_email){
		$result = self::ringcaptcha_validate();
		if ($result===FALSE){		
		        $errors->add( 'ring_fail', __( "<strong>ERROR</strong>: Phone Verification is required to proceed.", self::sluglang ));
		}

		return $errors;
	}

	function ringcaptcha_lost_password(){

		if(isset( $_REQUEST['user_login'] ) && empty($_REQUEST['user_login'])) {
			return;
		}

		$result = self::ringcaptcha_validate();
		if ($result===FALSE){		
		    wp_die(__( "<strong>ERROR</strong>: Phone Verification is required to proceed.", self::sluglang ));    
		}else{
			$user = get_user_by( 'login', $_REQUEST['user_login']);
			RingCaptchaPhones::add_phone($user->ID,self::$phoneData );
		}		
	}

	function save_ringcaptcha_profile_field($user_id ){
		/*if ( !current_user_can( 'edit_user', $user_id ) )
			return false;*/

		if(isset($_POST['ringcaptcha'])){
			self::$phoneNumber = $_POST['ringcaptcha'];
		}

		RingCaptchaPhones::add_phone($user_id,self::$phoneData );

		update_user_meta( $user_id, 'ringcaptcha', self::$phoneNumber );		
	}
	
	function ringcaptcha_validate(){
		$settings = get_option(self::slug);	  
	    
	    //only check if the rest of the form is valid	    
      	$ringcaptcha = new Ringcaptcha($settings["public_key"], $settings["private_key"]);      	
	    $pinCode = $_POST["ringcaptcha_pin_code"];
	    $token   = $_POST["ringcaptcha_session_id"];
	    if ($ringcaptcha->isValid($pinCode, $token) && !empty($pinCode) && !empty($token)) {
	        self::$phoneNumber   = $ringcaptcha->getPhoneNumber();
	        self::$phoneData = array(        	
		        'phone_number' => $ringcaptcha->getPhoneNumber(),
		        'transaction' => $ringcaptcha->getId(),
		        'geolocation' => $ringcaptcha->isGeolocated(),
				'phone_type' => $ringcaptcha->getPhoneType(),
		        'carrier_name' => $ringcaptcha->getCarrierName(),
		        'device_name' => $ringcaptcha->getDeviceName(),
		        'isp_name' => $ringcaptcha->getIspName()
	        );
	        $result = TRUE;		        	        
	    } else {											
	        $result = FALSE;	        
	    }
    	  
	    return $result;
	}

	/**
	*	Register Form Ringcaptcha
	**/
	function add_signup_meta($meta = array()) {
		$meta['ringcaptcha'] = self::$phoneNumber;
		return $meta;
	}

	function ringcaptcha_profile_field($user){
	?>
		<h3>Extra profile information</h3>
		<table class="form-table">
			<tr>
				<th><label for="ringcaptcha">Verified Telephone Number</label></th>
				<td>
					<input type="text" name="ringcaptcha" id="ringcaptcha" value="<?php echo esc_attr( get_user_meta($user->ID, 'ringcaptcha',true)); ?>" class="regular-text" /><br />
					<span class="description">Please enter your telephone number.</span>

				</td>

			</tr>

		</table>
	<?php
	}

	/**
	* Shortcodes
	**/
	function ringcaptcha_shortcode( $atts ) {
		$settings = get_option(self::slug);   
		$shortcode = "";
		
	    $atts = shortcode_atts( array(
	        'lang' => $settings["language"],
	        'key' => $settings["public_key"],
	        'country' => $settings["default_country"],
	    ), $atts );

		$shortcode .= "<style type='text/css'>#ringcaptcha_widget{display: inline-block;}body.login #login{width: 451px !important;}</style>";
		$shortcode .= '<div data-widget data-app="'.$atts["key"].'" data-locale="'.$atts["lang"].'" data-mode="onboarding"></div>';
		$shortcode .= '<script type="text/javascript" charset="UTF-8" src="//cdn.ringcaptcha.com/widget/v2/bundle.min.js"></script>';
		$shortcode .= '<script type="text/javascript">';
		$shortcode .= '	var country = "'.$atts["country"].'";$("[data-widget]").on("ready.rc.widget", function () {$(this).find("[data-country-iso=\''.$atts["country"].'\']").click();});';
		$shortcode .= '</script>';

	    return $shortcode;
	}

	/**
	* WooCommerce Integration
	**/

	// Our hooked in function - $fields is passed via the filter!
	function woocommerce_checkout_fields( $fields ) {

		 //print_r($fields);
		 //die();
	     /*$fields['billing_phone']['placeholder'] = 'My new placeholder';
	     $fields['billing_phone']['required'] = false;*/
	     $fields['billing_postcode']['required'] = false;
	     unset($fields['billing_phone']);
	     return $fields;
	}

	function woocommerce_phone_update($order_id){
		global $current_user;
		if(isset($_POST['ringcaptcha'])){
			self::$phoneNumber = $_POST['ringcaptcha'];
		}

		if($current_user->ID!=''){
			RingCaptchaPhones::add_phone($current_user->ID,self::$phoneData );
		}else{
			RingCaptchaPhones::add_phone(1,self::$phoneData );
		}
		update_post_meta( $order_id, '_billing_phone', sanitize_text_field( self::$phoneNumber ) );
	}
	//add_action( 'woocommerce_admin_order_data_after_billing_address', 'my_custom_checkout_field_display_admin_order_meta', 10, 1 );
 
	function woocommerce_phone_validate($order){
		$result = self::ringcaptcha_validate();
	    if ( $result===FALSE )
        	wc_add_notice( __( "Phone Verification is required to proceed.", self::sluglang ), 'error' );
	}

	function woocommerce_ringcaptcha(){
		echo '<div id="ringcaptcha_field" style="min-width: 320px;display: block;clear: both;">
		<label for="billing_phone">' . __('Phone') . '</label>';
		self::ringcaptcha_form();
		self::javascript();
		echo '</div>';
	}

	function woocommerce_settings($settings){
		$updated_settings = array();
			foreach ( $settings as $section ) {
			// at the bottom of the General Options section
			if ( isset( $section['id'] ) && 'general_options' == $section['id'] &&
			   isset( $section['type'] ) && 'sectionend' == $section['type'] ) {
			  $updated_settings[] = array(
			    'name'     => __( 'Activate RingCaptcha',  self::sluglang),
			    'desc_tip' => __( 'Activate RingCaptcha for the billing phone.', self::sluglang ),
			    'id'       => 'woocommerce_ringcaptcha_active',
			    'type'     => 'checkbox',
			    //'css'      => 'min-width:300px;',
			    'std'      => '1',  // WC < 2.0
			    'default'  => '1',  // WC >= 2.0
			    'desc'     => __( 'Active RingCaptcha for the billing phone.', self::sluglang ),
			  );
			}
			$updated_settings[] = $section;
			}

			return $updated_settings;
	}

	/**
	 * Utility Functions
	 */
    function get_base_url(){
        return plugins_url( null, __FILE__ );
    }

    //Returns the physical path of the plugin's root folder
    function get_base_path(){
        $folder = basename( dirname( __FILE__ ) );
        return WP_PLUGIN_DIR . '/' . $folder;
    }

}
new WPRingCaptcha;