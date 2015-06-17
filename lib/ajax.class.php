<?php
// No direct loading
defined('ABSPATH') OR die('No');

/**
 * Handles Library Dashboard AJAX pages and page elements
 */
class Lib_Dev_AJAX {
	/**
	 * Instance of core plugin class
	 */
	private $wp_librarian_dev_kit;
	
	/**
	 * Instance of WP-Librarian AJAX class (WP_LIB_AJAX|WP_LIB_AJAX_ACTION|WP_LIB_AJAX_PAGE)
	 */
	private $ajax;
	
	/**
	 * Holds messages to be sent to client for multi-stage processes
	 * @var Array
	 */
	private $message_buffer = array();
	
	/**
	 * Registers Dashboard AJAX hooks and adds instance of core plugin class as object property
	 * @param	WP_Librarian_Dev_Kit	$wp_librarian_dev_kit	Instance of core plugin class
	 */
	public function __construct(WP_Librarian_Dev_Kit $wp_librarian_dev_kit) {
		$this->wp_librarian_dev_kit = $wp_librarian_dev_kit;
		
		$this->registerHooks();
	}
	
	/**
	 * Registers Dashboard AJAX hooks to add Fixtures pages and actions to WP-Librarian
	 */
	public function registerHooks() {
		add_filter('wp_lib_dash_home_buttons',				array($this,	'addFixturesButton'),		10, 2);
		
		add_action('wp_lib_dash_page_man-fix',				array($this,	'addFixturesPage'),			10, 2);
		
		add_action('wp_lib_dash_api_generate-fixtures',		array($this,	'genFixtures'),				10, 1);
		add_action('wp_lib_dash_api_delete-fixtures',		array($this,	'deleteFixtures'),			10,	1);
	}
	
	/**
	 * Adds a button to the library Dashboard for lib admins to visit the test data page
	 * @link	https://github.com/kittsville/WP-Librarian/wiki/wp_lib_dash_home_buttons
	 * @param	array	$buttons	Dashboard buttons
	 * @return	array				Dash buttons with Fixture button added
	 */
	public function addFixturesButton(Array $buttons) {
		if (!wp_lib_is_library_admin())
			return $buttons;
		
		$buttons[] = array(
			'bName'	=> 'Fixtures',
			'icon'	=> 'admin-tools',
			'link'	=> 'dash-page',
			'value'	=> 'man-fix',
			'title'	=> 'Generate or delete test data'
		);
		
		return $buttons;
	}
	
	/**
	 * Adds a Dash page to view/create/delete test data
	 * @link	https://github.com/kittsville/WP-Librarian/wiki/wp_lib_dash_page_load
	 * @param	WP_LIB_AJAX_PAGE	$ajax_page	Instance of plugin AJAX page creating class
	 */
	public function addFixturesPage(WP_LIB_AJAX_PAGE $ajax_page) {
		// Stops non-library admins viewing page
		if (!wp_lib_is_library_admin())
			return;
		
		$form = array(
			$ajax_page->prepNonce('Manage Fixtures'),
			array(
				'type'	=> 'div',
				'inner'	=> array(
					array(
						'type'	=> 'input',
						'id'	=> 'item-count',
						'attr'	=> array(
							'type'			=> 'number',
							'min'			=> '0',
							'placeholder'	=> 'Items'
						)
					),
					array(
						'type'	=> 'input',
						'id'	=> 'member-count',
						'attr'	=> array(
							'type'			=> 'number',
							'min'			=> '0',
							'max'			=> '5000', // Current fixtures limit. Doesn't matter that user can remove attribute as server can catch excessive fixture requests
							'placeholder'	=> 'Members'
						)
					)
				)
			),
			array(
				'type'	=> 'button',
				'link'	=> 'none',
				'id'	=> 'gen-fixtures',
				'html'	=> 'Generate',
				'title'	=> 'Create fixtures'
			)
		);
		
		if (lib_dev_fixtures()->have_posts()) {
			$form[] = array(
				'type'		=> 'button',
				'link'		=> 'none',
				'id'		=> 'delete-fixtures',
				'classes'	=> 'dash-button-danger',
				'html'		=> 'Delete',
				'title'		=> 'Delete all fixtures'
			);
		}
		
		$page = array(
			array(
				'type'		=> 'paras',
				'content'	=> array(
					'Generate or delete fixture items and members'
				)
			),
			array(
				'type'		=> 'form',
				'content'	=> $form
			),
			array(
				'type'		=> 'div',
				'id'		=> 'fixture-process-messages'
			)
		);
		
		$ajax_page->sendPage('Fixture Management Panel', 'Fixture Management', $page, array($this->wp_librarian_dev_kit->getScriptUrl('FixtureManager')));
	}
	
	/**
	 * Parses fixtures data and creates items, members, loans and fines with them
	 * @link	https://github.com/kittsville/WP-Librarian/wiki/wp_lib_dash_api_
	 * @param	WP_LIB_AJAX_API	$ajax_api	Instance of WP-Librarian class for handling Dash API calls
	 */
	public function genFixtures(WP_LIB_AJAX_API $ajax_api) {
		$ajax_api->checkNonce('Manage Fixtures');
		
		$this->ajax = $ajax_api;
		
		// Process is broken up into multiple stages, using the session to track progress/data
		session_name('lib_dev_gen_test_data');
		session_start();
		
		if (isset($_POST['stage_code'])) {
			if ($_POST['stage_code'] === $_SESSION['stage_code']) {
				// Generates fresh resume code
				$_SESSION['stage_code'] = $this->genStageCode();
			} else {
				$this->ajax->stopAjax(1007);
			}
		} else {
			$this->addMessage('Starting Fixture Creation Process (this can take some time)...');
			$_SESSION['stage']			= 0;
			$_SESSION['stage_code']		= $this->genStageCode();
			$_SESSION['item_count']		= isset($_POST['item_count']) ? max((int) $_POST['item_count'], 0) : 40;
			$_SESSION['member_count']	= isset($_POST['member_count']) ? max((int) $_POST['member_count'], 0) : 10;
			$this->addMessage("Will attempt to generate {$_SESSION['item_count']} item(s) and {$_SESSION['member_count']} member(s)");
		}
		
		switch ($_SESSION['stage']) {
			// Loads existing fixtures in library
			case 0:
				// Skips everything if there's nothing to do
				if ($_SESSION['member_count'] === 0 && $_SESSION['item_count'] === 0) {
					$this->addMessage('Created 0 items and 0 members');
					$this->addMessage('You sure are pushing this Development Kit to its limits');
					
					$_SESSION['stage_code'] = false;
					
					break;
				}
				
				$wp_query = lib_dev_fixtures();
			
				// Creates an array of all existing fixtures in the library
				$_SESSION['existing_items']		= array();
				$_SESSION['existing_members']	= array();
				if ( $wp_query->have_posts() ){
					while ( $wp_query->have_posts() ) {
						$wp_query->the_post();
						
						switch(get_post_type()) {
							case 'wp_lib_items':
								$_SESSION['existing_items'][get_post_meta(get_the_ID(), '_lib_dev_id', true)] = get_the_ID();
							break;
							
							case 'wp_lib_members':
								$_SESSION['existing_members'][get_post_meta(get_the_ID(), '_lib_dev_id', true)] = get_the_ID();
							break;
						}
					}
				}
				
				$this->addMessage('Loaded existing item/member fixtures');
				
				// Next request will move on to the next stage
				++$_SESSION['stage'];
			break;
			
			// Prepares to create members by building list of possible members
			case 1:
				if ($_SESSION['member_count'] === 0) {
					$this->addMessage('No fixture members to create');
					
					/**
					 * Skips member creation step
					 * You could even say it...steps over it
					 * (•_•)
					 * ( •_•)>⌐■-■
					 * (⌐■_■)
					 */
					$_SESSION['stage'] += 2;
					break;
				}
				
				$this->addMessage("Creating {$_SESSION['member_count']} member(s)...");
				
				$member_json = @file_get_contents($this->wp_librarian_dev_kit->plugin_path . '/' . WP_Librarian_Dev_Kit::FIXTURE_DIR . '/members.json', 'r') or $ajax_api->stopAjax(1002);
				
				$members = json_decode($member_json);
				
				if ($members === null)
					$ajax_api->stopAjax(1003);
				
				$this->addMessage('Members fixtures file contains ' . count(get_object_vars($members)) . ' member(s).');
				
				$_SESSION['new_members'] = array();
				
				/**
				 * Loops over members, listing them as a possible fixture if they don't already exist in the library
				 * Note that $member_id is a unique hash used to identify members if their name/details are changed
				 * This is completely different from WP-Librarian's $member_id which is the WP post ID (an integer)
				 */
				foreach ($members as $member_id => $member) {
					if (count($_SESSION['new_members']) >= $_SESSION['member_count'])
						break;
					
					// Skips adding members that already exist in the library
					if (isset($_SESSION['existing_members'][$member_id]))
						continue;
					
					$member->ID = $member_id;
					
					$_SESSION['new_members'][] = $member;
				}
				
				if (count($_SESSION['new_members']) < count($_SESSION['member_count'])) {
					$shortfall = count($_SESSION['member_count']) - count($_SESSION['new_members']);
					
					$this->addMessage("Fell short of requested member count by {$shortfall} member(s). Mostly likely caused by insufficient fixtures in the JSON file used.");
				}
				
				$_SESSION['meta_use_threshold'] = get_option('lib_dev_meta_threshold', array(0))[0];
				
				// Next request will move on to the next stage
				++$_SESSION['stage'];
			break;
			
			// Creates requested number of fixture members in batches of 50
			case 2:
				for ($i = 0; $i < 50; $i++) {
					$member = array_pop($_SESSION['new_members']);
					
					if ($member === null)
						break;
					
					$member_id = wp_insert_post(array(
						'post_type'		=> 'wp_lib_members',
						'post_title'	=> $member->Name,
						'post_status'	=> 'publish'
					));
					
					if (is_wp_error($member_id)) {
						$this->addMessage("Error encountered creating member {$member->Name}");
						continue;
					}
					
					add_post_meta($member_id, '_lib_dev_id', $member->ID);
					
					$post_meta = array(
						'wp_lib_member_phone'	=> $member->Phone,
						'wp_lib_member_mobile'	=> $member->Mobile,
						'wp_lib_member_email'	=> $member->Email,
					);
					
					// Makes a certain percentage of member meta blank, for checking how WP-Librarian handles empty (optional) values
					foreach ($post_meta as $meta_key => $meta_value) {
						if (rand(0, 100) >= $_SESSION['meta_use_threshold'])
							add_post_meta($member_id, $meta_key, $meta_value);
						else
							add_post_meta($member_id, $meta_key, '');
					}
				}
				
				$remaining = count($_SESSION['new_members']);
				
				if ($remaining > 0) {
					$this->addMessage("Created {$i} new fixture member(s). {$remaining} remain");
				} else {
					$this->addMessage("Created {$i} new fixture member(s). Member generation completed");
					
					// Next request will move on to the next stage
					++$_SESSION['stage'];
				}
			break;
			
			// Locates requested number of fixture items
			case 3:
				if ($_SESSION['item_count'] === 0) {
					$this->addMessage('No fixture items to create');
					
					// Skips item creation stages
					$_SESSION['stage'] += 3;
					break;
				} else {
					$this->addMessage("Creating {$_SESSION['item_count']} item(s)...");
				}
				
				$_SESSION['api_key'] = $this->getApiKey();
				
				$this->wp_librarian_dev_kit->loadClass('isbndb-api');
				
				$publisher_query = new Lib_Dev_ISBNdb_Query($_SESSION['api_key'], 'publisher', 'doubleday');
				
				$this->checkIsbndbError($publisher_query);
				
				$_SESSION['new_items']	= array();
				$new_book_count			= 0;
				
				/**
				 * Book_id is a unique slug used by ISBNdb, not WP-Librarian's item_id which is a unique integer
				 */
				foreach ($publisher_query->response->data[0]->book_ids as $book_id) {
					if ($new_book_count >= $_SESSION['item_count'])
						break;
					
					// Skips adding books which already exist in the library
					if (isset($_SESSION['existing_items'][$book_id]))
						continue;
					
					$_SESSION['new_items'][] = $book_id;
					++$new_book_count;
				}
				
				$this->addMessage("Found {$new_book_count} potential fixture item(s)");
				
				if ($new_book_count < $_SESSION['item_count']) {
					$shortfall = $_SESSION['item_count'] - $new_book_count;
					$this->addMessage("Fell short of requested items by {$shortfall} item(s)");
				}
				
				// Next request will move on to the next stage
				++$_SESSION['stage'];
			break;
			
			// Prepares to get fixture items
			case 4:
				$_SESSION['item_descriptions'] = explode("\n\n", file_get_contents('http://loripsum.net/api/' . $_SESSION['item_count'] . '/plaintext'));
				
				// Last element is blank because of newline parsing
				array_pop($_SESSION['item_descriptions']);
				
				// Makes sure the media type 'Book' exists
				if (!term_exists('Book', 'wp_lib_media_type'))
					wp_insert_term('Book', 'wp_lib_media_type');
				
				$this->addMessage('Generated lorem ipsum for item descriptions');
				
				// Next request will move on to the next stage
				++$_SESSION['stage'];
			break;
			
			// Creates fixture items in batches of 10 per iteration
			case 5:
				$this->wp_librarian_dev_kit->loadClass('isbndb-api');
				
				for ($i = 0; $i < 10; $i++) {
					$book_id = array_pop($_SESSION['new_items']);
					
					// No more books to process
					if ($book_id === null)
						break;
					
					// Loads book title/authors/ISBN13 from ISBNdb API
					$book_query = new Lib_Dev_ISBNdb_Query($_SESSION['api_key'], 'book', $book_id);
					
					if (!$book_query->hasError()) {
						$book = $book_query->response->data[0];
						
						$authors = array();
						
						foreach ($book->author_data as $author) {
							$author_name = preg_replace('/(.*), (.*)/', '$2 $1', $author->name); // Turns 'Smith, John' into 'John Smith'
							$author_slug = $author->id;
							
							$authors[] = $author_name;
							
							if (!term_exists($author_name, 'wp_lib_authors')) {
								wp_insert_term($author_name, 'wp_lib_authors', array('slug' => $author_slug));
							}
						}
						
						
						
						$item_id = wp_insert_post(array(
							'post_type'		=> 'wp_lib_items',
							'post_title'	=> $book->title,
							'post_name'		=> $book->book_id,
							'post_content'	=> array_pop($_SESSION['item_descriptions']) . ' If you can read this then someone is running the WP-Librarian Dev Kit on a production site. Please shame them on Twitter!',
							'post_status'	=> 'publish',
							'tax_input'		=> array(
								'wp_lib_author'		=> $authors,
								'wp_lib_media_type'	=> 'book'
							)
						));
						
						if (is_wp_error($item_id)) {
							$this->addMessage("Error encountered creating item {$book->title}");
							continue;
						}
						
						add_post_meta($item_id, '_lib_dev_id',			$book->book_id);
						add_post_meta($item_id, 'wp_lib_item_isbn',		$book->isbn13);
						add_post_meta($item_id, 'wp_lib_item_loanable',	true);
					} else {
						if ($book_query->getError() === 'Unable to locate ' . $book_id) {
							$this->addMessage("ISBNdb API couldn't locate '{$book_id}', skipping item");
							continue;
						} else {
							$this->addMessage('Stopping owing to ISBNdb API error: ' . $book_query->getError());
							$this->ajax->stopAjax(1006);
						}
					}
				}
				
				$remaining = count($_SESSION['new_items']);
				
				if ($remaining > 0) {
					$this->addMessage("Created {$i} new fixture item(s). {$remaining} remain");
				} else {
					$this->addMessage("Created {$i} new fixture item(s). No items left to create");
					
					// Next request will move on to the next stage
					++$_SESSION['stage'];
				}
			break;
			
			case 6:
				$this->addMessage("Loan/fine creation will be added soon. That's everything for now!");
				$_SESSION['stage_code'] = false;
			break;
			
			default:
				$ajax_api->stopAjax(1007);
				session_destroy();
			break;
		}
		die();
	}
	
	/**
	 * Adds message to AJAX buffer, to update user on request status after a stage has been completed
	 * @param string $message The message to be sent to the Dashboard
	 */
	private function addMessage($message) {
		$this->message_buffer[] = $message;
	}
	
	/**
	 * Generates a random string that the client-side JS can use to resume a multi-stage process
	 * @return string A 12 character random string like c6cc22bb13c7
	 */
	private function genStageCode() {
		return bin2hex(openssl_random_pseudo_bytes(6));
	}
	
	/**
	 * Loads ISBNdb API v2 key and calls error if key doesn't exist
	 * @return string ISBNdb API v2 key
	 */
	private function getApiKey() {
		$api_key = get_option('lib_dev_api_key', false)[0];
		
		if (!$api_key)
			$ajax_api->stopAjax(1005, $api_key);
		else
			return $api_key;
	}
	
	/**
	 * Closes the Dashboard AJAX request if the ISBNdb API v2 returned an error
	 * @param	Lib_Dev_ISBNdb_Query $query	ISBNdb Query being checked
	 */
	private function checkIsbndbError(Lib_Dev_ISBNdb_Query $query) {
		if ($query->hasError()) {
			$this->addMessage('ISBNdb API Error: ' . $query->getError());
			$this->ajax->stopAjax(1006);
		}
	}
	
	/**
	 * Deletes fixture data, identified as all posts with the post meta 'wp_lib_test_data'
	 * @link	https://github.com/kittsville/WP-Librarian/wiki/wp_lib_dash_api_
	 * @param	WP_LIB_AJAX_API	$ajax_api	Instance of WP-Librarian class for handling Dash API calls
	 */
	public function deleteFixtures(WP_LIB_AJAX_API $ajax_api) {
		$ajax_api->checkNonce('Manage Fixtures');
		
		$this->ajax = $ajax_api;
		
		// Process is broken up into multiple stages, using the session to track progress/data
		session_name('lib_dev_delete_test_data');
		session_start();
		
		if (isset($_POST['stage_code'])) {
			if ($_POST['stage_code'] === $_SESSION['stage_code']) {
				// Generates fresh resume code
				$_SESSION['stage_code'] = $this->genStageCode();
			} else {
				$this->ajax->stopAjax(1007);
			}
		} else {
			$this->addMessage('Starting Fixture Deletion Process (this can take some time)...');
			$_SESSION['stage']			= 0;
			$_SESSION['stage_code']		= $this->genStageCode();
		}
		
		switch ($_SESSION['stage']) {
			// Loads all existing fixtures
			case 0:
				$wp_query = lib_dev_fixtures();
				
				if ($wp_query->have_posts()) {
					$_SESSION['fixtures']			= wp_list_pluck($wp_query->posts, 'ID');
					$_SESSION['fixture_count']		= count($_SESSION['fixtures']);
					$_SESSION['deletion_failed']	= 0;
					
					$this->addMessage("Found {$_SESSION['fixture_count']} fixture(s) to delete");
					
					// Next request will move on to the next stage
					++$_SESSION['stage'];
				} else {
					$this->addMessage('Nothing to delete, no work for me! ^_^');
					
					$_SESSION['stage_code'] = false;
				}
			break;
			
			// Iterates over fixtures, deleting them in batches
			case 1:
				/**
				 * Disables WP-Librarian's integrity constraints
				 * This allows for objects to be deleted while they still have dependant objects (e.g. a loan with a dependant fine)
				 */
				add_filter('wp_lib_bypass_deletion_checks', function() {
					return true;
				});
				
				// Request specific total. Session var is between-requests
				$fixtures_deleted = 0;
				
				for ($i = 0 ; $i < 20; $i++) {
					$fixture_id = array_pop($_SESSION['fixtures']);
					
					// If there are no more fixtures to delete
					if ($fixture_id === null)
						break;
					
					if (!wp_delete_post($fixture_id, true)) {
						$this->addMessage('Failed to delete fixture with ID ' . $fixture_id);
						++$_SESSION['deletion_failed'];
					} else {
						++$fixtures_deleted;
					}
				}
				
				$fixtures_remaining = count($_SESSION['fixtures']);
				
				if ($fixtures_remaining > 0) {
					$this->addMessage("Deleted {$fixtures_deleted} fixture(s). {$fixtures_remaining} remaining");
				} else {
					$fixtures_deleted = $_SESSION['fixture_count'] - $_SESSION['deletion_failed'];
					
					if ($_SESSION['deletion_failed'] > 0)
						$this->addMessage("Deletion partially completed. {$fixtures_deleted} fixture(s) deleted. {$_SESSION['deletion_failed']} failed.");
					else
						$this->addMessage("Deletion completed. All {$fixtures_deleted} fixture(s) have been deleted.");
					
					$_SESSION['stage_code'] = false;
				}
			break;
			
			default:
				$ajax_api->stopAjax(1007);
				session_destroy();
			break;
		}
		die();
	}
	
	/**
	 * Empties message buffer and sends code for client to proceed to next stage
	 */
	public function __destruct() {
		if (isset($this->ajax)) {
			$this->ajax->addContent($_SESSION['stage_code']);
			$this->ajax->addContent($this->message_buffer);
			session_write_close();
		}
	}
}
