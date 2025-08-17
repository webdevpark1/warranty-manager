<?php
/**
 * AJAX functionality for Warranty Manager
 * 
 * Path: /wp-content/plugins/woocommerce-warranty-manager/includes/class-warranty-ajax.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WM_Ajax {
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * Frontend instance
     */
    private $frontend;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new WM_Database();
        $this->frontend = new WM_Frontend();
        $this->init_hooks();
    }
    
    /**
     * Initialize AJAX hooks
     */
    private function init_hooks() {
        // Public AJAX actions (logged in and logged out users)
        add_action('wp_ajax_warranty_activate', array($this, 'warranty_activate'));
        add_action('wp_ajax_nopriv_warranty_activate', array($this, 'warranty_activate'));
        
        add_action('wp_ajax_warranty_check', array($this, 'warranty_check'));
        add_action('wp_ajax_nopriv_warranty_check', array($this, 'warranty_check'));
        
        // Admin AJAX actions (logged in users only)
        add_action('wp_ajax_admin_activate_warranty', array($this, 'admin_activate_warranty'));
        add_action('wp_ajax_admin_delete_warranty', array($this, 'admin_delete_warranty'));
        add_action('wp_ajax_admin_edit_warranty', array($this, 'admin_edit_warranty'));
        add_action('wp_ajax_admin_update_warranty', array($this, 'admin_update_warranty'));
        
        // Utility AJAX actions
        add_action('wp_ajax_get_order_details', array($this, 'get_order_details'));
        add_action('wp_ajax_validate_order_phone', array($this, 'validate_order_phone'));
    }
    
    /**
     * Handle warranty activation
     */
    public function warranty_activate() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'warranty_nonce')) {
            wp_send_json_error(__('Security check failed', 'warranty-manager'));
        }
        
        // Sanitize input data
        $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
        $order_id = sanitize_text_field($_POST['order_id'] ?? '');
        $phone_number = sanitize_text_field($_POST['phone_number'] ?? '');
        $product_name = sanitize_text_field($_POST['product_name'] ?? '');
        $warranty_months = intval($_POST['warranty_months'] ?? 0);
        
        // Validate required fields
        if (empty($customer_name) || empty($order_id) || empty($phone_number) || $warranty_months <= 0) {
            wp_send_json_error(__('Please fill in all required fields', 'warranty-manager'));
        }
        
        // Validate order and phone number
        $validation = $this->frontend->validate_order_phone($order_id, $phone_number);
        
        if (!$validation['valid']) {
            wp_send_json_error($validation['message']);
        }
        
        $order = $validation['order'];
        
        // Check if warranty already exists
        $existing = $this->database->get_warranty_by_order_and_phone($order_id, $phone_number);
        
        if ($existing) {
            if ($existing->status === 'active') {
                wp_send_json_error(__('Warranty is already activated for this order', 'warranty-manager'));
            } elseif ($existing->status === 'pending') {
                // Update existing pending warranty
                $settings = WooCommerceWarrantyManager::get_settings();
                $status = $settings['auto_activate'] === 'yes' ? 'active' : 'pending';
                
                $update_data = array(
                    'customer_name' => $customer_name,
                    'product_name' => $product_name,
                    'warranty_months' => $warranty_months,
                    'status' => $status
                );
                
                if ($status === 'active') {
                    $update_data['activation_date'] = current_time('mysql');
                }
                
                if ($this->database->update_warranty($existing->id, $update_data)) {
                    if ($status === 'active') {
                        // Send notification email
                        $updated_warranty = $this->database->get_warranty($existing->id);
                        $this->frontend->send_warranty_notification($updated_warranty, 'activated');
                        
                        wp_send_json_success(__('Warranty activated successfully!', 'warranty-manager'));
                    } else {
                        wp_send_json_success(__('Warranty activation request submitted. Please wait for approval.', 'warranty-manager'));
                    }
                } else {
                    wp_send_json_error(__('Failed to update warranty. Please try again.', 'warranty-manager'));
                }
            }
        } else {
            // Create new warranty record
            $settings = WooCommerceWarrantyManager::get_settings();
            $status = $settings['auto_activate'] === 'yes' ? 'active' : 'pending';
            
            $warranty_data = array(
                'order_id' => $order_id,
                'customer_name' => $customer_name,
                'customer_email' => $order->get_billing_email(),
                'phone_number' => $phone_number,
                'product_name' => $product_name,
                'warranty_months' => $warranty_months,
                'purchase_date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'status' => $status
            );
            
            if ($status === 'active') {
                $warranty_data['activation_date'] = current_time('mysql');
            }
            
            // Get product ID if product name matches
            if (!empty($product_name)) {
                foreach ($order->get_items() as $item) {
                    if ($item->get_name() === $product_name) {
                        $warranty_data['product_id'] = $item->get_product_id();
                        break;
                    }
                }
            }
            
            $warranty_id = $this->database->insert_warranty($warranty_data);
            
            if ($warranty_id) {
                if ($status === 'active') {
                    // Send notification email
                    $warranty = $this->database->get_warranty($warranty_id);
                    $this->frontend->send_warranty_notification($warranty, 'activated');
                    
                    wp_send_json_success(__('Warranty activated successfully!', 'warranty-manager'));
                } else {
                    wp_send_json_success(__('Warranty activation request submitted. Please wait for approval.', 'warranty-manager'));
                }
            } else {
                wp_send_json_error(__('Failed to create warranty record. Please try again.', 'warranty-manager'));
            }
        }
    }
    
    /**
     * Handle warranty check
     */
    public function warranty_check() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'warranty_nonce')) {
            wp_send_json_error(__('Security check failed', 'warranty-manager'));
        }
        
        $search_order_id = sanitize_text_field($_POST['search_order_id'] ?? '');
        $search_phone = sanitize_text_field($_POST['search_phone'] ?? '');
        
        // Validate input - must have either order ID or phone
        if (empty($search_order_id) && empty($search_phone)) {
            wp_send_json_error(__('Please provide either Order ID or Phone Number', 'warranty-manager'));
        }
        
        $warranties = array();
        
        if (!empty($search_order_id)) {
            $warranty = $this->database->get_warranty_by_order($search_order_id);
            if ($warranty) {
                $warranties[] = $warranty;
            }
        } elseif (!empty($search_phone)) {
            $warranties = $this->database->get_warranties_by_phone($search_phone);
        }
        
        if (empty($warranties)) {
            wp_send_json_error(__('No warranty records found. Please check your information and try again.', 'warranty-manager'));
        }
        
        // For multiple warranties, return the most recent active one, or the first one
        $warranty = $warranties[0];
        foreach ($warranties as $w) {
            if ($w->status === 'active') {
                $warranty = $w;
                break;
            }
        }
        
        // Calculate warranty details
        $warranty_data = array(
            'customer_name' => $warranty->customer_name,
            'phone_number' => $warranty->phone_number,
            'order_id' => $warranty->order_id,
            'product_name' => $warranty->product_name ?: __('N/A', 'warranty-manager'),
            'purchase_date' => date_i18n(get_option('date_format'), strtotime($warranty->purchase_date)),
            'warranty_months' => $warranty->warranty_months,
            'status' => $warranty->status
        );
        
        if (!empty($warranty->activation_date)) {
            $warranty_data['activation_date'] = date_i18n(get_option('date_format'), strtotime($warranty->activation_date));
        }
        
        if (!empty($warranty->expiry_date)) {
            $warranty_data['expiry_date'] = date_i18n(get_option('date_format'), strtotime($warranty->expiry_date));
            $warranty_data['warranty_remaining'] = $this->frontend->format_warranty_remaining($warranty->expiry_date);
        }
        
        // Get status message
        $status_info = $this->frontend->get_warranty_status_message($warranty->status, $warranty->expiry_date);
        $warranty_data['status_message'] = $status_info['message'];
        $warranty_data['status_class'] = $status_info['class'];
        
        // Generate certificate for active warranties
        if ($warranty->status === 'active') {
            $warranty_data['certificate_html'] = $this->frontend->generate_warranty_certificate($warranty);
        }
        
        wp_send_json_success($warranty_data);
    }
    
    /**
     * Admin activate warranty
     */
    public function admin_activate_warranty() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'warranty-manager'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'warranty_nonce')) {
            wp_send_json_error(__('Security check failed', 'warranty-manager'));
        }
        
        $warranty_id = intval($_POST['warranty_id'] ?? 0);
        
        if ($warranty_id <= 0) {
            wp_send_json_error(__('Invalid warranty ID', 'warranty-manager'));
        }
        
        $warranty = $this->database->get_warranty($warranty_id);
        
        if (!$warranty) {
            wp_send_json_error(__('Warranty not found', 'warranty-manager'));
        }
        
        if ($warranty->status === 'active') {
            wp_send_json_error(__('Warranty is already active', 'warranty-manager'));
        }
        
        // Update warranty status
        $update_data = array(
            'status' => 'active',
            'activation_date' => current_time('mysql')
        );
        
        if ($this->database->update_warranty($warranty_id, $update_data)) {
            // Send notification email
            $updated_warranty = $this->database->get_warranty($warranty_id);
            $this->frontend->send_warranty_notification($updated_warranty, 'activated');
            
            wp_send_json_success(__('Warranty activated successfully', 'warranty-manager'));
        } else {
            wp_send_json_error(__('Failed to activate warranty', 'warranty-manager'));
        }
    }
    
    /**
     * Admin delete warranty
     */
    public function admin_delete_warranty() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'warranty-manager'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'warranty_nonce')) {
            wp_send_json_error(__('Security check failed', 'warranty-manager'));
        }
        
        $warranty_id = intval($_POST['warranty_id'] ?? 0);
        
        if ($warranty_id <= 0) {
            wp_send_json_error(__('Invalid warranty ID', 'warranty-manager'));
        }
        
        if ($this->database->delete_warranty($warranty_id)) {
            wp_send_json_success(__('Warranty deleted successfully', 'warranty-manager'));
        } else {
            wp_send_json_error(__('Failed to delete warranty', 'warranty-manager'));
        }
    }
    
    /**
     * Admin edit warranty (get warranty data)
     */
    public function admin_edit_warranty() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'warranty-manager'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'warranty_nonce')) {
            wp_send_json_error(__('Security check failed', 'warranty-manager'));
        }
        
        $warranty_id = intval($_POST['warranty_id'] ?? 0);
        
        if ($warranty_id <= 0) {
            wp_send_json_error(__('Invalid warranty ID', 'warranty-manager'));
        }
        
        $warranty = $this->database->get_warranty($warranty_id);
        
        if (!$warranty) {
            wp_send_json_error(__('Warranty not found', 'warranty-manager'));
        }
        
        // Return warranty data for editing
        wp_send_json_success(array(
            'id' => $warranty->id,
            'order_id' => $warranty->order_id,
            'customer_name' => $warranty->customer_name,
            'customer_email' => $warranty->customer_email,
            'phone_number' => $warranty->phone_number,
            'product_name' => $warranty->product_name,
            'warranty_months' => $warranty->warranty_months,
            'status' => $warranty->status,
            'purchase_date' => $warranty->purchase_date,
            'activation_date' => $warranty->activation_date,
            'notes' => $warranty->notes
        ));
    }
    
    /**
     * Admin update warranty
     */
    public function admin_update_warranty() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'warranty-manager'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'warranty_nonce')) {
            wp_send_json_error(__('Security check failed', 'warranty-manager'));
        }
        
        $warranty_id = intval($_POST['warranty_id'] ?? 0);
        
        if ($warranty_id <= 0) {
            wp_send_json_error(__('Invalid warranty ID', 'warranty-manager'));
        }
        
        // Sanitize update data
        $update_data = array(
            'customer_name' => sanitize_text_field($_POST['customer_name'] ?? ''),
            'customer_email' => sanitize_email($_POST['customer_email'] ?? ''),
            'phone_number' => sanitize_text_field($_POST['phone_number'] ?? ''),
            'product_name' => sanitize_text_field($_POST['product_name'] ?? ''),
            'warranty_months' => intval($_POST['warranty_months'] ?? 0),
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        );
        
        // Validate required fields
        if (empty($update_data['customer_name']) || empty($update_data['phone_number']) || $update_data['warranty_months'] <= 0) {
            wp_send_json_error(__('Please fill in all required fields', 'warranty-manager'));
        }
        
        // Validate status
        if (!in_array($update_data['status'], array('pending', 'active', 'expired', 'cancelled'))) {
            wp_send_json_error(__('Invalid status', 'warranty-manager'));
        }
        
        // If status is being changed to active, set activation date
        $current_warranty = $this->database->get_warranty($warranty_id);
        if ($current_warranty && $current_warranty->status !== 'active' && $update_data['status'] === 'active') {
            $update_data['activation_date'] = current_time('mysql');
        }
        
        if ($this->database->update_warranty($warranty_id, $update_data)) {
            wp_send_json_success(__('Warranty updated successfully', 'warranty-manager'));
        } else {
            wp_send_json_error(__('Failed to update warranty', 'warranty-manager'));
        }
    }
    
    /**
     * Get order details (for auto-fill functionality)
     */
    public function get_order_details() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'warranty_nonce')) {
            wp_send_json_error(__('Security check failed', 'warranty-manager'));
        }
        
        $order_id = sanitize_text_field($_POST['order_id'] ?? '');
        
        if (empty($order_id)) {
            wp_send_json_error(__('Order ID is required', 'warranty-manager'));
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(__('Order not found', 'warranty-manager'));
        }
        
        $products = $this->frontend->get_order_products($order);
        
        wp_send_json_success(array(
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_email' => $order->get_billing_email(),
            'phone_number' => $order->get_billing_phone(),
            'purchase_date' => $order->get_date_created()->date('Y-m-d'),
            'products' => $products
        ));
    }
    
    /**
     * Validate order and phone combination
     */
    public function validate_order_phone() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'warranty_nonce')) {
            wp_send_json_error(__('Security check failed', 'warranty-manager'));
        }
        
        $order_id = sanitize_text_field($_POST['order_id'] ?? '');
        $phone_number = sanitize_text_field($_POST['phone_number'] ?? '');
        
        if (empty($order_id) || empty($phone_number)) {
            wp_send_json_error(__('Both Order ID and Phone Number are required', 'warranty-manager'));
        }
        
        $validation = $this->frontend->validate_order_phone($order_id, $phone_number);
        
        if ($validation['valid']) {
            wp_send_json_success(__('Order and phone number match', 'warranty-manager'));
        } else {
            wp_send_json_error($validation['message']);
        }
    }
}