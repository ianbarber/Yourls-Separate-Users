<?php
/*
Plugin Name: Separate Users
Plugin URI: https://github.com/ianbarber/Yourls-Separate-Users
Description: Allow some filtering of URLs based on the user that created them
Version: 1.1.0
Author: Ian Barber <ian.barber@gmail.com>
Author URI: http://phpir.com/
*/
if((yourls_is_active_plugin('authmp/plugin.php')) !== false) {
	die('Seperate Users is depricated to Auth Manager Plus.');
}
/**
 * Set the environment variables
 *
 * @return array 
 */
function seperate_users_env() {

	global $seperate_users_admin_user;
	global $seperate_users_allowed_plugin_pages;

	// have these been set in config.php?
	if ( !isset( $seperate_users_admin_user) ) {
		$seperate_users_admin_user = array('admin');
	}

	if ( !isset( $seperate_users_allowed_plugin_pages) ) {
		$seperate_users_allowed_plugin_pages = array();
	}

	// for other plugins to hook into for inclusion. Hint: array_merge()
	yourls_apply_filter( 'seperate_users_allowed_plugin_pages', $seperate_users_allowed_plugin_pages );

	return array('admins' => $seperate_users_admin_user, 'pages' => $seperate_users_allowed_plugin_pages);
}

/**
 * Activate the plugin, and add the user column to the table if not added
 *
 * @param array $args 
 */
yourls_add_action( 'activated_separate-users/plugin.php', 'separate_users_activated' );
function separate_users_activated($args) {
	global $ydb; 
    
	$table = YOURLS_DB_TABLE_URL;
	$version = version_compare(YOURLS_VERSION, '1.7.3') >= 0;

	if ($version) {
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
		if ($version) {
			$sql = "ALTER TABLE `$table` ADD `user` VARCHAR(255) NULL)";
			$insert = $ydb->fetchAffected($sql);
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
yourls_add_filter( 'api_url_stats', 'separate_users_api_url_stats' );
function separate_users_api_url_stats( $return, $shorturl ) {
	$keyword = str_replace( YOURLS_SITE . '/' , '', $shorturl ); // accept either 'http://ozh.in/abc' or 'abc'
	$keyword = yourls_sanitize_string( $keyword );
	$keyword = $keyword;

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
yourls_add_action( 'pre_yourls_infos', 'separate_users_pre_yourls_infos' );
function separate_users_pre_yourls_infos( $keyword ) {
	if( !separate_users_is_valid($keyword) ) {
		$authenticated = yourls_is_valid_user();
		if ( $authenticated === true ) 
				yourls_redirect( yourls_admin_url( '?access=denied' ), 302 );
			else
				yourls_redirect( YOURLS_SITE, 302 );
	}
}

/**
 * Add the user creating a link to the link when creating
 *
 * @param array $actions 
 */
yourls_add_action( 'insert_link', 'separate_users_insert_link' );
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
yourls_add_filter( 'admin_list_where', 'separate_users_admin_list_where' );
function separate_users_admin_list_where($where) {
	$user = YOURLS_USER; 
	$env = seperate_users_env();

	if(in_array($user, $env['admins'])) {
		return $where; // Allow admin user to see the lot. 
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
yourls_add_filter( 'get_db_stats', 'separate_users_get_db_stats' );
function separate_users_get_db_stats( $return, $where ) {

	$user = YOURLS_USER;
	$env = seperate_users_env();

	if(in_array($user, $env['admins'])) {
		return $return; // Allow admin user to see the lot. 
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
 */
yourls_add_action( 'auth_successful', 'seperate_users_intercept_admin' );
function seperate_users_intercept_admin() {
	// we use this GET param to send up a feedback notice to user
	if ( isset( $_GET['access'] ) && $_GET['access']=='denied' ) {
		yourls_add_notice('Access Denied');
	}
	// only worry about this with HTML draw
	if(!yourls_is_API()) {
		$user = YOURLS_USER; 
		$env = seperate_users_env();
		$admin = in_array($user, $env['admins']) ? true : false;
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
				if(!in_array($action_keyword, $env['pages']) ) {
					yourls_redirect( yourls_admin_url( '?access=denied' ), 302 );
				}
			}
		}	
	}
}

// remove disallowed plugins from link list
yourls_add_filter( 'admin_sublinks', 'separate_users_admin_sublinks' );
function separate_users_admin_sublinks( $links ) {
	$user = YOURLS_USER; 
	$env = seperate_users_env();
	$admin = in_array($user, $env['admins']) ? true : false;
	// restrict access to non-admins - removes from link list
	if( !$admin ) {
		foreach( $links['plugins'] as $link => $ar ) {
			if(!in_array($link, $env['pages']) )
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

	$user = YOURLS_USER;
	$env = seperate_users_env();

	if(in_array($user, $env['admins'])) {
		return true;
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

