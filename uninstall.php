<?php
/**
 * Uninstall routine — runs when the plugin is deleted (not just deactivated).
 *
 * Removes all plugin Option and User Meta. Follows WordPress best practices
 * for plugin cleanup so no orphaned data remains after deletion.
 *
 * @see https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 */

defined( 'ABSPATH' ) || exit;

// Remove the plugin's site options.
delete_option( 'author_cat_option' );

// Remove per-user category restrictions.
delete_metadata(
	'user',
	0,
	'_author_cat',
	'',
	true
);
