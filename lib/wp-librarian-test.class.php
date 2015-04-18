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
		add_filter('wp_lib_dash_home_buttons',		array($this,	'addTestDataButton'),	10, 2);
		
		add_action('wp_lib_dash_page_load',			array($this,	'addTestDataPage'),		10, 2);
	}
	
	/**
	 * Adds a button to the library Dashboard for lib admins to visit the test data page
	 * @link	https://github.com/kittsville/WP-Librarian/wiki/wp_lib_dash_home_buttons
	 * @param	array	$buttons	Dashboard buttons
	 * @return	array				Dash buttons with Test Data button added
	 */
	public function addTestDataButton(Array $buttons) {
		if (!wp_lib_is_library_admin())
			return $buttons;
		
		$buttons[] = array(
			'bName'	=> 'Test Data',
			'icon'	=> 'admin-tools',
			'link'	=> 'dash-page',
			'value'	=> 'test-data',
			'title'	=> 'Generate or delete test data'
		);
		
		return $buttons;
	}
	
	/**
	 * Adds a Dash page to view/create/delete test data
	 * @link	https://github.com/kittsville/WP-Librarian/wiki/wp_lib_dash_page_load
	 * @param	string				$url		Name of requested dash page, e.g. view-items
	 * @param	WP_LIB_AJAX_PAGE	$ajax_page	Instance of plugin AJAX page creating class
	 */
	public function addTestDataPage($url, WP_LIB_AJAX_PAGE $ajax_page) {
		$ajax_page->sendPage( 'Test Data', 'Test Data', array());
	}
}
