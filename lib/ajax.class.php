<?php
// No direct loading
defined('ABSPATH') OR die('No');

/**
 * Handles Library Dashboard AJAX pages and page elements
 */
class LIB_FIX_AJAX {
	/**
	 * Instance of core plugin class
	 */
	private $wp_librarian_fixtures;
	
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
	 * @param	WP_LIBRARIAN_FIXTURES	$wp_librarian_fixtures	Instance of core plugin class
	 */
	public function __construct(WP_LIBRARIAN_FIXTURES $wp_librarian_fixtures) {
		$this->wp_librarian_fixtures = $wp_librarian_fixtures;
		
		$this->registerHooks();
	}
	
	/**
	 * Registers Dashboard AJAX hooks to add Fixtures pages and actions to WP-Librarian
	 */
	public function registerHooks() {
		add_filter('wp_lib_dash_home_buttons',				array($this,	'addTestDataButton'),		10, 2);
		
		add_action('wp_lib_dash_page_test-data',			array($this,	'addTestDataPage'),			10, 2);
		
		add_action('wp_lib_dash_api_generate-fixtures',		array($this,	'genTestData'),				10, 1);
		add_action('wp_lib_dash_api_delete-fixtures',		array($this,	'deleteTestData'),			10,	1);
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
		
		$form = array(
			$ajax_page->prepNonce('Managing Test Data'),
			array(
				'type'	=> 'button',
				'link'	=> 'none',
				'id'	=> 'gen-test-data',
				'html'	=> 'Generate',
				'title'	=> 'Create fixture data'
			)
		);
		
		if (lib_fix_fixtures()->have_posts()) {
			$form[] = array(
				'type'		=> 'button',
				'link'		=> 'none',
				'id'		=> 'delete-test-data',
				'classes'	=> 'dash-button-danger',
				'html'		=> 'Delete',
				'title'		=> 'Delete all fixture data'
			);
		}
		
		$page[] = array(
			'type'		=> 'form',
			'content'	=> $form
		);
		
		$ajax_page->sendPage('Fixture Management Panel', 'Fixture Management', $page, array($this->wp_librarian_fixtures->getScriptUrl('dashboard')));
	}
	
	/**
	 * Parses fixtures data and creates items, members, loans and fines with them
	 * @link	https://github.com/kittsville/WP-Librarian/wiki/wp_lib_dash_api_
	 * @param	WP_LIB_AJAX_API	$ajax_api	Instance of WP-Librarian class for handling Dash API calls
	 */
	public function genTestData(WP_LIB_AJAX_API $ajax_api) {
		$ajax_api->checkNonce('Managing Test Data');
		
		$this->ajax = $ajax_api;
		
		// Process is broken up into multiple stages, using the session to track progress/data
		session_name('lib_fix_gen_test_data');
		session_start();
		
		if (isset($_POST['stage_code'])) {
			if ($_POST['stage_code'] === $_SESSION['stage_code']) {
				// Generates fresh resume code
				$_SESSION['stage_code'] = $this->genStageCode();
			} else {
				$this->ajax->stopAjax(1007);
			}
		} else {
			$this->addMessage('Starting Fixture Creation Process...');
			$_SESSION['stage']			= 0;
			$_SESSION['stage_code']		= $this->genStageCode();
			$_SESSION['item_count']		= isset($_POST['item_count']) ? max((int) $_POST['item_count'], 0) : 40;
			$_SESSION['member_count']	= isset($_POST['member_count']) ? max((int) $_POST['member_count'], 0) : 10;
			$this->addMessage("Will attempt to generate {$_SESSION['item_count']} item(s) and {$_SESSION['member_count']} member(s)");
		}
		
		switch ($_SESSION['stage']) {
			// Loads existing fixtures in library
			case 0:
				$wp_query = lib_fix_fixtures();
			
				// Creates an array of all existing fixtures in the library
				$_SESSION['existing_items']		= array();
				$_SESSION['existing_members']	= array();
				if ( $wp_query->have_posts() ){
					while ( $wp_query->have_posts() ) {
						$wp_query->the_post();
						
						switch(get_post_type()) {
							case 'wp_lib_items':
								$_SESSION['existing_items'][get_post_meta(get_the_ID(), '_lib_fix_id', true)] = get_the_ID();
							break;
							
							case 'wp_lib_members':
								$_SESSION['existing_members'][get_post_meta(get_the_ID(), '_lib_fix_id', true)] = get_the_ID();
							break;
						}
					}
				}
				
				$this->addMessage('Loaded existing item/member fixtures');
				
				// Next request will move on to the next stage
				++$_SESSION['stage'];
			break;
			
			// Creates requested number of fixture members
			case 1:
				if (true/*$_SESSION['member_count'] === 0*/) { // GGGGG
					$this->addMessage('No fixture members to create');
					++$_SESSION['stage'];
					break;
				}
				
				$member_json = @file_get_contents($this->wp_librarian_fixtures->plugin_path . '/fixtures/members.json', 'r') or $ajax_api->stopAjax(1002);
				
				$members = json_decode($member_json);
				
				if ($members === null)
					$ajax_api->stopAjax(1003);
				
				$members_added = 0;
				
				foreach ($members as $id => $member) {
					if ($members_added >= $_SESSION['member_count'])
						break;
					
					if (!isset($_SESSION['existing_members'][$id])) {
						$member_id = wp_insert_post(array(
							'post_type'		=> 'wp_lib_members',
							'post_title'	=> $member->Name,
							'post_status'	=> 'publish'
						));
						
						if (is_wp_error($member_id)) {
							$this->addMessage("Error encountered creating member {$member->Name}");
							continue;
						}
						
						add_post_meta($member_id, '_lib_fix_id',			$id);
						add_post_meta($member_id, 'wp_lib_member_phone',	$member->Phone);
						add_post_meta($member_id, 'wp_lib_member_mobile',	$member->Mobile);
						add_post_meta($member_id, 'wp_lib_member_email',	$member->Email);
						
						++$members_added;
					} else {
						continue;
					}
				}
				
				// Checks if loop ran out of fixtures before enough members had been created
				if (!($members_added >= $_SESSION['member_count']))
					$ajax_api->stopAjax(1004);
				
				$this->addMessage("Created {$members_added} members");
				
				// Next request will move on to the next stage
				++$_SESSION['stage'];
			break;
			
			// Locates requested number of fixture items
			case 2:
				if ($_SESSION['item_count'] === 0) {
					$this->addMessage('No fixture items to create');
					break;
				}
				
				$_SESSION['api_key'] = $this->getApiKey();
				
				$this->wp_librarian_fixtures->loadClass('isbndb-api');
				
				$publisher_query = new LIB_FIX_ISBNDB_QUERY($_SESSION['api_key'], 'publisher', 'doubleday');
				
				$this->checkIsbndbError($publisher_query);
				
				$_SESSION['new_items']	= array();
				$new_book_count			= 0;
				
				/**
				 * Book_id is a unique slug used by ISBNDB, not WP-Librarian's item_id which is a unique integer
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
			
			// Prepares to get fixture data
			case 3:
				$_SESSION['item_descriptions'] = explode("\n\n", file_get_contents('http://loripsum.net/api/' . $_SESSION['item_count'] . '/plaintext'));
				
				// Last element is blank because of newline parsing
				array_pop($_SESSION['item_descriptions']);
				
				// Makes sure the media type 'Book' exists
				if (!term_exists('Book', 'wp_lib_media_type'))
					wp_insert_term('Book', 'wp_lib_media_type');
				
				// Next request will move on to the next stage
				++$_SESSION['stage'];
			break;
			
			// Creates fixture data in batches of 10 items per iteration
			case 4:
				$this->wp_librarian_fixtures->loadClass('isbndb-api');
				
				for ($i = 0 ; $i < 10; $i++) {
					$book_id = array_pop($_SESSION['new_items']);
					
					// No more books to process
					if ($book_id === null)
						break;
					
					// Loads book title/authors/ISBN13 from ISBNDB API
					$book_query = new LIB_FIX_ISBNDB_QUERY($_SESSION['api_key'], 'book', $book_id);
					
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
							'post_content'	=> array_pop($_SESSION['item_descriptions']),
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
						
						add_post_meta($item_id, '_lib_fix_id',			$book->book_id);
						add_post_meta($item_id, 'wp_lib_item_isbn',		$book->isbn13);
						add_post_meta($item_id, 'wp_lib_item_loanable',	true);
					} else {
						if ($book_query->getError() === 'Unable to locate ' . $book_id) {
							$this->addMessage("ISBNDB API couldn't locate '{$book_id}', skipping item");
							continue;
						} else {
							$this->addMessage('Stopping owing to ISBNDB API error: ' . $book_query->getError());
							$this->ajax->stopAjax(1006);
						}
					}
				}
				
				$remaining = count($_SESSION['new_items']);
				
				if ($remaining > 0) {
					$this->addMessage("Created {$i} new fixture item(s). {$remaining} remain");
				} else {
					$this->addMessage("Created {$i} new fixture item(s). No fixture left to create");
					
					// Next request will move on to the next stage
					++$_SESSION['stage'];
				}
			break;
			
			case 5:
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
	 * Loads ISBNDB API v2 key and calls error if key doesn't exist
	 * @return string ISBNDB API v2 key
	 */
	private function getApiKey() {
		$api_key = get_option('wp_libfix_api_key', false)[0];
		
		if (!$api_key)
			$ajax_api->stopAjax(1005, $api_key);
		else
			return $api_key;
	}
	
	/**
	 * Closes the Dashboard AJAX request if the ISBNDB API v2 returned an error
	 * @param	LIB_FIX_ISBNDB_QUERY $query	ISBNDB Query being checked
	 */
	private function checkIsbndbError(LIB_FIX_ISBNDB_QUERY $query) {
		if ($query->hasError()) {
			$this->addMessage('ISBNDB API Error: ' . $query->getError());
			$this->ajax->stopAjax(1006);
		}
	}
	
	/**
	 * Deletes fixture data, identified as all posts with the post meta 'wp_lib_test_data'
	 * @link	https://github.com/kittsville/WP-Librarian/wiki/wp_lib_dash_api_
	 * @param	WP_LIB_AJAX_API	$ajax_api	Instance of WP-Librarian class for handling Dash API calls
	 */
	public function deleteTestData(WP_LIB_AJAX_API $ajax_api) {
		$ajax_api->addNotification('Placeholder for deleting test data');
		$ajax_api->endAction(false);
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