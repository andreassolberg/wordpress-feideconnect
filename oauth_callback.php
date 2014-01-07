<?php


require_once('../../../wp-load.php');


require_once('solberg-oauth/lib/soauth-client.php');
require_once('solberg-oauth/lib/soauth-storage-wordpress.php');

header('Content-type: text/plain; charset=utf-8');

// Get Options
$storedMetadata = json_decode(get_option('uwap-auth-metadata'), true);
$storedMetadata['providerID'] = 'uwap';

$store = new So_StorageWordPress();
$client = new So_Client($store, $storedMetadata);

if (is_user_logged_in() ) {
	// echo "You are logged in.";
	// exit;
} else {
	// echo "you are not logged in"; exit;
}




class UserInfoHandler {

	protected $rolemap = null;
	protected $metadata;
	public function __construct($metadata) {
		$this->metadata = $metadata;
	}

	protected function isAdmin($userid) {
		if (isset($this->rolemap['admin'])) {
			if ($this->rolemap['admin']['userid'] === $userid) return true;
		}
		return false;		
	}
	protected function resolveRole($userid, $groups) {
		if (isset($this->rolemap['admin'])) {
			if ($this->rolemap['admin']['userid'] === $userid) return $this->rolemap['admin']['role'];	
		}
		foreach($this->rolemap['rules'] AS $rule) {
			foreach($groups AS $groupid => $name) {
				if (in_array($groupid, $rule['groups'])) {
					return $rule['role'];
				}
			}
		}
		return $this->rolemap['default'];
	}

	public function getUserID(So_Client $client, So_AccessToken $accesstoken) {
		$userid = 0;

		// echo 'About to obtain used id from '; print_r($this->metadata);

		$userdataraw = $client->getHTTP($accesstoken, $this->metadata['userinfo']);
		$userdatap = json_decode($userdataraw, true);
		$userdata = $userdatap['data'];
		// [name] => Andreas Åkre Solberg
		// [userid] => andreas@uninett.no
		// [mail] => andreas.solberg@uninett.no
		// [groups] => Array
		
		$userinfo = array();
		$user_info['user_login'] = $userdata['userid'];
		$user_info['user_email'] = $userdata['mail'];
		$user_info['display_name'] = $userdata['name'];	


		// echo "Resolving role to " . $user_info['role'] . "\n";

		// Lookup user by uername
		$user = get_user_by('login', $userdata['userid']);


		/*
			If the user is currently logged in as an adminstrator, and also is the first user
			that anytime logs in using this plugin.
				then; store a new roles ruleset and include the current user as the fixed administrator.
		 */
		$roleruleset = get_option('uwap-roles-ruleset');
		if (!empty($roleruleset)) { 

				// echo "emptupdatingleset"; 
				// print_r(json_decode($roleruleset, true));
				// exit;
			$this->rolemap = json_decode($roleruleset, true);

		} else {

			// echo "empty roleruleset"; 
			// print_r($roleruleset);
			// exit;
			if (true || current_user_can( 'manage_options' ) )  {
				
				$roleruleset = array(
					'rules' => array(),
					'default' => 'subscriber',
					'admin' => array(
						'userid' => $userdata['userid'],
						'role' => 'administrator',
					),
				);

				update_option('uwap-roles-ruleset', json_encode($roleruleset));
				$this->rolemap = $roleruleset;

				// echo "emptupdatingleset"; 
				// print_r($roleruleset);
				// exit;

			} else {
				echo "Cannot continue to map an administrator..."; exit;
				// 
				return;

			}

		}


		$user_info['role'] = $this->resolveRole($userdata['userid'], $userdata['groups']);


		// global $wp_roles;
		// echo "Roles\n"; print_r($wp_roles->role_names);
		// echo "Groups\n"; print_r($userdata['groups']); 
		// exit;

		/*
			(
			    [administrator] => Administrator
			    [editor] => Editor
			    [author] => Author
			    [contributor] => Contributor
			    [subscriber] => Subscriber
			)
		 */



		$userid = null;

		if ($this->isAdmin($userdata['userid'])) {

			$userid = 1;
			$user_info['ID'] = $userid;
			// If changed... then update in database.
			wp_insert_user($user_info);

			// wp_set_current_user($userid);
			wp_set_auth_cookie($userid);

			// echo "User is admin!"; exit;

		// User is already registered within this wordpress installation
		} else if ($user) {

			$userid = $user->ID;
			$user_info['ID'] = $userid;
			// If changed... then update in database.
			wp_insert_user($user_info);

			// wp_set_current_user($userid);
			wp_set_auth_cookie(0);	


			// $roles = 
			// echo "User just created :\n"; print_r($userid); print_r($user_info); exit;
			// echo "User already exists:\n"; print_r($user);


		// User is accessing for the first time.
		} else {

			$user_info['user_pass'] = So_Utils::gen_uuid(); // Gets reset later on.
			$userid =  wp_insert_user($user_info);


			wp_set_auth_cookie($userid);

			// echo "User just created :\n"; print_r($userid); print_r($user_info); exit;
		}
		// get_user_meta($current_user->data['ID'], 'uwap_accesstoken', true);
		update_user_meta($userid, 'uwap_accesstoken', json_encode($accesstoken->getObj()));

		return $userid;

	}


}


$uih = new UserInfoHandler($storedMetadata);


// $token = $client->getToken('uwap');
$client->callback($uih);






// echo 'storedMetadata' . "\n";
// print_r($storedMetadata);
// echo 'options' . "\n";
// print_r($options);
