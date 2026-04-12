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

        // --- Section: Knowledge Base ---
        add_settings_section(
            'coachproof_kb_section',
            'Knowledge Base',
            function () {
                echo '<p>Configure the OpenAI Vector Store used for document-backed answers.</p>';
            },
            self::PAGE_SLUG
        );

        // Vector Store ID (read-only display + provisioning button).
        register_setting( self::OPTION_GROUP, 'coachproof_vector_store_id', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        add_settings_field( 'coachproof_vector_store_id', 'Vector Store', function () {
            $vs_id = get_option( 'coachproof_vector_store_id', '' );
            if ( $vs_id ) {
                printf(
                    '<code id="coachproof-vs-id-display">%s</code>',
                    esc_html( $vs_id )
                );
                echo '<input type="hidden" name="coachproof_vector_store_id" value="' . esc_attr( $vs_id ) . '" />';
                echo '<p class="description">Your knowledge documents will be synced to this Vector Store.</p>';
                echo '<p><button type="button" class="button button-link-delete" id="coachproof-delete-vs-btn">Delete &amp; Recreate</button></p>';
            } else {
                echo '<div id="coachproof-vs-create-area">';
                echo '<button type="button" class="button button-primary" id="coachproof-create-vs-btn">Create Vector Store</button>';
                echo '<span id="coachproof-vs-spinner" class="spinner" style="float:none;"></span>';
                echo '<p class="description">Creates a new Vector Store in your OpenAI account. Requires a valid API key.</p>';
                echo '</div>';
                echo '<div id="coachproof-vs-created" style="display:none;">';
                echo '<code id="coachproof-vs-id-display"></code>';
                echo '<p class="description" style="color:#16a34a;">✅ Vector Store created successfully!</p>';
                echo '</div>';
            }
            echo '<p id="coachproof-vs-error" style="color:#b91c1c;display:none;"></p>';
        }, self::PAGE_SLUG, 'coachproof_kb_section' );

        // Assistant ID (read-only display + provisioning button).
        register_setting( self::OPTION_GROUP, 'coachproof_assistant_id', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        add_settings_field( 'coachproof_assistant_id', 'AI Assistant', function () {
            $asst_id = get_option( 'coachproof_assistant_id', '' );
            if ( $asst_id ) {
                printf( '<code id="coachproof-asst-id-display">%s</code>', esc_html( $asst_id ) );
                echo '<input type="hidden" name="coachproof_assistant_id" value="' . esc_attr( $asst_id ) . '" />';
                echo '<p class="description">Answers will be grounded using this assistant\'s file_search tool.</p>';
                echo '<p><button type="button" class="button" id="coachproof-update-asst-btn">Update Assistant (re-sync settings)</button>';
                echo '  <span id="coachproof-asst-update-feedback" style="margin-left:8px;"></span></p>';
            } else {
                $vs_id = get_option( 'coachproof_vector_store_id', '' );
                echo '<div id="coachproof-asst-create-area">';
                if ( $vs_id ) {
                    echo '<button type="button" class="button button-primary" id="coachproof-create-asst-btn">Create Assistant</button>';
                    echo '<span id="coachproof-asst-spinner" class="spinner" style="float:none;"></span>';
                    echo '<p class="description">Creates an AI assistant linked to your Vector Store for grounded answering.</p>';
                } else {
                    echo '<p class="description" style="color:#854d0e;">⚠ Create a Vector Store first (above), then create the Assistant.</p>';
                }
                echo '</div>';
                echo '<div id="coachproof-asst-created" style="display:none;">';
                echo '<code id="coachproof-asst-id-display"></code>';
                echo '<p class="description" style="color:#16a34a;">✅ Assistant created successfully!</p>';
                echo '</div>';
            }
            echo '<p id="coachproof-asst-error" style="color:#b91c1c;display:none;"></p>';
        }, self::PAGE_SLUG, 'coachproof_kb_section' );
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

        <script>
        jQuery(function($) {
            var assistantNonce = '<?php echo esc_js( wp_create_nonce( 'coachproof_manage_assistant' ) ); ?>';

            // --- Create Vector Store ---
            $('#coachproof-create-vs-btn').on('click', function(e) {
                e.preventDefault();
                var $btn     = $(this);
                var $spinner = $('#coachproof-vs-spinner');
                var $error   = $('#coachproof-vs-error');

                $btn.prop('disabled', true);
                $spinner.addClass('is-active');
                $error.hide();

                $.post(ajaxurl, {
                    action: 'coachproof_create_vector_store',
                    _ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'coachproof_create_vs' ) ); ?>'
                }, function(response) {
                    $spinner.removeClass('is-active');
                    if (response.success) {
                        $('#coachproof-vs-create-area').hide();
                        $('#coachproof-vs-id-display').text(response.data.vector_store_id);
                        $('#coachproof-vs-created').show();
                    } else {
                        $error.text('❌ ' + (response.data.error || 'Failed to create Vector Store.')).show();
                        $btn.prop('disabled', false);
                    }
                }).fail(function() {
                    $spinner.removeClass('is-active');
                    $error.text('❌ Network error.').show();
                    $btn.prop('disabled', false);
                });
            });

            // --- Delete & Recreate VS ---
            $('#coachproof-delete-vs-btn').on('click', function(e) {
                e.preventDefault();
                if (!confirm('This will delete the current Vector Store reference. Documents will need to be re-synced. Continue?')) return;
                $('input[name="coachproof_vector_store_id"]').val('');
                $('form').submit();
            });

            // --- Create Assistant ---
            $('#coachproof-create-asst-btn').on('click', function(e) {
                e.preventDefault();
                var $btn     = $(this);
                var $spinner = $('#coachproof-asst-spinner');
                var $error   = $('#coachproof-asst-error');

                $btn.prop('disabled', true);
                $spinner.addClass('is-active');
                $error.hide();

                $.post(ajaxurl, {
                    action: 'coachproof_create_assistant',
                    _ajax_nonce: assistantNonce
                }, function(response) {
                    $spinner.removeClass('is-active');
                    if (response.success) {
                        $('#coachproof-asst-create-area').hide();
                        $('#coachproof-asst-id-display').text(response.data.assistant_id);
                        $('#coachproof-asst-created').show();
                    } else {
                        $error.text('❌ ' + (response.data.error || 'Failed to create assistant.')).show();
                        $btn.prop('disabled', false);
                    }
                }).fail(function() {
                    $spinner.removeClass('is-active');
                    $error.text('❌ Network error.').show();
                    $btn.prop('disabled', false);
                });
            });

            // --- Update Assistant ---
            $('#coachproof-update-asst-btn').on('click', function(e) {
                e.preventDefault();
                var $btn      = $(this);
                var $feedback = $('#coachproof-asst-update-feedback');
                var $error    = $('#coachproof-asst-error');

                $btn.prop('disabled', true);
                $feedback.text('Updating…').css('color','#6b7280');
                $error.hide();

                $.post(ajaxurl, {
                    action: 'coachproof_update_assistant',
                    _ajax_nonce: assistantNonce
                }, function(response) {
                    if (response.success) {
                        $feedback.text('✅ Updated').css('color','#16a34a');
                    } else {
                        $error.text('❌ ' + (response.data.error || 'Update failed.')).show();
                        $feedback.text('');
                    }
                    $btn.prop('disabled', false);
                }).fail(function() {
                    $error.text('❌ Network error.').show();
                    $feedback.text('');
                    $btn.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }
}

