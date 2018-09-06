<?php
/*
Plugin Name: Separate Users
Plugin URI: https://github.com/ianbarber/Yourls-Separate-Users
Description: Allow some filtering of URLs based on the user that created them
Version: 0.4.2
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

if(!defined('SEPARATE_USERS_ALLOWED_PLUGIN_PAGES')) {
	define('SEPARATE_USERS_ALLOWED_PLUGIN_PAGES', serialize(array(null)));
}
yourls_add_action( 'insert_link', 'separate_users_insert_link' );
yourls_add_action( 'activated_separate-users/plugin.php', 'separate_users_activated' );
yourls_add_action( 'auth_successful', 'seperate_users_intercept_admin' );
yourls_add_filter( 'admin_list_where', 'separate_users_admin_list_where' );
yourls_add_filter( 'is_valid_user', 'separate_users_is_valid_user' );
yourls_add_filter( 'api_url_stats', 'separate_users_api_url_stats' );
yourls_add_filter( 'get_db_stats', 'separate_users_get_db_stats' );
yourls_add_filter( 'admin_sublinks', 'separate_users_admin_sublinks' );

/**
 * Activate the plugin, and add the user column to the table if not added
 *
 * @param array $args 
 */
function separate_users_activated($args) {
	global $ydb; 
        
	$table = YOURLS_DB_TABLE_URL;

	if (version_compare(YOURLS_VERSION, '1.7.3') >= 0) {
		$sql = "DESCRIBE `$table`";
		$results = $ydb->fetchObjects($sql);
	} else {
		$results = $ydb->get_results("DESCRIBE $table");
	}

	$activated = false;
	foreach($results as $r) {
		if($r->Field == 'user') {
			$activated = true;
		}
	}
	if(!$activated) {

		if (version_compare(YOURLS_VERSION, '1.7.3') >= 0) {
			$binds = null;
			$sql = "ALTER TABLE `$table` ADD `user` VARCHAR(255) NULL)";
			$insert = $ydb->fetchAffected($sql,$binds);

		} else {

			$ydb->query("ALTER TABLE `$table` ADD `user` VARCHAR(255) NULL");

		}
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
	if (version_compare(YOURLS_VERSION, '1.7.3') >= 0) {
		$binds = array( 'user' => $user,
						'keyword' => $keyword);
		$sql = "UPDATE `$table` SET  `user` = :user WHERE `keyword` = :keyword";
		$result = $ydb->fetchAffected($sql, $binds);
	} else {
		$result = $ydb->query("UPDATE `$table` SET  `user` = $user WHERE `keyword` = $keyword");
	}
}

/**
 * Filter out the records which do not belong to the user. 
 *
 * @param string $where 
 * @return string
 */
function separate_users_admin_list_where($where) {
	$user = YOURLS_USER; 
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

	if (version_compare(YOURLS_VERSION, '1.7.3') >= 0) {
		$where['sql'] = $where['sql'] . " AND (`user` = :user OR `user` IS NULL) ";
		$where['binds']['user'] = $user;
	}
	else
		$where = $where . " AND (`user` = $user OR `user` IS NULL) ";

	return $where;
}

/**
 * Filter out db stats to user access lvl
 *
 * @param array $return
 * @param array $where
 * @return array 
 */
function separate_users_get_db_stats( $return, $where ) {

	$user = YOURLS_USER; 

	// admin check
	if(!is_serialized(SEPARATE_USERS_ADMIN_USER)) { 
		if($user == SEPARATE_USERS_ADMIN_USER) {
			return $return; // Allow admin user to see the lot. 
		}
	} else { 
		$SEPARATE_USERS_ADMIN_USER = unserialize(SEPARATE_USERS_ADMIN_USER);
		if(in_array($user, $SEPARATE_USERS_ADMIN_USER)) {
			return $return; // Allow admin user to see the lot. 
		}
	}

	// filter results
	global $ydb;
	$table_url = YOURLS_DB_TABLE_URL;

	if (version_compare(YOURLS_VERSION, '1.7.3') >= 0) {

		$where['sql'] = $where['sql'] . " AND (`user` = :user OR `user` IS NULL) ";
		$where['binds']['user'] = $user;

		$sql = "SELECT COUNT(keyword) as count, SUM(clicks) as sum FROM `$table_url` WHERE 1=1 " . $where['sql'];
		$binds = $where['binds'];

		$totals = $ydb->fetchObject($sql, $binds);

	} else {

		$where = $where . " AND (`user` = $user OR `user` IS NULL) ";
		$totals = $ydb->get_results("SELECT COUNT(keyword) as count, SUM(clicks) as sum FROM `$table_url` WHERE 1=1 " . $where );
	}

	$return = array( 'total_links' => $totals->count, 'total_clicks' => $totals->sum );

	return $return;

}

/**
 * Restricting Access to Plugin Administration(s) for non-admins
 *
 *  
 *
 *
 */
function seperate_users_intercept_admin() {
	// we use this GET param to send up a feedback notice to user
    if ( isset( $_GET['access'] ) && $_GET['access']=='denied' ) {
    	yourls_add_notice('Access Denied');
    }

	// only worry about this with HTML draw
	if(!yourls_is_API()) {
		$user = YOURLS_USER; 
		// admin check
		$admin = false;
		if(!is_serialized(SEPARATE_USERS_ADMIN_USER)) { 
			if($user == SEPARATE_USERS_ADMIN_USER)
				$admin = true;
		} else { 
			$SEPARATE_USERS_ADMIN_USER = unserialize(SEPARATE_USERS_ADMIN_USER);
			if(in_array($user, $SEPARATE_USERS_ADMIN_USER))
				$admin = true;
		}

		// restrict access to plugin mgmt non-admins
		if( !$admin ) {
			// intercept requests for global plugin management page
			if( isset( $_SERVER['REQUEST_URI'] ) && $_SERVER['REQUEST_URI'] == '/admin/plugins.php' ||
				// intercept requests for plugin management 
				isset( $_REQUEST['plugin'] ) ) {
				yourls_redirect( yourls_admin_url( '?access=denied' ), 302 );
			}
			// intercept requests for individual plugin management pages
			if ( isset( $_REQUEST['page'] ) ) {
				$action_keyword = $_REQUEST['page'];
				$allowed = unserialize(SEPARATE_USERS_ALLOWED_PLUGIN_PAGES);
				if(!in_array($link, $allowed) ) {
					yourls_redirect( yourls_admin_url( '?access=denied' ), 302 );
				}
			}
		}	
	}
}
// remove disallowed plugins from link list
function separate_users_admin_sublinks( $links ) {
	$user = YOURLS_USER; 
	// admin check
	$admin = false;
	if(!is_serialized(SEPARATE_USERS_ADMIN_USER)) { 
		if($user == SEPARATE_USERS_ADMIN_USER)
			$admin = true;
	} else { 
		$SEPARATE_USERS_ADMIN_USER = unserialize(SEPARATE_USERS_ADMIN_USER);
		if(in_array($user, $SEPARATE_USERS_ADMIN_USER))
			$admin = true;
	}

	// restrict access to non-admins - removes from link list
	if( !$admin ) {

		$allowed = unserialize(SEPARATE_USERS_ALLOWED_PLUGIN_PAGES);
		foreach( $links['plugins'] as $link => $ar ) {
			if(!in_array($link, $allowed) )
				unset($links['plugins'][$link]);
		}
	}

	sort($links['plugins']);
	return $links;
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

	if (version_compare(YOURLS_VERSION, '1.7.3') >= 0) {
		$binds = array( 'keyword' => $keyword, 'user' => $user);
		$sql = "SELECT 1 FROM `$table` WHERE  (`user` IS NULL OR `user` = :user) AND `keyword` = :keyword";
		$result = $ydb->fetchAffected($sql, $binds);
	} else
    	$result = $ydb->query("SELECT 1 FROM `$table` WHERE  (`user` IS NULL OR `user` = $user) AND `keyword` = $keyword");

	return $result > 0;
}
