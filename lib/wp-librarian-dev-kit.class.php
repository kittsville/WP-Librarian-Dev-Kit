<?php
// No direct loading
defined('ABSPATH') OR die('No');

/**
 * Hooks onto WP-Librarian's filters to provide dev kit functionality
 */
class WP_Librarian_Dev_Kit {
	/**
	 * Paths to plugin's subdirectories
	 * Use these rather than hard-coding subdirectory names
	 */
	const SCRIPT_DIR        = 'scripts';
	const STYLE_DIR         = 'styles';
	const CLASS_DIR         = 'lib';
	const FIXTURE_DIR       = 'fixtures';
	const VENDOR_DIR        = 'vendor';
	
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
	 * Copy of WP-Librarian's core class
	 * @var WP_Librarian
	 */
	public $wp_librarian;
	
	/**
	 * Sets up plugin
	 * @todo Consider merging some required files into this class
	 */
	public function __construct() {
		$this->plugin_path  = dirname(dirname(__FILE__));
		$this->plugin_url   = plugins_url('', dirname(__FILE__));
		
		// Registers callbacks to WordPress/WP-Librarian hooks
		$this->registerHooks();
		
		if (defined('DOING_AJAX') && DOING_AJAX) {
			$this->loadClass('ajax');
			new Lib_Dev_AJAX($this);
		}
		
		require_once($this->plugin_path . '/wp-librarian-dev-kit-helpers.php');
	}
	
	/**
	 * Registers WP-Librarian hooks
	 */
	private function registerHooks(){
		add_filter('wp_lib_loaded',                         array($this,    'addLibraryClass'),         10, 1);
		
		add_filter('wp_lib_error_codes',                    array($this,    'registerErrors'));
		
		add_filter('wp_lib_plugin_settings',                array($this,    'addSettings'));
		add_filter('wp_lib_settings_tabs',                  array($this,    'addSettingsTab'),          10, 1);
		
		add_action('wp_lib_display_plugin_version',         array($this,    'displayDevKitVersion'));
		
		add_action('wp_lib_register_settings',              array($this,    'registerSettingsSection'));
		
		add_action('wp_lib_settings_page',                  array($this,    'enqueueSettingsScripts'),  10, 1);
		
		add_action('admin_enqueue_scripts',                 array($this,    'registerAdminScripts'),    10, 1);
		
		add_action('wp_lib_admin_enqueue_scripts',          array($this,    'minifyScripts'),           10);
		add_action('wp_lib_enqueue_scripts',                array($this,    'minifyScripts'),           10);
		
		add_action('wp_lib_loan_created',                   array($this,    'markFixtureLoans'),        10, 3);
		add_action('wp_lib_fine_created',                   array($this,    'markFixtureFines'),        10, 4);
	}
	
	/**
	 * Loads library class from /lib directory
	 * @param   string  $library Name of library to be loaded, excluding .class.php
	 */
	public function loadClass($library) {
		require_once($this->plugin_path . '/' . self::CLASS_DIR . '/' . $library . '.class.php');
	}
	
	/**
	 * Loads vendor code
	 * @param   string  $library    Name of vendor library
	 */
	public function loadVendor($library) {
		require_once($this->plugin_path . '/' . self::VENDOR_DIR . '/' . $library . '.php');
	}
	
	/**
	 * Given the name of a CSS file, returns its full URL
	 * @param   string  $name   File name e.g. 'front-end-core'
	 * @return  string          Full file URL e.g. '.../styles/front-end-core.css'
	 */
	public function getStyleUrl($name) {
		return $this->plugin_url . '/' . self::STYLE_DIR . '/' . $name . '.css';
	}
	
	/**
	 * Given the name of a JS file, returns its full URL
	 * @param   string  $name   File name e.g. 'admin-dashboard'
	 * @return  string          Full file URL e.g. '.../scripts/admin.js'
	 */
	public function getScriptUrl($name) {
		return $this->plugin_url . '/' . self::SCRIPT_DIR . '/' . $name . '.js';
	}
	
	/**
	 * Adds instance of WP-Librarian's core class to Dev Kit's core classes properties
	 * @param   WP_Librarian    $wp_librarian   Instance of core plugin class
	 */
	public function addLibraryClass(WP_Librarian $wp_librarian) {
		$this->wp_librarian = $wp_librarian;
	}
	
	/**
	 * Adds WP-Librarian Dev Kit's error codes to WP-Librarian's list of error codes
	 * @param   array   $errors WP-Librarian's error codes and their descriptions
	 * @return  array           WP-Librarian's/WP-Lib Dev Kit's' error codes and their descriptions
	 */
	public function registerErrors($errors) {
		return $errors + array(
			'1001' => 'Unable to resume fixture generation',
			'1002' => 'Unable to open members fixtures file',
			'1003' => 'Unable to read members fixtures files',
			'1005' => "Invalid ISBNdb API Key '\p'",
			'1006' => 'ISBNdb API returned an error',
			'1007' => 'Attempted to resume session using invalid token'
		);
	}
	
	/**
	 * Adds settings to WP-Librarian's valid settings array, allowing for use of WP-Librarian's settings class
	 * @param   Array   $settings   WP-Librarian's valid settings
	 * @return  Array               WP-Librarian's modified settings
	 */
	public function addSettings(array $settings) {
		$settings['lib_dev_api_key']        = array('');
		$settings['lib_dev_meta_threshold'] = array(10);
		
		return $settings;
	}
	
	/**
	 * Adds settings section as a new tab to WP-Librarian's settings page
	 * @param   Array   $settings_tabs  Current WP-Lib settings page tabs
	 * @return  Array                   Modified WP-Lib settings page tabs
	 */
	public function addSettingsTab(Array $settings_tabs) {
		$settings_tabs['dev-kit'] = array('lib_dev_settings', 'Dev Kit');
		
		return $settings_tabs;
	}
	
	/**
	 * Registers plugin's settings section using WP-Librarian's settings class
	 */
	public function registerSettingsSection() {
		WP_Lib_Settings_Section::registerSection(array(
			'name'      => 'lib_dev_settings',
			'title'     => 'Dev Kit Settings',
			'settings'  => array(
				array(
					'name'          => 'lib_dev_api_key',
					'sanitize'      =>
						function($raw) {
							// Sanitizes input
							$api_key = ereg_replace('[^A-Za-z0-9]', '', $raw[0]);
							
							$this->loadClass('isbndb-api');
							
							// Checks key is valid
							$query = new Lib_Dev_ISBNdb_Query($api_key, 'book', 'raising_steam');
							
							if ($query->hasError())
								return array('');
							else
								return array($api_key);
						},
					'fields'        => array(
						array(
							'name'          => 'ISBNdb API Key',
							'field_type'    => 'textInput',
							'args'          => array(
								'alt'       => "A valid API key for the <a href='http://isbndb.com/api/v2/docs'>ISBNdb API V2</a> (they're free!)"
							)
						)
					)
				),
				array(
					'name'      => 'lib_dev_meta_threshold',
					'sanitize'  =>
						function($raw) {
							return array(max(min((int) $raw[0], 100), 0)); // Sanitizes to an int 0-100 inclusive
						},
					'fields'    => array(
						array(
							'name'          => 'Blank Meta Percentage',
							'field_type'    => 'textInput',
							'args'          => array(
								'alt'       => 'The percentage of meta fields (email/phone number) to leave blank when generating fixtures'
							)
						)
					)
				),
			)
		));
	}
	
	/**
	 * Creates minified WP-Librarian scripts/styles for /data directory
	 */
	public function minifyScripts() {
		$this->loadVendor('CssMin');
		$this->loadVendor('JShrink');
		
		$script_directory   = new DirectoryIterator($this->wp_librarian->plugin_path . '\\' . WP_Librarian::SCRIPT_DIR);
		$style_directory    = new DirectoryIterator($this->wp_librarian->plugin_path . '\\' . WP_Librarian::STYLE_DIR);
		
		$minified_directory = $this->wp_librarian->plugin_path . '\\' . WP_Librarian::MINIFIED_DIR . '\\';
		
		foreach ($script_directory as $script_info) {
			if ($script_info->isFile() && $script_info->getExtension() === 'js') {
				$script_pathname    = $script_info->getPathname();
				$minified_filename  = $minified_directory . $script_info->getBasename('.js') . '.min.js'; // derp.js -> derp.min.js
				
				// Only minifies an asset if it has been modified since the last minification or doesn't exist
				if (!file_exists($minified_filename) || filemtime($script_pathname) > filemtime($minified_filename)) {
					file_put_contents($minified_filename, \JShrink\Minifier::minify(file_get_contents($script_pathname)), LOCK_EX);
				}
			}
		}
		
		foreach ($style_directory as $style_info) {
			if ($style_info->isFile() && $style_info->getExtension() === 'css') {
				$style_pathname = $style_info->getPathname();
				$minified_filename  = $minified_directory . $style_info->getBasename('.css') . '.min.css'; // derp.css -> derp.min.css
				
				// Only minifies an asset if it has been modified since the last minification or doesn't exist
				if (!file_exists($minified_filename) || filemtime($style_pathname) > filemtime($minified_filename)) {
					file_put_contents($minified_filename, CssMin::minify(file_get_contents($style_pathname), array('RemoveComments' => false)), LOCK_EX);
				}
			}
		}
	}
	
	/**
	 * Updates/sets plugin's current version in WP settings
	 */
	public function runOnActivation() {
		update_option('lib_dev_version', $this->getPluginVersion());
	}
	
	/**
	 * Returns the current update channel, version, build and WP-Lib version compatibility of WP-Librarian Dev Kit
	 * @return  array   Current plugin version
	 */
	public function getPluginVersion() {
		return array(
			'channel'       => 'Alpha',
			'version'       => '0.1',
			'subversion'    => '001',
			'libBuild'      => '0.3',   // Compatible WP-Librarian version
		);
	}
	
	/**
	 * Renders Dev Kit version
	 */
	public function displayDevKitVersion() {
		$plugin_version = $this->getPluginVersion();
		
		echo '<span id="lib-dev-version">DEV KIT ' . $plugin_version['channel'] . ' ' . $plugin_version['version'] . '</span>';
	}
	
	/**
	 * Registers scripts for Dashboard and settings tab
	 * @param string $hook  The URL prefix of the current admin page
	 * @see                 http://codex.wordpress.org/Plugin_API/Action_Reference/admin_enqueue_scripts
	 */
	public function registerAdminScripts($hook) {
		wp_register_script('lib_dev_settings', $this->getScriptUrl('settings'),  array('jquery'), '0.1');
		
		wp_localize_script('lib_dev_settings', 'wp_libfix_vars', array(
			'apiKey'    => get_option('lib_dev_api_key', array(''))[0]
		));
	}
	
	/**
	 * Loads scripts on the plugin's settings page tab
	 * @param string $tab Current settings page tab name
	 */
	public function enqueueSettingsScripts($tab) {
		if ($tab === 'dev-kit')
			wp_enqueue_script('lib_dev_settings');
	}
	
	/**
	 * Marks all loans created from fixture items/member as fixtures
	 * This ensures fixture loans are cleaned up if plugin tries to delete fixtures
	 * @param   int         $loan_id    Post ID of new loan
	 * @param   WP_Lib_Item $item       Item being borrowed
	 * @param   int         $member_id  Post ID of member borrowing item
	 */
	public function markFixtureLoans($loan_id, WP_Lib_Item $item, $member_id) {
		// If either item/member are fixtures
		if (get_post_meta($item->ID, '_lib_dev_id', true) !== '' || get_post_meta($member_id, '_lib_dev_id', true) !== '' ) {
			add_post_meta($loan_id, '_lib_dev_id', true);
		}
	}
	
	/**
	 * Marks fines created from fixture loans as fixtures themselves
	 * This ensures fixture loans are cleaned up if plugin tries to delete fixtures
	 * @param   int         $fine_id    Post ID of new fine
	 * @param   int         $item_id    Post ID of new item
	 * @param   int         $member_id  Post ID of member borrowing item
	 * @param   WP_Lib_Loan $loan       Loan of Item to Member
	 */
	public function markFixtureFines($fine_id, $item_id, $member_id, WP_Lib_Loan $loan) {
		if (get_post_meta($loan->ID, '_lib_dev_id', true) !== '') {
			add_post_meta($fine_id, '_lib_dev_id', true);
		}
	}
}
