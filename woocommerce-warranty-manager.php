<?php
/**
 * Plugin Name: WooCommerce Warranty Manager
 * Plugin URI: https://yourwebsite.com/
 * Description: Complete warranty management system for WooCommerce with Woodmart theme integration. Provides modern AJAX-powered warranty activation and checking system.
 * Version: 1.0.0
 * Author: Didar
 * Author URI: https://yourwebsite.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: warranty-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WM_PLUGIN_FILE', __FILE__);
define('WM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WM_PLUGIN_VERSION', '1.0.0');
define('WM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>WooCommerce Warranty Manager</strong> requires WooCommerce to be installed and active.</p></div>';
    });
    return;
}

/**
 * Main Plugin Class
 */
class WooCommerceWarrantyManager {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Database instance
     */
    public $database;
    
    /**
     * Admin instance
     */
    public $admin;
    
    /**
     * Frontend instance
     */
    public $frontend;
    
    /**
     * AJAX instance
     */
    public $ajax;
    
    /**
     * Get single instance
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
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Activation and deactivation hooks
        register_activation_hook(WM_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(WM_PLUGIN_FILE, array($this, 'deactivate'));
        register_uninstall_hook(WM_PLUGIN_FILE, array('WooCommerceWarrantyManager', 'uninstall'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        $this->load_dependencies();
        $this->init_hooks();
        
        // Initialize components
        $this->database = new WM_Database();
        $this->admin = new WM_Admin();
        $this->frontend = new WM_Frontend();
        $this->ajax = new WM_Ajax();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once WM_PLUGIN_PATH . 'includes/class-warranty-database.php';
        require_once WM_PLUGIN_PATH . 'includes/class-warranty-admin.php';
        require_once WM_PLUGIN_PATH . 'includes/class-warranty-frontend.php';
        require_once WM_PLUGIN_PATH . 'includes/class-warranty-ajax.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('warranty-manager', false, dirname(WM_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'warranty-manager-css',
            WM_PLUGIN_URL . 'assets/css/warranty-manager.css',
            array(),
            WM_PLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'warranty-manager-js',
            WM_PLUGIN_URL . 'assets/js/warranty-manager.js',
            array('jquery'),
            WM_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script('warranty-manager-js', 'warranty_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('warranty_nonce'),
            'messages' => array(
                'processing' => __('Processing...', 'warranty-manager'),
                'error' => __('Something went wrong. Please try again.', 'warranty-manager'),
                'required_field' => __('This field is required.', 'warranty-manager'),
                'invalid_phone' => __('Please enter a valid phone number.', 'warranty-manager'),
                'confirm_activate' => __('Are you sure you want to activate this warranty?', 'warranty-manager')
            )
        ));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'warranty-manager') === false) {
            return;
        }
        
        wp_enqueue_style(
            'warranty-admin-css',
            WM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WM_PLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'warranty-admin-js',
            WM_PLUGIN_URL . 'assets/js/warranty-manager.js',
            array('jquery'),
            WM_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script('warranty-admin-js', 'warranty_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('warranty_nonce')
        ));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $database = new WM_Database();
        $database->create_tables();
        
        // Create warranty pages
        $this->create_warranty_pages();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag for admin notice
        set_transient('warranty_manager_activated', true, 30);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
        
        // Clear scheduled events if any
        wp_clear_scheduled_hook('warranty_manager_daily_cleanup');
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        global $wpdb;
        
        // Remove database tables
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}warranty_records");
        
        // Remove plugin options
        delete_option('warranty_manager_settings');
        delete_option('warranty_manager_version');
        
        // Remove warranty pages
        $pages = array('warranty-activation', 'warranty-check');
        foreach ($pages as $page_slug) {
            $page = get_page_by_path($page_slug);
            if ($page) {
                wp_delete_post($page->ID, true);
            }
        }
        
        // Clear any cached data
        wp_cache_flush();
    }
    
    /**
     * Create warranty pages
     */
    private function create_warranty_pages() {
        // Create Warranty Activation Page
        if (!get_page_by_path('warranty-activation')) {
            wp_insert_post(array(
                'post_title' => __('Warranty Activation', 'warranty-manager'),
                'post_content' => '[warranty_activation]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'warranty-activation',
                'post_author' => 1,
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            ));
        }
        
        // Create Warranty Check Page
        if (!get_page_by_path('warranty-check')) {
            wp_insert_post(array(
                'post_title' => __('Warranty Check', 'warranty-manager'),
                'post_content' => '[warranty_check]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'warranty-check',
                'post_author' => 1,
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            ));
        }
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_settings = array(
            'warranty_attribute' => '',
            'auto_activate' => 'no',
            'email_notifications' => 'yes',
            'default_warranty_months' => array(6, 12, 18, 24, 36)
        );
        
        add_option('warranty_manager_settings', $default_settings);
        add_option('warranty_manager_version', WM_PLUGIN_VERSION);
    }
    
    /**
     * Get plugin settings
     */
    public static function get_settings() {
        $defaults = array(
            'warranty_attribute' => '',
            'auto_activate' => 'no',
            'email_notifications' => 'yes',
            'default_warranty_months' => array(6, 12, 18, 24, 36)
        );
        
        return wp_parse_args(get_option('warranty_manager_settings', array()), $defaults);
    }
    
    /**
     * Update plugin settings
     */
    public static function update_settings($new_settings) {
        $current_settings = self::get_settings();
        $updated_settings = wp_parse_args($new_settings, $current_settings);
        update_option('warranty_manager_settings', $updated_settings);
    }
}

/**
 * Initialize the plugin
 */
function warranty_manager() {
    return WooCommerceWarrantyManager::get_instance();
}

// Start the plugin
warranty_manager();

/**
 * Add action links to plugin page
 */
add_filter('plugin_action_links_' . WM_PLUGIN_BASENAME, function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=warranty-settings') . '">' . __('Settings', 'warranty-manager') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

/**
 * Add activation admin notice
 */
add_action('admin_notices', function() {
    if (get_transient('warranty_manager_activated')) {
        delete_transient('warranty_manager_activated');
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>' . __('WooCommerce Warranty Manager', 'warranty-manager') . '</strong> ' . __('has been activated successfully!', 'warranty-manager') . '</p>';
        echo '<p>' . __('Visit', 'warranty-manager') . ' <a href="' . admin_url('admin.php?page=warranty-manager') . '">' . __('Warranty Manager', 'warranty-manager') . '</a> ' . __('to get started.', 'warranty-manager') . '</p>';
        echo '</div>';
    }
});