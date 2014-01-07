<?php
/*
Plugin Name: UWAP
Version: 0.1.0
Plugin URI: http://uwap.org
Description: Integrate Wordpress with UNINETT WebApp Park for authentication, groups and acitivty posting.
Author: Andreas Åkre Solberg
Author URI: http://blog.uwap.org

*/

/* Copyright (C) 2013 Andreas Åkre Solberg, UNINETT AS.

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

 */

require_once('solberg-oauth/lib/soauth-client.php');
require_once('solberg-oauth/lib/soauth-storage-wordpress.php');

require_once('lib/UWAPAuth.php');
require_once('lib/UWAPOAuth.php');
require_once('lib/UWAPOAuthStorage.php');



$uwap_version = '0.1.0';


/** Step 2 (from text above). */
add_action( 'admin_menu', 'uwap_plugin_menu' );




/*
	AUTHENTICATION
	Plugin hooks into authentication system
*/	

add_filter('authenticate', array('UWAPAuth', 'authenticate'), 10, 2);
add_action('wp_logout', array('UWAPAuth', 'logout'));
add_filter('show_password_fields', array('UWAPAuth', 'show_password_fields'));

add_action('lost_password', array('UWAPAuth', 'disable_function'));
add_action('retrieve_password', array('UWAPAuth', 'disable_function'));
add_action('password_reset',array('UWAPAuth', 'disable_function'));


add_action('publish_post', array('UWAPAuth', 'publish_post'));



/*
	Adding a menu item to the setup section of the admin dashboard
 */

function uwap_plugin_menu() {
	add_options_page( 'UWAP Setup', 'UWAP Setup', 'manage_options', 'uwap-setup', 'uwap_plugin_options' );
}

/*
	Implementing the setup menu item.
 */
function uwap_plugin_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}








	/**
	 * Configuring the connection to an UWAP instance.
	 */
	$uwap = array(
		'scheme' => 'http',
		'host' => 'app.bridge.uninett.no',
		// 'origin' => 'http://bridge.uninett.no',
		// 
	);


	$uwap['oauth'] = array(
		'authorization' => $uwap['scheme'] . '://core.' . $uwap['host'] . '/api/oauth/authorization',
		'token' => $uwap['scheme'] . '://core.' . $uwap['host'] . '/api/oauth/token',
		'userinfo' => $uwap['scheme'] . '://core.' . $uwap['host'] . '/api/userinfo',
	);

	// http://core.uwap.org/api/oauth/authorization


	$uwap['dev'] = $uwap['scheme'] . '://dev.' . $uwap['host'];
	$uwap['autoconnect'] = $uwap['dev'] . '/autoconnect.html';


	$uwap['url_api'] = plugins_url('api.php', __FILE__);
	$uwap['url_login'] = plugins_url('login.php', __FILE__) . '?return=' . urlencode(So_Utils::getURL());
	$uwap['url_return'] = plugins_url('oauth_callback.php', __FILE__);




	$url = $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__);
	$page = basename(__FILE__);

	if ($_REQUEST['metadata']) {
		$meta = json_decode($_REQUEST['metadata'], true);
		update_option('uwap-auth-metadata', json_encode($meta));
		print("Storing metadata"); print_r($meta); exit;
	
	}

		
	// Get Options
	$storedMetadata = get_option('uwap-auth-metadata');
	
	// echo "metadata"; print_r($storedMetadata); exit;

	if (!empty($storedMetadata)) {
		$storedMetadata = json_decode($storedMetadata, true);
	} else {
		$storedMetadata = null;
	}
		
	if (empty($storedMetadata)) {
		// Get Options
		$options = get_option('uwap_options');

		$metadata = array(
			'name' => get_bloginfo('name'),
			'descr' => get_bloginfo('description'),
			'url' => get_bloginfo('url'),
			'id' => sha1(get_bloginfo('url')),
			'return' => plugins_url('oauth_callback.php', __FILE__),
		);

		require_once('templates/setup.php');
		return;

	}




	// $storedMetadata['providerID'] = 'uwap';

	// $store = new So_StorageWordPress();
	// $client = new So_Client($store, $storedMetadata);

	// $client->authorize();


	require_once('templates/current-setup.php');
	return;



}




/*
	SETUP UWAP OAUTH STORAGE DATABASE
 */

register_activation_hook( __FILE__, 'uwap_store_setup' );

function uwap_store_setup() {
	global $wpdb;
	$table_tokens = $wpdb->prefix . "uwapstore_tokens"; 
	$sql = "CREATE TABLE $table_tokens (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		userid tinytext NOT NULL,
		provider_id tinytext NOT NULL,
		value text NOT NULL,
		UNIQUE KEY id (id)
	);";
	$table_states = $wpdb->prefix . "uwapstore_states"; 
	$sql2 = "CREATE TABLE $table_states (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		state tinytext NOT NULL,
		value text NOT NULL,
		UNIQUE KEY id (id)
	);";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	dbDelta( $sql2 );
}









