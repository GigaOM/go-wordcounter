<?php
/**
 * Plugin Name:	Word Counter
 * Description:	Adds server-side word counter functionality to WordPress.
 * Author:			Gigaom Network
 * Author URI:		http://gigaomnetwork.com/
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

require_once __DIR__ . '/components/class-go-wordcounter.php';
new GO_WordCounter();
