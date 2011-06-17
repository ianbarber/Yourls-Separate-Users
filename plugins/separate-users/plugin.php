<?php
/*
Plugin Name: Separate Users
Plugin URI: http://virgingroupdigital.wordpress.com
Description: Allow some filtering of URLs based on the user that created them
Version: 0.2
Author: Ian Barber <ian.barber@gmail.com>
Author URI: http://phpir.com/
*/

// Define the username given full view of the stats 
if(!defined('SEPARATE_USERS_ADMIN_USER')) {
        define("SEPARATE_USERS_ADMIN_USER", 'admin');
}

yourls_add_action( 'insert_link', 'separate_users_insert_link' );
yourls_add_action( 'activated_separate-users/plugin.php', 'separate_users_activated' );
yourls_add_action( 'admin_list_where', 'separate_users_admin_list_where' );
yourls_add_filter( 'is_valid_user', 'separate_users_is_valid_user' );
yourls_add_filter( 'api_url_stats', 'separate_users_api_url_stats' );


/**
 * Activate the plugin, and add the user column to the table if not added
 *
 * @param array $args 
 */
function separate_users_activated($args) {
        global $ydb; 
        
        $table = YOURLS_DB_TABLE_URL;
	$results = $ydb->get_results("DESCRIBE $table");
	$activated = false;
	foreach($results as $r) {
	        if($r->Field == 'user') {
	                $activated = true;
	        }
	}
	if(!$activated) {
		$ydb->query("ALTER TABLE `$table` ADD `user` VARCHAR(255) NULL");
	}
}

/**
 * Filter out URL access which you are not allowed
 *
 * @param array $return 
 * @param string $shorturl 
 * @return array
 */
function separate_users_api_url_stats( $return, $shorturl ) {
        global $ydb; 
        
        $keyword = str_replace( YOURLS_SITE . '/' , '', $shorturl ); // accept either 'http://ozh.in/abc' or 'abc'
	$keyword = yourls_sanitize_string( $keyword );
        $keyword = addslashes($keyword);
        $user = addslashes(YOURLS_USER);
        $table = YOURLS_DB_TABLE_URL;
        
        $result = $ydb->query("SELECT 1 FROM `$table` WHERE  `user` = '" . $user . "' AND `keyword` = '" . $keyword . "'");
        if($result > 0) {
                return $return;
        } else {
                return array('simple' => "URL is owned by another user", 'message' => 'URL is owned by another user', 'errorCode' => 403);
        }
}


/**
 * Restrict users viewing info pages to just those that have the permission
 *
 * @param bool $is_valid 
 * @return bool is_valid
 */
function separate_users_is_valid_user($is_valid) {
        global $keyword, $ydb; 
        
        if(!$is_valid || !defined("YOURLS_INFOS")) {
                return $is_valid;
        }
        
        $user = addslashes(YOURLS_USER);
        $table = YOURLS_DB_TABLE_URL;
        
        if($user == SEPARATE_USERS_ADMIN_USER) {
                return true;
        }
        
        $result = $ydb->query("SELECT 1 FROM `$table` WHERE  `user` = '" . $user . "' AND `keyword` = '" . $keyword . "'");

        return $result > 0 ? true : "Sorry, that URL was created by another user."; 
}

/**
 * Add the user creating a link to the link when creating
 *
 * @param array $actions 
 */
function separate_users_insert_link($actions) {
        global $ydb; 
        
        $keyword = $actions[2];
        $user = addslashes(YOURLS_USER); // this is pretty noddy, could do with some better filtering/checking
        $table = YOURLS_DB_TABLE_URL;
        
        // Insert $keyword against $username
        $result = $ydb->query("UPDATE `$table` SET  `user` = '" . $user . "' WHERE `keyword` = '" . $keyword . "'");
}

/**
 * Filter out the records which do not belong to the user. 
 *
 * @param string $where 
 * @return string
 */
function separate_users_admin_list_where($where) {
        $user = addslashes(YOURLS_USER); 
        if($user == SEPARATE_USERS_ADMIN_USER) {
                return $where; // Allow admin user to see the lot. 
        }
        return $where . " AND (`user` = '$user' OR `user` IS NULL) ";
}