<?php
/**
 * Shortcode Handler with Multiple Layouts
 * 
 * File: includes/class-wiz-gr-shortcode.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wiz_GR_Shortcode {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_shortcode('wiz_google_reviews', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }
    
    public function enqueue_frontend_assets() {
        if (!is_admin()) {
            wp_enqueue_style('wiz-gr-frontend', WIZ_GR_PLUGIN_URL . 'assets/css/frontend.css', array(), WIZ_GR_VERSION);
            
            $layout = get_option('wiz_gr_layout', 'grid');
            if ($layout === 'slider') {
                wp_enqueue_script('wiz-gr-slider', WIZ_GR_PLUGIN_URL . 'assets/js/slider.js', array('jquery'), WIZ_GR_VERSION, true);
            }
        }
    }
    
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'layout' => get_option('wiz_gr_layout', 'grid'),
            'theme' => get_option('wiz_gr_theme', 'light'),
            'limit' => 10,
        ), $atts, 'wiz_google_reviews');
        
        $reviews = Wiz_GR_Cache::get_reviews(array(
            'limit' => absint($atts['limit']),
            'order' => 'DESC',
        ));
        
        if (empty($reviews)) {
            return $this->render_no_reviews_message();
        }
        
        $layout = sanitize_text_field($atts['layout']);
        $theme = sanitize_text_field($atts['theme']);
        
        ob_start();
        
        echo '<div class="wiz-gr-reviews wiz-gr-theme-' . esc_attr($theme) . '" data-layout="' . esc_attr($layout) . '">';
        
        switch ($layout) {
            case 'slider':
                $this->render_slider_layout($reviews);
                break;
            case 'masonry':
                $this->render_masonry_layout($reviews);
                break;
            case 'list':
                $this->render_list_layout($reviews);
                break;
            case 'grid':
            default:
                $this->render_grid_layout($reviews);
                break;
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    private function render_grid_layout($reviews) {
        $columns = get_option('wiz_gr_columns', 3);
        echo '<div class="wiz-gr-grid wiz-gr-cols-' . esc_attr($columns) . '">';
        foreach ($reviews as $review) {
            $this->render_review_card($review);
        }
        echo '</div>';
    }
    
    private function render_slider_layout($reviews) {
        echo '<div class="wiz-gr-slider-wrapper">';
        echo '<div class="wiz-gr-slider">';
        foreach ($reviews as $review) {
            echo '<div class="wiz-gr-slide">';
            $this->render_review_card($review);
            echo '</div>';
        }
        echo '</div>';
        echo '<button class="wiz-gr-prev" aria-label="Previous">‹</button>';
        echo '<button class="wiz-gr-next" aria-label="Next">›</button>';
        echo '<div class="wiz-gr-dots"></div>';
        echo '</div>';
    }
    
    private function render_masonry_layout($reviews) {
        $columns = get_option('wiz_gr_columns', 3);
        echo '<div class="wiz-gr-masonry wiz-gr-cols-' . esc_attr($columns) . '">';
        foreach ($reviews as $review) {
            $this->render_review_card($review, true);
        }
        echo '</div>';
    }
    
    private function render_list_layout($reviews) {
        echo '<div class="wiz-gr-list">';
        foreach ($reviews as $review) {
            $this->render_review_card($review, false, true);
        }
        echo '</div>';
    }
    
    private function render_review_card($review, $masonry = false, $list = false) {
        $show_avatar = get_option('wiz_gr_show_avatar', true);
        $show_date = get_option('wiz_gr_show_date', true);
        $text_length = get_option('wiz_gr_text_length', 200);
        
        $card_class = 'wiz-gr-review-card';
        if ($masonry) $card_class .= ' wiz-gr-masonry-item';
        if ($list) $card_class .= ' wiz-gr-list-item';
        
        ?>
        <div class="<?php echo esc_attr($card_class); ?>">
            <div class="wiz-gr-review-header">
                <?php if ($show_avatar) : ?>
                    <?php if (!empty($review['profile_photo_url'])) : ?>
                    <img src="<?php echo esc_url($review['profile_photo_url']); ?>" 
                         alt="<?php echo esc_attr($review['author_name']); ?>" 
                         class="wiz-gr-review-avatar">
                    <?php else : ?>
                    <div class="wiz-gr-review-avatar wiz-gr-avatar-placeholder">
                        <?php echo esc_html(substr($review['author_name'], 0, 1)); ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="wiz-gr-review-author">
                    <?php if (!empty($review['author_url'])) : ?>
                    <a href="<?php echo esc_url($review['author_url']); ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="wiz-gr-author-name">
                        <?php echo esc_html($review['author_name']); ?>
                    </a>
                    <?php else : ?>
                    <span class="wiz-gr-author-name">
                        <?php echo esc_html($review['author_name']); ?>
                    </span>
                    <?php endif; ?>
                    
                    <div class="wiz-gr-review-meta">
                        <div class="wiz-gr-rating">
                            <?php echo $this->render_stars($review['rating']); ?>
                        </div>
                        <?php if ($show_date) : ?>
                        <span class="wiz-gr-review-time">
                            <?php echo esc_html($review['relative_time_description']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="wiz-gr-google-icon">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                </div>
            </div>
            
            <?php if (!empty($review['text'])) : ?>
            <div class="wiz-gr-review-text">
                <?php 
                $text = $review['text'];
                if (strlen($text) > $text_length) {
                    $text = substr($text, 0, $text_length) . '...';
                }
                echo '<p>' . esc_html($text) . '</p>';
                ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_stars($rating) {
        $rating = absint($rating);
        $output = '';
        
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
                $output .= '<span class="wiz-gr-star wiz-gr-star-filled">★</span>';
            } else {
                $output .= '<span class="wiz-gr-star wiz-gr-star-empty">☆</span>';
            }
        }
        
        return $output;
    }
    
    private function render_no_reviews_message() {
        ob_start();
        ?>
        <div class="wiz-gr-no-reviews">
            <p><?php _e('No reviews available yet.', 'wiz-google-reviews'); ?></p>
            <?php if (current_user_can('manage_options')) : ?>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wiz-google-reviews-config')); ?>" class="button">
                    <?php _e('Configure Widget', 'wiz-google-reviews'); ?>
                </a>
            </p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}