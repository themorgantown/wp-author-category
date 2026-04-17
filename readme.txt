=== Author Category ===
Contributors: jllro, bainternet, themorgantown
Tags: author category, limit author to category, author posts to category
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 0.9.0

Simple lightweight plugin to limit authors to post in specific categories only. Compatible with Gutenberg.

== Description ==

This plugin allows you to select specific categories per user. All of that user's posts will be posted in those categories only.


**Main Features:**

*   Only admins can set authorized categories for each user.
*   Only users with specified categories will be limited; others retain full control.
*   Removes unauthorized categories for selected users.
*   Removes categories from quick edit for selected users.
*   Option to clear selection.
*   Multiple categories per user.

French translation (since 0.8) thanks to jyd44

== Frequently Asked Questions ==

= How To Use =

Simply login as the admin user and under each user profile, select the category for that user.

== Changelog ==
 = 0.9.0 =
 Finalized the security hardening pass for post category restrictions.
 Restored real nonce verification for the custom metabox save flow.
 Fixed category updates to replace unauthorized categories instead of appending to them.
 Hardened category loading to avoid non-array warnings on newer PHP versions.
 Improved Yoast SEO compatibility by preserving non-category taxonomies regardless of callback payload shape.
 Added function-existence guard for Yoast SEO filter so the plugin no longer fails if Yoast is absent or updated.
 Added proper uninstall handler to clean up all plugin options and user meta on deletion.
 Rebuilt JavaScript assets with updated non-vulnerable dependencies (lodash 4.18.1, webpack 5.106.2).
 Synchronized plugin version metadata with the published readme.

 = 0.8.1 =
 Fixed critical bug where unauthorized categories were never removed from saved posts.
 Added missing JavaScript dependencies (wp-hooks, wp-components, wp-element).
 Added security checks to prevent unauthorized category manipulation.
 Fixed Yoast SEO filter to only affect category taxonomy, not all taxonomies.
 Added nonce verification and proper sanitization to metabox save handler.
 Added autosave and revision skip logic to category removal.
 Fixed XML-RPC handler to support multiple key structures.
 Prevented infinite loop in post-by-email category handler.
 Added error handling for missing manifest.json file.
 Modernized deprecated PHP patterns.
 Removed unused Composer dependencies.

 = 0.8 = 
Added POT file for translations.
Added french translation.
Fixed translation loading to an earlest time to allow panel translation.

 = 0.7 =
updated simple panel version.
added textdomain to plugin and to option panel.
wrapped checkboxes with labels
categoires are now ordered by name.

 = 0.6 =
Fixed xmlrpc posting issue.
Added an option panel to allow configuration of multiple categories.
added An action hook `in_author_category_metabox`

 = 0.5 = 
Added post by mail category limitation.

 = 0.4 = 
Added support for multiple categories per user.
added option to remove user selected category.

 = 0.3 =  
added plugin links,
added XMLRPC and Quickpress support
changed category save function from save_post to default input_tax field.
added a function to overwrite default category option per user.

 = 0.2 = 
Fixed admin profile update issue.

 = 0.1 = 
initial release