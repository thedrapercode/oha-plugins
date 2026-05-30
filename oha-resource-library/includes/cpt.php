<?php
if ( ! defined('ABSPATH') ) { exit; }

function oha_rl_register_cpt() {
    register_post_type( 'oha_resource', array(
        'labels'       => array( 'name' => 'Resources', 'singular_name' => 'Resource' ),
        'public'       => true,
        'has_archive'  => true,
        'rewrite'      => array( 'slug' => 'resources' ),
        'menu_icon'    => 'dashicons-portfolio',
        'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author' ),
        'show_in_rest' => true,
    ) );

    register_taxonomy( 'audience', 'oha_resource', array(
        'hierarchical'  => true,
        'label'         => 'Audience',
        'rewrite'       => array( 'slug' => 'audience' ),
        'show_in_rest'  => true,
    ) );

    register_taxonomy( 'resource_type', 'oha_resource', array(
        'hierarchical'  => false,
        'label'         => 'Resource Type',
        'rewrite'       => array( 'slug' => 'resource-type' ),
        'show_in_rest'  => true,
    ) );

    $audience_defaults = array(
        array( 'name' => 'Haunt Owners',  'slug' => 'haunt-owners' ),
        array( 'name' => 'Actors',        'slug' => 'actors' ),
        array( 'name' => 'Haunt Staff',   'slug' => 'haunt-staff' ),
        array( 'name' => 'Vendors',       'slug' => 'vendors' ),
        array( 'name' => 'Sponsors',      'slug' => 'sponsors' ),
    );
    foreach ( $audience_defaults as $d ) {
        if ( ! term_exists( $d['slug'], 'audience' ) ) {
            wp_insert_term( $d['name'], 'audience', array( 'slug' => $d['slug'] ) );
        }
    }

    $type_defaults = array(
        array( 'name' => 'Document',          'slug' => 'document' ),
        array( 'name' => 'Tutorial',          'slug' => 'tutorial' ),
        array( 'name' => 'Video',             'slug' => 'video' ),
        array( 'name' => 'Meeting Recording', 'slug' => 'meeting-recording' ),
        array( 'name' => 'Template',          'slug' => 'template' ),
    );
    foreach ( $type_defaults as $d ) {
        if ( ! term_exists( $d['slug'], 'resource_type' ) ) {
            wp_insert_term( $d['name'], 'resource_type', array( 'slug' => $d['slug'] ) );
        }
    }
}
add_action( 'init', 'oha_rl_register_cpt' );
