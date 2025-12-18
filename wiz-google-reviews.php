<?php
/**
 * Plugin Name: Wiz Google Reviews
 * Plugin URI: https://wiznest.com
 * Description: Display Google Business reviews with customizable layouts and styles
 * Version: 2.0.0
 * Author: WizNest
 * Author URI: https://wiznest.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wiz-google-reviews
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WIZ_GR_VERSION', '2.0.0');
define('WIZ_GR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WIZ_GR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WIZ_GR_PLUGIN_FILE', __FILE__);

// IMPORTANT: Add your Google Places API Key here
define('WIZ_GR_API_KEY', 'AIzaSyBc1BcxkqvOqNuPMQ3ecHWPswrdxok2IXM');

/**
 * Main Plugin Class
 */
class Wiz_Google_Reviews {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once WIZ_GR_PLUGIN_DIR . 'includes/class-wiz-gr-api.php';
        require_once WIZ_GR_PLUGIN_DIR . 'includes/class-wiz-gr-cache.php';
        require_once WIZ_GR_PLUGIN_DIR . 'includes/class-wiz-gr-oauth.php';
        require_once WIZ_GR_PLUGIN_DIR . 'includes/class-wiz-gr-admin.php';
        require_once WIZ_GR_PLUGIN_DIR . 'includes/class-wiz-gr-shortcode.php';
        require_once WIZ_GR_PLUGIN_DIR . 'includes/class-wiz-gr-widget-config.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database table for cache
        Wiz_GR_Cache::create_table();
        
        // Set default options
        $defaults = array(
            'place_id' => '',
            'cache_duration' => 24,
            'max_reviews' => 10,
            'last_fetch' => 0,
            'google_connected' => false,
            'layout' => 'grid',
            'theme' => 'light',
            'columns' => 3,
            'show_avatar' => true,
            'show_date' => true,
            'text_length' => 200,
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option('wiz_gr_' . $key) === false) {
                add_option('wiz_gr_' . $key, $value);
            }
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        wp_clear_scheduled_hook('wiz_gr_auto_refresh');
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('wiz-google-reviews', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize admin
        if (is_admin()) {
            Wiz_GR_Admin::get_instance();
            Wiz_GR_Widget_Config::get_instance();
        }
        
        // Initialize OAuth handler
        Wiz_GR_OAuth::get_instance();
        
        // Initialize shortcode
        Wiz_GR_Shortcode::get_instance();
    }
}

// Initialize plugin
function wiz_google_reviews() {
    return Wiz_Google_Reviews::get_instance();
}

// Start the plugin
wiz_google_reviews();