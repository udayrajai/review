<?php
/**
 * Google OAuth Handler
 * 
 * File: includes/class-wiz-gr-oauth.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wiz_GR_OAuth {
    
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
        add_action('admin_post_wiz_gr_connect_google', array($this, 'handle_connect'));
        add_action('admin_post_wiz_gr_disconnect_google', array($this, 'handle_disconnect'));
        add_action('admin_init', array($this, 'handle_oauth_callback'));
    }
    
    /**
     * Handle Google connection
     */
    public function handle_connect() {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wiz_gr_connect_google')) {
            wp_die(__('Security check failed.', 'wiz-google-reviews'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wiz-google-reviews'));
        }
        
        // For this implementation, we'll use Place ID search instead of full OAuth
        // Since we're using your API key, we just need the business Place ID
        
        $redirect_url = admin_url('admin.php?page=wiz-google-reviews-config&step=2');
        wp_safe_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle Google disconnection
     */
    public function handle_disconnect() {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wiz_gr_disconnect_google')) {
            wp_die(__('Security check failed.', 'wiz-google-reviews'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'wiz-google-reviews'));
        }
        
        // Clear connection data
        update_option('wiz_gr_google_connected', false);
        update_option('wiz_gr_place_id', '');
        update_option('wiz_gr_business_name', '');
        
        // Clear cache
        Wiz_GR_Cache::clear_cache();
        
        $redirect_url = admin_url('admin.php?page=wiz-google-reviews-config&disconnected=1');
        wp_safe_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle OAuth callback (placeholder for future OAuth implementation)
     */
    public function handle_oauth_callback() {
        // This is a placeholder for future OAuth implementation
        // Currently using direct API key approach
    }
    
    /**
     * Search for business by name
     * 
     * @param string $business_name
     * @return array|WP_Error
     */
    public static function search_business($business_name) {
        if (empty($business_name)) {
            return new WP_Error('empty_query', __('Please enter a business name.', 'wiz-google-reviews'));
        }
        
        $api_key = WIZ_GR_API_KEY;
        
        // Use Places API Text Search
        $url = add_query_arg(array(
            'query' => urlencode($business_name),
            'key' => $api_key,
            'fields' => 'place_id,name,formatted_address,rating,user_ratings_total',
        ), 'https://maps.googleapis.com/maps/api/place/textsearch/json');
        
        $response = wp_remote_get($url, array('timeout' => 15));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || $data['status'] !== 'OK') {
            return new WP_Error('search_failed', __('No businesses found.', 'wiz-google-reviews'));
        }
        
        // Return top 5 results
        $results = array();
        foreach (array_slice($data['results'], 0, 5) as $result) {
            $results[] = array(
                'place_id' => $result['place_id'],
                'name' => $result['name'],
                'address' => isset($result['formatted_address']) ? $result['formatted_address'] : '',
                'rating' => isset($result['rating']) ? $result['rating'] : 0,
                'total_reviews' => isset($result['user_ratings_total']) ? $result['user_ratings_total'] : 0,
            );
        }
        
        return $results;
    }
}