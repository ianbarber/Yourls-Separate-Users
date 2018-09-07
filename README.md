YOURLS-Seperate-Users
=====================

A simple YouRLS plug to hide different users information from each other. 

Features
--------
-  Links created by an authenticated user will only be visible in the admin area to that user (also `+` stats pages)
-  If public, links created by non-authenticated users will be accessible to all users in the admin area (also `+` stats pages)
-  Configurable `admin` account is able to access all links (but cannot yet see what user created them)
-  Main plugin management page is restricted to admin users
-  Individual plugin pages are restricted to admin users by default. Can be overridden by variable
-  Filter allows other plugins to make individual plugin pages visible to all users

Installation
------------
Copy the `separate-users` folder into the `/path/to/YOURLS/user/plugins/` folder. Activate in the admin area.

Configuration
-------------
To allow a user named Tony admin access, add the following to `config.php`:
```
$seperate_users_admin_user = array(
	'Tony',
	'admin'
);
```
To allow the page for the `Sample Page` plugin included in YOURLS to be accessible to all users:
```
$seperate_users_allowed_plugin_pages = array(
	'sample_page',
	'some_other_plugin'
);
```

Hooking into the filter
-----------------------
To allow a plugin with a plugin page slug of `my_page_slug` to be visible by default, add something like this to your code:
```
yourls_add_filter('seperate_users_allowed_plugin_pages', 'my_snappy_function' );
function my_snappy_function( $allowed_pages ) {

	$allowed_pages[] = 'my_page_slug';

	return $allowed_pages;
}

```
#### Please note: 
Currently this plugin is designed mainly to clean up the admin interface for different users, and doesn't guarantee there's no way for one user to influence or access the links of another!  
