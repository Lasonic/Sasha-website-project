<?php
/**
 * OpenAI File Sync Service
 *
 * Handles uploading files to the OpenAI Files API and attaching them to
 * a Vector Store. Also handles deletion on document removal.
 *
 * All chunking, embedding, and indexing is performed by OpenAI — this
 * service only proxies the files and manages sync metadata.
 *
 * @package CoachProofAI\Services
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Coachproof_OpenAI_File_Sync {

    /** OpenAI API base URL. */
    private const API_BASE = 'https://api.openai.com/v1';

    /**
     * Hook into WordPress.
     */
    public static function init(): void {
        // AJAX handlers (admin only).
        add_action( 'wp_ajax_coachproof_sync_doc',            [ __CLASS__, 'ajax_sync_document' ] );
        add_action( 'wp_ajax_coachproof_create_vector_store', [ __CLASS__, 'ajax_create_vector_store' ] );

        // Cleanup on document deletion.
        add_action( 'before_delete_post', [ __CLASS__, 'on_delete_post' ], 10, 1 );
    }

    // ---------------------------------------------------------------
    // AJAX: Sync a single document to OpenAI
    // ---------------------------------------------------------------

    /**
     * AJAX handler for the "Sync Now" button.
     */
    public static function ajax_sync_document(): void {
        check_ajax_referer( 'coachproof_sync_doc' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Insufficient permissions.' ], 403 );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || get_post_type( $post_id ) !== Coachproof_Knowledge_Post_Type::POST_TYPE ) {
            wp_send_json_error( [ 'error' => 'Invalid document.' ], 400 );
        }

        $result = self::sync_document( $post_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [
                'sync_status' => 'failed',
                'error'       => $result->get_error_message(),
            ] );
        }

        wp_send_json_success( $result );
    }

    // ---------------------------------------------------------------
    // AJAX: Create a Vector Store
    // ---------------------------------------------------------------

    /**
     * AJAX handler for "Create Vector Store" button on settings page.
     */
    public static function ajax_create_vector_store(): void {
        check_ajax_referer( 'coachproof_create_vs' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Insufficient permissions.' ], 403 );
        }

        $api_key = self::get_api_key();
        if ( empty( $api_key ) ) {
            wp_send_json_error( [ 'error' => 'No API key configured. Set SASHA_OPENAI_API_KEY first.' ] );
        }

        $response = wp_remote_post( self::API_BASE . '/vector_stores', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'OpenAI-Beta'   => 'assistants=v2',
            ],
            'body'    => wp_json_encode( [ 'name' => 'CoachProof Knowledge Base' ] ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'error' => 'Network error: ' . $response->get_error_message() ] );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status < 200 || $status >= 300 || empty( $body['id'] ) ) {
            $msg = $body['error']['message'] ?? 'Unknown error (HTTP ' . $status . ')';
            wp_send_json_error( [ 'error' => $msg ] );
        }

        // Save the Vector Store ID.
        update_option( 'coachproof_vector_store_id', sanitize_text_field( $body['id'] ) );

        wp_send_json_success( [
            'vector_store_id' => $body['id'],
            'name'            => $body['name'] ?? 'CoachProof Knowledge Base',
        ] );
    }

    // ---------------------------------------------------------------
    // Core sync logic
    // ---------------------------------------------------------------

    /**
     * Sync a knowledge document to OpenAI.
     *
     * 1. Upload file to OpenAI Files API.
     * 2. Attach file to the configured Vector Store.
     * 3. Update post meta with sync results.
     *
     * @param int $post_id
     * @return array|WP_Error Sync result array or error.
     */
    public static function sync_document( int $post_id ) {
        $api_key = self::get_api_key();
        if ( empty( $api_key ) ) {
            return self::fail( $post_id, 'No API key configured.' );
        }

        $vector_store_id = get_option( 'coachproof_vector_store_id', '' );
        if ( empty( $vector_store_id ) ) {
            return self::fail( $post_id, 'No Vector Store ID configured. Create one in Settings → CoachProof AI.' );
        }

        // Get attachment file path.
        $attachment_id = (int) get_post_meta( $post_id, '_coachproof_attachment_id', true );
        if ( ! $attachment_id ) {
            return self::fail( $post_id, 'No file attached to this document.' );
        }

        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return self::fail( $post_id, 'Attached file not found on disk.' );
        }

        // Validate file type.
        $mime = mime_content_type( $file_path );
        if ( ! in_array( $mime, Coachproof_Knowledge_Post_Type::ALLOWED_MIMES, true ) ) {
            return self::fail( $post_id, 'File type not supported: ' . $mime );
        }

        // Mark as syncing.
        update_post_meta( $post_id, '_coachproof_sync_status', 'syncing' );
        update_post_meta( $post_id, '_coachproof_sync_error', '' );

        // If there's an existing OpenAI file, delete it first.
        $old_file_id = get_post_meta( $post_id, '_coachproof_openai_file_id', true );
        if ( $old_file_id ) {
            self::delete_openai_file( $old_file_id, $vector_store_id, $api_key );
        }

        // --- Step 1: Upload file to OpenAI Files API ---
        $filename = basename( $file_path );

        // Use cURL for multipart file upload (wp_remote_post doesn't handle files well).
        $ch = curl_init();
        curl_setopt_array( $ch, [
            CURLOPT_URL            => self::API_BASE . '/files',
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $api_key,
            ],
            CURLOPT_POSTFIELDS     => [
                'purpose' => 'assistants',
                'file'    => new CURLFile( $file_path, $mime, $filename ),
            ],
        ] );

        $upload_response = curl_exec( $ch );
        $curl_error      = curl_error( $ch );
        $http_code       = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        if ( $curl_error ) {
            return self::fail( $post_id, 'Upload network error: ' . $curl_error );
        }

        $upload_data = json_decode( $upload_response, true );

        if ( $http_code < 200 || $http_code >= 300 || empty( $upload_data['id'] ) ) {
            $msg = $upload_data['error']['message'] ?? 'Upload failed (HTTP ' . $http_code . ')';
            return self::fail( $post_id, $msg );
        }

        $openai_file_id = $upload_data['id'];

        // --- Step 2: Attach to Vector Store ---
        $attach_response = wp_remote_post(
            self::API_BASE . '/vector_stores/' . $vector_store_id . '/files',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'OpenAI-Beta'   => 'assistants=v2',
                ],
                'body'    => wp_json_encode( [ 'file_id' => $openai_file_id ] ),
                'timeout' => 30,
            ]
        );

        if ( is_wp_error( $attach_response ) ) {
            return self::fail( $post_id, 'Vector Store attach error: ' . $attach_response->get_error_message() );
        }

        $attach_status = wp_remote_retrieve_response_code( $attach_response );
        $attach_body   = json_decode( wp_remote_retrieve_body( $attach_response ), true );

        if ( $attach_status < 200 || $attach_status >= 300 ) {
            $msg = $attach_body['error']['message'] ?? 'Attach failed (HTTP ' . $attach_status . ')';
            return self::fail( $post_id, $msg );
        }

        // --- Step 3: Update post meta ---
        $now      = current_time( 'c' );
        $file_hash = md5_file( $file_path );

        update_post_meta( $post_id, '_coachproof_openai_file_id', $openai_file_id );
        update_post_meta( $post_id, '_coachproof_sync_status',    'ready' );
        update_post_meta( $post_id, '_coachproof_sync_error',     '' );
        update_post_meta( $post_id, '_coachproof_synced_at',      $now );
        update_post_meta( $post_id, '_coachproof_file_hash',      $file_hash );

        error_log( sprintf(
            '[coachproof-ai] Document #%d synced successfully. OpenAI File ID: %s',
            $post_id,
            $openai_file_id
        ) );

        return [
            'sync_status'    => 'ready',
            'openai_file_id' => $openai_file_id,
            'synced_at'      => $now,
        ];
    }

    // ---------------------------------------------------------------
    // Cleanup on document deletion
    // ---------------------------------------------------------------

    /**
     * Delete the OpenAI file when a knowledge document is permanently deleted.
     *
     * @param int $post_id
     */
    public static function on_delete_post( int $post_id ): void {
        if ( get_post_type( $post_id ) !== Coachproof_Knowledge_Post_Type::POST_TYPE ) {
            return;
        }

        $file_id         = get_post_meta( $post_id, '_coachproof_openai_file_id', true );
        $vector_store_id = get_option( 'coachproof_vector_store_id', '' );
        $api_key         = self::get_api_key();

        if ( $file_id && $api_key ) {
            self::delete_openai_file( $file_id, $vector_store_id, $api_key );
            error_log( sprintf(
                '[coachproof-ai] Deleted OpenAI file %s for document #%d',
                $file_id,
                $post_id
            ) );
        }
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    /**
     * Delete a file from OpenAI (and detach from Vector Store).
     *
     * @param string $file_id
     * @param string $vector_store_id
     * @param string $api_key
     */
    private static function delete_openai_file( string $file_id, string $vector_store_id, string $api_key ): void {
        // Detach from Vector Store.
        if ( $vector_store_id ) {
            wp_remote_request(
                self::API_BASE . '/vector_stores/' . $vector_store_id . '/files/' . $file_id,
                [
                    'method'  => 'DELETE',
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'OpenAI-Beta'   => 'assistants=v2',
                    ],
                    'timeout' => 15,
                ]
            );
        }

        // Delete the file itself.
        wp_remote_request(
            self::API_BASE . '/files/' . $file_id,
            [
                'method'  => 'DELETE',
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'timeout' => 15,
            ]
        );
    }

    /**
     * Mark a document as failed and return a WP_Error.
     *
     * @param int    $post_id
     * @param string $message
     * @return WP_Error
     */
    private static function fail( int $post_id, string $message ): WP_Error {
        update_post_meta( $post_id, '_coachproof_sync_status', 'failed' );
        update_post_meta( $post_id, '_coachproof_sync_error', $message );

        error_log( sprintf( '[coachproof-ai] Sync failed for doc #%d: %s', $post_id, $message ) );

        return new WP_Error( 'sync_failed', $message );
    }

    /**
     * Resolve the API key.
     *
     * @return string
     */
    private static function get_api_key(): string {
        if ( defined( 'SASHA_OPENAI_API_KEY' ) && SASHA_OPENAI_API_KEY ) {
            return SASHA_OPENAI_API_KEY;
        }
        return (string) get_option( 'coachproof_chatbot_api_key', '' );
    }
}
