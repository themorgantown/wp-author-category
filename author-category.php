<?php
/*
Plugin Name: Author Category (LFI version)
Plugin URI: https://github.com/lafranceinsoumise/wp-author-category
Description: Simple plugin to limit categories authors can post to.
Version: 0.9.0
Author: La France insoumise
Author URI: https://github.com/lafranceinsoumise/
Text Domain: author_cat
Domain Path: /languages
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/
/*
        *   Copyright (C) 2012 - 2013 Ohad Raz <admin@bainternet.info> (http://en.bainternet.info)
        *   Copyright (C) 2020 La France insoumise <site@lafranceinsoumise.fr> (https://github.com/lafranceinsoumise)

        This program is free software; you can redistribute it and/or modify
        it under the terms of the GNU General Public License as published by
        the Free Software Foundation; either version 2 of the License, or
        (at your option) any later version.

        This program is distributed in the hope that it will be useful,
        but WITHOUT ANY WARRANTY; without even the implied warranty of
        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
        GNU General Public License for more details.

        You should have received a copy of the GNU General Public License
        along with this program; if not, write to the Free Software
        Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* Disallow direct access to the plugin file */
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'author_category' ) ) {
    class author_category
    {
        /**
         * $txtDomain
         *
         * Holds textDomain
         * @var string
         */
        public $txtDomain = 'author_cat';

        public $user_cats = array();

        /**
         * get_user_cats
         *
         * @param null $user_id
         * @return array|mixed
         */
        public function get_user_cats($user_id = null)
        {
            if ($user_id === null) {
                $current_user = wp_get_current_user();
                $user_id = $current_user->ID;
            }

            $cats = get_user_meta($user_id, '_author_cat', true);

            if (!is_array($cats) || empty($cats)) {
                return array();
            } else {
                return $cats;
            }
        }

        public function __construct()
        {
            add_action( 'init', function () {
                $this->user_cats = $this->get_user_cats();
            } );

            add_action( 'init', array( $this, 'load_translation' ) );

            // For site administrators
            if ( is_admin() ) {
                // add Author Categories section on users edit page
                add_action( 'show_user_profile', array( $this, 'extra_user_profile_fields' ) );
                add_action( 'edit_user_profile', array( $this, 'extra_user_profile_fields' ) );
                add_action( 'personal_options_update', array( $this, 'save_extra_user_profile_fields' ) );
                add_action( 'edit_user_profile_update', array( $this, 'save_extra_user_profile_fields' ) );

                // our js removes unauthorized categories from Gutenber setting panels
                add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_js' ) );

                // remove Yoast seo primary category picker (fucks with our js)
                if ( function_exists( 'wpseo_primary_term_taxonomies' ) ) {
                    add_filter( 'wpseo_primary_term_taxonomies', array( $this, 'remove_yoast' ) );
                }

                // remove unauthorized categories at save time
                add_action( 'save_post_post', array( $this, 'remove_unauthorized_categories' ), 50, 2 );

                //add metabox
                add_action( 'quick_edit_show_taxonomy', array( $this, 'remove_quick_edit' ), 0, 2 );
                add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
                add_action( 'save_post_post', array( $this, 'save_meta_box_categories' ), 10, 2 );
            }

            // Add property to rest api result for guntenberg to know which to hide
            add_action( 'rest_api_init', array( $this, 'rest_api_add_author_category_field' ) );


            //add_filter('pre_option_default_category', array($this, 'user_default_category_option'));

            //xmlrpc post insert hook and quickpress
            add_filter( 'xmlrpc_wp_insert_post_data', array( $this, 'xmlrpc_default_category' ), 2 );

            //post by email add category
            add_action( 'publish_phone', array( $this, 'post_by_email_default_category' ) );
        }

        public function enqueue_js()
        {
            if ( empty( $this->user_cats ) ) {
                return;
            }

            $manifest_path = plugin_dir_path(__FILE__) . '/js/dist/manifest.json';
            if ( ! file_exists( $manifest_path ) ) {
                return;
            }

            $manifest_content = file_get_contents( $manifest_path );
            if ( false === $manifest_content ) {
                return;
            }

            $assets = json_decode( $manifest_content, true );

            if ( ! is_array( $assets ) || ! isset( $assets['main.js'] ) ) {
                return;
            }

            $script_relative_path = 'js/dist/' . $assets['main.js'];
            $script_path          = plugin_dir_path( __FILE__ ) . $script_relative_path;
            $script_version       = file_exists( $script_path ) ? (string) filemtime( $script_path ) : null;

            wp_enqueue_script(
                'author-category',
                plugins_url( $script_relative_path, __FILE__ ),
                array( 'wp-edit-post', 'wp-editor', 'wp-compose', 'wp-api-fetch', 'wp-url', 'wp-hooks', 'wp-components', 'wp-element' ),
                $script_version,
                true
            );
        }

        /**
         * remove Yoast primary taxonomy picker for category only
         *
         * @param array $all_taxonomies
         * @return array
         */
        public function remove_yoast($all_taxonomies)
        {
            // Defensive: wpseo_primary_term_taxonomies may change or be removed
            if ( ! function_exists( 'wpseo_primary_term_taxonomies' ) || empty( $this->user_cats ) ) {
                return $all_taxonomies;
            }

            // Only remove the category taxonomy, preserve others
            return array_filter( $all_taxonomies, function ( $taxonomy ) {
                if ( is_string( $taxonomy ) ) {
                    return $taxonomy !== 'category';
                }

                return ! isset( $taxonomy->taxonomy ) || $taxonomy->taxonomy !== 'category';
            } );
        }

        /**
         * remove unauthorized categories after saving
         *
         * @param int   $post_id
         * @param object $post
         */
        public function remove_unauthorized_categories($post_id, $post)
        {
            // Skip autosaves, revisions, and non-post types
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
                return;
            }

            // Only process if the current user is the post author
            if (get_current_user_id() !== (int) $post->post_author) {
                return;
            }

            if (empty($this->user_cats)) {
                return;
            }

            $unfiltered_cats = wp_get_post_categories($post_id);
            $authorized_cats = array_intersect($unfiltered_cats, $this->get_user_cats());

            if (!empty(array_diff($unfiltered_cats, $authorized_cats))) {
                wp_set_post_categories($post_id, $authorized_cats, false);
            }
        }

        /**
         * Adds a author_category field on rest api to be used by Gutenberg
         */
        public function rest_api_add_author_category_field()
        {
            if ( empty( $this->user_cats ) ) {
                return;
            }

            register_rest_field( 'category', 'author_category', array(
                'get_callback' => function ( $term ) {
                    // Only expose to authenticated users who can edit posts
                    if ( ! current_user_can( 'edit_posts' ) ) {
                        return false;
                    }
                    return in_array( (int) $term['id'], $this->get_user_cats(), true );
                },
                'schema' => array(
                    'description' => __( 'Whether the category is authorized for the current author.', $this->txtDomain ),
                    'type'        => 'boolean',
                    'context'     => array( 'view', 'edit' ),
                ),
            ) );
        }

        /**
         * xmlrpc_default_category
         *
         * function to handle XMLRPC calls
         *
         * @param  array $post_data   post data
         * @param  array $content_struct xmlrpc post data
         * @return array
         */
        public function xmlrpc_default_category($post_data, $content_struct)
        {
            if (!empty($this->user_cats)) {
                // XML-RPC uses different key structures depending on the client
                if (isset($post_data['tax_input']['category'])) {
                    // Merge with existing tax_input, keeping only authorized cats
                    $post_data['tax_input']['category'] = $this->user_cats;
                } elseif (isset($post_data['post_category'])) {
                    $post_data['post_category'] = $this->user_cats;
                } else {
                    $post_data['post_category'] = $this->user_cats;
                }
            }

            return $post_data;
        }

        /**
         * post_by_email_default_category
         *
         * @param  int $post_id
         */
        public function post_by_email_default_category($post_id)
        {
            if (!empty($this->user_cats)) {
                // Verify the post belongs to the current user
                $post = get_post($post_id);
                if (!$post || get_current_user_id() !== (int) $post->post_author) {
                    return;
                }

                $email_post = array(
                    'ID'            => $post_id,
                    'post_category' => $this->user_cats,
                );
                // Prevent infinite loop by removing this filter temporarily
                remove_filter('publish_phone', array($this, 'post_by_email_default_category'));
                wp_update_post($email_post);
                add_filter('publish_phone', array($this, 'post_by_email_default_category'));
            }
        }

        /**
         * remove_quick_edit
         *
         * @return bool
         */
        public function remove_quick_edit($show, $taxonomy_name)
        {
            if ( 'category' === $taxonomy_name && ! empty( $this->user_cats ) ) {
                return false;
            }

            return $show;
        }

        /**
         * Adds the meta box container
         */
        public function add_meta_box()
        {
            if (!empty($this->user_cats)) {
                //remove default metabox
                remove_meta_box('categorydiv', 'post', 'side');
                //add user specific categories
                add_meta_box(
                    'author_cat',
                    __('Author category', $this->txtDomain),
                    array( &$this, 'render_meta_box_content' ),
                    'post',
                    'side',
                    'low',
                    array(
                        '__back_compat_meta_box' => true,
                    )
                );
            }
        }


        /**
         * Save metabox categories with nonce verification
         *
         * @param int    $post_id
         * @param object $post
         */
        public function save_meta_box_categories($post_id, $post)
        {
            // Skip autosaves and revisions
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
                return;
            }

            if (!isset($_POST['author_cat_noncename']) || !wp_verify_nonce(wp_unslash($_POST['author_cat_noncename']), plugin_basename(__FILE__))) {
                return;
            }

            if (!current_user_can('edit_post', $post_id)) {
                return;
            }

            // Verify the post belongs to the current user
            if (get_current_user_id() !== (int) $post->post_author) {
                return;
            }

            // Check user has authorized categories
            if (empty($this->user_cats)) {
                return;
            }

            // Only set categories if submitted via metabox
            if (isset($_POST['post_category']) && is_array($_POST['post_category'])) {
                $submitted_cats = array_map('intval', wp_unslash($_POST['post_category']));
                // Only allow authorized categories
                $authorized_cats = array_intersect($submitted_cats, $this->user_cats);
                wp_set_post_categories($post_id, $authorized_cats, false);
            }
        }

        /**
         * Render Meta Box content
         */
        public function render_meta_box_content()
        {
            $cats = $this->get_user_cats();
            $selected_cats = array();

            $post_id = get_the_ID();
            if ( $post_id ) {
                $selected_cats = array_map( 'intval', wp_get_post_categories( $post_id ) );
            }

            wp_nonce_field(plugin_basename(__FILE__), 'author_cat_noncename');

            if (count($cats) == 1) {
                $c = get_category($cats[0]);
                if (!$c) {
                    return;
                }
                printf(
                    '<p>%1$s <strong>%2$s</strong> %3$s</p>',
                    esc_html__( 'This will be posted in:', $this->txtDomain ),
                    esc_html( $c->name ),
                    esc_html__( 'category.', $this->txtDomain )
                );
                echo '<input name="post_category[]" type="hidden" value="' . esc_attr($c->term_id) . '">';
            } else {
                echo '<span>' . esc_html__('Make sure you select only the categories you want:', $this->txtDomain) . '</span><br />';

                foreach ($cats as $cat) {
                    $c = get_category($cat);
                    if (!$c) {
                        continue;
                    }
                    printf(
                        '<label><input name="post_category[]" type="checkbox" value="%1$s" %2$s> %3$s</label><br />',
                        esc_attr( $c->term_id ),
                        checked( in_array( (int) $c->term_id, $selected_cats, true ), true, false ),
                        esc_html( $c->name )
                    );
                }
            }
        }

        /**
         * This will generate the category field on the users profile
         */
        public function extra_user_profile_fields($user)
        {
            // only admin can see and save the categories
            if (!current_user_can('manage_options')) {
                return;
            }

            $select = wp_dropdown_categories(array(
                            'orderby'      => 'name',
                            'show_count'   => 0,
                            'hierarchical' => 1,
                            'hide_empty'   => 0,
                            'echo'         => 0,
                            'name'         => 'author_cat[]'));
            $saved = get_user_meta($user->ID, '_author_cat', true);
            foreach ((array)$saved as $c) {
                $select = str_replace('value="'.$c.'"', 'value="'.$c.'" selected="selected"', $select);
            }
            $select = str_replace('<select', '<select multiple="multiple"', $select); ?>
            <h3><?php echo esc_html__( 'Author Category', $this->txtDomain ); ?></h3>
            <table class="form-table" role="presentation">
                <tr id="author_cat">
                    <th><label for="author_cat"><?php echo esc_html__( 'Category', $this->txtDomain ); ?></label></th>
                    <td>
                        <?php echo $select; ?>
                        <p class="description">
                            <?php echo esc_html__( 'Select one or more categories to limit an author to those categories only. Use Ctrl or Cmd to select more than one.', $this->txtDomain ); ?>
                        </p>
                    </td>
                </tr>
                <tr id="author_cat_clear">
                    <th><label for="author_cat_clear"><?php echo esc_html__( 'Clear Category', $this->txtDomain ); ?></label></th>
                    <td>
                        <p class="description">
                            <input type="checkbox" name="author_cat_clear" id="author_cat_clear" value="1" />
                            <?php echo esc_html__( 'Check this to remove category restrictions for this user.', $this->txtDomain ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php
        }


        /**
         * This will save category field on the users profile
         *
         * @param  int $user_id
         * @return bool
         */
        public function save_extra_user_profile_fields($user_id)
        {
            //only admin can see and save the categories
            if (!current_user_can('manage_options') || !current_user_can('edit_user', $user_id)) {
                return false;
            }

            if (!isset($_POST['author_cat']) || !is_array($_POST['author_cat'])) {
                // No categories submitted - clear the limitation
                delete_user_meta($user_id, '_author_cat');
                return true;
            }

            $author_cat = array_map('intval', wp_unslash($_POST['author_cat']));

            // Validate all categories exist
            foreach ($author_cat as $cat) {
                if (!term_exists($cat, 'category')) {
                    return false;
                }
            }

            if (isset($_POST['author_cat_clear']) && '1' === wp_unslash($_POST['author_cat_clear'])) {
                delete_user_meta($user_id, '_author_cat');
            } else {
                update_user_meta($user_id, '_author_cat', $author_cat);
            }

            return true;
        }

        /**
         * Loads translations
         */
        public function load_translation()
        {
            load_plugin_textdomain($this->txtDomain, false, dirname(plugin_basename(__FILE__)) . '/languages/');
        }
    }
}

new author_category();
