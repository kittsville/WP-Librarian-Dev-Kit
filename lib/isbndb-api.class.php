<?php
// No direct loading
defined( 'ABSPATH' ) OR die('No');

/**
 * A single request made to the ISBNdb API V2
 * @link http://isbndb.com/api/v2/docs
 */
class Lib_Dev_ISBNdb_Query {
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
	 * @param	string			$api_key	Valid API key to access the ISBNdb API
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
	 * Checks whether the ISBNdb returned an error
	 * @return bool Whether an error occurred
	 */
	public function hasError() {
		return isset($this->response->error);
	}
	
	/**
	 * Returns error returned by ISBNdb, if one exists
	 * @return string|null	Error, if one occurred, or NULL
	 */
	public function getError() {
		return $this->hasError() ? $this->response->error : null;
	}
}
