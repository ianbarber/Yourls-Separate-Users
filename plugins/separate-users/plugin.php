<?php
/*
Plugin Name: Separate Users
Plugin URI: http://virgingroupdigital.wordpress.com
Description: Allow some filtering of URLs based on the user that created them
Version: 0.1
Author: Ian Barber <ian.barber@gmail.com>
Author URI: http://phpir.com/
*/

yourls_add_action( 'insert_link', 'separate_users_insert_link' );
yourls_add_action( 'activated_separate-users/plugin.php', 'separate_users_activated' );
yourls_add_action( 'admin_list_where', 'separate_users_admin_list_where' );

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
        return $where . " AND (`user` = '$user' OR `user` IS NULL) ";
}