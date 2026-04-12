<?php
/**
 * OpenAI Assistant Provider (Grounded)
 *
 * Implements Coachproof_Chat_Provider_Interface using the OpenAI Assistants API v2.
 * Each conversation maps to an OpenAI Thread. Answers are grounded via file_search.
 *
 * Flow:
 *   1. Create or reuse a Thread (conversation_id = thread_id).
 *   2. Add the user's message to the Thread.
 *   3. Create a Run with the configured Assistant.
 *   4. Poll until the Run completes.
 *   5. Retrieve the assistant's reply + citations.
 *
 * @package CoachProofAI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Coachproof_OpenAI_Assistant_Provider implements Coachproof_Chat_Provider_Interface {

    /** OpenAI API base URL. */
    private const API_BASE = 'https://api.openai.com/v1';

    /** Maximum Run poll attempts (each ~1.5s apart). */
    private const MAX_POLL_ATTEMPTS = 40; // ~60 seconds max

    /** Phrases that indicate the assistant couldn't find a grounded answer. */
    private const FALLBACK_PHRASES = [
        'I don\'t have specific information',
        'not in our knowledge base',
        'I couldn\'t find',
        'I don\'t have enough information',
        'connect you with',
        'speak with an advisor',
        'beyond my current knowledge',
    ];

    /**
     * Send a user message via the Assistants API.
     *
     * @param string $message  Sanitised user message.
     * @param array  $context  conversation_id, page_context, lead_profile, system_note.
     * @return Coachproof_Chat_Result
     */
    public function send_message( string $message, array $context = [] ): Coachproof_Chat_Result {
        $trace_id     = wp_generate_uuid4();
        $api_key      = $this->get_api_key();
        $assistant_id = get_option( 'coachproof_assistant_id', '' );

        if ( empty( $api_key ) ) {
            return $this->error_result( 'provider_config_error', $trace_id, 'No API key configured.' );
        }

        if ( empty( $assistant_id ) ) {
            // Fall back to chat completions if no assistant exists yet.
            error_log( "[coachproof-ai][{$trace_id}] No assistant configured — falling back to Chat Completions." );
            $fallback = new Coachproof_OpenAI_Responses_Provider();
            return $fallback->send_message( $message, $context );
        }

        $thread_id = $context['conversation_id'] ?? '';
        $headers   = $this->api_headers( $api_key );

        // --- Step 1: Create or validate Thread ---
        if ( empty( $thread_id ) ) {
            $thread_id = $this->create_thread( $headers, $trace_id );
            if ( is_wp_error( $thread_id ) ) {
                return $this->error_result( 'thread_create_error', $trace_id, $thread_id->get_error_message() );
            }
        }

        // --- Step 2: Add user message to Thread ---
        $msg_result = $this->add_message( $thread_id, $message, $headers, $trace_id );
        if ( is_wp_error( $msg_result ) ) {
            return $this->error_result( 'message_add_error', $trace_id, $msg_result->get_error_message() );
        }

        // --- Step 3: Create a Run ---
        $additional_instructions = $context['system_note'] ?? '';
        $run_id = $this->create_run( $thread_id, $assistant_id, $additional_instructions, $headers, $trace_id );
        if ( is_wp_error( $run_id ) ) {
            return $this->error_result( 'run_create_error', $trace_id, $run_id->get_error_message() );
        }

        // --- Step 4: Poll until complete ---
        $run_status = $this->poll_run( $thread_id, $run_id, $headers, $trace_id );
        if ( is_wp_error( $run_status ) ) {
            return $this->error_result( 'run_poll_error', $trace_id, $run_status->get_error_message() );
        }

        if ( $run_status !== 'completed' ) {
            return $this->error_result( 'run_failed', $trace_id, 'Run ended with status: ' . $run_status );
        }

        // --- Step 5: Retrieve the reply ---
        $reply = $this->get_latest_reply( $thread_id, $headers, $trace_id );
        if ( is_wp_error( $reply ) ) {
            return $this->error_result( 'reply_fetch_error', $trace_id, $reply->get_error_message() );
        }

        $reply_text = $reply['text'];
        $sources    = $reply['sources'];

        // --- Step 6: Detect fallback ---
        $answer_type = 'grounded';
        $ui_state    = 'answer';

        foreach ( self::FALLBACK_PHRASES as $phrase ) {
            if ( stripos( $reply_text, $phrase ) !== false ) {
                $answer_type = 'escalation';
                $ui_state    = 'escalation';
                break;
            }
        }

        return new Coachproof_Chat_Result(
            reply_text:      $reply_text,
            ui_state:        $ui_state,
            delivery_mode:   'single',
            conversation_id: $thread_id,
            error_code:      null,
            trace_id:        $trace_id,
            actions:         [
                'answer_type' => $answer_type,
                'sources'     => $sources,
            ]
        );
    }

    // ---------------------------------------------------------------
    // Assistants API Calls
    // ---------------------------------------------------------------

    /**
     * Create a new Thread.
     *
     * @return string|WP_Error Thread ID or error.
     */
    private function create_thread( array $headers, string $trace_id ) {
        $response = wp_remote_post( self::API_BASE . '/threads', [
            'headers' => $headers,
            'body'    => '{}',
            'timeout' => 15,
        ] );

        $data = $this->parse_response( $response, $trace_id );
        if ( is_wp_error( $data ) ) return $data;

        if ( empty( $data['id'] ) ) {
            return new WP_Error( 'thread_error', 'No thread ID returned.' );
        }

        error_log( "[coachproof-ai][{$trace_id}] Thread created: {$data['id']}" );
        return $data['id'];
    }

    /**
     * Add a message to a Thread.
     *
     * @return true|WP_Error
     */
    private function add_message( string $thread_id, string $content, array $headers, string $trace_id ) {
        $response = wp_remote_post( self::API_BASE . "/threads/{$thread_id}/messages", [
            'headers' => $headers,
            'body'    => wp_json_encode( [
                'role'    => 'user',
                'content' => $content,
            ] ),
            'timeout' => 15,
        ] );

        $data = $this->parse_response( $response, $trace_id );
        if ( is_wp_error( $data ) ) return $data;

        return true;
    }

    /**
     * Create a Run on a Thread.
     *
     * @return string|WP_Error Run ID or error.
     */
    private function create_run( string $thread_id, string $assistant_id, string $additional_instructions, array $headers, string $trace_id ) {
        $body = [ 'assistant_id' => $assistant_id ];

        if ( ! empty( $additional_instructions ) ) {
            $body['additional_instructions'] = $additional_instructions;
        }

        $response = wp_remote_post( self::API_BASE . "/threads/{$thread_id}/runs", [
            'headers' => $headers,
            'body'    => wp_json_encode( $body ),
            'timeout' => 15,
        ] );

        $data = $this->parse_response( $response, $trace_id );
        if ( is_wp_error( $data ) ) return $data;

        if ( empty( $data['id'] ) ) {
            return new WP_Error( 'run_error', 'No run ID returned.' );
        }

        error_log( "[coachproof-ai][{$trace_id}] Run created: {$data['id']} on thread {$thread_id}" );
        return $data['id'];
    }

    /**
     * Poll a Run until it reaches a terminal state.
     *
     * @return string|WP_Error Terminal status or error.
     */
    private function poll_run( string $thread_id, string $run_id, array $headers, string $trace_id ) {
        $terminal_statuses = [ 'completed', 'failed', 'cancelled', 'expired', 'incomplete' ];

        for ( $i = 0; $i < self::MAX_POLL_ATTEMPTS; $i++ ) {
            $response = wp_remote_get( self::API_BASE . "/threads/{$thread_id}/runs/{$run_id}", [
                'headers' => $headers,
                'timeout' => 10,
            ] );

            $data = $this->parse_response( $response, $trace_id );
            if ( is_wp_error( $data ) ) return $data;

            $status = $data['status'] ?? 'unknown';

            if ( in_array( $status, $terminal_statuses, true ) ) {
                error_log( "[coachproof-ai][{$trace_id}] Run {$run_id} terminal status: {$status}" );
                return $status;
            }

            // Wait before next poll. Use shorter waits initially.
            usleep( $i < 5 ? 500000 : 1500000 ); // 0.5s for first 5, then 1.5s
        }

        return new WP_Error( 'run_timeout', 'Run did not complete within the timeout period.' );
    }

    /**
     * Get the latest assistant reply from a Thread.
     *
     * @return array|WP_Error  { text: string, sources: string[] }
     */
    private function get_latest_reply( string $thread_id, array $headers, string $trace_id ) {
        $response = wp_remote_get(
            self::API_BASE . "/threads/{$thread_id}/messages?order=desc&limit=1",
            [
                'headers' => $headers,
                'timeout' => 10,
            ]
        );

        $data = $this->parse_response( $response, $trace_id );
        if ( is_wp_error( $data ) ) return $data;

        $messages = $data['data'] ?? [];
        if ( empty( $messages ) ) {
            return new WP_Error( 'no_reply', 'No messages found in the thread.' );
        }

        $latest  = $messages[0];
        $content = $latest['content'] ?? [];
        $text    = '';
        $sources = [];

        foreach ( $content as $block ) {
            if ( ( $block['type'] ?? '' ) === 'text' ) {
                $text_block = $block['text'] ?? [];
                $text      .= $text_block['value'] ?? '';

                // Extract file citations from annotations.
                $annotations = $text_block['annotations'] ?? [];
                foreach ( $annotations as $ann ) {
                    if ( ( $ann['type'] ?? '' ) === 'file_citation' ) {
                        $file_id = $ann['file_citation']['file_id'] ?? '';
                        if ( $file_id && ! in_array( $file_id, $sources, true ) ) {
                            $sources[] = $file_id;
                        }
                        // Clean citation markers from text (e.g. 【4:0†source】).
                        if ( ! empty( $ann['text'] ) ) {
                            $text = str_replace( $ann['text'], '', $text );
                        }
                    }
                }
            }
        }

        $text = trim( $text );

        if ( empty( $text ) ) {
            return new WP_Error( 'empty_reply', 'Assistant returned an empty reply.' );
        }

        // Resolve file IDs to document names where possible.
        $named_sources = $this->resolve_source_names( $sources );

        return [
            'text'    => $text,
            'sources' => $named_sources,
        ];
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Resolve OpenAI file IDs to WP knowledge document titles.
     *
     * @param string[] $file_ids
     * @return string[]
     */
    private function resolve_source_names( array $file_ids ): array {
        if ( empty( $file_ids ) ) return [];

        $names = [];
        foreach ( $file_ids as $file_id ) {
            // Look up the knowledge doc that has this OpenAI file ID.
            $posts = get_posts( [
                'post_type'  => Coachproof_Knowledge_Post_Type::POST_TYPE,
                'meta_key'   => '_coachproof_openai_file_id',
                'meta_value' => $file_id,
                'numberposts' => 1,
                'fields'      => 'ids',
            ] );

            if ( ! empty( $posts ) ) {
                $names[] = get_the_title( $posts[0] );
            } else {
                $names[] = $file_id; // Fallback to raw ID if not found.
            }
        }

        return array_unique( $names );
    }

    /**
     * Standard API headers for Assistants v2.
     *
     * @param string $api_key
     * @return array
     */
    private function api_headers( string $api_key ): array {
        return [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
            'OpenAI-Beta'   => 'assistants=v2',
        ];
    }

    /**
     * Parse an API response into an array.
     *
     * @return array|WP_Error
     */
    private function parse_response( $response, string $trace_id ) {
        if ( is_wp_error( $response ) ) {
            error_log( "[coachproof-ai][{$trace_id}] WP_Error: " . $response->get_error_message() );
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status < 200 || $status >= 300 ) {
            $msg = $body['error']['message'] ?? 'HTTP ' . $status;
            error_log( "[coachproof-ai][{$trace_id}] API error: {$msg}" );
            return new WP_Error( 'api_error', $msg );
        }

        if ( ! is_array( $body ) ) {
            return new WP_Error( 'parse_error', 'Malformed JSON response.' );
        }

        return $body;
    }

    /**
     * Resolve the API key.
     *
     * @return string
     */
    private function get_api_key(): string {
        if ( defined( 'SASHA_OPENAI_API_KEY' ) && SASHA_OPENAI_API_KEY ) {
            return SASHA_OPENAI_API_KEY;
        }
        return (string) get_option( 'coachproof_chatbot_api_key', '' );
    }

    /**
     * Build a safe error result.
     *
     * @param string $error_code
     * @param string $trace_id
     * @param string $log_msg
     * @return Coachproof_Chat_Result
     */
    private function error_result( string $error_code, string $trace_id, string $log_msg = '' ): Coachproof_Chat_Result {
        if ( $log_msg ) {
            error_log( "[coachproof-ai][{$trace_id}] {$error_code}: {$log_msg}" );
        }
        return new Coachproof_Chat_Result(
            reply_text:      'Sorry, I wasn\'t able to process your request right now. Please try again shortly.',
            ui_state:        'error',
            delivery_mode:   'single',
            conversation_id: '',
            error_code:      $error_code,
            trace_id:        $trace_id
        );
    }
}
