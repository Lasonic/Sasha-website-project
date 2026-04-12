<?php
/**
 * CoachProof AI – Admin Settings
 *
 * Registers the admin settings page for provider-agnostic configuration.
 * Protected by the manage_options capability.
 *
 * API key precedence:
 *   1. SASHA_OPENAI_API_KEY constant (wp-config.php / environment)
 *   2. coachproof_chatbot_api_key option  (this settings page)
 *
 * @package CoachProofAI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Coachproof_Settings {

    /** Option group name. */
    private const OPTION_GROUP = 'coachproof_chatbot_settings';

    /** Settings page slug. */
    private const PAGE_SLUG = 'coachproof-ai';

    /**
     * Hook into WordPress admin.
     */
    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    /**
     * Add the settings page under the Settings menu.
     */
    public static function add_menu_page(): void {
        add_options_page(
            'CoachProof AI Settings',
            'CoachProof AI',
            'manage_options',
            self::PAGE_SLUG,
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Register all settings fields.
     */
    public static function register_settings(): void {
        // --- Section: Provider Configuration ---
        add_settings_section(
            'coachproof_provider_section',
            'AI Provider Configuration',
            function () {
                echo '<p>Configure the AI provider that powers the chatbot. Settings here are provider-agnostic where possible.</p>';
            },
            self::PAGE_SLUG
        );

        // API Key (fallback — constant takes precedence)
        register_setting( self::OPTION_GROUP, 'coachproof_chatbot_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        add_settings_field( 'coachproof_chatbot_api_key', 'API Key', function () {
            $has_constant = defined( 'SASHA_OPENAI_API_KEY' ) && SASHA_OPENAI_API_KEY;
            if ( $has_constant ) {
                echo '<input type="text" disabled value="••••••••  (set via constant)" class="regular-text" />';
                echo '<p class="description">The API key is defined as a constant in wp-config.php and takes precedence over this field.</p>';
            } else {
                $value = get_option( 'coachproof_chatbot_api_key', '' );
                printf(
                    '<input type="password" name="coachproof_chatbot_api_key" value="%s" class="regular-text" autocomplete="off" />',
                    esc_attr( $value )
                );
                echo '<p class="description">Alternatively, define <code>SASHA_OPENAI_API_KEY</code> in wp-config.php for better security.</p>';
            }
        }, self::PAGE_SLUG, 'coachproof_provider_section' );

        // Model
        register_setting( self::OPTION_GROUP, 'coachproof_chatbot_model', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'gpt-4.1',
        ) );
        add_settings_field( 'coachproof_chatbot_model', 'Model', function () {
            $value = get_option( 'coachproof_chatbot_model', 'gpt-4.1' );
            printf(
                '<input type="text" name="coachproof_chatbot_model" value="%s" class="regular-text" />',
                esc_attr( $value )
            );
            echo '<p class="description">OpenAI model identifier, e.g. <code>gpt-4.1</code>, <code>gpt-4.1-mini</code>.</p>';
        }, self::PAGE_SLUG, 'coachproof_provider_section' );

        // System Prompt
        register_setting( self::OPTION_GROUP, 'coachproof_chatbot_system_prompt', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => 'You are a professional coaching assistant. Guide the user towards the most appropriate coaching module based on their needs.',
        ) );
        add_settings_field( 'coachproof_chatbot_system_prompt', 'System Prompt', function () {
            $value = get_option(
                'coachproof_chatbot_system_prompt',
                'You are a professional coaching assistant. Guide the user towards the most appropriate coaching module based on their needs.'
            );
            printf(
                '<textarea name="coachproof_chatbot_system_prompt" rows="5" class="large-text">%s</textarea>',
                esc_textarea( $value )
            );
            echo '<p class="description">The system-level instructions sent to the AI on every request.</p>';
        }, self::PAGE_SLUG, 'coachproof_provider_section' );

        // Timeout
        register_setting( self::OPTION_GROUP, 'coachproof_chatbot_timeout', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 30,
        ) );
        add_settings_field( 'coachproof_chatbot_timeout', 'Request Timeout (seconds)', function () {
            $value = get_option( 'coachproof_chatbot_timeout', 30 );
            printf(
                '<input type="number" name="coachproof_chatbot_timeout" value="%d" min="5" max="120" />',
                intval( $value )
            );
        }, self::PAGE_SLUG, 'coachproof_provider_section' );

        // Rate Limit
        register_setting( self::OPTION_GROUP, 'coachproof_chatbot_rate_limit', array(
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 10,
        ) );
        add_settings_field( 'coachproof_chatbot_rate_limit', 'Rate Limit (requests/minute/IP)', function () {
            $value = get_option( 'coachproof_chatbot_rate_limit', 10 );
            printf(
                '<input type="number" name="coachproof_chatbot_rate_limit" value="%d" min="1" max="100" />',
                intval( $value )
            );
            echo '<p class="description">Maximum number of chat requests per minute from a single IP address.</p>';
        }, self::PAGE_SLUG, 'coachproof_provider_section' );
    }

    /**
     * Render the settings page.
     */
    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }
        ?>
        <div class="wrap">
            <h1>CoachProof AI Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( self::PAGE_SLUG );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
