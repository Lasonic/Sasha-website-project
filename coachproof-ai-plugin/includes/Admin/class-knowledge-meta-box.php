<?php
/**
 * Knowledge Document – Meta Box
 *
 * Provides the file-upload UI and sync controls on the post edit screen.
 *
 * @package CoachProofAI\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Coachproof_Knowledge_Meta_Box {

    /**
     * Hook into WordPress.
     */
    public static function init(): void {
        add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_boxes' ] );
        add_action( 'save_post_' . Coachproof_Knowledge_Post_Type::POST_TYPE, [ __CLASS__, 'save_meta' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
    }

    /**
     * Register the meta boxes.
     */
    public static function register_meta_boxes(): void {
        add_meta_box(
            'coachproof_kb_file',
            'Document File',
            [ __CLASS__, 'render_file_meta_box' ],
            Coachproof_Knowledge_Post_Type::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'coachproof_kb_sync',
            'OpenAI Sync Status',
            [ __CLASS__, 'render_sync_meta_box' ],
            Coachproof_Knowledge_Post_Type::POST_TYPE,
            'side',
            'high'
        );
    }

    /**
     * Render the file upload meta box.
     *
     * @param WP_Post $post
     */
    public static function render_file_meta_box( WP_Post $post ): void {
        wp_nonce_field( 'coachproof_kb_file_nonce', '_coachproof_kb_nonce' );

        $attachment_id = (int) get_post_meta( $post->ID, '_coachproof_attachment_id', true );
        $filename      = '';
        $file_url      = '';

        if ( $attachment_id ) {
            $filename = basename( get_attached_file( $attachment_id ) ?: '' );
            $file_url = wp_get_attachment_url( $attachment_id );
        }

        $allowed = implode( ', ', array_map( function( $ext ) { return '.' . $ext; }, Coachproof_Knowledge_Post_Type::ALLOWED_EXTENSIONS ) );
        ?>
        <div class="coachproof-file-upload">
            <input type="hidden"
                   id="coachproof_attachment_id"
                   name="coachproof_attachment_id"
                   value="<?php echo esc_attr( $attachment_id ); ?>" />

            <div id="coachproof-file-preview" style="<?php echo $attachment_id ? '' : 'display:none;'; ?>">
                <p>
                    <strong>Current file:</strong>
                    <a href="<?php echo esc_url( $file_url ); ?>" target="_blank" id="coachproof-file-link">
                        <?php echo esc_html( $filename ); ?>
                    </a>
                    <button type="button" class="button" id="coachproof-remove-file" style="margin-left:8px;">Remove</button>
                </p>
            </div>

            <div id="coachproof-file-select" style="<?php echo $attachment_id ? 'display:none;' : ''; ?>">
                <button type="button" class="button button-primary" id="coachproof-upload-btn">
                    Select or Upload File
                </button>
                <p class="description">Allowed file types: <?php echo esc_html( $allowed ); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render the sync status meta box.
     *
     * @param WP_Post $post
     */
    public static function render_sync_meta_box( WP_Post $post ): void {
        $status    = get_post_meta( $post->ID, '_coachproof_sync_status', true ) ?: 'draft';
        $file_id   = get_post_meta( $post->ID, '_coachproof_openai_file_id', true );
        $error     = get_post_meta( $post->ID, '_coachproof_sync_error', true );
        $synced_at = get_post_meta( $post->ID, '_coachproof_synced_at', true );

        $badge_class = 'coachproof-status-badge coachproof-status--' . esc_attr( $status );
        ?>
        <div class="coachproof-sync-box">
            <p>
                <strong>Status:</strong>
                <span class="<?php echo $badge_class; ?>" id="coachproof-sync-status-badge">
                    <?php echo esc_html( ucfirst( $status ) ); ?>
                </span>
            </p>

            <?php if ( $file_id ) : ?>
                <p><strong>OpenAI File ID:</strong> <code><?php echo esc_html( $file_id ); ?></code></p>
            <?php endif; ?>

            <?php if ( $synced_at ) : ?>
                <p><strong>Last synced:</strong> <?php echo esc_html( wp_date( 'M j, Y g:i A', strtotime( $synced_at ) ) ); ?></p>
            <?php endif; ?>

            <?php if ( 'failed' === $status && $error ) : ?>
                <div class="notice notice-error inline" style="margin:8px 0;">
                    <p><?php echo esc_html( $error ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( $post->ID && get_post_status( $post->ID ) === 'publish' ) : ?>
                <p>
                    <button type="button" class="button button-primary" id="coachproof-sync-now-btn"
                            data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                            data-nonce="<?php echo esc_attr( wp_create_nonce( 'coachproof_sync_doc' ) ); ?>">
                        <?php echo in_array( $status, [ 'failed', 'stale' ], true ) ? '↻ Re-sync Now' : '⬆ Sync Now'; ?>
                    </button>
                </p>
                <p id="coachproof-sync-feedback" style="display:none;"></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save attachment meta on post save.
     *
     * @param int     $post_id
     * @param WP_Post $post
     */
    public static function save_meta( int $post_id, WP_Post $post ): void {
        // Verify nonce.
        if ( ! isset( $_POST['_coachproof_kb_nonce'] ) ||
             ! wp_verify_nonce( $_POST['_coachproof_kb_nonce'], 'coachproof_kb_file_nonce' ) ) {
            return;
        }

        // Autosave guard.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Capability check.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $new_attachment_id = isset( $_POST['coachproof_attachment_id'] )
            ? absint( $_POST['coachproof_attachment_id'] )
            : 0;

        $old_attachment_id = (int) get_post_meta( $post_id, '_coachproof_attachment_id', true );

        update_post_meta( $post_id, '_coachproof_attachment_id', $new_attachment_id );

        // If the attachment changed, mark as stale (unless it's a brand new doc).
        if ( $new_attachment_id && $old_attachment_id && $new_attachment_id !== $old_attachment_id ) {
            update_post_meta( $post_id, '_coachproof_sync_status', 'stale' );
        }

        // If no attachment selected, ensure status is draft.
        if ( ! $new_attachment_id ) {
            update_post_meta( $post_id, '_coachproof_sync_status', 'draft' );
        }

        // Set initial draft status for new docs with a file.
        if ( $new_attachment_id && ! $old_attachment_id ) {
            $current_status = get_post_meta( $post_id, '_coachproof_sync_status', true );
            if ( ! $current_status ) {
                update_post_meta( $post_id, '_coachproof_sync_status', 'draft' );
            }
        }
    }

    /**
     * Enqueue admin JS and CSS for the meta boxes.
     *
     * @param string $hook
     */
    public static function enqueue_admin_assets( string $hook ): void {
        global $post_type;

        if ( $post_type !== Coachproof_Knowledge_Post_Type::POST_TYPE ) {
            return;
        }

        // WordPress media library.
        if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            wp_enqueue_media();
        }

        // Inline admin JS for the file picker and sync button.
        wp_add_inline_script( 'jquery', self::get_admin_js() );

        // Inline admin CSS for status badges.
        wp_add_inline_style( 'wp-admin', self::get_admin_css() );
    }

    /**
     * Admin JS for file picker and sync button.
     *
     * @return string
     */
    private static function get_admin_js(): string {
        return <<<'JS'
jQuery(function($) {
    // --- File upload via WP Media Library ---
    var frame;
    $('#coachproof-upload-btn').on('click', function(e) {
        e.preventDefault();
        if (frame) { frame.open(); return; }
        frame = wp.media({
            title: 'Select Knowledge Document',
            button: { text: 'Use this file' },
            multiple: false,
            library: { type: ['application/pdf','text/plain','text/markdown','text/csv','application/vnd.openxmlformats-officedocument.wordprocessingml.document'] }
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#coachproof_attachment_id').val(attachment.id);
            $('#coachproof-file-link').attr('href', attachment.url).text(attachment.filename);
            $('#coachproof-file-preview').show();
            $('#coachproof-file-select').hide();
        });
        frame.open();
    });

    $('#coachproof-remove-file').on('click', function(e) {
        e.preventDefault();
        $('#coachproof_attachment_id').val('');
        $('#coachproof-file-preview').hide();
        $('#coachproof-file-select').show();
    });

    // --- Sync Now button ---
    $('#coachproof-sync-now-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $feedback = $('#coachproof-sync-feedback');
        var postId = $btn.data('post-id');
        var nonce  = $btn.data('nonce');

        $btn.prop('disabled', true).text('Syncing…');
        $feedback.hide();

        $.post(ajaxurl, {
            action: 'coachproof_sync_doc',
            post_id: postId,
            _ajax_nonce: nonce
        }, function(response) {
            if (response.success) {
                var d = response.data;
                $('#coachproof-sync-status-badge')
                    .attr('class', 'coachproof-status-badge coachproof-status--' + d.sync_status)
                    .text(d.sync_status.charAt(0).toUpperCase() + d.sync_status.slice(1));
                $btn.text('⬆ Sync Now');
                $feedback.text('✅ Synced successfully').css('color','#16a34a').show();
            } else {
                $feedback.text('❌ ' + (response.data.error || 'Sync failed')).css('color','#b91c1c').show();
                $btn.text('↻ Re-sync Now');
            }
            $btn.prop('disabled', false);
        }).fail(function() {
            $feedback.text('❌ Network error during sync.').css('color','#b91c1c').show();
            $btn.prop('disabled', false).text('↻ Re-sync Now');
        });
    });
});
JS;
    }

    /**
     * Admin CSS for status badges.
     *
     * @return string
     */
    private static function get_admin_css(): string {
        return <<<'CSS'
.coachproof-status-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.6;
    text-transform: capitalize;
}
.coachproof-status--draft     { background: #e5e7eb; color: #374151; }
.coachproof-status--syncing   { background: #dbeafe; color: #1d4ed8; }
.coachproof-status--ready     { background: #dcfce7; color: #166534; }
.coachproof-status--failed    { background: #fee2e2; color: #991b1b; }
.coachproof-status--stale     { background: #fef9c3; color: #854d0e; }

.coachproof-sync-box p { margin: 6px 0; }
CSS;
    }
}
