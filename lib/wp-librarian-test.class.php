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
		add_filter('wp_lib_dash_home_buttons',				array($this,	'addTestDataButton'),	10, 2);
		
		add_action('wp_lib_dash_page_load_test-data',		array($this,	'addTestDataPage'),		10, 2);
		
		add_action('wp_lib_dash_action_gen-test-data',		array($this,	'genTestData'),			10, 1);
		add_action('wp_lib_dash_action_delete-test-data',	array($this,	'deleteTestData'),		10,	1);
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
	 * @param	WP_LIB_AJAX_PAGE	$ajax_page	Instance of plugin AJAX page creating class
	 */
	public function addTestDataPage(WP_LIB_AJAX_PAGE $ajax_page) {
		$page = array(
			array(
				'type'		=> 'paras',
				'content'	=> array(
					'Generate or delete test data'
				)
			)
		);
		
		$form[] = $ajax_page->prepNonce('Managing Test Data');
		
		// Looks for any objects marked as test data
		$query = new WP_Query(array(
			'post_type'		=> 'wp_lib_items',
			'post_status'	=> 'publish',
			'meta_query'	=> array(
				array(
					'key'		=> 'wp_lib_test_data',
					'compare'	=> 'EXISTS'
				)
			)
		));
		
		// If there is no test data, allow for its creation
		if (!$query->have_posts()) {
		$form[] = array(
			'type'	=> 'button',
			'link'	=> 'action',
			'value'	=> 'gen-test-data',
			'html'	=> 'Generate',
			'title'	=> 'Create all fixture data'
		);
		} else {
			$form[] = array(
				'type'		=> 'button',
				'link'		=> 'action',
				'value'		=> 'delete-test-data',
				'classes'	=> 'dash-button-danger',
				'html'		=> 'Delete',
				'title'		=> 'Delete all fixture data'
			);
		}
		
		$page[] = array(
			'type'		=> 'form',
			'content'	=> $form
		);
		
		$ajax_page->sendPage( 'Test Data Management Panel', 'Test Data', $page);
	}
	
	/**
	 * Parses fixtures data and creates items, members, loans and fines with them
	 * @link	https://github.com/kittsville/WP-Librarian/wiki/wp_lib_dash_action_
	 * @param	WP_LIB_AJAX_ACTION	$ajax_action	Instance of WP-Librarian class for handling Dash actions
	 */
	public function genTestData(WP_LIB_AJAX_ACTION $ajax_action) {
		$ajax_action->addNotification('Placeholder for creating test data');
		$ajax_action->endAction(false);
	}
	
	/**
	 * Deletes fixture data, identified as all posts with the post meta 'wp_lib_test_data'
	 * @link	https://github.com/kittsville/WP-Librarian/wiki/wp_lib_dash_action_
	 * @param	WP_LIB_AJAX_ACTION	$ajax_action	Instance of WP-Librarian class for handling Dash actions
	 */
	public function deleteTestData(WP_LIB_AJAX_ACTION $ajax_action) {
		$ajax_action->addNotification('Placeholder for deleting test data');
		$ajax_action->endAction(false);
	}
}
