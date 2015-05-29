<?php
// No direct loading
defined( 'ABSPATH' ) OR die('No');

/**
 * Queries for all fixture posts (items/members/loans/fines)
 * @return WP_Query Query for all fixture data
 */
function lib_dev_fixtures() {
	return new WP_Query(array(
		'post_type'		=> array('wp_lib_items', 'wp_lib_members', 'wp_lib_loans', 'wp_lib_fines'),
		'nopaging'		=> true,
		'meta_query'	=> array(
			array(
				'key'		=> '_lib_dev_id',
				'compare'	=> 'EXISTS'
			)
		)
	));
}
