<?php
/**
 * WP-LIBRARIAN TEST
 * Processes YAML Data and creates database entries
 */

// No direct loading
defined('ABSPATH') OR die('No');

/**
 * Hooks onto WP_Librarian filters to provide the means to create test data
 */
class WP_LIBRARIAN_TEST {
	/**
	 * Registers WP-Librarian hooks
	 */
	function __construct(){
		add_filter('wp_lib_dash_page',		array($this, 'addTestDataButton'), 10, 2);
	}
	
	/**
	 * Adds a button to the library Dashboard to visit the test data page
	 * @param	array	$page		Complete Dash page
	 * @param	string	$dash_page	The page's identifying string e.g. 'pay-fines'
	 */
	public function addTestDataButton(Array $page, $dash_page) {
		if ($dash_page !== 'dashboard')
			return $page;
		
		return $page;
	}
}