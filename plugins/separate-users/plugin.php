<?php
/*
Plugin Name: Separate Users
Plugin URI: http://virgingroupdigital.wordpress.com
Description: Allow some filtering of URLs based on the user that created them
Version: 0.3
Author: Ian Barber <ian.barber@gmail.com>
Author URI: http://phpir.com/
*/

// Check if serialization is used
function is_serialized($data) {
	// if it isn't a string, it isn't serialized
	if(!is_string($data))
		return false;
		$data = trim($data);
	if('N;' == $data)
		return true;
	if(!preg_match( '/^([adObis]):/', $data, $badions))
		return false;
		switch($badions[1]) {
		case 'a' :
		case 'O' :
		case 's' :
	if(preg_match( "/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data))
		return true;
		break;
		case 'b' :
		case 'i' :
		case 'd' :
	if(preg_match( "/^{$badions[1]}:[0-9.E-]+;\$/", $data))
		return true;
		break;
	}
	return false;
}

// Define the username given full view of the stats 
if(!defined('SEPARATE_USERS_ADMIN_USER')) {
	define('SEPARATE_USERS_ADMIN_USER', serialize(array("admin")));
}

yourls_add_action( 'insert_link', 'separate_users_insert_link' );
yourls_add_action( 'activated_separate-users/plugin.php', 'separate_users_activated' );
yourls_add_filter( 'admin_list_where', 'separate_users_admin_list_where' );
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
        $keyword = str_replace( YOURLS_SITE . '/' , '', $shorturl ); // accept either 'http://ozh.in/abc' or 'abc'
	$keyword = yourls_sanitize_string( $keyword );
        $keyword = addslashes($keyword);
        
        if(separate_users_is_valid($keyword)) {
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
        global $keyword; 
        
        if(!$is_valid || !defined("YOURLS_INFOS")) {
                return $is_valid;
        }

        return separate_users_is_valid($keyword) ? true : "Sorry, that URL was created by another user."; 
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
		if(!is_serialized(SEPARATE_USERS_ADMIN_USER)) { 
			if($user == SEPARATE_USERS_ADMIN_USER) {
				return $where; // Allow admin user to see the lot. 
			}
		} else { 
			$SEPARATE_USERS_ADMIN_USER = unserialize(SEPARATE_USERS_ADMIN_USER);
				if(in_array($user, $SEPARATE_USERS_ADMIN_USER)) {
					return $where; // Allow admin user to see the lot. 
				}
		}

        return $where . " AND (`user` = '$user' OR `user` IS NULL) ";
}

/**
 * Internal module function for testing user access to a keyword
 *
 * @param string $user 
 * @param string $keyword 
 * @return boolean
 */
function separate_users_is_valid( $keyword ) {
        global $ydb; 
        
        $user = addslashes(YOURLS_USER);
		if(!is_serialized(SEPARATE_USERS_ADMIN_USER)) { 
			if($user == SEPARATE_USERS_ADMIN_USER) {
				return true;
			}
		} else { 
			$SEPARATE_USERS_ADMIN_USER = unserialize(SEPARATE_USERS_ADMIN_USER);
			if(in_array($user, $SEPARATE_USERS_ADMIN_USER)) {
				return true;
			}
		}        
       
        $table = YOURLS_DB_TABLE_URL;
        $result = $ydb->query("SELECT 1 FROM `$table` WHERE  (`user` IS NULL OR `user` = '" . $user . "') AND `keyword` = '" . $keyword . "'");
        return $result > 0;
}
