<?php



/**
 * A Wordpress/Mysql implementation of the Storage API for solberg-oauth.
 */
class So_StorageWordPress extends So_Storage {

	protected $db;
	function __construct() {
		parent::__construct();

		global $wpdb;
		$this->db = $wpdb;

		// $this->db->insert($this->tableName('states'), array('state' => 'sldkfjsdlkfj', 'value' => '{"slkdfjlsdkjflsk": "33"}'));

		// echo '<pre>';
		// $myrows = $this->db->get_results( "SELECT state, value FROM " . $this->tableName('states') );
		// print_r($myrows);
	}
	
	protected function tableName($table) {
		return $this->db->prefix . "uwapstore_" . $table;
	}

	protected function extractOne($collection, $criteria, $fields = array('id', 'value') ) {
		$rows = $this->extractList($collection, $criteria, $fields);
		if (count($rows) < 1) return null;
		return $rows[0];
	}

	protected function extractList($collection, $criteria, $fields = array('id', 'value') ) {
		$whereclause = '';
		if (!empty($criteria)) {
			$where = array();
			foreach($criteria AS $key => $value ) {
				$where[] = $key . " = '" . mysql_real_escape_string($value) . "'";
			}
			$whereclause = ' WHERE ' . join(" AND ", $where);
		}
		$fieldstr = join($fields, ',');
		$sql = "SELECT " . $fieldstr . " FROM " . $this->tableName($collection) . " " . $whereclause;

		$myrows = $this->db->get_results($sql, ARRAY_A);
		$rows = array();

		// echo ("SQL : \n"); print_r($sql); 
		// echo "result\n"; print_r(var_export($myrows ,true));

		// $rows = array();
		// $result = mysql_query($sql, $this->db);
		if(empty($myrows)) return array();
			// throw new Exception ("Could not successfully run query ($sql) fromDB:" . mysql_error());

		foreach($myrows AS $row) {
			if (empty($row["value"])) continue;

			$row['value'] = json_decode($row["value"], true);
			$rows[] = $row;
		}
		return $rows;
	}

	// public function insert($collection, $data, $keys) {
	// 	$edata = json_encode($data);

	// 	$cols = join($keys, ', ');
	// 	$keydata = array();
	// 	foreach($keys AS $key) {
	// 		$keydata[] = $data[$key];
	// 	}
	// 	$sql = "INSERT INTO oauth_" . $collection . " (" . $cols . ", value) VALUES ('" .  join($keydata, "', '") . "', '" . $edata . "')";
	// 	error_log("SQL : " . $sql);
	// 	$result = mysql_query($sql, $this->db);
	// 	if(!$result)
	// 		throw new Exception ("Could not successfully run query ($sql) fromDB:" . mysql_error());
	// }

	// public function update($collection, $data, $keys) {
	// 	$edata = json_encode($data);

	// 	foreach($keys AS $key) {
	// 		$keydata[] = $data[$key];
	// 	}

	// 	$whereclause = '';
	// 	$where = array();
	// 	foreach($keys AS $key) {
	// 		$where[] = $key . " = '" . mysql_real_escape_string($data[$key]) . "'";
	// 	}
	// 	$whereclause = ' WHERE ' . join(" AND ", $where);
	
	// 	$sql = "UPDATE oauth_" . $collection . " SET VALUE = '" . $edata . "' WHERE " . $whereclause;

	// 	error_log("SQL update : " . $sql);
	// 	$result = mysql_query($sql, $this->db);
	// 	if(!$result)
	// 		throw new Exception ("Could not successfully run query ($sql) fromDB:" . mysql_error());
	// }

	// /*
	//  * Return an associated array or throws an exception.
	//  */
	// public function getClient($client_id) {
	// 	$result = $this->extractOne('clients', array('client_id' => $client_id));
	// 	if ($result === null) throw new So_Exception('invalid_client', 'Unknown client identifier');
	// 	return $result;
	// }

	// /*
	//  * Return an associated array or throws an exception.
	//  */
	// public function getProviderConfig($provider_id) {
	// 	$result = $this->extractOne('providers', array('provider_id' => $provider_id));
	// 	if ($result === null) throw new Exception('Unknown provider identifier');
	// 	return $result;
	// }
	
	// public function getAuthorization($client_id, $userid) {
	// 	$result = $this->extractOne('authorization', 
	// 		array(
	// 			'client_id' => $client_id,
	// 			'userid' => $userid
	// 		)
	// 	);
	// 	error_log('Extracting authz ' . var_export($result, true));
	// 	if ($result === null) return null;
	// 	return So_Authorization::fromObj($result);
	// }
	
	// public function setAuthorization(So_Authorization $auth) {
	// 	if ($auth->stored) {
	// 		// UPDATE
	// 		error_log('update obj auth ' . var_export($auth->getObj(), true) );
	// 		$this->update("authorization", $auth->getObj(), array("userid", "client_id"));
	// 	} else {
	// 		// INSERT
	// 		error_log('insert obj auth ' . var_export($auth->getObj(), true) );
	// 		// $this->db->authorization->insert($auth->getObj());
	// 		$this->insert("authorization", $auth->getObj(), array("userid", "client_id"));
	// 	}
	// }
	
	public function putAccessToken($provider_id, $userid, So_AccessToken $accesstoken) {

		return;
		
		// $obj = $accesstoken->getObj();
		// $obj['provider_id'] = $provider_id;
		// $obj['userid'] = $userid;
		// // $this->db->tokens->insert($obj);
		// $this->insert("authorization", $obj, array("userid", "client_id"));

		// $this->db->tokens->insert(array(
		// 	'provider_id' => $provider_id,
		// 	'userid' => $userid,
		// 	'token' => $accesstoken->getObj()
		// ));
	}
	
	/*
	 * Returns null or an array of So_AccessToken objects.
	 */
	public function getTokens($provider_id, $userid) {
		$result = $this->extractList('tokens', array('provider_id' => $provider_id, 'userid' => $userid));
		if ($result === null) return null;
		
		$objs = array();
		foreach($result AS $res) {
			$objs[] = So_AccessToken::fromObj($res);
		}
		return $objs;
	}
	
	/*
	 * Returns null or a specific access token.
	 */
	public function getToken($token) {
		error_log('Storage › getToken(' . $token . ')');
		$result = $this->extractOne('tokens', array('access_token' => $token));
		if ($result === null) throw new Exception('Could not find the specified token.');
		
		return So_AccessToken::fromObj($result);
	}
		
	// public function putCode(So_AuthorizationCode $code) {
	// 	//$this->db->codes->insert($code->getObj());
	// 	$this->insert("codes", $code->getObj(), array("client_id", "code"));
	// }
	// public function getCode($client_id, $code) {
	// 	$result = $this->extractOne('codes', array('client_id' => $client_id, 'code' => $code));
	// 	if ($result === null) throw new So_Exception('invalid_grant', 'Invalid authorization code.');
	// 	//$this->db->codes->remove($result, array("safe" => true));
	// 	return So_AuthorizationCode::fromObj($result);
	// }
	
	public function putState($state, $obj) {
		$obj['state'] = $state;
		$this->db->insert($this->tableName('states'), array('state' => $state, 'value' => json_encode($obj)));
	}
	public function getState($state) {
		$myrows = $this->db->get_results( "SELECT state, value, ts FROM " . $this->tableName('states') . " WHERE state='" . mysql_escape_string($state). "'");

		$result = $this->extractOne('states', array('state' => $state));
		if ($result === null) throw new Exception('Could not find state in state storage. May be it is already extracted.');
		// $this->db->states->remove($result, array("safe" => true));
		return $result;
	}






}
