<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OHA_Site_PWA_Metabox {

    private static $_instance = null;

    public static function instance() {
        if ( null === self::$_instance ) self::$_instance = new self();
        return self::$_instance;
    }

    public function init() {
        add_action( 'add_meta_boxes',                    array( $this, 'register' ) );
        add_action( 'save_post',                         array( $this, 'save_meta' ), 10, 2 );
        add_action( 'wp_ajax_oha_spwa_send_post_push',   array( $this, 'ajax_send' ) );
    }

    public function register() {
        foreach ( array( 'post', 'page' ) as $type ) {
            add_meta_box(
                'oha-spwa-push',
                'Push Notification',
                array( $this, 'render' ),
                $type,
                'side',
                'default'
            );
        }
    }

    public function render( $post ) {
        wp_nonce_field( 'oha_spwa_metabox_' . $post->ID, 'oha_spwa_metabox_nonce' );

        $is_published  = ( $post->post_status === 'publish' );
        $last_sent     = get_post_meta( $post->ID, '_oha_spwa_push_sent', true );
        $saved_title   = get_post_meta( $post->ID, '_oha_spwa_push_title', true );
        $saved_message = get_post_meta( $post->ID, '_oha_spwa_push_message', true );
        $saved_url     = get_post_meta( $post->ID, '_oha_spwa_push_url', true );
        $on_publish    = get_post_meta( $post->ID, '_oha_spwa_push_on_publish', true );

        $default_title = $saved_title ?: $post->post_title;
        $default_url   = $saved_url   ?: ( $is_published ? get_permalink( $post->ID ) : '' );
        ?>
        <style>
        #oha-spwa-push .oha-mb-field{margin-bottom:10px}
        #oha-spwa-push .oha-mb-field label{display:block;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:#646970;margin-bottom:3px}
        #oha-spwa-push .oha-mb-field input,
        #oha-spwa-push .oha-mb-field textarea{width:100%;box-sizing:border-box;font-size:12px}
        #oha-spwa-push .oha-mb-field textarea{resize:vertical;min-height:52px}
        #oha-spwa-push .oha-mb-last-sent{font-size:11px;color:#8c8f94;margin-bottom:10px;padding:5px 7px;background:#f6f7f7;border-radius:3px}
        #oha-spwa-push .oha-mb-result{font-size:12px;margin-top:8px;padding:6px 8px;border-radius:3px;display:none}
        #oha-spwa-push .oha-mb-result.success{background:#d4edda;color:#155724;display:block}
        #oha-spwa-push .oha-mb-result.error{background:#f8d7da;color:#721c24;display:block}
        #oha-spwa-push .oha-mb-send-btn{width:100%;margin-top:4px}
        </style>

        <?php if ( $last_sent ) : ?>
        <div class="oha-mb-last-sent">Last sent: <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_sent ) ) ); ?></div>
        <?php endif; ?>

        <div class="oha-mb-field">
            <label for="oha_mb_title_<?php echo $post->ID; ?>">Title</label>
            <input type="text" id="oha_mb_title_<?php echo $post->ID; ?>" name="oha_spwa_push_title" value="<?php echo esc_attr( $default_title ); ?>" maxlength="100">
        </div>
        <div class="oha-mb-field">
            <label for="oha_mb_message_<?php echo $post->ID; ?>">Message</label>
            <textarea id="oha_mb_message_<?php echo $post->ID; ?>" name="oha_spwa_push_message" maxlength="200"><?php echo esc_textarea( $saved_message ); ?></textarea>
        </div>
        <div class="oha-mb-field">
            <label for="oha_mb_url_<?php echo $post->ID; ?>">Link URL</label>
            <input type="url" id="oha_mb_url_<?php echo $post->ID; ?>" name="oha_spwa_push_url" value="<?php echo esc_attr( $default_url ); ?>">
        </div>

        <?php if ( ! $is_published ) : ?>
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer">
            <input type="checkbox" name="oha_spwa_push_on_publish" value="1" <?php checked( $on_publish, '1' ); ?>>
            Send when published
        </label>
        <?php else : ?>
        <button type="button" class="button oha-mb-send-btn"
                id="oha-mb-send-<?php echo $post->ID; ?>"
                data-post="<?php echo $post->ID; ?>"
                data-nonce="<?php echo esc_attr( wp_create_nonce( 'oha_spwa_post_push_' . $post->ID ) ); ?>">
            Send Notification Now
        </button>
        <div class="oha-mb-result" id="oha-mb-result-<?php echo $post->ID; ?>"></div>
        <script>
        (function(){
            var btn = document.getElementById('oha-mb-send-<?php echo $post->ID; ?>');
            var res = document.getElementById('oha-mb-result-<?php echo $post->ID; ?>');
            if (!btn) return;
            btn.addEventListener('click', function(){
                var title   = document.getElementById('oha_mb_title_<?php echo $post->ID; ?>').value.trim();
                var message = document.getElementById('oha_mb_message_<?php echo $post->ID; ?>').value.trim();
                var url     = document.getElementById('oha_mb_url_<?php echo $post->ID; ?>').value.trim();
                res.className = 'oha-mb-result';
                if (!title || !message) {
                    res.className += ' error';
                    res.textContent = 'Title and message are required.';
                    return;
                }
                btn.disabled = true;
                btn.textContent = 'Sending...';
                var fd = new FormData();
                fd.append('action',   'oha_spwa_send_post_push');
                fd.append('nonce',    btn.dataset.nonce);
                fd.append('post_id',  btn.dataset.post);
                fd.append('title',    title);
                fd.append('message',  message);
                fd.append('url',      url);
                fetch(ajaxurl, { method: 'POST', body: fd })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        btn.disabled = false;
                        btn.textContent = 'Send Notification Now';
                        if (data.success) {
                            res.className = 'oha-mb-result success';
                            res.textContent = 'Sent to ' + (data.data.recipients || 0) + ' subscriber' + (data.data.recipients === 1 ? '' : 's') + '.';
                        } else {
                            res.className = 'oha-mb-result error';
                            res.textContent = data.data || 'Send failed.';
                        }
                    })
                    .catch(function(){
                        btn.disabled = false;
                        btn.textContent = 'Send Notification Now';
                        res.className = 'oha-mb-result error';
                        res.textContent = 'Network error. Please try again.';
                    });
            });
        })();
        </script>
        <?php endif; ?>
        <?php
    }

    public function save_meta( $post_id, $post ) {
        if ( ! isset( $_POST['oha_spwa_metabox_nonce'] ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oha_spwa_metabox_nonce'] ) ), 'oha_spwa_metabox_' . $post_id ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $title   = sanitize_text_field( wp_unslash( $_POST['oha_spwa_push_title']   ?? '' ) );
        $message = sanitize_text_field( wp_unslash( $_POST['oha_spwa_push_message'] ?? '' ) );
        $url     = esc_url_raw( wp_unslash( $_POST['oha_spwa_push_url'] ?? '' ) );

        update_post_meta( $post_id, '_oha_spwa_push_title',   $title );
        update_post_meta( $post_id, '_oha_spwa_push_message', $message );
        update_post_meta( $post_id, '_oha_spwa_push_url',     $url );

        $on_publish = isset( $_POST['oha_spwa_push_on_publish'] );
        if ( $on_publish ) {
            update_post_meta( $post_id, '_oha_spwa_push_on_publish', '1' );
        } else {
            delete_post_meta( $post_id, '_oha_spwa_push_on_publish' );
        }

        // Fire on publish: works for both classic editor and block editor meta box iframe.
        // Condition: post is now published, checkbox was checked, notification not yet sent.
        if ( $on_publish &&
             $post->post_status === 'publish' &&
             $title && $message &&
             ! get_post_meta( $post_id, '_oha_spwa_push_sent', true )
        ) {
            $result = OHA_Site_PWA_OneSignal::instance()->send( $title, $message, $url );
            if ( ! is_wp_error( $result ) ) {
                update_post_meta( $post_id, '_oha_spwa_push_sent', current_time( 'mysql' ) );
                delete_post_meta( $post_id, '_oha_spwa_push_on_publish' );
            }
        }
    }

    public function ajax_send() {
        $post_id = absint( $_POST['post_id'] ?? 0 );

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'oha_spwa_post_push_' . $post_id ) ) {
            wp_send_json_error( 'Invalid security token.' );
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( 'Access denied.' );
        }

        $title   = sanitize_text_field( wp_unslash( $_POST['title']   ?? '' ) );
        $message = sanitize_text_field( wp_unslash( $_POST['message'] ?? '' ) );
        $url     = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );

        if ( ! $title || ! $message ) {
            wp_send_json_error( 'Title and message are required.' );
        }

        // Keep the meta box values up to date with whatever was in the fields
        update_post_meta( $post_id, '_oha_spwa_push_title',   $title );
        update_post_meta( $post_id, '_oha_spwa_push_message', $message );
        update_post_meta( $post_id, '_oha_spwa_push_url',     $url );

        $result = OHA_Site_PWA_OneSignal::instance()->send( $title, $message, $url );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        update_post_meta( $post_id, '_oha_spwa_push_sent', current_time( 'mysql' ) );
        wp_send_json_success( array( 'recipients' => isset( $result['recipients'] ) ? (int) $result['recipients'] : 0 ) );
    }
}
