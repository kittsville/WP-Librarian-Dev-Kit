<?php
// No direct loading
defined( 'ABSPATH' ) OR die('No');

/**
 * Handles retrieving data and managing access limitations to the ISBNdb API V2
 * @link http://isbndb.com/api/v2/docs
 */
class LIB_Dev_ISBNDB {
	/**
	 * The key to grant access to the API
	 */
	private $apiKey;
	
	/**
	 * Sets API key on object construction
	 * @param string $api_key Valid ISBNdb API v2 key
	 */
	public function __construct($api_key) {
		$this->apiKey = $api_key;
	}
	
	/**
	 * Checks if current API key property or given API key is valid
	 * @param	string $api_key API key to check validity
	 * @return	bool			Whether the key is valid
	 */
	public static function validKey($api_key) {
		$request = new LIB_Dev_ISBNDB_Query($api_key, 'book', 'raising_steam');
		
		// If request doesn't have an error then the API key is valid
		return !$request->hasError();
	}
}

/**
 * A single request made to the ISBNdb API V2
 * @link http://isbndb.com/api/v2/docs
 */
class LIB_Dev_ISBNDB_Query {
	/**
	 * URL to access API
	 */
	const URL_BASE = 'http://isbndb.com/api/v2/json/';
	
	/**
	 * Parsed JSON returned by the API
	 * False if request failed
	 */
	public $response;
	
	/**
	 * URL that was used to query API
	 */
	public $query_url;
	
	/**
	 * Makes the request on object initialisation
	 * @param	string			$api_key	Valid API key to access the ISBNDB API
	 * @param	string			$endpoint	The API endpoint being queried, e.g. 'books'
	 * @param	Array|string	$args		API request argument(s)
	 */
	public function __construct($api_key, $endpoint, $args) {
		// Formats array as URL query parameters
		$query_params = is_array($args) ? http_build_query($args) : $args;
		
		$this->query_url = self::URL_BASE . $api_key . '/' . $endpoint . '/' . $query_params;
		
		$context = stream_context_create(array('http' => array(
			'ignore_errors' => true
		)));
		
		$this->response = file_get_contents($this->query_url, false, $context);
		
		// Parses JSON if request succeeded (not including API errors)
		if ($this->response !== false && http_response_code() === 200) {
			$this->response = json_decode($this->response);
		} else {
			$this->response = new stdClass();
			
			$this->response->error = 'HTTP Error ' + http_response_code();
		}
	}
	
	/**
	 * Checks whether the ISBNDB returned an error
	 * @return bool Whether an error occurred
	 */
	public function hasError() {
		return isset($this->response->error);
	}
	
	/**
	 * Returns error returned by ISBNDB, if one exists
	 * @return string|null	Error, if one occurred, or NULL
	 */
	public function getError() {
		return $this->hasError() ? $this->response->error : null;
	}
}
