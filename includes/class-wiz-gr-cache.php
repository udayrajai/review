<?php
/**
 * Cache Handler
 * 
 * File: includes/class-wiz-gr-cache.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wiz_GR_Cache {
    
    /**
     * Table name
     */
    const TABLE_NAME = 'wiz_gr_reviews';
    
    /**
     * Create database table
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            author_name varchar(255) NOT NULL,
            author_url varchar(500) DEFAULT '',
            profile_photo_url varchar(500) DEFAULT '',
            rating tinyint(1) unsigned NOT NULL,
            text text NOT NULL,
            time bigint(20) unsigned NOT NULL,
            relative_time_description varchar(100) DEFAULT '',
            cached_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY rating (rating),
            KEY time (time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Store reviews in database
     * 
     * @param array $reviews
     * @return bool|WP_Error
     */
    public static function set_reviews($reviews) {
        global $wpdb;
        
        if (empty($reviews) || !is_array($reviews)) {
            return new WP_Error('invalid_reviews', __('Invalid reviews data.', 'wiz-google-reviews'));
        }
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        // Clear existing reviews
        $wpdb->query("TRUNCATE TABLE $table_name");
        
        // Insert new reviews
        foreach ($reviews as $review) {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'author_name' => $review['author_name'],
                    'author_url' => $review['author_url'],
                    'profile_photo_url' => $review['profile_photo_url'],
                    'rating' => $review['rating'],
                    'text' => $review['text'],
                    'time' => $review['time'],
                    'relative_time_description' => $review['relative_time_description'],
                    'cached_at' => current_time('mysql'),
                ),
                array('%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s')
            );
            
            if ($result === false) {
                return new WP_Error('db_insert_failed', __('Failed to cache reviews.', 'wiz-google-reviews'));
            }
        }
        
        return true;
    }
    
    /**
     * Get cached reviews
     * 
     * @param array $args
     * @return array
     */
    public static function get_reviews($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'limit' => 10,
            'order' => 'DESC',
            'orderby' => 'time',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        // Build query
        $orderby = in_array($args['orderby'], array('time', 'rating')) ? $args['orderby'] : 'time';
        $order = in_array(strtoupper($args['order']), array('ASC', 'DESC')) ? strtoupper($args['order']) : 'DESC';
        $limit = absint($args['limit']);
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d",
            $limit
        );
        
        $reviews = $wpdb->get_results($query, ARRAY_A);
        
        return $reviews ? $reviews : array();
    }
    
    /**
     * Get cache status
     * 
     * @return array
     */
    public static function get_cache_info() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $latest = $wpdb->get_var("SELECT cached_at FROM $table_name ORDER BY cached_at DESC LIMIT 1");
        
        return array(
            'count' => absint($count),
            'cached_at' => $latest ? strtotime($latest) : 0,
            'age_hours' => $latest ? round((time() - strtotime($latest)) / HOUR_IN_SECONDS, 1) : 0,
        );
    }
    
    /**
     * Clear cache
     * 
     * @return bool
     */
    public static function clear_cache() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $result = $wpdb->query("TRUNCATE TABLE $table_name");
        
        return $result !== false;
    }
    
    /**
     * Check if cache is valid
     * 
     * @return bool
     */
    public static function is_cache_valid() {
        $info = self::get_cache_info();
        
        if ($info['count'] === 0) {
            return false;
        }
        
        $cache_duration = get_option('wiz_gr_cache_duration', 24);
        
        return $info['age_hours'] < $cache_duration;
    }
}