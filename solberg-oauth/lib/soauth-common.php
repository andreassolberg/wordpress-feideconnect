<?php

/*
 * This file is part of Solberg-OAuth
 * Read more here: https://github.com/andreassolberg/solberg-oauth
 */

assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_WARNING, 1);
assert_options(ASSERT_QUIET_EVAL, 0);


function http_parse_headers( $header, $hdrs ) {
	$key = null;
	$value = null;

	$fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
	foreach( $fields as $field ) {
	    if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
	        $key = strtolower(preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1]))));
	        $value = trim($match[2]);

	        if (isset($key)) {
	        	if (!isset($hdrs[$key])) {
	        		$hdrs[$key] = array();
	        	}
	        	$hdrs[$key][] = $value;
	        }

	    }
	}

}


class So_ExpiredToken extends Exception {}
class So_AuthorizationRequired extends Exception {
	public $scopes;
	public $client_id;
}
class So_InsufficientScope extends Exception {}

/*
 * Log to MongoDB 
 */
class So_log {
	protected static $db;
	
	// Logged error messages beyond this level, will not 
	// be logged
	protected static $logLevel = 4;
	protected static $stacktrace = true;
	
	private static function init($logLevel = null, $stacktrace = null) {
		if ($logLevel !== null) {
			self::$logLevel = $logLevel;
		}
		if ($stacktrace !== null) {
			self::$stacktrace = $stacktrace;
		}
		if (empty(self::$db)) {
			// $m = new Mongo();
			// self::$db = $m->oauth;
		}
	}

	public static function debug($message, $obj = null) { 
		error_log('debug ' . $message . ' ' . json_encode($obj));
	}
	public static function info($message, $obj = null) {
		error_log('debug ' . $message . ' ' . json_encode($obj));
	}
	public static function warn($message, $obj = null) {
		error_log('debug ' . $message . ' ' . json_encode($obj));
	}
	public static function error($message, $obj = null) {
		error_log('debug ' . $message . ' ' . json_encode($obj));
	}
}

/**
 * Persistent Storage. Pluggable.
 */
abstract class So_Storage {
	function __construct() {
	}
	// public abstract function getClient($client_id);
}

/**
 * A MongoDB implementation of the Storage API is 
 */
class So_StorageMongo extends So_Storage {
	protected $db;
	function __construct() {
		parent::__construct();
		$m = new Mongo();
		$this->db = $m->oauth;
	}
	private function extractOne($collection, $criteria) {
		$cursor = $this->db->{$collection}->find($criteria);
		if ($cursor->count() < 1) return null;
		return $cursor->getNext();
	}

	private function extractList($collection, $criteria) {
		$cursor = $this->db->{$collection}->find($criteria);
		if ($cursor->count() < 1) return null;
		
		$result = array();
		foreach($cursor AS $element) $result[] = $element;
		return $result;
	}

	/*
	 * Return an associated array or throws an exception.
	 */
	public function getClient($client_id) {
		$result = $this->extractOne('clients', array('client_id' => $client_id));
		if ($result === null) throw new So_Exception('invalid_client', 'Unknown client identifier');
		return $result;
	}

	/*
	 * Return an associated array or throws an exception.
	 */
	public function getProviderConfig($provider_id) {
		$result = $this->extractOne('providers', array('provider_id' => $provider_id));
		if ($result === null) throw new Exception('Unknown provider identifier');
		return $result;
	}
	
	public function getAuthorization($client_id, $userid) {
		$result = $this->extractOne('authorization', 
			array(
				'client_id' => $client_id,
				'userid' => $userid
			)
		);
		error_log('Extracting authz ' . var_export($result, true));
		if ($result === null) return null;
		return So_Authorization::fromObj($result);
	}
	
	public function setAuthorization(So_Authorization $auth) {
		if ($auth->stored) {
			// UPDATE
			error_log('update obj auth ' . var_export($auth->getObj(), true) );
			$this->db->authorization->update(
				array('userid' => $auth->userid, 'client_id' => $auth->client_id),
				$auth->getObj()
			);
		} else {
			// INSERT
			error_log('insert obj auth ' . var_export($auth->getObj(), true) );
			$this->db->authorization->insert($auth->getObj());
		}
	}


	
	public function putAccessToken($id, $userid, So_AccessToken $accesstoken) {
		$obj = $accesstoken->getObj();
		$obj['id'] = $id;
		$obj['userid'] = $userid;
		$this->db->tokens->insert($obj);

		// $this->db->tokens->insert(array(
		// 	'provider_id' => $provider_id,
		// 	'userid' => $userid,
		// 	'token' => $accesstoken->getObj()
		// ));
	}
	
	/*
	 * Returns null or an array of So_AccessToken objects.
	 */
	public function getTokens($id, $userid) {
		$result = $this->extractList('tokens', array('id' => $id, 'userid' => $userid));
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
		
	public function putCode(So_AuthorizationCode $code) {
		$this->db->codes->insert($code->getObj());
	}
	public function getCode($client_id, $code) {
		$result = $this->extractOne('codes', array('client_id' => $client_id, 'code' => $code));
		if ($result === null) throw new So_Exception('invalid_grant', 'Invalid authorization code.');
		$this->db->codes->remove($result, array("safe" => true));
		return So_AuthorizationCode::fromObj($result);
	}
	
	public function putState($state, $obj) {
		$obj['state'] = $state;
		$this->db->states->insert($obj);
	}
	public function getState($state) {
		$result = $this->extractOne('states', array('state' => $state));
		if ($result === null) throw new So_Exception('invalid_grant', 'Invalid authorization code.');
		$this->db->states->remove($result, array("safe" => true));
		return $result;
	}

}


class So_RedirectException extends Exception {
	protected $url;
	function __construct($url) {
		$this->url = $url;
	}
	function getURL() {
		return $this->url;
	}
}


class So_AuthorizationCode {
	public $issued, $validuntil, $tokenexpiresin, $code, $userid, $userdata, $client_id, $scope;
	function __construct() {
	}
	
	function getObj() {
		$obj = array();
		foreach($this AS $key => $value) {
			if (in_array($key, array())) continue;
			if ($value === null) continue;
			$obj[$key] = $value;
		}
		return $obj;
	}
	
	static function fromObj($obj) {
		$n = new So_AuthorizationCode();
		if (isset($obj['issued'])) $n->issued = $obj['issued'];
		if (isset($obj['validuntil'])) $n->validuntil = $obj['validuntil'];
		if (isset($obj['tokenexpiresin'])) $n->tokenexpiresin = $obj['tokenexpiresin'];
		if (isset($obj['code'])) $n->code = $obj['code'];
		if (isset($obj['userid'])) $n->userid = $obj['userid'];
		if (isset($obj['userdata'])) $n->userdata = $obj['userdata'];
		if (isset($obj['client_id'])) $n->client_id = $obj['client_id'];
		if (isset($obj['scope'])) $n->scope = $obj['scope'];
		return $n;
	}
	
	static function generate($client_id, $userid, $userdata, $scope, $expires_in = 3600) {
		$n = new So_AuthorizationCode();
		$n->client_id = $client_id;
		$n->userid = $userid;
		$n->userdata = $userdata;
		$n->scope = $scope;

		$n->tokenexpiresin = $expires_in;
		$n->issued = time();
		$n->validuntil = time() + 3600;
		$n->code = So_Utils::gen_uuid();

		return $n;
	}
}

class So_AccessToken {
	public $issued, $validuntil, $client_id, $userid, $access_token, $token_type, $refresh_token, $scope, $userdata, $clientdata;
	
	function __construct() {
		$this->issued = time();
	}
	static function generate($client_id, $userid, $userdata, $scope = null, $refreshtoken = true, $expires_in = 3600) {
		$n = new So_AccessToken();

		$n->client_id = $client_id;
		$n->userid = $userid;
		$n->userdata = $userdata;

		$n->validuntil = $n->issued + $expires_in;
		$n->access_token = So_Utils::gen_uuid();

		$n->clientdata = array(
			'client_id' => $client_id
		);

		if ($refreshtoken) {
			$n->refresh_token = So_Utils::gen_uuid();			
		}

		$n->token_type = 'bearer';
		
		if ($scope) {
			$n->scope = $scope;
		}
		return $n;
	}
	function getScope() {
		return join(' ', $this->scope);
	}
	
	function getAuthorizationHeader() {
		return 'Authorization: Bearer ' . $this->access_token . "\r\n";
	}
	
	function gotScopes($gotscopes) {
		if ($gotscopes === null) return true;
		if (empty($gotscopes)) return true;
		if ($this->scope === null) return false;
		
		assert('is_array($gotscopes)');
		assert('is_array($this->scope)');
		
		foreach($gotscopes AS $gotscope) {
			if (!in_array($gotscope, $this->scope)) return false;
		}
		return true;
	}
	
	// Is the token valid 
	function isValid() {
		if ($this->validuntil === null) return true;
		if ($this->validuntil > (time() + 2)) return true; // If a token is valid in less than two seconds, treat it as expired.
		return false;
	}
	
	function requireValid($scope) {
		if (!$this->isValid()) throw new So_ExpiredToken('Token expired');
		if (!$this->gotScopes($scope)) throw new Exception('Token did not include the required scopes.');
	}
	
	function getObj() {
		$obj = array();
		foreach($this AS $key => $value) {
			if (in_array($key, array())) continue;
			if ($value === null) continue;
			$obj[$key] = $value;
		}
		return $obj;
	}
	
	static function fromObj($obj) {
		$n = new So_AccessToken();
		if (isset($obj['issued'])) $n->issued = $obj['issued'];


		if (isset($obj['expires_in'])) $n->validuntil = $n->issued + $obj['expires_in'];
		if (isset($obj['validuntil'])) $n->validuntil = $obj['validuntil'];


		if (isset($obj['client_id'])) $n->client_id = $obj['client_id'];
		if (isset($obj['userid'])) $n->userid = $obj['userid'];
		if (isset($obj['userdata'])) $n->userdata = $obj['userdata'];
		if (isset($obj['clientdata'])) $n->clientdata = $obj['clientdata'];
		if (isset($obj['access_token'])) $n->access_token = $obj['access_token'];
		if (isset($obj['token_type'])) $n->token_type = $obj['token_type'];
		if (isset($obj['refresh_token'])) $n->refresh_token = $obj['refresh_token'];
		if (isset($obj['scope'])) $n->scope = $obj['scope'];


		return $n;
	}
	function getValue() {
		return $this->access_token;
	}
	function getToken() {
		$result = array();
		$result['access_token'] = $this->access_token;
		$result['token_type'] = $this->token_type;
		if (!empty($this->validuntil)) {
			$result['expires_in'] = $this->validuntil - time();
		}
		if (!empty($this->refresh_token)) {
			$result['refresh_token'] = $this->refresh_token;
		}
		if (!empty($this->scope)) {
			$result['scope'] = $this->getScope();
		}
		return $result;
	}
}


class So_UnauthorizedRequest extends So_Exception {

}


class So_InvalidResponse extends So_Exception {
	public $raw;
}

class So_Exception extends Exception {
	protected $code, $state;
	function __construct($code, $message, $state = null) {
		parent::__construct($message);
		$this->code = $code;
		$this->state = $state;
	}
	function getResponse() {
		$message = array('error' => $this->code, 'error_description' => $this->getMessage() );
		if (!empty($this->state)) $message['state'] = $this->state;
		$m = new So_ErrorResponse();
	}
}




// ---------- // ---------- // ---------- // ----------  MESSAGES




class So_Message {
	function __construct($message) {	
	}
	function asQS() {
		$qs = array();
		foreach($this AS $key => $value) {
			if (empty($value)) continue;
			$qs[] = urlencode($key) . '=' . urlencode($value);
		}
		return join('&', $qs);
	}

	public function getRedirectURL($endpoint, $hash = false) {
		if ($hash) {
			$redirurl = $endpoint . '#' . $this->asQS();
		} else {
			if (strstr($endpoint, "?")) {
				$redirurl = $endpoint . '&' . $this->asQS();
			} else {
				$redirurl = $endpoint . '?' . $this->asQS();
			}
			
		}
		return $redirurl;
	}
	
	public function sendRedirect($endpoint, $hash = false) {
		$redirurl = $this->getRedirectURL($endpoint, $hash);		
		header('Location: ' . $redirurl);
		exit;
	}
	public function sendBody() {
		header('Content-Type: application/json; charset=utf-8');

		$body = array();
		foreach($this AS $key => $value) {
			if (empty($value)) continue;
			$body[$key] = $value;
		}

		echo json_encode($body);
		exit;
	}
	

	
	public function post($endpoint) {
		error_log('posting to endpoint: ' . $endpoint);
		$postdata = $this->asQS();
		
		error_log('Sending body: ' . $postdata);
		
		$opts = array('http' =>
		    array(
		        'method'  => 'POST',
		        'header'  => 'Content-type: application/x-www-form-urlencoded' . "\r\n",
		        'content' => $postdata
		    )
		);
		$context  = stream_context_create($opts);

		$result = file_get_contents($endpoint, false, $context);
		
		$resultobj = json_decode($result, true);
		

		return $resultobj;
	}
}

class So_Request extends So_Message {
	function __construct($message) {
		parent::__construct($message);
	}
}

abstract class So_AuthenticatedRequest extends So_Request {
	public $client_id;
	protected $client_secret;
	function __construct($message) {
		parent::__construct($message);
		$this->client_id		= So_Utils::optional($message, 'client_id');
		$this->client_secret		= So_Utils::optional($message, 'client_secret');
	}
	function setClientCredentials($u, $p) {
		error_log('setClientCredentials ('  . $u. ',' . $p. ')');
		$this->client_id = $u;
		$this->client_secret = $p;
	}
	function getAuthorizationHeader() {
		if (empty($this->client_id) || empty($this->client_secret)) throw new Exception('Cannot authenticate without username and passwd');
		return 'Authorization: Basic ' . base64_encode($this->client_id . ':' . $this->client_secret);
	}
	function checkCredentials($u, $p) {
		if ($u !== $this->client_id) throw new So_Exception('invalid_grant', 'Invalid client credentials');
	}
	function parseServer($server) {
		if (isset($_SERVER['PHP_AUTH_USER'])) {
			$this->client_id = $_SERVER['PHP_AUTH_USER'];
		}
		if (isset($_SERVER['PHP_AUTH_PW'])) {
			$this->client_secret = $_SERVER['PHP_AUTH_PW'];
		}
		error_log('Authenticated request with [' . $this->client_id . '] and [' . $this->client_secret . ']');
	}
	
	protected function getContentType($hdrs) {
		foreach ($hdrs AS $h) {
			if (preg_match('|^Content-[Tt]ype:\s*text/plain|i', $h, $matches)) {
				return 'application/x-www-form-urlencoded';
			} else if (preg_match('|^Content-[Tt]ype:\s*application/x-www-form-urlencoded|i', $h, $matches)) {
				return 'application/x-www-form-urlencoded';
			}
		}
		return 'application/json';
	}
	
	protected function getStatusCode($hdrs) {
		$explode = explode(' ', $hdrs[0]);
		return $explode[1];
	}
	
	public function post($endpoint) {
		
		$postdata = $this->asQS();		
		error_log('Posting typically a token request: ' .var_export(array(
		 		'endpoint' => $endpoint,
				'header' => $this->getAuthorizationHeader(),
				'body' => $postdata,
		 	), true));
		So_log::debug('Posting typically a token request: ',
		 	array(
		 		'endpoint' => $endpoint,
				'header' => $this->getAuthorizationHeader(),
				'body' => $postdata,
		 	));
		
		$opts = array('http' =>
		    array(
		        'method'  => 'POST',
		        'header'  => "Content-type: application/x-www-form-urlencoded\r\n" . 
				// '',
				$this->getAuthorizationHeader() . "\r\n",
		        'content' => $postdata
		    )
		);
		$context  = @stream_context_create($opts);

		error_log("Posting to ednpoint: " . $endpoint);
		$result = @file_get_contents($endpoint, false, $context);
		$statuscode = $this->getStatusCode($http_response_header);
		
		if ((string)$statuscode !== '200') {
			
			So_log::error('When sending a token request, using a provided code, the returned status code was not 200 OK.',
				array(
					'resultdata' => $result,
					'headers' => $http_response_header
				)
			);
			
			throw new Exception('When sending a token request, using a provided code, the returned status code was not 200 OK.');
		}
		$ct = $this->getContentType($http_response_header);
		
		if ($ct === 'application/json') {

			error_log('RESPONSE WAS: '. var_export($result, true));

			$resultobj = json_decode($result, true);
			if ($resultobj === null) {
				$e = new So_InvalidResponse('na', 'Statuscode 200, but content was invalid JSON, on Token endpoint.');
				$e->raw = $result;
				throw $e;
			}
			
		} else if ($ct === 'application/x-www-form-urlencoded') {
			
			$resultobj = array();
			parse_str(trim($result), $resultobj);
			
		} else {
			// cannot be reached, right now.
			throw new Exception('Invalid content type in Token response.');
		}
		error_log("Done. Output was: " . $result );
		So_log::debug('Successfully parsed the Token Response body',array('response' => $resultobj));
		return $resultobj;
	}
	
}

class So_AuthRequest extends So_Request {
	public $response_type, $client_id, $redirect_uri, $scope, $state;
	function __construct($message) {
		parent::__construct($message);
		$this->response_type	= So_Utils::prequire($message, 'response_type', array('code', 'token'), true);		
		$this->client_id 		= So_Utils::prequire($message, 'client_id');
		$this->redirect_uri		= So_Utils::optional($message, 'redirect_uri');
		$this->scope			= So_Utils::spacelist(So_Utils::optional($message, 'scope'));
		$this->state			= So_Utils::optional($message, 'state');
	}
	
	function asQS() {
		$qs = array();
		foreach($this AS $key => $value) {
			if (empty($value)) continue;
			if ($key === 'scope') {
				$qs[] = urlencode($key) . '=' . urlencode(join(' ', $value));
				continue;
			} 
			$qs[] = urlencode($key) . '=' . urlencode($value);
		}
		return join('&', $qs);
	}
	
	function getResponse($message) {
		$message['state'] = $this->state;
		return new So_AuthResponse($message);
	}
}

class So_TokenRequest extends So_AuthenticatedRequest {
	public $grant_type, $code, $redirect_uri;
	function __construct($message) {
		parent::__construct($message);
		$this->grant_type		= So_Utils::prequire($message, 'grant_type', array('authorization_code', 'refresh_token', 'client_credentials'));
		$this->code 			= So_Utils::optional($message, 'code');
		$this->redirect_uri		= So_Utils::optional($message, 'redirect_uri');
	}

}

class So_Response extends So_Message {
	function __construct($message) {
		parent::__construct($message);
	}
}

class So_TokenResponse extends So_Response {
	public $access_token, $token_type, $expires_in, $refresh_token, $scope, $state;
	function __construct($message) {
		
		// Hack to add support for Facebook. Token type is missing.
		if (empty($message['token_type'])) $message['token_type'] = 'bearer';
		
		parent::__construct($message);
		$this->access_token		= So_Utils::prequire($message, 'access_token');
		$this->token_type		= So_Utils::prequire($message, 'token_type');
		$this->expires_in		= So_Utils::optional($message, 'expires_in');
		$this->refresh_token	= So_Utils::optional($message, 'refresh_token');
		$this->scope			= So_Utils::optional($message, 'scope');
		$this->state			= So_Utils::optional($message, 'state');
	}
}

class So_ErrorResponse extends So_Response {
	public $error, $error_description, $error_uri, $state;
	function __construct($message) {
		parent::__construct($message);
		$this->error 				= So_Utils::prequire($message, 'error', array(
			'invalid_request', 'access_denied', 'invalid_client', 'invalid_grant', 'unauthorized_client', 'unsupported_grant_type', 'invalid_scope'
		));
		$this->error_description	= So_Utils::optional($message, 'error_description');
		$this->error_uri			= So_Utils::optional($message, 'error_uri');
		$this->state				= So_Utils::optional($message, 'state');
	}
}

class So_AuthResponse extends So_Message {
	public $code, $state;
	function __construct($message) {
		parent::__construct($message);
		$this->code 		= So_Utils::prequire($message, 'code');
		$this->state		= So_Utils::optional($message, 'state');
	}
	function getTokenRequest($message = array()) {
		$message['code'] = $this->code;
		$message['grant_type'] = 'authorization_code';
		return new So_TokenRequest($message);
	}
}




// ---------- // ---------- // ---------- // ----------  Utils

class So_Utils {
	
	
	static function spacelist($arg) {
		if ($arg === null) return null;
		return explode(' ', $arg);
	}
	
	static function geturl() {
		$url = ((!empty($_SERVER['HTTPS'])) ? 
			"https://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'] : 
			"http://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
		return $url;
	}

	/**
	 * Add one or more query parameters to the given URL.
	 *
	 * @param $url  The URL the query parameters should be added to.
	 * @param $parameter  The query parameters which should be added to the url. This should be
	 *                    an associative array. For backwards comaptibility, it can also be a
	 *                    query string representing the new parameters. This will write a warning
	 *                    to the log.
	 * @return The URL with the new query parameters.
	 */
	public static function addURLparameter($url, $parameter) {

		/* For backwards compatibility - allow $parameter to be a string. */
		if(is_string($parameter)) {
			/* Print warning to log. */
			$backtrace = debug_backtrace();
			$where = $backtrace[0]['file'] . ':' . $backtrace[0]['line'];
			SimpleSAML_Logger::warning(
				'Deprecated use of SimpleSAML_Utilities::addURLparameter at ' .	$where .
				'. The parameter "$parameter" should now be an array, but a string was passed.');

			$parameter = self::parseQueryString($parameter);
		}
		assert('is_array($parameter)');

		$queryStart = strpos($url, '?');
		if($queryStart === FALSE) {
			$oldQuery = array();
			$url .= '?';
		} else {
			$oldQuery = substr($url, $queryStart + 1);
			if($oldQuery === FALSE) {
				$oldQuery = array();
			} else {
				$oldQuery = self::parseQueryString($oldQuery);
			}
			$url = substr($url, 0, $queryStart + 1);
		}

		$query = array_merge($oldQuery, $parameter);
		$url .= http_build_query($query, '', '&');

		return $url;
	}

	/**
	 * Parse a query string into an array.
	 *
	 * This function parses a query string into an array, similar to the way the builtin
	 * 'parse_str' works, except it doesn't handle arrays, and it doesn't do "magic quotes".
	 *
	 * Query parameters without values will be set to an empty string.
	 *
	 * @param $query_string  The query string which should be parsed.
	 * @return The query string as an associative array.
	 */
	public static function parseQueryString($query_string) {
		assert('is_string($query_string)');

		$res = array();
		foreach(explode('&', $query_string) as $param) {
			$param = explode('=', $param);
			$name = urldecode($param[0]);
			if(count($param) === 1) {
				$value = '';
			} else {
				$value = urldecode($param[1]);
			}

			$res[$name] = $value;
		}

		return $res;
	}
	
	// Found here:
	// 	http://stackoverflow.com/questions/2040240/php-function-to-generate-v4-uuid
	static function gen_uuid() {
	    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
	        // 32 bits for "time_low"
	        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

	        // 16 bits for "time_mid"
	        mt_rand( 0, 0xffff ),

	        // 16 bits for "time_hi_and_version",
	        // four most significant bits holds version number 4
	        mt_rand( 0, 0x0fff ) | 0x4000,

	        // 16 bits, 8 bits for "clk_seq_hi_res",
	        // 8 bits for "clk_seq_low",
	        // two most significant bits holds zero and one for variant DCE1.1
	        mt_rand( 0, 0x3fff ) | 0x8000,

	        // 48 bits for "node"
	        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
	    );
	}
	
	public static function optional($message, $key) {
		if (empty($message[$key])) return null;
		return $message[$key];
	}
	public static function prequire($message, $key, $values = null, $multivalued = false) {
		if (empty($message[$key])) {
			throw new So_Exception('invalid_request', 'Message does not include prequired parameter [' . $key . ']');
		}
		if (!empty($values)) {
			if ($multivalued) {
				$rvs = explode(' ', $message[$key]);
				foreach($rvs AS $v) {
					if (!in_array($v, $values)) {
						throw new So_Exception('invalid_request', 'Message parameter [' . $key . '] does include an illegal / unknown value.');
					}					
				}
			}
			if (!in_array($message[$key], $values)) {
				throw new So_Exception('invalid_request', 'Message parameter [' . $key . '] does include an illegal / unknown value.');
			}
		} 
		return $message[$key];
	}
}

