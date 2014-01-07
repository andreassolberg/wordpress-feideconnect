<?php


require_once('../../../wp-load.php');


require_once('solberg-oauth/lib/soauth-client.php');
require_once('solberg-oauth/lib/soauth-storage-wordpress.php');

header('Content-type: text/plain; charset: utf-8');

// Get Options
$storedMetadata = json_decode(get_option('uwap-auth-metadata'), true);
$storedMetadata['providerID'] = 'uwap';



$store = new So_StorageWordPress();
$client = new So_Client($store, $storedMetadata);

$return = null;
if (isset($_REQUEST['return'])) {
	$return = $_REQUEST['return'];
}

// $token = $client->getToken('uwap');
// array $requestScope = null, array $requireScope = null, $returnTo = null
$client->authorize(null, null, $return);



