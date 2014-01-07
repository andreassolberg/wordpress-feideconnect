<?php

require_once('../../../wp-admin/admin.php');

if ( !current_user_can( 'manage_options' ) )  {
	wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}



$result = array('status' => "na");


$inputraw = file_get_contents("php://input");
if ($inputraw) {
	$object = json_decode($inputraw, true);
}


/*
	Posting metadata to store in configuration.
 */
if (!empty($object['action']) && $object['action'] === 'reset') {


	update_option('uwap-auth-metadata', '');
	update_option('uwap-roles-ruleset', '');
	$result['status'] = 'ok';





} else if (!empty($object['action']) && $object['action'] === 'update-rolemap') {


	
	$newRolemap = $object['rolemap'];
	update_option('uwap-roles-ruleset', json_encode($newRolemap));
	$result['status'] = 'ok';

} else if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'getroles') {



	global $wp_roles;
	global $wp_current_user;
	$result['roles'] = $wp_roles->role_names;

	$accesstokenraw = get_user_meta($current_user->data->ID, 'uwap_accesstoken', true);
	if (empty($accesstokenraw)) {
		$result['uwaptoken'] = false;
	} else {
		$result['uwaptoken'] = true;
		$accesstoken = json_decode($accesstokenraw, true);
		$accesstoken = So_AccessToken::fromObj($accesstoken);
		// echo "accesstoken\n"; print_r($accesstokenraw);

		$storedMetadata = json_decode(get_option('uwap-auth-metadata'), true);
		$storedMetadata['providerID'] = 'uwap';

		$store = new So_StorageWordPress();
		$client = new So_Client($store, $storedMetadata);

		$userdataraw = $client->getHTTP($accesstoken, $storedMetadata['userinfo']);
		$userdatap = json_decode($userdataraw, true);
		$userdata = $userdatap['data'];

		$result['uwaptoken'] = true;
		// echo "acestoken"; print_r($accesstoken); 
		$result['groups'] = $userdata['groups'];
	}

	$roleruleset = get_option('uwap-roles-ruleset');
	if (!empty($roleruleset)) {
		$result['roleruleset'] = json_decode($roleruleset, true);
	}
	

	$result['user'] = $current_user->data;
	$result['status'] = 'ok';


} else if (!empty($object['metadata'])) {

	
	update_option('uwap-auth-metadata', json_encode($object['metadata']));


	$result['status'] = 'ok';

	// print("Storing metadata"); 
	// print_r($object['metadata']); 
	// exit;
}


header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);