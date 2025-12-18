<?php
/**
 * Widget Configurator Interface
 * 
 * File: includes/class-wiz-gr-widget-config.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wiz_GR_Widget_Config {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_config_page'));
        add_action('admin_post_wiz_gr_save_business', array($this, 'save_business'));
        add_action('admin_post_wiz_gr_save_layout', array($this, 'save_layout'));
        add_action('admin_post_wiz_gr_save_style', array($this, 'save_style'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_wiz_gr_search_business', array($this, 'ajax_search_business'));
    }
    
    public function add_config_page() {
        add_menu_page(
            __('Google Reviews', 'wiz-google-reviews'),
            __('Google Reviews', 'wiz-google-reviews'),
            'manage_options',
            'wiz-google-reviews-config',
            array($this, 'render_config_page'),
            'dashicons-star-filled',
            30
        );
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'wiz-google-reviews-config') === false) {
            return;
        }
        
        wp_enqueue_style('wiz-gr-config', WIZ_GR_PLUGIN_URL . 'assets/css/config.css', array(), WIZ_GR_VERSION);
        wp_enqueue_script('wiz-gr-config', WIZ_GR_PLUGIN_URL . 'assets/js/config.js', array('jquery'), WIZ_GR_VERSION, true);
        
        wp_localize_script('wiz-gr-config', 'wizGR', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
//            'nonce' => wp_create_nonce('wiz_gr_ajax'),
            'ajax_nonce'    => wp_create_nonce( 'wiz_gr_ajax_nonce' ),
            'connect_nonce' => wp_create_nonce( 'wiz_gr_connect_business' ),
            'adminPostUrl' => admin_url( 'admin-post.php' ),
        ));
    }
    
    public function ajax_search_business() {
        check_ajax_referer('wiz_gr_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $results = Wiz_GR_OAuth::search_business($query);
        
        if (is_wp_error($results)) {
            wp_send_json_error($results->get_error_message());
        }
        
        wp_send_json_success($results);
    }
    
    public function save_business() {
        check_admin_referer('wiz_gr_save_business');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $place_id = isset($_POST['place_id']) ? sanitize_text_field($_POST['place_id']) : '';
        $business_name = isset($_POST['business_name']) ? sanitize_text_field($_POST['business_name']) : '';
        
        update_option('wiz_gr_place_id', $place_id);
        update_option('wiz_gr_business_name', $business_name);
        update_option('wiz_gr_google_connected', true);
        
        // Fetch reviews immediately
        $api = Wiz_GR_API::get_instance();
        $api->fetch_reviews();
        
        wp_safe_redirect(admin_url('admin.php?page=wiz-google-reviews-config&step=3'));
        exit;
    }
    
    public function save_layout() {
        check_admin_referer('wiz_gr_save_layout');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $layout = isset($_POST['layout']) ? sanitize_text_field($_POST['layout']) : 'grid';
        $columns = isset($_POST['columns']) ? absint($_POST['columns']) : 3;
        
        update_option('wiz_gr_layout', $layout);
        update_option('wiz_gr_columns', $columns);
        
        wp_safe_redirect(admin_url('admin.php?page=wiz-google-reviews-config&step=4'));
        exit;
    }
    
    public function save_style() {
        check_admin_referer('wiz_gr_save_style');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $theme = isset($_POST['theme']) ? sanitize_text_field($_POST['theme']) : 'light';
        $show_avatar = isset($_POST['show_avatar']) ? 1 : 0;
        $show_date = isset($_POST['show_date']) ? 1 : 0;
        $text_length = isset($_POST['text_length']) ? absint($_POST['text_length']) : 200;
        
        update_option('wiz_gr_theme', $theme);
        update_option('wiz_gr_show_avatar', $show_avatar);
        update_option('wiz_gr_show_date', $show_date);
        update_option('wiz_gr_text_length', $text_length);
        
        wp_safe_redirect(admin_url('admin.php?page=wiz-google-reviews-config&step=5'));
        exit;
    }
    
    public function render_config_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $step = isset($_GET['step']) ? absint($_GET['step']) : 1;
        $is_connected = get_option('wiz_gr_google_connected', false);
        
        ?>
        <div class="wrap wiz-gr-configurator">
            <h1><?php _e('Free Widget Configurator', 'wiz-google-reviews'); ?></h1>
            
            <!-- Progress Steps -->
            <div class="wiz-gr-steps">
                <div class="wiz-gr-step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                    <span class="step-number">1</span>
                    <span class="step-label"><?php _e('Connect Google', 'wiz-google-reviews'); ?></span>
                </div>
                <div class="wiz-gr-step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                    <span class="step-number">2</span>
                    <span class="step-label"><?php _e('Select Layout', 'wiz-google-reviews'); ?></span>
                </div>
                <div class="wiz-gr-step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                    <span class="step-number">3</span>
                    <span class="step-label"><?php _e('Select Style', 'wiz-google-reviews'); ?></span>
                </div>
                <div class="wiz-gr-step <?php echo $step >= 4 ? 'active' : ''; ?> <?php echo $step > 4 ? 'completed' : ''; ?>">
                    <span class="step-number">4</span>
                    <span class="step-label"><?php _e('Set up widget', 'wiz-google-reviews'); ?></span>
                </div>
                <div class="wiz-gr-step <?php echo $step >= 5 ? 'active' : ''; ?>">
                    <span class="step-number">5</span>
                    <span class="step-label"><?php _e('Insert code', 'wiz-google-reviews'); ?></span>
                </div>
            </div>
            
            <div class="wiz-gr-config-content">
                <?php
                switch ($step) {
                    case 1:
                        $this->render_step_connect();
                        break;
                    case 2:
                        $this->render_step_layout();
                        break;
                    case 3:
                        $this->render_step_style();
                        break;
                    case 4:
                        $this->render_step_setup();
                        break;
                    case 5:
                        $this->render_step_code();
                        break;
                    default:
                        $this->render_step_connect();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_step_connect() {
        $is_connected = get_option('wiz_gr_google_connected', false);
        $business_name = get_option('wiz_gr_business_name', '');
        
        ?>
        <div class="wiz-gr-step-content">
            <h2><?php _e('Connect Google', 'wiz-google-reviews'); ?></h2>
            
            <?php if ($is_connected) : ?>
                <div class="wiz-gr-connected-box">
                    <div class="connected-icon">✓</div>
                    <h3><?php _e('Connected to Google', 'wiz-google-reviews'); ?></h3>
                    <p><?php echo esc_html($business_name); ?></p>
                    
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline;">
                        <input type="hidden" name="action" value="wiz_gr_disconnect_google">
                        <?php wp_nonce_field('wiz_gr_disconnect_google'); ?>
                        <button type="submit" class="button"><?php _e('Disconnect', 'wiz-google-reviews'); ?></button>
                    </form>
                    
                    <a href="<?php echo admin_url('admin.php?page=wiz-google-reviews-config&step=2'); ?>" class="button button-primary">
                        <?php _e('Continue to Layout', 'wiz-google-reviews'); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="wiz-gr-connect-box">
                    <div class="search-business-form">
                        <h3><?php _e('Search for your business', 'wiz-google-reviews'); ?></h3>
                        <input type="text" id="business-search" class="regular-text" placeholder="<?php _e('Enter your business name...', 'wiz-google-reviews'); ?>">
                        <button type="button" id="search-business-btn" class="button button-primary">
                            <?php _e('Search', 'wiz-google-reviews'); ?>
                        </button>
                        
                        <div id="business-results" class="business-results"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_step_layout() {
        $current_layout = get_option('wiz_gr_layout', 'grid');
        $columns = get_option('wiz_gr_columns', 3);
        
        ?>
        <div class="wiz-gr-step-content">
            <h2><?php _e('Select Layout', 'wiz-google-reviews'); ?></h2>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="wiz_gr_save_layout">
                <?php wp_nonce_field('wiz_gr_save_layout'); ?>
                
                <div class="layout-options">
                    <label class="layout-option <?php echo $current_layout === 'slider' ? 'selected' : ''; ?>">
                        <input type="radio" name="layout" value="slider" <?php checked($current_layout, 'slider'); ?>>
                        <div class="layout-preview">
                            <div class="preview-slider">
                                <div class="preview-card"></div>
                                <div class="preview-card"></div>
                                <div class="preview-card"></div>
                            </div>
                            <span class="layout-name"><?php _e('Slider', 'wiz-google-reviews'); ?></span>
                        </div>
                    </label>
                    
                    <label class="layout-option <?php echo $current_layout === 'grid' ? 'selected' : ''; ?>">
                        <input type="radio" name="layout" value="grid" <?php checked($current_layout, 'grid'); ?>>
                        <div class="layout-preview">
                            <div class="preview-grid">
                                <div class="preview-card"></div>
                                <div class="preview-card"></div>
                                <div class="preview-card"></div>
                            </div>
                            <span class="layout-name"><?php _e('Grid', 'wiz-google-reviews'); ?></span>
                        </div>
                    </label>
                    
                    <label class="layout-option <?php echo $current_layout === 'masonry' ? 'selected' : ''; ?>">
                        <input type="radio" name="layout" value="masonry" <?php checked($current_layout, 'masonry'); ?>>
                        <div class="layout-preview">
                            <div class="preview-masonry">
                                <div class="preview-card short"></div>
                                <div class="preview-card tall"></div>
                                <div class="preview-card"></div>
                            </div>
                            <span class="layout-name"><?php _e('Masonry', 'wiz-google-reviews'); ?></span>
                        </div>
                    </label>
                    
                    <label class="layout-option <?php echo $current_layout === 'list' ? 'selected' : ''; ?>">
                        <input type="radio" name="layout" value="list" <?php checked($current_layout, 'list'); ?>>
                        <div class="layout-preview">
                            <div class="preview-list">
                                <div class="preview-card wide"></div>
                                <div class="preview-card wide"></div>
                            </div>
                            <span class="layout-name"><?php _e('List', 'wiz-google-reviews'); ?></span>
                        </div>
                    </label>
                </div>
                
                <div class="columns-setting">
                    <label><?php _e('Columns (Grid/Masonry):', 'wiz-google-reviews'); ?></label>
                    <select name="columns">
                        <option value="2" <?php selected($columns, 2); ?>>2</option>
                        <option value="3" <?php selected($columns, 3); ?>>3</option>
                        <option value="4" <?php selected($columns, 4); ?>>4</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <a href="<?php echo admin_url('admin.php?page=wiz-google-reviews-config&step=1'); ?>" class="button">
                        <?php _e('Back', 'wiz-google-reviews'); ?>
                    </a>
                    <button type="submit" class="button button-primary">
                        <?php _e('Continue to Style', 'wiz-google-reviews'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
    
    private function render_step_style() {
        $theme = get_option('wiz_gr_theme', 'light');
        $show_avatar = get_option('wiz_gr_show_avatar', true);
        $show_date = get_option('wiz_gr_show_date', true);
        $text_length = get_option('wiz_gr_text_length', 200);
        
        ?>
        <div class="wiz-gr-step-content">
            <h2><?php _e('Select Style', 'wiz-google-reviews'); ?></h2>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="wiz_gr_save_style">
                <?php wp_nonce_field('wiz_gr_save_style'); ?>
                
                <div class="style-options">
                    <h3><?php _e('Theme', 'wiz-google-reviews'); ?></h3>
                    <div class="theme-options">
                        <label class="theme-option <?php echo $theme === 'light' ? 'selected' : ''; ?>">
                            <input type="radio" name="theme" value="light" <?php checked($theme, 'light'); ?>>
                            <div class="theme-preview light-theme">
                                <div class="theme-card">
                                    <div class="theme-text"></div>
                                    <div class="theme-text short"></div>
                                </div>
                                <span><?php _e('Light', 'wiz-google-reviews'); ?></span>
                            </div>
                        </label>
                        
                        <label class="theme-option <?php echo $theme === 'dark' ? 'selected' : ''; ?>">
                            <input type="radio" name="theme" value="dark" <?php checked($theme, 'dark'); ?>>
                            <div class="theme-preview dark-theme">
                                <div class="theme-card">
                                    <div class="theme-text"></div>
                                    <div class="theme-text short"></div>
                                </div>
                                <span><?php _e('Dark', 'wiz-google-reviews'); ?></span>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="display-options">
                    <h3><?php _e('Display Options', 'wiz-google-reviews'); ?></h3>
                    
                    <label class="checkbox-option">
                        <input type="checkbox" name="show_avatar" value="1" <?php checked($show_avatar, true); ?>>
                        <?php _e('Show Reviewer Avatar', 'wiz-google-reviews'); ?>
                    </label>
                    
                    <label class="checkbox-option">
                        <input type="checkbox" name="show_date" value="1" <?php checked($show_date, true); ?>>
                        <?php _e('Show Review Date', 'wiz-google-reviews'); ?>
                    </label>
                    
                    <div class="text-length-option">
                        <label><?php _e('Review Text Length:', 'wiz-google-reviews'); ?></label>
                        <input type="number" name="text_length" value="<?php echo esc_attr($text_length); ?>" min="50" max="500" step="50">
                        <span class="description"><?php _e('characters', 'wiz-google-reviews'); ?></span>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="<?php echo admin_url('admin.php?page=wiz-google-reviews-config&step=2'); ?>" class="button">
                        <?php _e('Back', 'wiz-google-reviews'); ?>
                    </a>
                    <button type="submit" class="button button-primary">
                        <?php _e('Continue to Setup', 'wiz-google-reviews'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
    
    private function render_step_setup() {
        ?>
        <div class="wiz-gr-step-content">
            <h2><?php _e('Set up widget', 'wiz-google-reviews'); ?></h2>
            
            <div class="setup-info">
                <p><?php _e('Your widget is almost ready! Review your configuration:', 'wiz-google-reviews'); ?></p>
                
                <div class="config-summary">
                    <div class="summary-item">
                        <strong><?php _e('Business:', 'wiz-google-reviews'); ?></strong>
                        <span><?php echo esc_html(get_option('wiz_gr_business_name', 'Not connected')); ?></span>
                    </div>
                    <div class="summary-item">
                        <strong><?php _e('Layout:', 'wiz-google-reviews'); ?></strong>
                        <span><?php echo esc_html(ucfirst(get_option('wiz_gr_layout', 'grid'))); ?></span>
                    </div>
                    <div class="summary-item">
                        <strong><?php _e('Theme:', 'wiz-google-reviews'); ?></strong>
                        <span><?php echo esc_html(ucfirst(get_option('wiz_gr_theme', 'light'))); ?></span>
                    </div>
                </div>
                
                <div class="widget-preview">
                    <h3><?php _e('Preview', 'wiz-google-reviews'); ?></h3>
                    <?php echo do_shortcode('[wiz_google_reviews]'); ?>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="<?php echo admin_url('admin.php?page=wiz-google-reviews-config&step=3'); ?>" class="button">
                    <?php _e('Back', 'wiz-google-reviews'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=wiz-google-reviews-config&step=5'); ?>" class="button button-primary">
                    <?php _e('Get Code', 'wiz-google-reviews'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    private function render_step_code() {
        $shortcode = '[wiz_google_reviews]';
        
        ?>
        <div class="wiz-gr-step-content">
            <h2><?php _e('Insert code', 'wiz-google-reviews'); ?></h2>
            
            <div class="code-box">
                <p><?php _e('Copy and paste this shortcode anywhere on your site:', 'wiz-google-reviews'); ?></p>
                
                <div class="shortcode-box">
                    <code id="widget-shortcode"><?php echo esc_html($shortcode); ?></code>
                    <button type="button" class="button copy-shortcode" data-clipboard-target="#widget-shortcode">
                        <?php _e('Copy', 'wiz-google-reviews'); ?>
                    </button>
                </div>
                
                <div class="usage-instructions">
                    <h3><?php _e('How to use:', 'wiz-google-reviews'); ?></h3>
                    <ol>
                        <li><?php _e('Copy the shortcode above', 'wiz-google-reviews'); ?></li>
                        <li><?php _e('Edit any page or post', 'wiz-google-reviews'); ?></li>
                        <li><?php _e('Paste the shortcode where you want reviews to appear', 'wiz-google-reviews'); ?></li>
                        <li><?php _e('Publish or update the page', 'wiz-google-reviews'); ?></li>
                    </ol>
                </div>
                
                <div class="success-message">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <p><?php _e('Your Google Reviews widget is ready!', 'wiz-google-reviews'); ?></p>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="<?php echo admin_url('admin.php?page=wiz-google-reviews-config&step=1'); ?>" class="button">
                    <?php _e('Start Over', 'wiz-google-reviews'); ?>
                </a>
                <a href="<?php echo admin_url('edit.php?post_type=page'); ?>" class="button button-primary">
                    <?php _e('Add to Page', 'wiz-google-reviews'); ?>
                </a>
            </div>
        </div>
        <?php
    }
}