<?php
/**
 * Plugin Name: WP-Librarian Test Data Generator
 * Description: Generates data for WP-Librarian to be tested on
 * Version: 0.0.1
 * For WP-Librarian: Alpha v3 (Badger Claw)
 * Author: Kit Maywood
 * Author URI: https://github.com/kittsville
 * License: GPL2
 */

/*
	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

require_once (dirname( __FILE__ ) . '/lib/wp-librarian-test.class.php');

new WP_LIBRARIAN_TEST;
