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
	}
	
	/**
	 * Adds a button to the library Dashboard for lib admins to visit the test data page
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
}