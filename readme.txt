=== Plugin Name ===
Contributors: pkthree
Donate link: http://www.theblog.ca
Tags: login, redirect, admin, administration, dashboard, users, authentication
Requires at least: 2.6.2
Tested up to: 2.8
Stable tag: trunk

Redirect users to different locations after logging in.

== Description ==

Define a set of redirect rules for specific users, users with specific roles, users with specific capabilities, and a blanket rule for all other users. This is all managed in Settings > Login redirects. Version 1.5 and up of this plugin is compatible only with WordPress 2.6.2 and up.

This plugin also includes a function `rul_register` that acts the same as the `wp_register` function you see in templates (typically producing the Register or Site Admin links in the sidebar), except that it will return the custom defined admin address. `rul_register` takes three parameters: the "before" code (by default "&lt;li&gt;"), the "after" code (by default "&lt;/li&gt;"), and whether to echo or return the result (default is `true` and thus echo).

== Installation ==

Unzip wplogin\_redirect.php to your WordPress plugins folder.

Redirect rules are configured in the Settings > Login redirects admin menu.

== Screenshots ==

1. Defining redirect rules per role.

== Frequently Asked Questions ==

Please visit the plugin page at http://www.theblog.ca/wplogin-redirect with any questions.