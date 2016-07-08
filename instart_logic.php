<?php
/**
 * @package Instart
 */
/*
Plugin Name: Instart-Logic
Plugin URI: http://instartlogic.com/
Description: Instart Logic integration plugin. This enables the purge/invalidation of cached URLs in the Instart Logic Service in response to different site events.
Version: 1.0.0
Author: Ferlito/van der Wyk
Author URI: http://pfvdw.com
License: MIT
*/

defined('ABSPATH') or die('Access denied.');

require_once('instart_logic_options.php');
require_once('instart_logic.api.inc');

add_action( 'admin_menu', 'instart_logic_add_admin_menu' );
add_action( 'admin_init', 'instart_logic_settings_init' );
add_action( 'admin_notices', 'instart_logic_show_purge_message');
add_action( 'save_post', 'instart_logic_purge_current_url' );

?>