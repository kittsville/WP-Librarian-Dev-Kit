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
	 * Path to plugin folder, without trailing slash
	 * @var string
	 */
	public $plugin_path;
	
	/**
	 * Public URL to plugin folder, without trailing slash
	 * @var string
	 */
	public $plugin_url;
	
	/**
	 * Sets up plugin
	 * @todo Consider merging some required files into this class
	 */
	public function __construct() {
		$this->plugin_path	= dirname(dirname(__FILE__));
		$this->plugin_url	= plugins_url('', dirname(__FILE__));
		
		// Registers functions to WordPress hooks
		$this->registerHooks();
	}
	
	/**
	 * Registers WP-Librarian hooks
	 */
	private function registerHooks(){
		add_action('wp_lib_plugin_settings',				array($this,	'addSettings'));
		add_filter('wp_lib_settings_tabs',					array($this,	'addSettingsTab'),		10, 1);
		
		add_action('wp_lib_register_settings',				array($this,	'registerSettingsSection'));
		
		add_filter('wp_lib_dash_home_buttons',				array($this,	'addTestDataButton'),	10, 2);
		
		add_action('wp_lib_dash_page_load_test-data',		array($this,	'addTestDataPage'),		10, 2);
		
		add_action('wp_lib_dash_action_gen-test-data',		array($this,	'genTestData'),			10, 1);
		add_action('wp_lib_dash_action_delete-test-data',	array($this,	'deleteTestData'),		10,	1);
	}
	
	/**
	 * Given the name of a CSS file, returns its full URL
	 * @param	string	$name	File name e.g. 'front-end-core'
	 * @return	string			Full file URL e.g. '.../styles/front-end-core.css'
	 */
	public function getStyleUrl($name) {
		return $this->plugin_url . '/styles/' . $name . $suffix . '.css';
	}
	
	/**
	 * Given the name of a JS file, returns its full URL
	 * @param	string	$name	File name e.g. 'admin-dashboard'
	 * @return	string			Full file URL e.g. '.../scripts/admin.js'
	 */
	public function getScriptUrl($name) {
		return $this->plugin_url . '/scripts/' . $name . $suffix . '.js';
	}
	
	/**
	 * Adds settings to WP-Librarian's valid settings array, allowing for use of WP-Librarian's settings class
	 * @param	Array	$settings	WP-Librarian's valid settings
	 * @return	Array				WP-Librarian's modified settings
	 */
	public function addSettings(array $settings) {
		$settings['wp_libfix_api_key'] = array('');
		
		return $settings;
	}
	
	/**
	 * Adds settings section as a new tab to WP-Librarian's settings page
	 * @param	Array	$settings_tabs	Current WP-Lib settings page tabs
	 * @return	Array					Modified WP-Lib settings page tabs
	 */
	public function addSettingsTab(Array $settings_tabs) {
		$settings_tabs['test-data'] = array('wp_libfix_settings', 'Test Data');
		
		return $settings_tabs;
	}
	
	/**
	 * Registers plugin's settings section using WP-Librarian's settings class
	 */
	public function registerSettingsSection() {
		WP_LIB_SETTINGS_SECTION::registerSection(array(
			'name'		=> 'wp_libfix_settings',
			'title'		=> 'Test Data Settings',
			'page'		=> 'wp_libfix_settings-options',
			'settings'	=> array(
				array(
					'name'			=> 'wp_libfix_api_key',
					'sanitize'		=>
						function($raw) {
							// Ensures loan length is an integer between 1-100 (inclusive)
							return array(ereg_replace('[^A-Za-z0-9]', '', $raw[0]));
						},
					'fields'		=> array(
						array(
							'name'			=> 'ISBNdb API Key',
							'field_type'	=> 'textInput',
							'args'			=> array(
								'alt'		=> 'A valid API key for the <a href="http://isbndb.com/api/v2/docs">ISBNdb API V2</a>'
							)
						)
					)
				)
			)
		));
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
		// Stops non-library admins viewing page
		if (!wp_lib_is_library_admin())
			return;
		
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
