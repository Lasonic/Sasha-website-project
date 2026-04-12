<?php
/**
 * Plugin Name: CoachProof AI
 * Plugin URI:  https://github.com/Lasonic/Sasha-website-project
 * Description: AI-powered coaching chatbot — guides visitors to appropriate coaching modules.
 * Version:     0.1.0-dev
 * Author:      Pawel & Sasha
 * License:     GPL-2.0-or-later
 * Text Domain: coachproof-ai
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access.
}

// ------------------------------------------------------------------
// Constants
// ------------------------------------------------------------------
define( 'COACHPROOF_AI_VERSION', '0.1.0-dev' );
define( 'COACHPROOF_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'COACHPROOF_AI_URL', plugin_dir_url( __FILE__ ) );

// ------------------------------------------------------------------
// Autoload includes
// ------------------------------------------------------------------
require_once COACHPROOF_AI_DIR . 'includes/interface-chat-provider.php';
require_once COACHPROOF_AI_DIR . 'includes/class-chat-result.php';
require_once COACHPROOF_AI_DIR . 'includes/class-openai-responses-provider.php';
require_once COACHPROOF_AI_DIR . 'includes/class-coachproof-settings.php';
require_once COACHPROOF_AI_DIR . 'includes/class-coachproof-rest-api.php';

// ------------------------------------------------------------------
// Shortcode: [coachproof_chatbot]
// Renders the mount point container that the frontend JS attaches to.
// ------------------------------------------------------------------
add_shortcode( 'coachproof_chatbot', function () {
    // Generate a nonce for the REST request so the frontend can authenticate.
    $nonce = wp_create_nonce( 'wp_rest' );

    return sprintf(
        '<div id="coachproof-ai" class="coachproof-ai-container" data-nonce="%s" data-rest-url="%s"></div>',
        esc_attr( $nonce ),
        esc_url( rest_url( 'coachproof-ai/v1/message' ) )
    );
} );

// ------------------------------------------------------------------
// Enqueue frontend assets only when the shortcode is present.
// ------------------------------------------------------------------
add_action( 'wp_enqueue_scripts', function () {
    global $post;

    if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'coachproof_chatbot' ) ) {
        return;
    }

    wp_enqueue_style(
        'coachproof-chat-widget',
        COACHPROOF_AI_URL . 'assets/css/chat-widget.css',
        array(),
        COACHPROOF_AI_VERSION
    );

    wp_enqueue_script(
        'coachproof-chat-widget',
        COACHPROOF_AI_URL . 'assets/js/chat-widget.js',
        array(),
        COACHPROOF_AI_VERSION,
        true // Load in footer.
    );
} );

// ------------------------------------------------------------------
// Initialise admin settings.
// ------------------------------------------------------------------
if ( is_admin() ) {
    Coachproof_Settings::init();
}

// ------------------------------------------------------------------
// Initialise REST API routes.
// ------------------------------------------------------------------
add_action( 'rest_api_init', array( 'Coachproof_REST_API', 'register_routes' ) );
