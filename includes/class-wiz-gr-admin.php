<?php
/**
 * Admin Interface
 * 
 * File: includes/class-wiz-gr-admin.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wiz_GR_Admin {
    
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
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_wiz_gr_fetch_reviews', array($this, 'handle_fetch_reviews'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }
    
    /**
     * Add admin menu page
     */
    public function add_menu_page() {
        add_options_page(
            __('Wiz Google Reviews', 'wiz-google-reviews'),
            __('Google Reviews', 'wiz-google-reviews'),
            'manage_options',
            'wiz-google-reviews',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wiz_gr_settings', 'wiz_gr_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        register_setting('wiz_gr_settings', 'wiz_gr_place_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        
        register_setting('wiz_gr_settings', 'wiz_gr_cache_duration', array(
            'type' => 'integer',
            'default' => 24,
            'sanitize_callback' => 'absint',
        ));
    }
    
    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        if ($hook !== 'settings_page_wiz-google-reviews') {
            return;
        }
        
        wp_enqueue_style(
            'wiz-gr-admin',
            WIZ_GR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WIZ_GR_VERSION
        );
    }
    
    /**
     * Handle fetch reviews action
     */
    public function handle_fetch_reviews() {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wiz_gr_fetch_reviews')) {
            wp_die(__('Security check failed.', 'wiz-google-reviews'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wiz-google-reviews'));
        }
        
        // Fetch reviews
        $api = Wiz_GR_API::get_instance();
        $result = $api->fetch_reviews();
        
        // Redirect with message
        $redirect_url = admin_url('options-general.php?page=wiz-google-reviews');
        
        if (is_wp_error($result)) {
            $redirect_url = add_query_arg(array(
                'wiz_gr_message' => 'error',
                'wiz_gr_error' => urlencode($result->get_error_message()),
            ), $redirect_url);
        } else {
            $redirect_url = add_query_arg(array(
                'wiz_gr_message' => 'success',
                'wiz_gr_count' => $result['count'],
            ), $redirect_url);
        }
        
        wp_safe_redirect($redirect_url);
        exit;
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'wiz-google-reviews'));
        }
        
        // Get cache info
        $cache_info = Wiz_GR_Cache::get_cache_info();
        $last_fetch = get_option('wiz_gr_last_fetch', 0);
        
        ?>
        <div class="wrap wiz-gr-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php $this->render_messages(); ?>
            
            <div class="wiz-gr-layout">
                <div class="wiz-gr-main">
                    <div class="wiz-gr-card">
                        <h2><?php _e('API Configuration', 'wiz-google-reviews'); ?></h2>
                        
                        <form method="post" action="options.php">
                            <?php
                            settings_fields('wiz_gr_settings');
                            ?>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="wiz_gr_api_key"><?php _e('Google Places API Key', 'wiz-google-reviews'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" 
                                               id="wiz_gr_api_key" 
                                               name="wiz_gr_api_key" 
                                               value="<?php echo esc_attr(get_option('wiz_gr_api_key')); ?>" 
                                               class="regular-text"
                                               placeholder="AIzaSy...">
                                        <p class="description">
                                            <?php _e('Your Google Places API key. Get one from', 'wiz-google-reviews'); ?> 
                                            <a href="https://console.cloud.google.com/apis" target="_blank">Google Cloud Console</a>
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="wiz_gr_place_id"><?php _e('Google Place ID', 'wiz-google-reviews'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" 
                                               id="wiz_gr_place_id" 
                                               name="wiz_gr_place_id" 
                                               value="<?php echo esc_attr(get_option('wiz_gr_place_id')); ?>" 
                                               class="regular-text"
                                               placeholder="ChIJ...">
                                        <p class="description">
                                            <?php _e('Your Google Business Place ID. Find it using', 'wiz-google-reviews'); ?> 
                                            <a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank">Place ID Finder</a>
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="wiz_gr_cache_duration"><?php _e('Cache Duration (hours)', 'wiz-google-reviews'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" 
                                               id="wiz_gr_cache_duration" 
                                               name="wiz_gr_cache_duration" 
                                               value="<?php echo esc_attr(get_option('wiz_gr_cache_duration', 24)); ?>" 
                                               min="1" 
                                               max="168"
                                               class="small-text">
                                        <p class="description">
                                            <?php _e('How long to cache reviews before allowing a refresh (1-168 hours). Default: 24 hours.', 'wiz-google-reviews'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <?php submit_button(__('Save Settings', 'wiz-google-reviews')); ?>
                        </form>
                    </div>
                    
                    <div class="wiz-gr-card">
                        <h2><?php _e('Fetch Reviews', 'wiz-google-reviews'); ?></h2>
                        
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="wiz_gr_fetch_reviews">
                            <?php wp_nonce_field('wiz_gr_fetch_reviews'); ?>
                            
                            <p>
                                <?php _e('Manually fetch reviews from Google. This is limited to once every 24 hours to control API costs.', 'wiz-google-reviews'); ?>
                            </p>
                            
                            <?php
                            $can_fetch = (time() - $last_fetch) >= (get_option('wiz_gr_cache_duration', 24) * HOUR_IN_SECONDS);
                            
                            if ($can_fetch) {
                                submit_button(__('Fetch Reviews Now', 'wiz-google-reviews'), 'primary', 'submit', false);
                            } else {
                                $time_remaining = (get_option('wiz_gr_cache_duration', 24) * HOUR_IN_SECONDS) - (time() - $last_fetch);
                                $hours_remaining = ceil($time_remaining / HOUR_IN_SECONDS);
                                ?>
                                <button type="button" class="button button-primary" disabled>
                                    <?php echo esc_html(sprintf(__('Available in %d hours', 'wiz-google-reviews'), $hours_remaining)); ?>
                                </button>
                                <?php
                            }
                            ?>
                        </form>
                    </div>
                    
                    <div class="wiz-gr-card">
                        <h2><?php _e('Display Reviews', 'wiz-google-reviews'); ?></h2>
                        <p><?php _e('Use this shortcode to display reviews on any page or post:', 'wiz-google-reviews'); ?></p>
                        <code class="wiz-gr-shortcode">[wiz_google_reviews]</code>
                    </div>
                </div>
                
                <div class="wiz-gr-sidebar">
                    <div class="wiz-gr-card">
                        <h3><?php _e('Cache Status', 'wiz-google-reviews'); ?></h3>
                        
                        <div class="wiz-gr-stat">
                            <span class="wiz-gr-stat-label"><?php _e('Cached Reviews:', 'wiz-google-reviews'); ?></span>
                            <span class="wiz-gr-stat-value"><?php echo esc_html($cache_info['count']); ?></span>
                        </div>
                        
                        <?php if ($cache_info['cached_at'] > 0) : ?>
                        <div class="wiz-gr-stat">
                            <span class="wiz-gr-stat-label"><?php _e('Last Updated:', 'wiz-google-reviews'); ?></span>
                            <span class="wiz-gr-stat-value">
                                <?php echo esc_html(human_time_diff($cache_info['cached_at'], time()) . ' ago'); ?>
                            </span>
                        </div>
                        
                        <div class="wiz-gr-stat">
                            <span class="wiz-gr-stat-label"><?php _e('Cache Age:', 'wiz-google-reviews'); ?></span>
                            <span class="wiz-gr-stat-value">
                                <?php echo esc_html(sprintf(__('%s hours', 'wiz-google-reviews'), $cache_info['age_hours'])); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="wiz-gr-card">
                        <h3><?php _e('Free Version Limits', 'wiz-google-reviews'); ?></h3>
                        <ul class="wiz-gr-limits">
                            <li>✓ <?php _e('1 Google Business', 'wiz-google-reviews'); ?></li>
                            <li>✓ <?php _e('10 Reviews Maximum', 'wiz-google-reviews'); ?></li>
                            <li>✓ <?php _e('24-hour Refresh Limit', 'wiz-google-reviews'); ?></li>
                            <li>✓ <?php _e('Local Caching', 'wiz-google-reviews'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="wiz-gr-card wiz-gr-help">
                        <h3><?php _e('Need Help?', 'wiz-google-reviews'); ?></h3>
                        <p><?php _e('Documentation and support:', 'wiz-google-reviews'); ?></p>
                        <ul>
                            <li><a href="https://wiznest.com/docs" target="_blank"><?php _e('Documentation', 'wiz-google-reviews'); ?></a></li>
                            <li><a href="https://wiznest.com/support" target="_blank"><?php _e('Support', 'wiz-google-reviews'); ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render admin messages
     */
    private function render_messages() {
        if (isset($_GET['wiz_gr_message'])) {
            $message_type = sanitize_key($_GET['wiz_gr_message']);
            
            if ($message_type === 'success') {
                $count = isset($_GET['wiz_gr_count']) ? absint($_GET['wiz_gr_count']) : 0;
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php echo esc_html(sprintf(__('Successfully fetched %d reviews from Google!', 'wiz-google-reviews'), $count)); ?>
                    </p>
                </div>
                <?php
            } elseif ($message_type === 'error') {
                $error = isset($_GET['wiz_gr_error']) ? sanitize_text_field(urldecode($_GET['wiz_gr_error'])) : __('An error occurred.', 'wiz-google-reviews');
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html($error); ?></p>
                </div>
                <?php
            }
        }
        
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Settings saved successfully!', 'wiz-google-reviews'); ?></p>
            </div>
            <?php
        }
    }
}