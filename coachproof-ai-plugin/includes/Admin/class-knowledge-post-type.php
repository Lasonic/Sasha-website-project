<?php
/**
 * Knowledge Document Post Type
 *
 * Registers the `coachproof_kb_doc` custom post type and customises the
 * admin list table with sync-status columns and row actions.
 *
 * @package CoachProofAI\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Coachproof_Knowledge_Post_Type {

    /** Custom post type slug. */
    public const POST_TYPE = 'coachproof_kb_doc';

    /** Allowed file MIME types for OpenAI Files API. */
    public const ALLOWED_MIMES = [
        'application/pdf',
        'text/plain',
        'text/markdown',
        'text/csv',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
    ];

    /** Allowed file extensions (human-readable for validation messages). */
    public const ALLOWED_EXTENSIONS = [ 'pdf', 'txt', 'md', 'csv', 'docx' ];

    /**
     * Hook into WordPress.
     */
    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'register_post_type' ] );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns',  [ __CLASS__, 'custom_columns' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ __CLASS__, 'render_column' ], 10, 2 );
    }

    /**
     * Register the custom post type.
     */
    public static function register_post_type(): void {
        $labels = [
            'name'               => 'Knowledge Documents',
            'singular_name'      => 'Knowledge Document',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Document',
            'edit_item'          => 'Edit Document',
            'new_item'           => 'New Document',
            'view_item'          => 'View Document',
            'search_items'       => 'Search Documents',
            'not_found'          => 'No documents found.',
            'not_found_in_trash' => 'No documents found in Trash.',
            'menu_name'          => 'Knowledge Docs',
        ];

        register_post_type( self::POST_TYPE, [
            'labels'       => $labels,
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => true,
            'menu_icon'    => 'dashicons-media-document',
            'menu_position'=> 26,
            'supports'     => [ 'title' ],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ] );
    }

    /**
     * Define custom admin list columns.
     *
     * @param array $columns
     * @return array
     */
    public static function custom_columns( array $columns ): array {
        $new = [];
        $new['cb']    = $columns['cb'];
        $new['title'] = $columns['title'];
        $new['coachproof_file']       = 'File';
        $new['coachproof_sync_status'] = 'Sync Status';
        $new['coachproof_openai_id']  = 'OpenAI File ID';
        $new['coachproof_synced_at']  = 'Last Synced';
        $new['date']  = $columns['date'];
        return $new;
    }

    /**
     * Render custom column content.
     *
     * @param string $column
     * @param int    $post_id
     */
    public static function render_column( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'coachproof_file':
                $att_id = (int) get_post_meta( $post_id, '_coachproof_attachment_id', true );
                if ( $att_id ) {
                    $filename = basename( get_attached_file( $att_id ) ?: '' );
                    echo esc_html( $filename ?: '—' );
                } else {
                    echo '<em>No file</em>';
                }
                break;

            case 'coachproof_sync_status':
                $status = get_post_meta( $post_id, '_coachproof_sync_status', true ) ?: 'draft';
                $badge_class = 'coachproof-status-badge coachproof-status--' . esc_attr( $status );
                printf( '<span class="%s">%s</span>', $badge_class, esc_html( ucfirst( $status ) ) );

                // Show error tooltip for failed status.
                if ( 'failed' === $status ) {
                    $error = get_post_meta( $post_id, '_coachproof_sync_error', true );
                    if ( $error ) {
                        printf( '<br><small style="color:#b91c1c;">%s</small>', esc_html( $error ) );
                    }
                }
                break;

            case 'coachproof_openai_id':
                $file_id = get_post_meta( $post_id, '_coachproof_openai_file_id', true );
                echo $file_id ? '<code>' . esc_html( $file_id ) . '</code>' : '—';
                break;

            case 'coachproof_synced_at':
                $synced = get_post_meta( $post_id, '_coachproof_synced_at', true );
                if ( $synced ) {
                    echo esc_html( wp_date( 'M j, Y g:i A', strtotime( $synced ) ) );
                } else {
                    echo '—';
                }
                break;
        }
    }
}
