<?php
/**
 * AI Chatbot Settings Page
 *
 * @package AIChatbot
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// WordPress hooks
add_action( 'admin_menu', 'ai_chatbot_add_admin_menu' );
add_action( 'admin_init', 'ai_chatbot_settings_init' );

/**
 * Add admin menu for plugin settings.
 */
function ai_chatbot_add_admin_menu() {
    add_options_page(
        __( 'AI Chatbot Settings', 'ai-chatbot' ),
        __( 'AI Chatbot', 'ai-chatbot' ),
        'manage_options',
        'ai-chatbot-settings',
        'ai_chatbot_settings_page'
    );
}

/**
 * Render settings page.
 */
function ai_chatbot_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        
        <?php
        // Show success message if settings were saved
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'Settings saved successfully!', 'ai-chatbot' ); ?></p>
            </div>
            <?php
        }
        ?>
        
        <form action="options.php" method="post">
            <?php
            settings_fields( 'ai_chatbot_settings_group' );
            do_settings_sections( 'ai-chatbot-settings' );
            submit_button( __( 'Save Settings', 'ai-chatbot' ) );
            ?>
        </form>
    </div>
    <?php
}

/**
 * Initialize plugin settings.
 */
function ai_chatbot_settings_init() {
    register_setting( 'ai_chatbot_settings_group', 'ai_chatbot_google_api_key' );

    add_settings_section(
        'ai_chatbot_settings_section',
        __( 'API Configuration', 'ai-chatbot' ),
        'ai_chatbot_settings_section_callback',
        'ai-chatbot-settings'
    );

    add_settings_field(
        'google_api_key',
        __( 'Google Generative AI API Key', 'ai-chatbot' ),
        'ai_chatbot_google_api_key_render',
        'ai-chatbot-settings',
        'ai_chatbot_settings_section'
    );
}

/**
 * Settings section callback.
 */
function ai_chatbot_settings_section_callback() {
    echo '<p>' . esc_html__( 'Configure the Google Generative AI API key for your AI Chatbot.', 'ai-chatbot' ) . '</p>';

    $url = ai_chatbot_get_smart_search_url();
    $access_token = ai_chatbot_get_smart_search_access_token();

    // Warn the user if WP Engine Smart Search settings are not found
    if ( ! $url || ! $access_token ) {
        ?>
        <div class="notice notice-error inline">
            <p>
                <strong><?php echo esc_html__( 'WP Engine Smart Search settings not found.', 'ai-chatbot' ); ?></strong><br>
                <?php echo esc_html__( 'Please configure the WP Engine Smart Search plugin in order to use the AI Chatbot.', 'ai-chatbot' ); ?>
            </p>
        </div>
        <?php
    }
}

/**
 * Render Google API Key field.
 */
function ai_chatbot_google_api_key_render() {
    $value = ai_chatbot_get_google_api_key();
    ?>
    <input type="text" 
           name="ai_chatbot_google_api_key" 
           value="<?php echo esc_attr( $value ); ?>" 
           class="regular-text"
           placeholder="<?php esc_attr_e( 'Enter your Google Generative AI API Key', 'ai-chatbot' ); ?>" />
    <p class="description">
        <?php esc_html_e( 'Your Google Generative AI API key for powering the chatbot responses.', 'ai-chatbot' ); ?>
        <br>
        <?php 
        printf( 
            esc_html__( 'Get your API key from the %s.', 'ai-chatbot' ),
            '<a href="https://aistudio.google.com/app/apikey" target="_blank">' . esc_html__( 'Google AI Studio', 'ai-chatbot' ) . '</a>'
        ); 
        ?>
    </p>
    <?php
}

/**
 * Get Google Generative AI API Key.
 *
 * @return string The API key.
 */
function ai_chatbot_get_google_api_key() {
    return get_option( 'ai_chatbot_google_api_key', '' );
}

/**
 * Get Smart Search URL from WP Engine Content Engine settings.
 *
 * @return string The search URL.
 */
function ai_chatbot_get_smart_search_url() {
    $wpe_settings = get_option( 'wpe_content_engine_option_name', [] );
    return isset( $wpe_settings['url'] ) ? $wpe_settings['url'] : '';
}

/**
 * Get Smart Search Access Token from WP Engine Content Engine settings.
 *
 * @return string The access token.
 */
function ai_chatbot_get_smart_search_access_token() {
    $wpe_settings = get_option( 'wpe_content_engine_option_name', [] );
    return isset( $wpe_settings['access_token'] ) ? $wpe_settings['access_token'] : '';
}
