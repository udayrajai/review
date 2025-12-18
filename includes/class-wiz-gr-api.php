<?php
/**
 * Google Places API Handler
 * 
 * File: includes/class-wiz-gr-api.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wiz_GR_API {
    
    const API_ENDPOINT = 'https://maps.googleapis.com/maps/api/place/details/json';
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Fetch reviews from Google Places API
     */
    public function fetch_reviews() {
        // Use the API key defined in main plugin file
        $api_key = WIZ_GR_API_KEY;
        $place_id = get_option('wiz_gr_place_id');
        
        if (empty($api_key) || $api_key === 'YOUR_GOOGLE_PLACES_API_KEY_HERE') {
            return new WP_Error('missing_api_key', __('Plugin is not configured. Please contact the administrator.', 'wiz-google-reviews'));
        }
        
        if (empty($place_id)) {
            return new WP_Error('missing_place_id', __('No business connected.', 'wiz-google-reviews'));
        }
        
        // Check rate limiting
        $last_fetch = get_option('wiz_gr_last_fetch', 0);
        $cache_duration = get_option('wiz_gr_cache_duration', 24) * HOUR_IN_SECONDS;
        
        if ((time() - $last_fetch) < $cache_duration) {
            $time_remaining = $cache_duration - (time() - $last_fetch);
            $hours_remaining = ceil($time_remaining / HOUR_IN_SECONDS);
            
            return new WP_Error(
                'rate_limited',
                sprintf(
                    __('Please wait %d hours before fetching reviews again.', 'wiz-google-reviews'),
                    $hours_remaining
                )
            );
        }
        
        // Build API request
        $url = add_query_arg(array(
            'place_id' => $place_id,
            'key' => $api_key,
            'fields' => 'reviews,rating,user_ratings_total',
            'language' => get_locale(),
        ), self::API_ENDPOINT);
        
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'sslverify' => true,
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('api_request_failed', $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error('api_error', sprintf(__('API error: %d', 'wiz-google-reviews'), $status_code));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['status'])) {
            return new WP_Error('invalid_response', __('Invalid API response.', 'wiz-google-reviews'));
        }
        
        if ($data['status'] !== 'OK') {
            return new WP_Error('api_status_error', sprintf(__('API Status: %s', 'wiz-google-reviews'), $data['status']));
        }
        
        if (!isset($data['result']['reviews']) || empty($data['result']['reviews'])) {
            return new WP_Error('no_reviews', __('No reviews found.', 'wiz-google-reviews'));
        }
        
        $reviews = $data['result']['reviews'];
        $max_reviews = get_option('wiz_gr_max_reviews', 10);
        $reviews = array_slice($reviews, 0, $max_reviews);
        
        // Process reviews
        $processed_reviews = array();
        foreach ($reviews as $review) {
            $processed_reviews[] = array(
                'author_name' => isset($review['author_name']) ? sanitize_text_field($review['author_name']) : '',
                'author_url' => isset($review['author_url']) ? esc_url($review['author_url']) : '',
                'profile_photo_url' => isset($review['profile_photo_url']) ? esc_url($review['profile_photo_url']) : '',
                'rating' => isset($review['rating']) ? absint($review['rating']) : 0,
                'text' => isset($review['text']) ? sanitize_textarea_field($review['text']) : '',
                'time' => isset($review['time']) ? absint($review['time']) : 0,
                'relative_time_description' => isset($review['relative_time_description']) ? sanitize_text_field($review['relative_time_description']) : '',
            );
        }
        
        // Cache reviews
        $cache_result = Wiz_GR_Cache::set_reviews($processed_reviews);
        
        if (is_wp_error($cache_result)) {
            return $cache_result;
        }
        
        update_option('wiz_gr_last_fetch', time());
        
        return array(
            'success' => true,
            'reviews' => $processed_reviews,
            'count' => count($processed_reviews),
            'message' => sprintf(__('Fetched %d reviews successfully.', 'wiz-google-reviews'), count($processed_reviews))
        );
    }
}