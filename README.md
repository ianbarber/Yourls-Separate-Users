A simple YouRLS plug to hide different users information from each other. 

To install, just copy the plugins/separate-users folder to your plugins folder (usually user/plugins). Once activated, any new links created will only be visible in the admin to the user that created them, and the + stats page will also be restricted to only the user that created it. Users cannot create shortcodes the same as one already created by another user. 

If an account named 'admin' is created, it will be able to view all links. The specific name can be changed by defining SEPARATE_USERS_ADMIN_USER in the config, e.g. 

    define ('SEPARATE_USERS_ADMIN_USER', 'tony');

to give the account 'tony' full access.


If you want to define multiple users, here are the steps to follow.

    define('SEPARATE_USERS_ADMIN_USER', serialize(array('tony', 'jack')));

to give the accounts 'tony' and 'jack' full access.


If you want to allow non-admins access to specific plugin pages:

    define('SEPARATE_USERS_ALLOWED_PLUGIN_PAGES', serialize(array('plugin_slug0', 'plugin_slug')));

Please note: currently this plugin is designed mainly to clean up the admin interface for different users, and doesn't guarantee there's no way for one user to influence or access the links of another!  
