<?php
/**
 * OpenAI Responses API Provider
 *
 * Implements Chat_Provider_Interface using the OpenAI Responses API
 * (POST /v1/responses). This is a stateless, single-turn request/response
 * pattern — no Threads, no Runs, no polling.
 *
 * @package SashaChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OpenAI_Responses_Provider implements Chat_Provider_Interface {

    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    /**
     * Send a user message to the OpenAI Responses API.
     *
     * @param string $message  Sanitised user message.
     * @param array  $context  Optional: conversation_id, page_context.
     * @return Chat_Result
     */
    public function send_message( string $message, array $context = array() ): Chat_Result {
        $trace_id = wp_generate_uuid4();
        $api_key  = $this->get_api_key();

        if ( empty( $api_key ) ) {
            error_log( "[sasha-chatbot][{$trace_id}] ERROR: No API key configured." );
            return $this->error_result( 'provider_config_error', $trace_id );
        }

        $model          = get_option( 'sasha_chatbot_model', 'gpt-4o' );
        $system_prompt  = get_option( 'sasha_chatbot_system_prompt', 'You are a professional coaching assistant. Guide the user towards the most appropriate coaching module based on their needs.' );
        $timeout        = (int) get_option( 'sasha_chatbot_timeout', 30 );

        if ( $model === 'gpt-4.1' ) {
            $model = 'gpt-4o';
        }

        $body = array(
            'model'    => $model,
            'messages' => array(
                array( 'role' => 'system', 'content' => $system_prompt ),
                array( 'role' => 'user', 'content' => $message )
            ),
        );

        $response = wp_remote_post( self::API_URL, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
            'timeout' => $timeout,
        ) );

        // --- Handle WP_Error (network failure, timeout, DNS, etc.) ---
        if ( is_wp_error( $response ) ) {
            error_log( sprintf(
                '[sasha-chatbot][%s] WP_Error: %s',
                $trace_id,
                $response->get_error_message()
            ) );
            return $this->error_result( 'provider_network_error', $trace_id );
        }

        // --- Handle non-200 HTTP status ---
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code < 200 || $status_code >= 300 ) {
            error_log( sprintf(
                '[sasha-chatbot][%s] HTTP %d: %s',
                $trace_id,
                $status_code,
                wp_remote_retrieve_body( $response )
            ) );

            $error_code = ( 429 === $status_code ) ? 'provider_rate_limited' : 'provider_api_error';
            return $this->error_result( $error_code, $trace_id );
        }

        // --- Decode response body ---
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $data ) ) {
            error_log( "[sasha-chatbot][{$trace_id}] Malformed JSON response from OpenAI." );
            return $this->error_result( 'provider_parse_error', $trace_id );
        }

        // --- Extract reply text from the Chat Completions API output shape ---
        $reply_text = $data['choices'][0]['message']['content'] ?? '';

        if ( empty( $reply_text ) ) {
            error_log( "[sasha-chatbot][{$trace_id}] Empty reply_text in OpenAI response." );
            return $this->error_result( 'provider_empty_response', $trace_id );
        }

        $conversation_id = $context['conversation_id'] ?? ( $data['id'] ?? '' );

        return new Chat_Result(
            reply_text:      $reply_text,
            ui_state:        'answer',
            delivery_mode:   'single',
            conversation_id: $conversation_id,
            error_code:      null,
            trace_id:        $trace_id
        );
    }

    /**
     * Resolve the API key using the documented precedence:
     * 1. SASHA_OPENAI_API_KEY constant (wp-config.php or environment)
     * 2. sasha_chatbot_api_key option (admin settings fallback)
     *
     * @return string
     */
    private function get_api_key(): string {
        if ( defined( 'SASHA_OPENAI_API_KEY' ) && SASHA_OPENAI_API_KEY ) {
            return SASHA_OPENAI_API_KEY;
        }
        return (string) get_option( 'sasha_chatbot_api_key', '' );
    }

    /**
     * Build a safe error Chat_Result. Never leaks raw provider details.
     *
     * @param string $error_code
     * @param string $trace_id
     * @return Chat_Result
     */
    private function error_result( string $error_code, string $trace_id ): Chat_Result {
        return new Chat_Result(
            reply_text:      'Sorry, I wasn\'t able to process your request right now. Please try again shortly.',
            ui_state:        'error',
            delivery_mode:   'single',
            conversation_id: '',
            error_code:      $error_code,
            trace_id:        $trace_id
        );
    }
}
