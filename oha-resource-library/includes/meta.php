<?php
if ( ! defined('ABSPATH') ) { exit; }

add_action( 'add_meta_boxes', function() {
    add_meta_box( 'oha_rl_embed',   'Video / Embed URL',        'oha_rl_embed_render',   'oha_resource', 'side', 'high' );
    add_meta_box( 'oha_rl_private', 'Private File (Members Only)', 'oha_rl_private_render', 'oha_resource', 'side', 'default' );
} );

function oha_rl_embed_render( $post ) {
    wp_nonce_field( 'oha_rl_embed_save', 'oha_rl_embed_nonce' );
    $url = get_post_meta( $post->ID, '_oha_embed_url', true );
    echo '<p style="margin:0 0 6px;"><label for="oha_embed_url">YouTube or Vimeo URL</label></p>';
    echo '<input type="url" name="oha_embed_url" id="oha_embed_url" value="' . esc_attr( $url ) . '" style="width:100%;" placeholder="https://youtube.com/watch?v=..." />';
    echo '<p class="description" style="margin-top:6px;">When set, members see an embedded player instead of a download button. Set Resource Type to Video or Meeting Recording.</p>';
}

function oha_rl_private_render( $post ) {
    wp_nonce_field( 'oha_rl_private_save', 'oha_rl_private_nonce' );
    $path = get_post_meta( $post->ID, '_oha_private_path', true );
    $name = get_post_meta( $post->ID, '_oha_private_name', true );
    if ( $path && file_exists( $path ) ) {
        echo '<p><strong>Current file:</strong><br>' . esc_html( $name ? $name : basename( $path ) ) . '</p>';
        echo '<p><em>Stored privately; not publicly accessible.</em></p>';
    } else {
        echo '<p>No private file uploaded yet.</p>';
    }
    echo '<p><label for="oha_private_upload">Upload/Replace</label><br/><input type="file" name="oha_private_upload" id="oha_private_upload" /></p>';
}

add_action( 'save_post_oha_resource', function( $post_id ) {
    // Save embed URL
    if ( isset( $_POST['oha_rl_embed_nonce'] ) && wp_verify_nonce( $_POST['oha_rl_embed_nonce'], 'oha_rl_embed_save' ) ) {
        if ( ! ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) && current_user_can( 'edit_post', $post_id ) ) {
            $url = isset( $_POST['oha_embed_url'] ) ? esc_url_raw( wp_unslash( $_POST['oha_embed_url'] ) ) : '';
            update_post_meta( $post_id, '_oha_embed_url', $url );
        }
    }

    // Save private file
    if ( isset( $_POST['oha_rl_private_nonce'] ) && wp_verify_nonce( $_POST['oha_rl_private_nonce'], 'oha_rl_private_save' ) ) {
        if ( ! ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) && current_user_can( 'edit_post', $post_id ) ) {
            if ( ! empty( $_FILES['oha_private_upload']['name'] ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $uploaded = wp_handle_upload( $_FILES['oha_private_upload'], array( 'test_form' => false ) );
                if ( empty( $uploaded['error'] ) ) {
                    $base = oha_rl_get_files_dir();
                    if ( ! file_exists( $base ) ) wp_mkdir_p( $base );
                    $dest = trailingslashit( $base ) . wp_unique_filename( $base, basename( $uploaded['file'] ) );
                    rename( $uploaded['file'], $dest );
                    update_post_meta( $post_id, '_oha_private_path', $dest );
                    update_post_meta( $post_id, '_oha_private_name', basename( $_FILES['oha_private_upload']['name'] ) );
                }
            }
        }
    }
} );
