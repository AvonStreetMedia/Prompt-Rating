<?php
/**
 * Plugin Name: PromptRocket Ratings
 * Plugin URI: https://promptrocket.io
 * Description: Simple rating system with attitude. Prompts that don't suck deserve ratings that don't either.
 * Version: 1.0.1
 * Author: PromptRocket
 * Text Domain: promptrocket-ratings
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('PR_RATINGS_VERSION', '1.0.1');
define('PR_RATINGS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PR_RATINGS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class - keeping it simple
 */
class PromptRocketRatings {
    
    /**
     * Rating text labels - the fun part
     */
    public static $rating_labels = [
        1 => 'Total dumpster fire',
        2 => 'Kinda sucks',
        3 => 'Doesn\'t suck',
        4 => 'Actually good',
        5 => 'Holy $#!† this works!'
    ];
    
    /**
     * Initialize the plugin
     */
    public function __construct() {
        // Create database table on activation
        register_activation_hook(__FILE__, [$this, 'create_ratings_table']);
        
        // Load our components
        add_action('init', [$this, 'load_components']);
        
        // Add ratings to post content
        add_filter('the_content', [$this, 'add_ratings_to_content']);
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_pr_submit_rating', [$this, 'handle_rating_submission']);
        add_action('wp_ajax_nopriv_pr_submit_rating', [$this, 'handle_rating_submission']);
        
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Add meta box for disabling ratings
        add_action('add_meta_boxes', [$this, 'add_disable_ratings_meta_box']);
        add_action('save_post', [$this, 'save_disable_ratings_meta']);
    }
    
    /**
     * Create the database table for storing ratings
     */
    public function create_ratings_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'promptrocket_ratings';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            rating tinyint(1) NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Store version for future updates
        update_option('pr_ratings_db_version', PR_RATINGS_VERSION);
    }
    
    /**
     * Load additional components
     */
    public function load_components() {
        // We'll add includes here as needed
        // For now, keeping everything in one file for simplicity
    }
    
    /**
     * Add ratings display and form to post content
     */
    public function add_ratings_to_content($content) {
        // Only add to single posts
        if (!is_single() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        // Skip if not a post
        if (get_post_type() !== 'post') {
            return $content;
        }
        
        $post_id = get_the_ID();
        
        // Check if ratings are disabled for this post
        $disable_ratings = get_post_meta($post_id, '_disable_promptrocket_ratings', true);
        if ($disable_ratings === 'yes') {
            return $content;
        }
        
        // Get rating data
        $rating_data = $this->get_post_rating($post_id);
        
        // Add rating display at top
        $rating_display = $this->render_rating_display($post_id, $rating_data);
        
        // Add rating form at bottom
        $rating_form = $this->render_rating_form($post_id);
        
        // Combine it all
        return $rating_display . $content . $rating_form;
    }
    
    /**
     * Get rating data for a post
     */
    public function get_post_rating($post_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'promptrocket_ratings';
        
        // Get from cache first
        $cache_key = 'pr_rating_' . $post_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Query database
        $results = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_ratings,
                AVG(rating) as average_rating
            FROM $table_name 
            WHERE post_id = %d",
            $post_id
        ));
        
        $data = [
            'average' => $results->average_rating ? round($results->average_rating, 1) : 0,
            'count' => intval($results->total_ratings),
            'stars' => $results->average_rating ? round($results->average_rating) : 0
        ];
        
        // Cache for 1 hour
        set_transient($cache_key, $data, HOUR_IN_SECONDS);
        
        return $data;
    }
    
    /**
     * Render the rating display (top of post)
     */
    public function render_rating_display($post_id, $rating_data) {
        $html = '<div class="pr-rating-display">';
        $html .= '<h2>Prompt Rating</h2>';
        
        // Don't show if no ratings yet
        if ($rating_data['count'] === 0) {
            $html .= '<div class="pr-no-ratings">
                <span class="pr-be-first">Be the first to rate this prompt!</span>
            </div>';
        } else {
            $stars_html = $this->render_stars($rating_data['stars'], false);
            $label = self::$rating_labels[$rating_data['stars']] ?? '';
            
            $html .= '<div class="pr-rating-summary">';
            $html .= $stars_html;
            $html .= sprintf(
                '<span class="pr-rating-text">%s (%s/5 from %d %s)</span>',
                esc_html($label),
                $rating_data['average'],
                $rating_data['count'],
                $rating_data['count'] === 1 ? 'rating' : 'ratings'
            );
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render the rating form (bottom of post)
     */
    public function render_rating_form($post_id) {
        // Check if user already rated (via cookie)
        $cookie_name = 'pr_rated_' . $post_id;
        if (isset($_COOKIE[$cookie_name])) {
            return '<div class="pr-rating-form pr-already-rated">
                <p>Thanks for rating this prompt!</p>
            </div>';
        }
        
        $html = '<div class="pr-rating-form" data-post-id="' . esc_attr($post_id) . '">';
        $html .= '<p class="pr-rating-cta">Rate this prompt\'s suck level:</p>';
        $html .= '<div class="pr-rating-stars">';
        
        for ($i = 1; $i <= 5; $i++) {
            $html .= sprintf(
                '<span class="pr-star" data-rating="%d" title="%s">⭐</span>',
                $i,
                esc_attr(self::$rating_labels[$i])
            );
        }
        
        $html .= '</div>';
        $html .= '<div class="pr-rating-message"></div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render stars display
     */
    public function render_stars($rating, $interactive = false) {
        $html = '<div class="pr-stars">';
        
        for ($i = 1; $i <= 5; $i++) {
            $filled = $i <= $rating ? 'filled' : 'empty';
            $html .= sprintf(
                '<span class="pr-star %s">⭐</span>',
                $filled
            );
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Handle AJAX rating submission
     */
    public function handle_rating_submission() {
        // Verify nonce
        if (!check_ajax_referer('pr_rating_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid request');
        }
        
        $post_id = intval($_POST['post_id']);
        $rating = intval($_POST['rating']);
        
        // Validate rating
        if ($rating < 1 || $rating > 5) {
            wp_send_json_error('Invalid rating value');
        }
        
        // Check if already rated (cookie)
        $cookie_name = 'pr_rated_' . $post_id;
        if (isset($_COOKIE[$cookie_name])) {
            wp_send_json_error('Already rated');
        }
        
        // Get IP address
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Save rating
        global $wpdb;
        $table_name = $wpdb->prefix . 'promptrocket_ratings';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'post_id' => $post_id,
                'rating' => $rating,
                'ip_address' => $ip_address,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s']
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to save rating');
        }
        
        // Clear cache
        delete_transient('pr_rating_' . $post_id);
        
        // Set cookie (expires in 30 days)
        setcookie($cookie_name, $rating, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);
        
        // Get updated rating data
        $rating_data = $this->get_post_rating($post_id);
        
        wp_send_json_success([
            'message' => 'Thanks for rating!',
            'rating_data' => $rating_data,
            'label' => self::$rating_labels[$rating]
        ]);
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_assets() {
        if (!is_single() || get_post_type() !== 'post') {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'pr-ratings',
            PR_RATINGS_PLUGIN_URL . 'assets/ratings.css',
            [],
            PR_RATINGS_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'pr-ratings',
            PR_RATINGS_PLUGIN_URL . 'assets/ratings.js',
            ['jquery'],
            PR_RATINGS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('pr-ratings', 'pr_ratings', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pr_rating_nonce'),
            'labels' => self::$rating_labels
        ]);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php',
            'PromptRocket Ratings',
            'Prompt Ratings',
            'manage_options',
            'promptrocket-ratings',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'promptrocket_ratings';
        
        // Get top rated posts
        $top_rated = $wpdb->get_results(
            "SELECT 
                post_id,
                COUNT(*) as total_ratings,
                AVG(rating) as average_rating
            FROM $table_name 
            GROUP BY post_id
            ORDER BY average_rating DESC, total_ratings DESC
            LIMIT 20"
        );
        
        // Get recent ratings
        $recent = $wpdb->get_results(
            "SELECT * FROM $table_name 
            ORDER BY created_at DESC 
            LIMIT 50"
        );
        
        ?>
        <div class="wrap">
            <h1>PromptRocket Ratings</h1>
            
            <div class="pr-admin-grid">
                <div class="pr-admin-section">
                    <h2>Top Rated Prompts</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Post</th>
                                <th>Average Rating</th>
                                <th>Total Ratings</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_rated as $item): 
                                $post = get_post($item->post_id);
                                if (!$post) continue;
                                $rating = round($item->average_rating, 1);
                                $stars = round($item->average_rating);
                                $label = self::$rating_labels[$stars] ?? '';
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo get_edit_post_link($item->post_id); ?>">
                                        <?php echo esc_html($post->post_title); ?>
                                    </a>
                                    <br>
                                    <a href="<?php echo get_permalink($item->post_id); ?>" target="_blank">View</a>
                                </td>
                                <td><?php echo $rating; ?>/5</td>
                                <td><?php echo $item->total_ratings; ?></td>
                                <td><?php echo esc_html($label); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="pr-admin-section">
                    <h2>Recent Ratings</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Post</th>
                                <th>Rating</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $item): 
                                $post = get_post($item->post_id);
                                if (!$post) continue;
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo get_permalink($item->post_id); ?>" target="_blank">
                                        <?php echo esc_html($post->post_title); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                    echo str_repeat('⭐', $item->rating);
                                    echo ' (' . self::$rating_labels[$item->rating] . ')';
                                    ?>
                                </td>
                                <td><?php echo human_time_diff(strtotime($item->created_at)); ?> ago</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="pr-admin-footer">
                <p><strong>Shortcodes:</strong></p>
                <p><code>[promptrocket_top_rated count="10"]</code> - Display top rated prompts</p>
            </div>
        </div>
        
        <style>
            .pr-admin-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-top: 20px;
            }
            .pr-admin-section {
                background: white;
                padding: 20px;
                border: 1px solid #ccc;
            }
            .pr-admin-footer {
                margin-top: 20px;
                padding: 20px;
                background: #f0f0f0;
                border-left: 4px solid #E63946;
            }
            @media (max-width: 1200px) {
                .pr-admin-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        <?php
    }
    
    /**
     * Add meta box to post editor for disabling ratings
     */
    public function add_disable_ratings_meta_box() {
        add_meta_box(
            'pr_disable_ratings',
            'PromptRocket Ratings',
            [$this, 'render_disable_ratings_meta_box'],
            'post',
            'side',
            'default'
        );
    }
    
    /**
     * Render the disable ratings meta box
     */
    public function render_disable_ratings_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('pr_disable_ratings_nonce', 'pr_disable_ratings_nonce');
        
        // Get current value
        $disable_ratings = get_post_meta($post->ID, '_disable_promptrocket_ratings', true);
        ?>
        <label for="pr_disable_ratings">
            <input type="checkbox" 
                   name="pr_disable_ratings" 
                   id="pr_disable_ratings" 
                   value="yes" 
                   <?php checked($disable_ratings, 'yes'); ?> />
            Disable ratings for this post
        </label>
        <p class="description">Check this to hide ratings on this specific post.</p>
        <?php
    }
    
    /**
     * Save the disable ratings meta box value
     */
    public function save_disable_ratings_meta($post_id) {
        // Check if nonce is set
        if (!isset($_POST['pr_disable_ratings_nonce'])) {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['pr_disable_ratings_nonce'], 'pr_disable_ratings_nonce')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save or delete the meta
        if (isset($_POST['pr_disable_ratings']) && $_POST['pr_disable_ratings'] === 'yes') {
            update_post_meta($post_id, '_disable_promptrocket_ratings', 'yes');
        } else {
            delete_post_meta($post_id, '_disable_promptrocket_ratings');
        }
    }
}

// Initialize the plugin
new PromptRocketRatings();

/**
 * Shortcode for displaying top rated posts
 * Usage: [promptrocket_top_rated count="10"]
 */
function pr_top_rated_shortcode($atts) {
    $atts = shortcode_atts([
        'count' => 10
    ], $atts);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'promptrocket_ratings';
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            post_id,
            COUNT(*) as total_ratings,
            AVG(rating) as average_rating
        FROM $table_name 
        GROUP BY post_id
        HAVING total_ratings >= 1
        ORDER BY average_rating DESC, total_ratings DESC
        LIMIT %d",
        $atts['count']
    ));
    
    if (empty($results)) {
        return '<p>No rated prompts yet!</p>';
    }
    
    $html = '<div class="pr-top-rated">';
    $html .= '<ol>';
    
    foreach ($results as $result) {
        $post = get_post($result->post_id);
        if (!$post) continue;
        
        $rating = round($result->average_rating, 1);
        $stars = round($result->average_rating);
        $label = PromptRocketRatings::$rating_labels[$stars] ?? '';
        
        $html .= sprintf(
            '<li><a href="%s">%s</a> - %s (%s/5)</li>',
            get_permalink($post),
            esc_html($post->post_title),
            esc_html($label),
            $rating
        );
    }
    
    $html .= '</ol>';
    $html .= '</div>';
    
    return $html;
}
add_shortcode('promptrocket_top_rated', 'pr_top_rated_shortcode');
