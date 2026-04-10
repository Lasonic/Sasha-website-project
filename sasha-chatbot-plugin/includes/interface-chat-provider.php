<?php
/**
 * Chat Provider Interface
 *
 * All AI providers must implement this interface.
 * The rest of the plugin never touches a provider directly —
 * it always goes through this contract.
 *
 * @package SashaChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Chat_Provider_Interface {

    /**
     * Send a user message to the AI provider and receive a normalised result.
     *
     * @param string $message      The visitor's chat message (already sanitised).
     * @param array  $context      Optional context: page_context, conversation_id, etc.
     * @return Chat_Result          A normalised value object.
     */
    public function send_message( string $message, array $context = array() ): Chat_Result;
}
