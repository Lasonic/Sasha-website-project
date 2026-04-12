<?php
/**
 * CoachProof AI – REST API
 *
 * Registers the public REST endpoint for the chatbot.
 * Handles nonce validation, input sanitisation, rate limiting,
 * and delegates to Gated_Response_Builder which enforces the intake gate.
 *
 * Endpoint: POST /wp-json/coachproof-ai/v1/message
 *
 * Request:
 *   {
 *     "message":         "string",
 *     "conversation_id": "string?",
 *     "page_context":    "string?",
 *     "lead_profile": {
 *       "name":       "string?",
 *       "age":        "int?",
 *       "occupation": "string?",
 *       "objective":  "string?"
 *     }
 *   }
 *   Header: X-WP-Nonce
 *
 * Response (extended contract):
 *   {
 *     conversation_id, reply_text, ui_state, delivery_mode,
 *     actions: { mode, current_step, missing_fields, intake_complete, objectives? },
 *     requires_auth, error_code, trace_id
 *   }
 *
 * @package CoachProofAI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Coachproof_REST_API {

    /** Maximum allowed message length in characters. */
    private const MAX_MESSAGE_LENGTH = 1000;

    /**
     * Register REST routes.
     */
    public static function register_routes(): void {
        register_rest_route( 'coachproof-ai/v1', '/message', array(
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

                // Lead profile snapshot sent by the client on every request.
                // The backend re-validates this; it does not trust the client state.
                'lead_profile' => array(
                    'required'          => false,
                    'type'              => 'object',
                    'default'           => [],
                    'sanitize_callback' => function ( $value ) {
                        if ( ! is_array( $value ) ) {
                            return [];
                        }
                        return array_filter( array_map( 'sanitize_text_field', array_map( 'strval', $value ) ) );
                    },
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
            $result = new Coachproof_Chat_Result(
                reply_text:      'You\'re sending messages too quickly. Please wait a moment and try again.',
                ui_state:        'error',
                delivery_mode:   'single',
                conversation_id: '',
                error_code:      'rate_limited',
                trace_id:        $trace_id
            );
            return new WP_REST_Response( $result->to_array(), 429 );
        }

        // --- Build Lead_Profile from the client-supplied snapshot ---
        $raw_profile = $request->get_param( 'lead_profile' );

        // Age requires numeric handling; the object sanitizer above converts everything
        // to strings, so we pull it from the raw JSON body directly.
        $raw_body    = $request->get_json_params() ?? [];
        $raw_age     = $raw_body['lead_profile']['age'] ?? null;
        if ( $raw_age !== null && is_numeric( $raw_age ) ) {
            $raw_profile['age'] = (int) $raw_age;
        }

        $profile = Lead_Profile::from_array( is_array( $raw_profile ) ? $raw_profile : [] );

        // --- Build context ---
        $context = array(
            'conversation_id' => $request->get_param( 'conversation_id' ),
            'page_context'    => $request->get_param( 'page_context' ),
        );

        // --- Delegate to the gated response builder ---
        $builder = new Gated_Response_Builder( self::get_provider() );
        $result  = $builder->build(
            message:  $request->get_param( 'message' ),
            profile:  $profile,
            context:  $context,
            trace_id: $trace_id
        );

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
        $transient_key = 'coachproof_rl_' . md5( $ip );
        $limit         = (int) get_option( 'coachproof_chatbot_rate_limit', 10 );
        $current       = (int) get_transient( $transient_key );

        if ( $current >= $limit ) {
            error_log( sprintf(
                '[coachproof-ai][%s] Rate limited IP: %s (%d/%d)',
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
     *
     * Uses the Assistants API provider (grounded via file_search).
     * If no assistant is configured yet, the Assistant provider
     * automatically falls back to Chat Completions.
     *
     * @return Coachproof_Chat_Provider_Interface
     */
    private static function get_provider(): Coachproof_Chat_Provider_Interface {
        return new Coachproof_OpenAI_Assistant_Provider();
    }
}
