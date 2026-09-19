<?php
if (!defined('ABSPATH')) exit;

class OHA_Awards_CPT {
    public static function init() {
        add_action('init', [__CLASS__,'register_cpt']);
    }

    public static function register_cpt() {
        $labels = [
            'name'               => 'Award Entries',
            'singular_name'      => 'Award Entry',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Award Entry',
            'edit_item'          => 'Edit Award Entry',
            'new_item'           => 'New Award Entry',
            'view_item'          => 'View Award Entry',
            'search_items'       => 'Search Award Entries',
            'not_found'          => 'No entries found',
            'not_found_in_trash' => 'No entries found in Trash',
            'menu_name'          => 'Awards'
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'supports'           => ['title','author'],
            'has_archive'        => false,
            'rewrite'            => false,
        ];

        register_post_type('oha_award_entry', $args);
    }
}
