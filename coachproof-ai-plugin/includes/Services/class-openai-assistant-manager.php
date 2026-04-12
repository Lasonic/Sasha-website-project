<?php
/**
 * OpenAI Assistant Manager
 *
 * Handles one-time creation and updates of the OpenAI Assistant that powers
 * the grounded FAQ answering. The Assistant is configured with the file_search
 * tool linked to the CoachProof Vector Store.
 *
 * @package CoachProofAI\Services
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Coachproof_OpenAI_Assistant_Manager {

    /** OpenAI API base URL. */
    private const API_BASE = 'https://api.openai.com/v1';

    /** Default grounding instructions appended to the system prompt. */
    private const GROUNDING_RULES = <<<'TXT'

IMPORTANT RULES — FOLLOW STRICTLY:

CONVERSATION FLOW:
1. When a user first appears after intake, use their profile (name, age, occupation, objective) to recommend the most suitable coaching or training module from the knowledge documents.
2. Briefly explain WHY this module fits their situation.
3. After the recommendation, tell the user they have two options:
   a) Share more details about their situation for a more targeted recommendation.
   b) Ask any questions about the services, packages, processes, or anything within scope.
4. For follow-up questions, answer helpfully and stay grounded in the knowledge documents.

SAFETY RULES:
- Answer ONLY using information from the provided knowledge documents.
- If you cannot find a relevant answer in the documents, say: "I don't have specific information about that in our knowledge base. Would you like me to connect you with one of our advisors for a personalised consultation?"
- Never speculate or provide financial advice that isn't backed by the documents.
- Always be professional, warm, and concise.
- Use the user's first name naturally.
- When referencing information, mention the source naturally without using technical citation markers.
TXT;

    /**
     * Hook into WordPress.
     */
    public static function init(): void {
        add_action( 'wp_ajax_coachproof_create_assistant', [ __CLASS__, 'ajax_create_assistant' ] );
        add_action( 'wp_ajax_coachproof_update_assistant', [ __CLASS__, 'ajax_update_assistant' ] );
    }

    /**
     * AJAX: Create a new Assistant.
     */
    public static function ajax_create_assistant(): void {
        check_ajax_referer( 'coachproof_manage_assistant' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Insufficient permissions.' ], 403 );
        }

        // Check prerequisites.
        $api_key = self::get_api_key();
        if ( empty( $api_key ) ) {
            wp_send_json_error( [ 'error' => 'No API key configured.' ] );
        }

        $vs_id = get_option( 'coachproof_vector_store_id', '' );
        if ( empty( $vs_id ) ) {
            wp_send_json_error( [ 'error' => 'Create a Vector Store first (Knowledge Base section above).' ] );
        }

        // Check if one already exists.
        $existing = get_option( 'coachproof_assistant_id', '' );
        if ( $existing ) {
            wp_send_json_error( [ 'error' => 'An assistant already exists: ' . $existing . '. Use "Update" instead.' ] );
        }

        $result = self::create_assistant( $api_key, $vs_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'error' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Update an existing Assistant's config.
     */
    public static function ajax_update_assistant(): void {
        check_ajax_referer( 'coachproof_manage_assistant' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'error' => 'Insufficient permissions.' ], 403 );
        }

        $api_key      = self::get_api_key();
        $assistant_id = get_option( 'coachproof_assistant_id', '' );
        $vs_id        = get_option( 'coachproof_vector_store_id', '' );

        if ( empty( $assistant_id ) ) {
            wp_send_json_error( [ 'error' => 'No assistant exists. Create one first.' ] );
        }

        $result = self::update_assistant( $api_key, $assistant_id, $vs_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'error' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result );
    }

    /**
     * Create a new OpenAI Assistant.
     *
     * @param string $api_key
     * @param string $vs_id   Vector Store ID.
     * @return array|WP_Error
     */
    public static function create_assistant( string $api_key, string $vs_id ) {
        $model        = get_option( 'coachproof_chatbot_model', 'gpt-4o' );
        $system_prompt = get_option(
            'coachproof_chatbot_system_prompt',
            'You are a professional financial coaching assistant. Guide the user towards the most appropriate service based on their needs.'
        );

        $instructions = $system_prompt . self::GROUNDING_RULES;

        $body = [
            'name'           => 'CoachProof Financial Advisor',
            'model'          => $model,
            'instructions'   => $instructions,
            'tools'          => [
                [ 'type' => 'file_search' ],
            ],
            'tool_resources' => [
                'file_search' => [
                    'vector_store_ids' => [ $vs_id ],
                ],
            ],
        ];

        $response = wp_remote_post( self::API_BASE . '/assistants', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'OpenAI-Beta'   => 'assistants=v2',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'network_error', 'Network error: ' . $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status < 200 || $status >= 300 || empty( $data['id'] ) ) {
            $msg = $data['error']['message'] ?? 'Unknown error (HTTP ' . $status . ')';
            return new WP_Error( 'api_error', $msg );
        }

        update_option( 'coachproof_assistant_id', sanitize_text_field( $data['id'] ) );

        error_log( '[coachproof-ai] Assistant created: ' . $data['id'] );

        return [
            'assistant_id' => $data['id'],
            'name'         => $data['name'] ?? 'CoachProof Financial Advisor',
            'model'        => $data['model'] ?? $model,
        ];
    }

    /**
     * Update an existing Assistant's instructions, model, and vector store.
     *
     * @param string $api_key
     * @param string $assistant_id
     * @param string $vs_id
     * @return array|WP_Error
     */
    public static function update_assistant( string $api_key, string $assistant_id, string $vs_id ) {
        $model         = get_option( 'coachproof_chatbot_model', 'gpt-4o' );
        $system_prompt = get_option(
            'coachproof_chatbot_system_prompt',
            'You are a professional financial coaching assistant.'
        );

        $instructions = $system_prompt . self::GROUNDING_RULES;

        $body = [
            'model'          => $model,
            'instructions'   => $instructions,
            'tools'          => [
                [ 'type' => 'file_search' ],
            ],
            'tool_resources' => [
                'file_search' => [
                    'vector_store_ids' => [ $vs_id ],
                ],
            ],
        ];

        $response = wp_remote_request( self::API_BASE . '/assistants/' . $assistant_id, [
            'method'  => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'OpenAI-Beta'   => 'assistants=v2',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'network_error', 'Network error: ' . $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status < 200 || $status >= 300 ) {
            $msg = $data['error']['message'] ?? 'Unknown error (HTTP ' . $status . ')';
            return new WP_Error( 'api_error', $msg );
        }

        error_log( '[coachproof-ai] Assistant updated: ' . $assistant_id );

        return [
            'assistant_id' => $assistant_id,
            'model'        => $data['model'] ?? $model,
            'updated'      => true,
        ];
    }

    /**
     * Get the grounding rules constant (used by Gated_Response_Builder).
     *
     * @return string
     */
    public static function get_grounding_rules(): string {
        return self::GROUNDING_RULES;
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
