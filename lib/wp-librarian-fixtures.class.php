<?php
// No direct loading
defined('ABSPATH') OR die('No');

/**
 * Hooks onto WP_Librarian filters to provide the means to create test data
 */
class WP_LIBRARIAN_FIXTURES {
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
		
		// Registers callbacks to WordPress/WP-Librarian hooks
		$this->registerHooks();
		
		if (defined('DOING_AJAX') && DOING_AJAX) {
			$this->loadClass('ajax');
			new LIB_FIX_AJAX($this);
		}
		
		require_once($this->plugin_path . '/wp-librarian-fixtures-helpers.php');
	}
	
	/**
	 * Registers WP-Librarian hooks
	 */
	private function registerHooks(){
		add_filter('wp_lib_error_codes',					array($this,	'registerErrors'));
		
		add_filter('wp_lib_plugin_settings',				array($this,	'addSettings'));
		add_filter('wp_lib_settings_tabs',					array($this,	'addSettingsTab'),			10, 1);
		
		add_action('wp_lib_register_settings',				array($this,	'registerSettingsSection'));
		
		add_action('wp_lib_settings_page',					array($this,	'enqueueSettingsScripts'),	10, 1);
		
		add_action('admin_enqueue_scripts',					array($this,	'registerAdminScripts'),	10, 1);
	}
	
	/**
	 * Loads library class from /lib directory
	 * @param	string	$helper	Name of library to be loaded, excluding .class.php
	 */
	public function loadClass($library) {
		require_once($this->plugin_path . '/lib/' . $library . '.class.php');
	}
	
	/**
	 * Given the name of a CSS file, returns its full URL
	 * @param	string	$name	File name e.g. 'front-end-core'
	 * @return	string			Full file URL e.g. '.../styles/front-end-core.css'
	 */
	public function getStyleUrl($name) {
		return $this->plugin_url . '/styles/' . $name . '.css';
	}
	
	/**
	 * Given the name of a JS file, returns its full URL
	 * @param	string	$name	File name e.g. 'admin-dashboard'
	 * @return	string			Full file URL e.g. '.../scripts/admin.js'
	 */
	public function getScriptUrl($name) {
		return $this->plugin_url . '/scripts/' . $name . '.js';
	}
	
	/**
	 * Adds WP-Librarian-Fixtures error codes to WP-Librarian's list of error codes
	 * @param	array	$errors	WP-Librarian's error codes and their descriptions
	 * @return	array			WP-Librarian's/WP-Lib-Fixtures' error codes and their descriptions
	 */
	public function registerErrors($errors) {
		return $errors + array(
			'1001' => 'Unable to resume fixture generation',
			'1002' => 'Unable to open members fixtures file',
			'1003' => 'Unable to read members fixtures files',
			'1004' => 'Not enough fixture members to satisfy request',
			'1005' => "Invalid ISBNDB API Key '\p'",
			'1006' => 'ISBNDB API returned an error',
			'1007' => 'Attempted to resume session using invalid token'
		);
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
			'settings'	=> array(
				array(
					'name'			=> 'wp_libfix_api_key',
					'sanitize'		=>
						function($raw) {
							// Sanitizes input
							$api_key = ereg_replace('[^A-Za-z0-9]', '', $raw[0]);
							
							$this->loadClass('isbndb-api');
							
							// Checks key is valid
							$query = new LIB_FIX_ISBNDB_QUERY($api_key, 'book', 'raising_steam');
							
							if ($query->hasError())
								return array('');
							else
								return array($api_key);
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
	 * Registers scripts for Dashboard and settings tab
	 * @param string $hook	The URL prefix of the current admin page
	 * @see					http://codex.wordpress.org/Plugin_API/Action_Reference/admin_enqueue_scripts
	 */
	public function registerAdminScripts($hook) {
		wp_register_script('wp_libfix_setings', $this->getScriptUrl('settings'),  array('jquery'), '0.1');
		
		wp_localize_script('wp_libfix_setings', 'wp_libfix_vars', array(
			'apiKey'	=> get_option('wp_libfix_api_key', array(''))[0]
		));
	}
	
	/**
	 * Loads scripts on the plugin's settings page tab
	 * @param string $tab Current settings page tab name
	 */
	public function enqueueSettingsScripts($tab) {
		if ($tab === 'test-data')
			wp_enqueue_script('wp_libfix_setings');
	}
}