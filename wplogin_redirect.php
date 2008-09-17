<?php
/*
Plugin Name: Peter's Login Redirect
Plugin URI: http://www.theblog.ca/wplogin-redirect
Description: Redirect users to different locations after logging in. Define a set of rules for specific users, user with specific roles, users with specific capabilities, and a blanket rule for all other users. This is all managed in Settings > Login redirects. Version 1.5 and up of this plugin is compatible only with WordPress 2.6.2 and up.
Author: Peter
Version: 1.5.1
Change Log:
2008-09-17  1.5.1: Fixed compatibility for sites with a different table prefix setting in wp-config.php. (Thanks Eric!) 
Author URI: http://www.theblog.ca
*/

/*
--------------
All settings are configured in Settings > Login redirects in the WordPress admin panel
--------------
*/

global $wpdb;
global $rul_db_addresses;
// Name of the database table that will hold group information and moderator rules
$rul_db_addresses = $wpdb->prefix . 'login_redirects';

// Thanks to http://wordpress.org/support/topic/97314 for this function
// This extra function is necessary to support the use case where someone was previously logged in

function redirect_current_user_can($capability, $current_user) {
    global $wpdb;

    $roles = get_option($wpdb->prefix . 'user_roles');
    $user_roles = $current_user->{$wpdb->prefix . 'capabilities'};
    $user_roles = array_keys($user_roles, true);
    $role = $user_roles[0];
    $capabilities = $roles[$role]['capabilities'];

    if ( in_array( $capability, array_keys( $capabilities, true) ) ) {
        // check array keys of capabilities for match against requested capability
        return true;
    }
    return false;
}

// This function set the URL to redirect to

function redirect_to_front_page( $redirect_to, $requested_redirect_to, $user ) {
    global $wpdb, $rul_db_addresses;

    // If they're on the login page, don't do anything
    if ( !isset ( $user->user_login ) ) {
        return $redirect_to;
    }

    // Check for a redirect rule for this user
    $rul_user = $wpdb->get_var('SELECT rul_url FROM ' . $rul_db_addresses . 
        ' WHERE rul_type = \'user\' AND rul_value = \'' . $user->user_login . '\' LIMIT 1');
    
    if ( $rul_user ) {
        $redirect_to = $rul_user;
        return $redirect_to;
    }

    // Check for a redirect rule that matches this user's role
    $rul_roles = $wpdb->get_results('SELECT rul_value, rul_url FROM ' . $rul_db_addresses . 
        ' WHERE rul_type = \'role\'', OBJECT);
        
    if ( $rul_roles ) {
        foreach ( $rul_roles as $rul_role ) {
            if ( isset ( $user->{$wpdb->prefix . 'capabilities'}[$rul_role->rul_value] ) ) {
                $redirect_to = $rul_role->rul_url;
                return $redirect_to;
            }
        }
    }

    // Check for a redirect rule that matches this user's capability
    $rul_levels = $wpdb->get_results('SELECT rul_value, rul_url FROM ' . $rul_db_addresses . 
        ' WHERE rul_type = \'level\' ORDER BY rul_order, rul_value', OBJECT);
        
    if ( $rul_levels ) {
        foreach ( $rul_levels as $rul_level ) {
            if ( redirect_current_user_can ( $rul_level->rul_value, $user ) ) {
                $redirect_to = $rul_level->rul_url;
                return $redirect_to;
            }
        }
    }
    
    // If none of the above matched, look for a rule to apply to all users    
    $rul_all = $wpdb->get_var('SELECT rul_url FROM ' . $rul_db_addresses . 
        ' WHERE rul_type = \'all\' LIMIT 1');

    if ( $rul_all ) {
        $redirect_to = $rul_all;
        return $redirect_to;
    }
    
    // No rules matched or existed, so just send them to the WordPress admin panel as usual
    return $redirect_to;
    
}

if (is_admin()) {

    // Returns all option HTML for all usernames in the system except for those supplied to it
    function rul_returnusernames($exclude) {
        global $wpdb;

        $rul_returnusernames = '';
        
        // Build the "not in" part of the MySQL query
        $exclude_users = "'" . implode($exclude, "','") . "'";
        
        $rul_userresults = $wpdb->get_results('SELECT user_login FROM ' . $wpdb->users . ' WHERE user_login NOT IN (' . $exclude_users . ') ORDER BY user_login', ARRAY_N);
        
        // Built the option HTML
        if ($rul_userresults) {
            foreach ($rul_userresults as $rul_userresult) {
                $rul_returnusernames .= '                <option value="' . $rul_userresult[0] . '">' . $rul_userresult[0] . '</option>' . "\n";
            }
        }
            
        return $rul_returnusernames;
    }

    // Returns all roles in the system
    function rul_returnrolenames() {
        global $wp_roles;

        $rul_returnrolenames = array();
        foreach (array_keys($wp_roles->role_names) as $rul_rolename) {
            $rul_returnrolenames[$rul_rolename] = $rul_rolename;
        }
        
        return $rul_returnrolenames;   
    }
    
    // Returns option HTML for all roles in the system, except for those supplied to it
    function rul_returnroleoptions($exclude) {
    
        // Relies on a function that just returns the role names
        $rul_rolenames = rul_returnrolenames($exclude);
        
        $rul_returnroleoptions = '';

        // Build the option HTML
        if ($rul_rolenames) {
            foreach ($rul_rolenames as $rul_rolename) {
                if (!isset($exclude[$rul_rolename])) {
                    $rul_returnroleoptions .= '                <option value="' . $rul_rolename . '">' . $rul_rolename . '</option>' . "\n";             
                }
            }
        }
        
        return $rul_returnroleoptions;
    
    }
    
    // Returns all level names in the system
    function rul_returnlevelnames() {
        global $wp_roles;
        
        $rul_returnlevelnames = array();
        
        // Builds the array of level names by combing through each of the roles and listing their levels
        foreach ($wp_roles->roles as $wp_role) {
            $rul_returnlevelnames = array_unique((array_merge($rul_returnlevelnames, array_keys($wp_role['capabilities']))));
        }
        
        // Sort the level names in alphabetical order
        sort($rul_returnlevelnames);
        
        return $rul_returnlevelnames;
        
    }
    
    // Returns option HTML for all levels in the system, except for those supplied to it
    function rul_returnleveloptions($exclude) {
        
        // Relies on a function that just returns the level names
        $rul_levelnames = rul_returnlevelnames();
        
        $rul_returnleveloptions = '';
        
        // Build the option HTML
        foreach ($rul_levelnames as $rul_levelname) {
            if (!isset($exclude[$rul_levelname])) {
                $rul_returnleveloptions .= '                <option value="' . $rul_levelname . '">' . $rul_levelname . '</option>' . "\n";
            }
        }
        
        return $rul_returnleveloptions;
        
    }
    
    // Processes the rule updates per user
    function rul_submit_username($usernames, $addresses) {
        global $wpdb, $rul_db_addresses;
        
        $rul_whitespace = '        ';

        // Open the informational div
        $rul_process_submit = '<div id="message" class="updated fade">' . "\n";
        
        // Code for closing the informational div
        $rul_process_close = $rul_whitespace . '</div>' . "\n";
        
        // ----------------------------------
        // Process the rule changes
        // ----------------------------------

        if($usernames && $addresses) {
            $rul_submit_success = true;
            $rul_usernames_updated = array();
            $rul_username_keys = array_keys($usernames);
            $rul_username_loop = 0;
            
            // Loop through all submitted usernames
            foreach( $usernames as $username ) {
                $i = $rul_username_keys[$rul_username_loop];

                if ( username_exists($username) ) {

                    // Check to see whether it matches the "local URL" test
                    $address = rul_safe_redirect($addresses[$i]);
                
                    if (!$address) {
                        $rul_submit_success = false;
                        $rul_process_submit .= '<p><strong>**** ERROR: Non-local or invalid URL submitted for user "' . $username . '" ****</strong></p>' . "\n";
                    }
                    
                    else {
                        // Update the existing entry or insert a new one
                        $rul_update_username = $wpdb->query('REPLACE INTO ' . $rul_db_addresses . ' SET rul_url = \'' . $address . '\', rul_type = \'user\', rul_value = \'' . $username . '\'');
                        
                        if (!$rul_update_username) {
                            $rul_submit_success = false;
                            $rul_process_submit .= '<p><strong>**** ERROR: Unknown error updating user-specific URL for user "' . $username . '" ****</strong></p>' . "\n";
                        }
                    }
                    
                    // Make a note that we've updated this username
                    $rul_usernames_updated[] = $username;
                }
                elseif ($username != -1) {
                    $rul_submit_success = false;
                    $rul_process_submit .= '<p><strong>**** ERROR: Non-existent username submitted ****</strong></p>' . "\n";
                }
                
                ++$rul_username_loop;
            }
            
            // Prepare the "not in" MySQL code
            $rul_usernames_notin = "'" . implode($rul_usernames_updated, "','") . "'";            
            
            // Delete all username rules in the database that weren't updated (in other words, the user unchecked the box next to it)
            $wpdb->query('DELETE FROM ' . $rul_db_addresses . ' WHERE rul_type = \'user\' AND rul_value NOT IN (' . $rul_usernames_notin . ')');
            
            if ($rul_submit_success) {
                $rul_process_submit .= '<p>Successfully updated user-specific URLs</p>' . "\n";
            }
        }

        // Close the informational div
        $rul_process_submit .= $rul_process_close;
        
        // We've made it this far, so success!
        return $rul_process_submit;
    }

    // Processes the rule updates per role
    function rul_submit_role($roles, $addresses) {
        global $wpdb, $rul_db_addresses;
        
        $rul_whitespace = '        ';

        // Open the informational div
        $rul_process_submit = '<div id="message" class="updated fade">' . "\n";
        
        // Code for closing the informational div
        $rul_process_close = $rul_whitespace . '</div>' . "\n";
        
        // ----------------------------------
        // Process the rule changes
        // ----------------------------------

        if($roles && $addresses) {
            $rul_submit_success = true;
            $rul_roles_updated = array();
            $rul_role_keys = array_keys($roles);
            $rul_role_loop = 0;
            
            // Loop through all submitted roles
            foreach( $roles as $role ) {
                $i = $rul_role_keys[$rul_role_loop];
                
                // Get a list of roles in the system so that we can verify that a valid role was submitted
                $rul_existing_rolenames = rul_returnrolenames();
                if ( isset($rul_existing_rolenames[$role]) ) {

                    // Check to see whether it matches the "local URL" test
                    $address = rul_safe_redirect($addresses[$i]);
                
                    if (!$address) {
                        $rul_submit_success = false;
                        $rul_process_submit .= '<p><strong>**** ERROR: Non-local or invalid URL submitted for role "' . $role . '" ****</strong></p>' . "\n";
                    }
                    
                    else {
                        // Update the existing entry or insert a new one
                        $rul_update_role = $wpdb->query('REPLACE INTO ' . $rul_db_addresses . ' SET rul_url = \'' . $address . '\', rul_type = \'role\', rul_value = \'' . $role . '\'');
                        
                        if (!$rul_update_role) {
                            $rul_submit_success = false;
                            $rul_process_submit .= '<p><strong>**** ERROR: Unknown error updating role-specific URL for role "' . $role . '" ****</strong></p>' . "\n";
                        }
                    }
                    
                    // Make a note that this role name was updated
                    $rul_roles_updated[] = $role;
                }
                elseif ($role != -1) {
                    $rul_submit_success = false;
                    $rul_process_submit .= '<p><strong>**** ERROR: Non-existent role submitted ****</strong></p>' . "\n";
                }
                
                ++$rul_role_loop;
            }
            
            // Built the "not in" MySQL query
            $rul_roles_notin = "'" . implode($rul_roles_updated, "','") . "'";            
            
            // Delete all role rules in the database that weren't updated (in other words, the user unchecked the box next to it)
            $wpdb->query('DELETE FROM ' . $rul_db_addresses . ' WHERE rul_type = \'role\' AND rul_value NOT IN (' . $rul_roles_notin . ')');
            
            if ($rul_submit_success) {
                $rul_process_submit .= '<p>Successfully updated role-specific URLs</p>' . "\n";
            }
        }

        // Close the informational div
        $rul_process_submit .= $rul_process_close;
        
        // We've made it this far, so success!
        return $rul_process_submit;
    }
    
    function rul_submit_level($levels, $orders, $addresses) {
        global $wpdb, $rul_db_addresses;
        
        $rul_whitespace = '        ';

        // Open the informational div
        $rul_process_submit = '<div id="message" class="updated fade">' . "\n";
        
        // Code for closing the informational div
        $rul_process_close = $rul_whitespace . '</div>' . "\n";
        
        // ----------------------------------
        // Process the rule changes
        // ----------------------------------

        if($levels && $addresses) {
            $rul_submit_success = true;
            $rul_levels_updated = array();
            $rul_level_keys = array_keys($levels);
            $rul_level_loop = 0;
            
            // Loop through all submitted levels
            foreach( $levels as $level ) {
                $i = $rul_level_keys[$rul_level_loop];
                
                // Build the array of existing level names
                $rul_existing_levelnames = array_flip(rul_returnlevelnames());
                
                // The order should only be between 0 and 99
                $order = abs(intval($orders[$i]));
                if ($order > 99) {
                    $order = 0;
                }

                if ( isset($rul_existing_levelnames[$level]) ) {

                    // Check to see whether it passes the "local URL" test
                    $address = rul_safe_redirect($addresses[$i]);
                
                    if (!$address) {
                        $rul_submit_success = false;
                        $rul_process_submit .= '<p><strong>**** ERROR: Non-local or invalid URL submitted for level "' . $level . '" ****</strong></p>' . "\n";
                    }
                    
                    else {
                        // Update the existing entry or insert a new one
                        $rul_update_level = $wpdb->query('REPLACE INTO ' . $rul_db_addresses . ' SET rul_url = \'' . $address . '\', rul_type = \'level\', rul_value = \'' . $level . '\', rul_order = ' . $order);
                        
                        if (!$rul_update_level) {
                            $rul_submit_success = false;
                            $rul_process_submit .= '<p><strong>**** ERROR: Unknown error updating level-specific URL for level "' . $level . '" ****</strong></p>' . "\n";
                        }
                    }
                    
                    // Make a note that this level was updated
                    $rul_levels_updated[] = $level;
                }
                elseif ($level != -1) {
                    $rul_submit_success = false;
                    $rul_process_submit .= '<p><strong>**** ERROR: Non-existent level submitted ****</strong></p>' . "\n";
                }
                
                ++$rul_level_loop;
            }
            
            // Build the "not in" MySQL code
            $rul_levels_notin = "'" . implode($rul_levels_updated, "','") . "'";
            
            // Delete all level rules in the database that weren't updated (in other words, the user unchecked the box next to it)
            $wpdb->query('DELETE FROM ' . $rul_db_addresses . ' WHERE rul_type = \'level\' AND rul_value NOT IN (' . $rul_levels_notin . ')');
            
            if ($rul_submit_success) {
                $rul_process_submit .= '<p>Successfully updated level-specific URLs</p>' . "\n";
            }
        }

        // Close the informational div
        $rul_process_submit .= $rul_process_close;
        
        // We've made it this far, so success!
        return $rul_process_submit;
    }
    
    function rul_submit_all($update_or_delete, $address) {
        global $wpdb, $rul_db_addresses;
        
        $rul_whitespace = '        ';

        // Open the informational div
        $rul_process_submit = '<div id="message" class="updated fade">' . "\n";
        
        // Code for closing the informational div
        $rul_process_close = $rul_whitespace . '</div>' . "\n";
        
        // ----------------------------------
        // Process the rule changes
        // ----------------------------------
        
        // Since we never actually, remove the "all" entry, here we just make its value empty
        if($update_or_delete == 'Delete' || $address == '') {
            $update = $wpdb->update (
                $rul_db_addresses,
                array ('rul_url' => '' ),
                array ('rul_type' => 'all')
            );
            
            if ($update === false) {
                $rul_process_submit .= '<p><strong>**** ERROR: Unknown database problem removing URL for "all other users" ****</strong></p>' . "\n";
            }
            else {
                $rul_process_submit .= '<p>Successfully removed URL for "all other users"</p>';
            }
        }
        
        elseif($update_or_delete == 'Update') {
            $address = rul_safe_redirect($address);

            if (!$address) {
                $rul_process_submit .= '<p><strong>**** ERROR: Non-local or invalid URL submitted ****</strong></p>' . "\n";
            }
            
            else {
                $update = $wpdb->update (
                    $rul_db_addresses,
                    array ('rul_url' => $address ),
                    array ('rul_type' => 'all')
                );

                if ($update === false) {
                    $rul_process_submit .= '<p><strong>**** ERROR: Unknown database problem updating URL for "all other users" ****</strong></p>' . "\n";
                }
                else {
                    $rul_process_submit .= '<p>Successfully updated URL for "all other users"</p>' . "\n";
                }
            }
        }

        // Close the informational div
        $rul_process_submit .= $rul_process_close;
        
        // We've made it this far, so success!
        return $rul_process_submit;
    }

    /*
    Stolen fron wp_safe_redirect, which validates the URL
    */

    function rul_safe_redirect($location) {

    	// Need to look at the URL the way it will end up in wp_redirect()
    	$location = wp_sanitize_redirect($location);

    	// browsers will assume 'http' is your protocol, and will obey a redirect to a URL starting with '//'
    	if ( substr($location, 0, 2) == '//' ) {
    		$location = 'http:' . $location;
        }
        
    	// In php 5 parse_url may fail if the URL query part contains http://, bug #38143
    	$test = ( $cut = strpos($location, '?') ) ? substr( $location, 0, $cut ) : $location;

    	$lp  = parse_url($test);
    	$wpp = parse_url(get_option('home'));

    	$allowed_hosts = (array) apply_filters('allowed_redirect_hosts', array($wpp['host']), isset($lp['host']) ? $lp['host'] : '');

    	if ( isset($lp['host']) && ( !in_array($lp['host'], $allowed_hosts) && $lp['host'] != strtolower($wpp['host'])) ) {
    		return false;
        }
        else {
            return $location;
        }

    }
    
    // This is the Settings > Login redirects menu
    function rul_optionsmenu() {
        global $wpdb, $rul_db_addresses;
        
        // Process submitted information to update redirect rules
        if ($_POST['rul_usernamesubmit']) {    
            $rul_process_submit = rul_submit_username($_POST['rul_username'], $_POST['rul_usernameaddress']);
        }
        elseif ($_POST['rul_rolesubmit']) {
            $rul_process_submit = rul_submit_role($_POST['rul_role'], $_POST['rul_roleaddress']);
        }
        elseif ($_POST['rul_levelsubmit']) {
            $rul_process_submit = rul_submit_level($_POST['rul_level'], $_POST['rul_levelorder'], $_POST['rul_leveladdress']);
        }
        elseif ($_POST['rul_allsubmit']) {
            $rul_process_submit = rul_submit_all($_POST['rul_allsubmit'], $_POST['rul_all']);
        }
        
        // -----------------------------------
        // Get the existing rules
        // -----------------------------------
        
        $rul_rules = $wpdb->get_results('SELECT rul_type, rul_value, rul_url, rul_order FROM ' . $rul_db_addresses . ' ORDER BY rul_type, rul_order, rul_value', ARRAY_N);

        if ($rul_rules) {
        
            $i = 0;
            $i_user = 0; $rul_usernames_existing = array();
            $i_role = 0; $rul_roles_existing = array();
            $i_level = 0; $rul_levels_existing = array();
            $rul_usernamevalues = '';
            
            while ($i < count($rul_rules)) {

                list($rul_type, $rul_value, $rul_url, $rul_order) = $rul_rules[$i];

                // Specific users
                if ($rul_type == 'user') {

                    $rul_usernamevalues .= '            <tr>' . "\n";
                    $rul_usernamevalues .= '                <td><p><input type="checkbox" name="rul_username[' . $i_user . ']" value="' . $rul_value . '" checked="checked" /> ' . $rul_value . '</p></td>' . "\n";
                    $rul_usernamevalues .= '                <td><p><input type="text" size="45" maxlength="500" name="rul_usernameaddress[' . $i_user . ']" value="' . $rul_url . '" /></p></td>' . "\n";
                    $rul_usernamevalues .= '            </tr>' . "\n";
                    
                    $rul_usernames_existing[] = $rul_value;
                    
                    ++$i_user;
                    ++$i;
                }
                
                elseif ($rul_type == 'role') {
                
                    $rul_rolevalues .= '            <tr>' . "\n";
                    $rul_rolevalues .= '                <td><p><input type="checkbox" name="rul_role[' . $i_role . ']" value="' . $rul_value . '" checked="checked" /> ' . $rul_value . '</p></td>' . "\n";
                    $rul_rolevalues .= '                <td><p><input type="text" size="45" maxlength="500" name="rul_roleaddress[' . $i_role . ']" value="' . $rul_url . '" /></p></td>' . "\n";
                    $rul_rolevalues .= '            </tr>' . "\n";
                    
                    $rul_roles_existing[$rul_value] = '';
                    
                    ++$i_role;
                    ++$i;
                    
                }
                elseif ($rul_type == 'level') {
                    $rul_levelvalues .= '            <tr>' . "\n";
                    $rul_levelvalues .= '                <td><p><input type="checkbox" name="rul_level[' . $i_level . ']" value="' . $rul_value . '" checked="checked" /> ' . $rul_value . '</p></td>' . "\n";
                    $rul_levelvalues .= '                <td><p><input type="text" size="2" maxlength="2" name="rul_levelorder[' . $i_level . ']" value="' . $rul_order . '" /></p></td>' . "\n";
                    $rul_levelvalues .= '                <td><p><input type="text" size="45" maxlength="500" name="rul_leveladdress[' . $i_level . ']" value="' . $rul_url . '" /></p></td>' . "\n";
                    $rul_levelvalues .= '            </tr>' . "\n";

                    $rul_levels_existing[$rul_value] = '';
                    
                    ++$i_level;
                    ++$i;
                    
                }
                elseif ($rul_type == 'all') {
                    $rul_allvalue = $rul_url;
                    ++$i;
                    
                }
            }

        }
?>
    <div class="wrap">
        <h2>Manage login redirect rules</h2>
        <?php print $rul_process_submit; ?>
        <p>Define different local URLs to which different users, users with specific roles, users with specific levels, and all other users will be redirected.</p>

        <h3>Specific users</h3>
        <form name="rul_usernameform" action="<?php print $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__); ?>" method="post">
        <table class="widefat">
            <tr>
                <th>Username</th>
                <th>URL</th>
            </tr>
<?php print $rul_usernamevalues; ?>
            
        </table>
        <p>Add: 
            <select name="rul_username[<?php print $i_user; ?>]" >
                <option value="-1">Select a username</option>
<?php print rul_returnusernames($rul_usernames_existing); ?>
            </select>
            <br />URL: <input type="text" size="45" maxlength="500" name="rul_usernameaddress[<?php print $i_user; ?>]" />
        </p>
        <p class="submit"><input type="submit" name="rul_usernamesubmit" value="Update" /></p>
        </form>
            
        <h3>Specific roles</h3>
        <form name="rul_roleform" action="<?php print $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__); ?>" method="post">
        <table class="widefat">
            <tr>
                <th>Role</th>
                <th>URL</th>
            </tr>
            <?php print $rul_rolevalues; ?>
            
        </table>
        <p>Add: 
            <select name="rul_role[<?php print $i_role; ?>]" >
                <option value="-1">Select a role</option>
<?php print rul_returnroleoptions($rul_roles_existing); ?>
            </select>
            <br />URL: <input type="text" size="45" maxlength="500" name="rul_roleaddress[<?php print $i_role; ?>]" />
        </p>
        <p class="submit"><input type="submit" name="rul_rolesubmit" value="Update" /></p>
        </form> 
 
        <h3>Specific levels</h3>
        <form name="rul_levelform" action="<?php print $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__); ?>" method="post">
        <table class="widefat">
            <tr>
                <th>Level</th>
                <th>Order</th>
                <th>URL</th>
            </tr>
            <?php print $rul_levelvalues; ?>
            
        </table>
        <p>Add: 
            <select name="rul_level[<?php print $i_level; ?>]" >
                <option value="-1">Select a level</option>
<?php print rul_returnleveloptions($rul_levels_existing); ?>
            </select>
            <br />Order: <input type="text" size="2" maxlength="2" name="rul_levelorder[<?php print $i_level; ?>]" />
            <br />URL: <input type="text" size="45" maxlength="500" name="rul_leveladdress[<?php print $i_level; ?>]" />
        </p>
        <p class="submit"><input type="submit" name="rul_levelsubmit" value="Update" /></p>
        </form> 
        
        <h3>All other users</h3>
        <form name="rul_allform" action="<?php print $_SERVER['PHP_SELF'] . '?page=' . basename(__FILE__); ?>" method="post">
        <p>URL: <input type="text" size="45" maxlength="500" name="rul_all" value="<?php print $rul_allvalue; ?>" /></p>
        <p class="submit"><input type="submit" name="rul_allsubmit" value="Update" /> <input type="submit" name="rul_allsubmit" value="Delete" /></p>
        </form>
    </div>
<?php
    }
    
    // Add and remove database tables when installing and uninstalling

    function rul_install () {
    global $wpdb, $rul_db_addresses;
    
    // Add the table to hold group information and moderator rules
    if($wpdb->get_var('SHOW TABLES LIKE \'' . $rul_db_addresses . '\'') != $rul_db_addresses) {
        $sql = 'CREATE TABLE ' . $rul_db_addresses . ' (
        `rul_type` enum(\'user\',\'role\',\'level\',\'all\') NOT NULL,
        `rul_value` varchar(255) NOT NULL,
        `rul_url` longtext NOT NULL,
        `rul_order` int(2) NOT NULL,
        UNIQUE KEY `rul_type` (`rul_type`,`rul_value`)
        )';

      	$wpdb->query($sql);
        
        // Insert the "all" redirect entry
        $wpdb->insert($rul_db_addresses,
            array('rul_type' => 'all')
        );
	}
}

function rul_uninstall () {
    global $wpdb, $rul_db_addresses;
    
    // Remove the table we created
    if($wpdb->get_var('SHOW TABLES LIKE \'' . $rul_db_addresses . '\'') == $rul_db_addresses) {
        $sql = 'DROP TABLE ' . $rul_db_addresses;
		$wpdb->query($sql);
	}
}

    function rul_addoptionsmenu() {
    	add_options_page('Login redirects', 'Login redirects', 7, 'wplogin_redirect.php', 'rul_optionsmenu');
    }

    add_action('admin_menu','rul_addoptionsmenu',1);
}

register_activation_hook( __FILE__, 'rul_install' );
register_deactivation_hook( __FILE__, 'rul_uninstall' );
add_filter('login_redirect', 'redirect_to_front_page', 10, 3);
?>