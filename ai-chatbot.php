<?php
/**
 * Plugin Name: AI Chatbot
 * Plugin URI: https://github.com/ToughCrab24/smart-search-rag-chatbot
 * Description: AI chatbot powered by WP Engine's Managed Vector Database
 * Version: 1.0.0
 * Author: Kellen Mace
 * Author URI: https://kellenmace.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: ai-chatbot
 * Domain Path: /languages
 * Requires at least: 6.7
 * Tested up to: 6.7
 * Requires PHP: 8.1
 *
 * @package AIChatbot
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants
define( 'AI_CHATBOT_VERSION', '1.0.0' );
define( 'AI_CHATBOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_CHATBOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_CHATBOT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Includes
require_once AI_CHATBOT_PLUGIN_DIR . 'includes/rest-api.php';
require_once AI_CHATBOT_PLUGIN_DIR . 'includes/settings.php';

// WordPress hooks
add_action( 'init', 'ai_chatbot_load_text_domain' );
add_action( 'wp_enqueue_scripts', 'ai_chatbot_enqueue_scripts' );
add_action( 'wp_footer', 'ai_chatbot_render_widget' );

/**
 * Load text domain for translations.
 */
function ai_chatbot_load_text_domain() {
    load_plugin_textdomain( 'ai-chatbot', false, dirname( AI_CHATBOT_PLUGIN_BASENAME ) . '/languages' );
}

/**
 * Enqueue frontend scripts and styles.
 */
function ai_chatbot_enqueue_scripts() {
    wp_enqueue_script(
        'ai-chatbot-app',
        AI_CHATBOT_PLUGIN_URL . 'dist/ai-chatbot.js',
        [],
        AI_CHATBOT_VERSION,
        [ 'strategy' => 'defer' ]
    );

    wp_enqueue_style(
        'ai-chatbot-styles',
        AI_CHATBOT_PLUGIN_URL . 'dist/ai-chatbot.css',
        [],
        AI_CHATBOT_VERSION
    );

    // Localize script for REST API endpoint
    wp_localize_script(
        'ai-chatbot-app',
        'aiChatbot',
        [
            'chatEndpoint' => get_rest_url( null, AI_CHATBOT_ROUTE_NAMESPACE . AI_CHATBOT_ROUTE ),
        ]
    );
}

/**
 * Render chatbot widget in footer.
 */
function ai_chatbot_render_widget() {
    // Only show on frontend
    if ( is_admin() ) {
        return;
    }

    // AI chatbot gets injected here
    ?>
    <div id="ai-chatbot-widget"></div>
    <?php
}
