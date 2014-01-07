<?php

class UWAPAuth {
	

	function authenticate($user, $username) {
		if(is_a($user, 'WP_User')) { return $user; }

		// echo '<pre>';
		if (is_user_logged_in() ) {
			global $current_user;
			// echo "current user is "; print_r($current_user); exit;
			return $current_user;
		}


		// Get Options
		$storedMetadata = json_decode(get_option('uwap-auth-metadata'), true);
		$storedMetadata['providerID'] = 'uwap';

		$store = new So_StorageWordPress();
		$client = new So_Client($store, $storedMetadata);

		// $token = $client->getToken('uwap');
		$returnTo = So_Utils::geturl();
		$returnTo = str_replace('reauth=1', 'reauth=0', $returnTo);
		// echo "returnTo " . $returnTo; exit;


		$client->authorize(null, null, $returnTo);


	}

	function publish_post($post_id) {

        $post = get_post($post_id);
        $author = get_userdata($post->post_author);
        $accesstokenraw = get_user_meta($author->ID, 'uwap_accesstoken', true);

		$accesstoken = json_decode($accesstokenraw, true);
		$accesstoken = So_AccessToken::fromObj($accesstoken);

		// echo '<pre>About to publish data ' . "\n";


		$ts = 1000*strtotime((string) $post->post_date);
		$activity = array(
			'title' => $post->post_title,
			'message' => $post->post_content,
			'ts' => $ts,
			'links' => array(
				array(
					'href' => $post->guid,
				),
			),
			'promoted' => false,
			'public' => true,
			'groups' => array(
				"uwap:realm:uninett_no"
			),
		);

		$storedMetadata = json_decode(get_option('uwap-auth-metadata'), true);
		$storedMetadata['providerID'] = 'uwap';

		$store = new So_StorageWordPress();
		$client = new So_Client($store, $storedMetadata);

		$result = $client->getHTTP($accesstoken, 'http://core.app.bridge.uninett.no/api/feed/post', array(
			'method' => 'POST',
			'data' => array('msg' => $activity),
		));

		// print_r($result); exit;

        // print_r($post);
        // print_r($accesstokenraw);
        // print_r($accesstoken); 
        // print_r($activity);
        // echo '</pre>';
        // exit;


	}


	function logout() {
		// global $simplesaml_authentication_opt, $simplesaml_configured, $as;
		// if (!$simplesaml_configured) {
		// 	die("simplesaml-authentication not configured");
		// }
		// $as->logout(get_option('siteurl'));
	}

	// Don't show password fields on user profile page.
	function show_password_fields($show_password_fields) {
		return false;
	}

	function disable_function() {
		die('Disabled');
	}

}