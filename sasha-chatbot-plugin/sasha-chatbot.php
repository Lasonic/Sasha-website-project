<?php
/**
 * Plugin Name: Sasha Chatbot
 * Plugin URI:  https://github.com/Lasonic/Sasha-website-project
 * Description: AI-powered coaching chatbot — guides visitors to appropriate coaching modules.
 * Version:     0.1.0-dev
 * Author:      Pawel & Sasha
 * License:     GPL-2.0-or-later
 * Text Domain: sasha-chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access.
}

// ------------------------------------------------------------------
// Constants
// ------------------------------------------------------------------
define( 'SASHA_CHATBOT_VERSION', '0.1.0-dev' );
define( 'SASHA_CHATBOT_DIR', plugin_dir_path( __FILE__ ) );
define( 'SASHA_CHATBOT_URL', plugin_dir_url( __FILE__ ) );

// ------------------------------------------------------------------
// Autoload includes
// ------------------------------------------------------------------
require_once SASHA_CHATBOT_DIR . 'includes/interface-chat-provider.php';
require_once SASHA_CHATBOT_DIR . 'includes/class-chat-result.php';
require_once SASHA_CHATBOT_DIR . 'includes/class-openai-responses-provider.php';
require_once SASHA_CHATBOT_DIR . 'includes/class-sasha-settings.php';
require_once SASHA_CHATBOT_DIR . 'includes/class-sasha-rest-api.php';

// ------------------------------------------------------------------
// Shortcode: [sasha_chatbot]
// Renders the mount point container that the frontend JS attaches to.
// ------------------------------------------------------------------
add_shortcode( 'sasha_chatbot', function () {
    // Generate a nonce for the REST request so the frontend can authenticate.
    $nonce = wp_create_nonce( 'wp_rest' );

    return sprintf(
        '<div id="sasha-chatbot" class="sasha-chatbot-container" data-nonce="%s" data-rest-url="%s"></div>',
        esc_attr( $nonce ),
        esc_url( rest_url( 'sasha-chatbot/v1/message' ) )
    );
} );

// ------------------------------------------------------------------
// Enqueue frontend assets only when the shortcode is present.
// ------------------------------------------------------------------
add_action( 'wp_enqueue_scripts', function () {
    global $post;

    if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'sasha_chatbot' ) ) {
        return;
    }

    wp_enqueue_style(
        'sasha-chat-widget',
        SASHA_CHATBOT_URL . 'assets/css/chat-widget.css',
        array(),
        SASHA_CHATBOT_VERSION
    );

    wp_enqueue_script(
        'sasha-chat-widget',
        SASHA_CHATBOT_URL . 'assets/js/chat-widget.js',
        array(),
        SASHA_CHATBOT_VERSION,
        true // Load in footer.
    );
} );

// ------------------------------------------------------------------
// Initialise admin settings.
// ------------------------------------------------------------------
if ( is_admin() ) {
    Sasha_Settings::init();
}

// ------------------------------------------------------------------
// Initialise REST API routes.
// ------------------------------------------------------------------
add_action( 'rest_api_init', array( 'Sasha_REST_API', 'register_routes' ) );
