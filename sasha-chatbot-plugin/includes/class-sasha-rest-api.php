<?php
/**
 * Sasha Chatbot – REST API
 *
 * Registers the public REST endpoint for the chatbot.
 * Handles nonce validation, input sanitisation, rate limiting,
 * and delegates to the configured ChatProviderInterface.
 *
 * Endpoint: POST /wp-json/sasha-chatbot/v1/message
 *
 * Request:
 *   { "message": "string", "conversation_id": "string?", "page_context": "string?" }
 *   Header: X-WP-Nonce
 *
 * Response:
 *   { conversation_id, reply_text, ui_state, delivery_mode, actions, requires_auth, error_code, trace_id }
 *
 * @package SashaChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sasha_REST_API {

    /** Maximum allowed message length in characters. */
    private const MAX_MESSAGE_LENGTH = 1000;

    /**
     * Register REST routes.
     */
    public static function register_routes(): void {
        register_rest_route( 'sasha-chatbot/v1', '/message', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_message' ),
            'permission_callback' => '__return_true', // Public endpoint.
            'args'                => array(
                'message' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ( $value ) {
                        if ( empty( trim( $value ) ) ) {
                            return new WP_Error( 'empty_message', 'Message cannot be empty.', array( 'status' => 400 ) );
                        }
                        if ( mb_strlen( $value ) > self::MAX_MESSAGE_LENGTH ) {
                            return new WP_Error(
                                'message_too_long',
                                sprintf( 'Message must be under %d characters.', self::MAX_MESSAGE_LENGTH ),
                                array( 'status' => 400 )
                            );
                        }
                        return true;
                    },
                ),
                'conversation_id' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => '',
                ),
                'page_context' => array(
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => '',
                ),
            ),
        ) );
    }

    /**
     * Handle the incoming chat message.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_message( WP_REST_Request $request ): WP_REST_Response {
        $trace_id = wp_generate_uuid4();

        // --- Rate limiting via transients (per IP) ---
        $rate_check = self::check_rate_limit( $trace_id );
        if ( is_wp_error( $rate_check ) ) {
            $result = new Chat_Result(
                reply_text:      'You\'re sending messages too quickly. Please wait a moment and try again.',
                ui_state:        'error',
                delivery_mode:   'single',
                conversation_id: '',
                error_code:      'rate_limited',
                trace_id:        $trace_id
            );
            return new WP_REST_Response( $result->to_array(), 429 );
        }

        // --- Build context ---
        $message = $request->get_param( 'message' );
        $context = array(
            'conversation_id' => $request->get_param( 'conversation_id' ),
            'page_context'    => $request->get_param( 'page_context' ),
        );

        // --- Delegate to the configured provider ---
        $provider = self::get_provider();
        $result   = $provider->send_message( $message, $context );

        // Determine HTTP status from result.
        $status = ( 'error' === $result->ui_state ) ? 502 : 200;

        return new WP_REST_Response( $result->to_array(), $status );
    }

    /**
     * Simple IP-based rate limiting using WordPress transients.
     *
     * @param string $trace_id For log correlation.
     * @return true|WP_Error
     */
    private static function check_rate_limit( string $trace_id ) {
        $ip            = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $transient_key = 'sasha_rl_' . md5( $ip );
        $limit         = (int) get_option( 'sasha_chatbot_rate_limit', 10 );
        $current       = (int) get_transient( $transient_key );

        if ( $current >= $limit ) {
            error_log( sprintf(
                '[sasha-chatbot][%s] Rate limited IP: %s (%d/%d)',
                $trace_id,
                $ip,
                $current,
                $limit
            ) );
            return new WP_Error( 'rate_limited', 'Too many requests.' );
        }

        // Increment counter with a 60-second window.
        set_transient( $transient_key, $current + 1, 60 );

        return true;
    }

    /**
     * Instantiate the configured chat provider.
     * Currently hardcoded to OpenAI Responses API.
     * Future: read from sasha_chatbot_provider option and resolve dynamically.
     *
     * @return Chat_Provider_Interface
     */
    private static function get_provider(): Chat_Provider_Interface {
        return new OpenAI_Responses_Provider();
    }
}
