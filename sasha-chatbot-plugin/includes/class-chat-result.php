<?php
/**
 * Chat Result Value Object
 *
 * Normalised response returned by any Chat_Provider_Interface implementation.
 * The REST layer and frontend consume this shape exclusively.
 *
 * @package SashaChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Chat_Result {

    /** @var string The AI-generated reply text. */
    public string $reply_text;

    /**
     * @var string UI state hint for the frontend.
     * One of: idle, loading, streaming, answer, auth-required, error, escalation.
     */
    public string $ui_state;

    /**
     * @var string Delivery mode.
     * 'single' for a complete response, 'stream' if streaming is active.
     */
    public string $delivery_mode;

    /** @var string Conversation identifier for multi-turn continuity. */
    public string $conversation_id;

    /** @var string|null Machine-readable error code (null on success). */
    public ?string $error_code;

    /** @var string Unique trace ID for server-side log correlation. */
    public string $trace_id;

    /** @var array|null Optional action payloads for the frontend. */
    public ?array $actions;

    /** @var bool Whether this response requires authentication to view fully. */
    public bool $requires_auth;

    /**
     * @param string      $reply_text
     * @param string      $ui_state
     * @param string      $delivery_mode
     * @param string      $conversation_id
     * @param string|null $error_code
     * @param string      $trace_id
     * @param array|null  $actions
     * @param bool        $requires_auth
     */
    public function __construct(
        string  $reply_text,
        string  $ui_state       = 'answer',
        string  $delivery_mode  = 'single',
        string  $conversation_id = '',
        ?string $error_code     = null,
        string  $trace_id       = '',
        ?array  $actions        = null,
        bool    $requires_auth  = false
    ) {
        $this->reply_text      = $reply_text;
        $this->ui_state        = $ui_state;
        $this->delivery_mode   = $delivery_mode;
        $this->conversation_id = $conversation_id ?: wp_generate_uuid4();
        $this->error_code      = $error_code;
        $this->trace_id        = $trace_id ?: wp_generate_uuid4();
        $this->actions         = $actions;
        $this->requires_auth   = $requires_auth;
    }

    /**
     * Serialise to the REST response contract shape.
     *
     * @return array
     */
    public function to_array(): array {
        return array(
            'conversation_id' => $this->conversation_id,
            'reply_text'      => $this->reply_text,
            'ui_state'        => $this->ui_state,
            'delivery_mode'   => $this->delivery_mode,
            'actions'         => $this->actions,
            'requires_auth'   => $this->requires_auth,
            'error_code'      => $this->error_code,
            'trace_id'        => $this->trace_id,
        );
    }
}
