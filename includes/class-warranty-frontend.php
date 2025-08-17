<?php
/**
 * Frontend functionality for Warranty Manager
 * 
 * Path: /wp-content/plugins/woocommerce-warranty-manager/includes/class-warranty-frontend.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WM_Frontend {
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new WM_Database();
        $this->init_hooks();
    }
    
    /**
     * Initialize frontend hooks
     */
    private function init_hooks() {
        add_shortcode('warranty_activation', array($this, 'warranty_activation_shortcode'));
        add_shortcode('warranty_check', array($this, 'warranty_check_shortcode'));
        add_filter('body_class', array($this, 'add_body_classes'));
        add_action('wp_head', array($this, 'add_structured_data'));
    }
    
    /**
     * Add body classes for warranty pages
     */
    public function add_body_classes($classes) {
        if (is_page('warranty-activation')) {
            $classes[] = 'warranty-activation-page';
            $classes[] = 'warranty-page';
        }
        
        if (is_page('warranty-check')) {
            $classes[] = 'warranty-check-page';
            $classes[] = 'warranty-page';
        }
        
        return $classes;
    }
    
    /**
     * Add structured data for warranty pages
     */
    public function add_structured_data() {
        if (is_page('warranty-activation') || is_page('warranty-check')) {
            $site_name = get_bloginfo('name');
            $site_url = get_site_url();
            
            $structured_data = array(
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => get_the_title(),
                'description' => get_the_excerpt(),
                'url' => get_permalink(),
                'mainEntity' => array(
                    '@type' => 'Service',
                    'name' => 'Product Warranty Service',
                    'provider' => array(
                        '@type' => 'Organization',
                        'name' => $site_name,
                        'url' => $site_url
                    )
                )
            );
            
            echo '<script type="application/ld+json">' . wp_json_encode($structured_data) . '</script>' . "\n";
        }
    }
    
    /**
     * Warranty activation shortcode
     */
    public function warranty_activation_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'title' => __('Warranty Activation', 'warranty-manager'),
            'subtitle' => __('Activate your product warranty by providing the required information', 'warranty-manager'),
            'show_title' => 'yes',
            'show_info' => 'yes',
            'theme' => 'default'
        ), $atts);
        
        ob_start();
        
        include WM_PLUGIN_PATH . 'templates/warranty-activation-form.php';
        
        return ob_get_clean();
    }
    
    /**
     * Warranty check shortcode
     */
    public function warranty_check_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'title' => __('Check Warranty Status', 'warranty-manager'),
            'subtitle' => __('Enter your order ID or phone number to check warranty status', 'warranty-manager'),
            'show_title' => 'yes',
            'show_tips' => 'yes',
            'theme' => 'default'
        ), $atts);
        
        ob_start();
        
        include WM_PLUGIN_PATH . 'templates/warranty-check-form.php';
        
        return ob_get_clean();
    }
    
    /**
     * Get warranty options for dropdown
     */
    public function get_warranty_options() {
        $settings = WooCommerceWarrantyManager::get_settings();
        $warranty_attribute = $settings['warranty_attribute'];
        $options = '';
        
        if (!empty($warranty_attribute)) {
            $terms = get_terms(array(
                'taxonomy' => 'pa_' . $warranty_attribute,
                'hide_empty' => false,
                'orderby' => 'meta_value_num',
                'meta_key' => 'order',
                'order' => 'ASC'
            ));
            
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                    // Extract number from term name (e.g., "12 Months" -> 12)
                    $months = intval(preg_replace('/[^0-9]/', '', $term->name));
                    if ($months > 0) {
                        $options .= '<option value="' . esc_attr($months) . '">' . esc_html($term->name) . '</option>';
                    }
                }
            }
        }
        
        // Fallback to default options if no attribute terms found
        if (empty($options)) {
            $default_months = $settings['default_warranty_months'];
            foreach ($default_months as $months) {
                $options .= '<option value="' . esc_attr($months) . '">' . sprintf(__('%d Months', 'warranty-manager'), $months) . '</option>';
            }
        }
        
        return $options;
    }
    
    /**
     * Validate order and phone number
     */
    public function validate_order_phone($order_id, $phone_number) {
        // Get WooCommerce order
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array(
                'valid' => false,
                'message' => __('Invalid order ID', 'warranty-manager'),
                'code' => 'invalid_order'
            );
        }
        
        // Check if order is completed
        if (!in_array($order->get_status(), array('completed', 'processing'))) {
            return array(
                'valid' => false,
                'message' => __('Order must be completed to activate warranty', 'warranty-manager'),
                'code' => 'order_not_completed'
            );
        }
        
        // Get billing phone from order
        $billing_phone = $order->get_billing_phone();
        
        if (empty($billing_phone)) {
            return array(
                'valid' => false,
                'message' => __('No phone number found in order records', 'warranty-manager'),
                'code' => 'no_phone_in_order'
            );
        }
        
        // Clean phone numbers for comparison
        $clean_input_phone = preg_replace('/[^0-9]/', '', $phone_number);
        $clean_billing_phone = preg_replace('/[^0-9]/', '', $billing_phone);
        
        // Check for exact match or partial match (last 10 digits)
        $phone_match = false;
        if ($clean_input_phone === $clean_billing_phone) {
            $phone_match = true;
        } elseif (strlen($clean_input_phone) >= 10 && strlen($clean_billing_phone) >= 10) {
            $input_last_10 = substr($clean_input_phone, -10);
            $billing_last_10 = substr($clean_billing_phone, -10);
            if ($input_last_10 === $billing_last_10) {
                $phone_match = true;
            }
        }
        
        if (!$phone_match) {
            return array(
                'valid' => false,
                'message' => __('Phone number does not match order records', 'warranty-manager'),
                'code' => 'phone_mismatch'
            );
        }
        
        return array(
            'valid' => true,
            'order' => $order,
            'message' => __('Order and phone number verified', 'warranty-manager')
        );
    }
    
    /**
     * Get order products for display
     */
    public function get_order_products($order) {
        $products = array();
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if ($product) {
                $warranty_info = $this->get_product_warranty_info($product);
                
                $products[] = array(
                    'id' => $product->get_id(),
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total(),
                    'sku' => $product->get_sku(),
                    'warranty_months' => $warranty_info['months'],
                    'warranty_terms' => $warranty_info['terms']
                );
            }
        }
        
        return $products;
    }
    
    /**
     * Get product warranty information
     */
    public function get_product_warranty_info($product) {
        $settings = WooCommerceWarrantyManager::get_settings();
        $warranty_attribute = $settings['warranty_attribute'];
        
        $warranty_info = array(
            'months' => 0,
            'terms' => ''
        );
        
        if (!empty($warranty_attribute) && $product) {
            $warranty_value = $product->get_attribute('pa_' . $warranty_attribute);
            if (!empty($warranty_value)) {
                $months = intval(preg_replace('/[^0-9]/', '', $warranty_value));
                if ($months > 0) {
                    $warranty_info['months'] = $months;
                }
            }
            
            // Get warranty terms from product meta
            $warranty_terms = get_post_meta($product->get_id(), '_warranty_terms', true);
            if (!empty($warranty_terms)) {
                $warranty_info['terms'] = $warranty_terms;
            }
        }
        
        return $warranty_info;
    }
    
    /**
     * Format warranty remaining time
     */
    public function format_warranty_remaining($expiry_date) {
        if (empty($expiry_date)) {
            return __('N/A', 'warranty-manager');
        }
        
        $expiry = new DateTime($expiry_date);
        $now = new DateTime();
        
        if ($expiry <= $now) {
            return __('Expired', 'warranty-manager');
        }
        
        $diff = $now->diff($expiry);
        
        if ($diff->y > 0) {
            if ($diff->y == 1) {
                return sprintf(__('1 year, %d month(s)', 'warranty-manager'), $diff->m);
            } else {
                return sprintf(__('%d years, %d month(s)', 'warranty-manager'), $diff->y, $diff->m);
            }
        } elseif ($diff->m > 0) {
            if ($diff->m == 1) {
                return sprintf(__('1 month, %d day(s)', 'warranty-manager'), $diff->d);
            } else {
                return sprintf(__('%d months, %d day(s)', 'warranty-manager'), $diff->m, $diff->d);
            }
        } else {
            if ($diff->d == 1) {
                return __('1 day', 'warranty-manager');
            } else {
                return sprintf(__('%d days', 'warranty-manager'), $diff->d);
            }
        }
    }
    
    /**
     * Get warranty status class
     */
    public function get_warranty_status_class($status, $expiry_date = '') {
        $classes = array('warranty-status', 'warranty-status-' . $status);
        
        if ($status === 'active' && !empty($expiry_date)) {
            $expiry = new DateTime($expiry_date);
            $now = new DateTime();
            
            if ($expiry <= $now) {
                $classes[] = 'warranty-expired';
            } else {
                $days_remaining = $now->diff($expiry)->days;
                if ($days_remaining <= 30) {
                    $classes[] = 'warranty-expiring-soon';
                }
            }
        }
        
        return implode(' ', $classes);
    }
    
    /**
     * Generate warranty certificate HTML
     */
    public function generate_warranty_certificate($warranty) {
        if (!$warranty || $warranty->status !== 'active') {
            return '';
        }
        
        $order = wc_get_order($warranty->order_id);
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        $site_logo = get_custom_logo();
        
        ob_start();
        ?>
        <div class="warranty-certificate">
            <div class="warranty-certificate-header">
                <?php if ($site_logo): ?>
                    <div class="warranty-logo">
                        <?php echo $site_logo; ?>
                    </div>
                <?php endif; ?>
                <h2><?php echo esc_html($site_name); ?></h2>
                <h3><?php _e('WARRANTY CERTIFICATE', 'warranty-manager'); ?></h3>
                <div class="warranty-certificate-number">
                    <?php _e('Certificate No:', 'warranty-manager'); ?> 
                    <strong><?php echo 'WC-' . str_pad($warranty->id, 8, '0', STR_PAD_LEFT); ?></strong>
                </div>
            </div>
            
            <div class="warranty-certificate-body">
                <div class="warranty-validity-statement">
                    <p><?php _e('This certificate confirms that the following product is covered under our warranty program:', 'warranty-manager'); ?></p>
                </div>
                
                <div class="warranty-info-grid">
                    <div class="warranty-info-item">
                        <strong><?php _e('Customer Name:', 'warranty-manager'); ?></strong>
                        <span><?php echo esc_html($warranty->customer_name); ?></span>
                    </div>
                    
                    <div class="warranty-info-item">
                        <strong><?php _e('Order ID:', 'warranty-manager'); ?></strong>
                        <span>#<?php echo esc_html($warranty->order_id); ?></span>
                    </div>
                    
                    <div class="warranty-info-item">
                        <strong><?php _e('Product:', 'warranty-manager'); ?></strong>
                        <span><?php echo esc_html($warranty->product_name ?: __('See Order Details', 'warranty-manager')); ?></span>
                    </div>
                    
                    <div class="warranty-info-item">
                        <strong><?php _e('Phone Number:', 'warranty-manager'); ?></strong>
                        <span><?php echo esc_html($warranty->phone_number); ?></span>
                    </div>
                    
                    <div class="warranty-info-item">
                        <strong><?php _e('Purchase Date:', 'warranty-manager'); ?></strong>
                        <span><?php echo date_i18n(get_option('date_format'), strtotime($warranty->purchase_date)); ?></span>
                    </div>
                    
                    <div class="warranty-info-item">
                        <strong><?php _e('Activation Date:', 'warranty-manager'); ?></strong>
                        <span><?php echo date_i18n(get_option('date_format'), strtotime($warranty->activation_date)); ?></span>
                    </div>
                    
                    <div class="warranty-info-item">
                        <strong><?php _e('Warranty Period:', 'warranty-manager'); ?></strong>
                        <span><?php echo sprintf(__('%d months', 'warranty-manager'), $warranty->warranty_months); ?></span>
                    </div>
                    
                    <div class="warranty-info-item">
                        <strong><?php _e('Expiry Date:', 'warranty-manager'); ?></strong>
                        <span><?php echo date_i18n(get_option('date_format'), strtotime($warranty->expiry_date)); ?></span>
                    </div>
                </div>
                
                <div class="warranty-coverage-details">
                    <h4><?php _e('Warranty Coverage:', 'warranty-manager'); ?></h4>
                    <p><?php _e('This warranty covers defects in materials and workmanship under normal use and conditions.', 'warranty-manager'); ?></p>
                </div>
                
                <div class="warranty-terms">
                    <h4><?php _e('Terms & Conditions:', 'warranty-manager'); ?></h4>
                    <ul>
                        <li><?php _e('This warranty covers manufacturing defects only.', 'warranty-manager'); ?></li>
                        <li><?php _e('Warranty does not cover damage due to misuse, abuse, or normal wear and tear.', 'warranty-manager'); ?></li>
                        <li><?php _e('Original purchase receipt and this certificate may be required for warranty claims.', 'warranty-manager'); ?></li>
                        <li><?php _e('Warranty is non-transferable and valid only for the original purchaser.', 'warranty-manager'); ?></li>
                        <li><?php _e('Warranty service must be performed by authorized service providers.', 'warranty-manager'); ?></li>
                        <li><?php _e('This warranty is in addition to and does not affect your statutory consumer rights.', 'warranty-manager'); ?></li>
                    </ul>
                </div>
                
                <?php if (!empty($warranty->notes)): ?>
                <div class="warranty-special-notes">
                    <h4><?php _e('Special Notes:', 'warranty-manager'); ?></h4>
                    <p><?php echo wp_kses_post($warranty->notes); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="warranty-certificate-footer">
                <div class="warranty-qr-code">
                    <!-- QR Code placeholder - can be implemented with QR code library -->
                    <div class="qr-placeholder">
                        <small><?php _e('QR Code', 'warranty-manager'); ?></small>
                    </div>
                </div>
                
                <div class="warranty-verification">
                    <p><strong><?php _e('Verification URL:', 'warranty-manager'); ?></strong></p>
                    <p><small><?php echo esc_url(get_permalink(get_page_by_path('warranty-check')) . '?verify=' . base64_encode($warranty->order_id . '|' . $warranty->phone_number)); ?></small></p>
                </div>
                
                <div class="warranty-signature">
                    <div class="signature-line">
                        <div class="signature-placeholder"></div>
                        <p><?php _e('Authorized Signature', 'warranty-manager'); ?></p>
                    </div>
                    
                    <div class="company-details">
                        <p><strong><?php echo esc_html($site_name); ?></strong></p>
                        <p><?php _e('Website:', 'warranty-manager'); ?> <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html(parse_url($site_url, PHP_URL_HOST)); ?></a></p>
                        <p><?php _e('Issue Date:', 'warranty-manager'); ?> <?php echo date_i18n(get_option('date_format')); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="warranty-certificate-actions">
            <button type="button" class="warranty-btn warranty-btn-secondary" onclick="window.print()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6,9 6,2 18,2 18,9"></polyline>
                    <path d="M6,18H4a2,2 0 0,1-2-2V11a2,2 0 0,1,2-2H20a2,2 0 0,1,2,2v5a2,2 0 0,1-2,2H18"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                <?php _e('Print Certificate', 'warranty-manager'); ?>
            </button>
            
            <button type="button" class="warranty-btn warranty-btn-primary" onclick="warranty_download_pdf()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7,10 12,15 17,10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <?php _e('Download PDF', 'warranty-manager'); ?>
            </button>
        </div>
        
        <style>
        .warranty-certificate {
            background: #ffffff;
            color: #000000;
        }
        
        .warranty-logo img {
            max-height: 60px;
            margin-bottom: 16px;
        }
        
        .warranty-certificate-number {
            margin-top: 12px;
            font-size: 14px;
            color: #666;
        }
        
        .warranty-validity-statement {
            text-align: center;
            margin-bottom: 24px;
            font-style: italic;
            color: #555;
        }
        
        .warranty-coverage-details {
            margin: 24px 0;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid var(--warranty-primary);
        }
        
        .warranty-coverage-details h4 {
            margin-bottom: 8px;
            color: var(--warranty-gray-800);
        }
        
        .warranty-special-notes {
            margin: 24px 0;
            padding: 16px;
            background: #fff3cd;
            border-radius: 6px;
            border: 1px solid #ffeaa7;
        }
        
        .warranty-special-notes h4 {
            margin-bottom: 8px;
            color: #856404;
        }
        
        .qr-placeholder {
            width: 80px;
            height: 80px;
            border: 2px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }
        
        .warranty-verification {
            flex: 1;
            text-align: center;
        }
        
        .warranty-verification p {
            margin: 4px 0;
            font-size: 12px;
        }
        
        .signature-line {
            text-align: center;
            margin-bottom: 16px;
        }
        
        .signature-placeholder {
            width: 200px;
            height: 50px;
            border-bottom: 2px solid #000;
            margin: 0 auto 8px;
        }
        
        .company-details p {
            margin: 4px 0;
            font-size: 12px;
        }
        
        @media print {
            .warranty-certificate-actions,
            .warranty-result.success > strong,
            .warranty-form-wrapper > h2,
            .warranty-form-wrapper > p {
                display: none !important;
            }
            
            .warranty-certificate {
                page-break-inside: avoid;
                border: 2px solid #000;
                padding: 30px;
                margin: 0;
                box-shadow: none;
                background: white !important;
                color: black !important;
            }
            
            .warranty-certificate * {
                color: black !important;
            }
        }
        </style>
        
        <script>
        function warranty_download_pdf() {
            // Create a new window for printing
            var printWindow = window.open('', '_blank');
            var certificateContent = document.querySelector('.warranty-certificate').outerHTML;
            var certificateStyles = document.querySelector('style').outerHTML;
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Warranty Certificate - <?php echo esc_js($warranty->customer_name); ?></title>
                    <meta charset="utf-8">
                    ${certificateStyles}
                    <style>
                        body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
                        .warranty-certificate { border: 2px solid #000; }
                    </style>
                </head>
                <body>
                    ${certificateContent}
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.focus();
            
            setTimeout(function() {
                printWindow.print();
                printWindow.close();
            }, 250);
        }
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Send warranty notification email
     */
    public function send_warranty_notification($warranty, $type = 'activated') {
        $settings = WooCommerceWarrantyManager::get_settings();
        
        if ($settings['email_notifications'] !== 'yes' || empty($warranty->customer_email)) {
            return false;
        }
        
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        $admin_email = get_option('admin_email');
        
        $subject = '';
        $message = '';
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $admin_email . '>'
        );
        
        switch ($type) {
            case 'activated':
                $subject = sprintf(__('[%s] Warranty Activated - Order #%s', 'warranty-manager'), $site_name, $warranty->order_id);
                
                $message = $this->get_email_template('activated', array(
                    'customer_name' => $warranty->customer_name,
                    'order_id' => $warranty->order_id,
                    'product_name' => $warranty->product_name ?: __('N/A', 'warranty-manager'),
                    'warranty_months' => $warranty->warranty_months,
                    'activation_date' => date_i18n(get_option('date_format'), strtotime($warranty->activation_date)),
                    'expiry_date' => date_i18n(get_option('date_format'), strtotime($warranty->expiry_date)),
                    'warranty_check_url' => get_permalink(get_page_by_path('warranty-check')),
                    'site_name' => $site_name,
                    'site_url' => $site_url
                ));
                break;
                
            case 'expiring':
                $subject = sprintf(__('[%s] Warranty Expiring Soon - Order #%s', 'warranty-manager'), $site_name, $warranty->order_id);
                
                $days_remaining = max(0, (new DateTime())->diff(new DateTime($warranty->expiry_date))->days);
                
                $message = $this->get_email_template('expiring', array(
                    'customer_name' => $warranty->customer_name,
                    'order_id' => $warranty->order_id,
                    'product_name' => $warranty->product_name ?: __('N/A', 'warranty-manager'),
                    'expiry_date' => date_i18n(get_option('date_format'), strtotime($warranty->expiry_date)),
                    'days_remaining' => $days_remaining,
                    'warranty_check_url' => get_permalink(get_page_by_path('warranty-check')),
                    'site_name' => $site_name,
                    'site_url' => $site_url
                ));
                break;
                
            case 'expired':
                $subject = sprintf(__('[%s] Warranty Expired - Order #%s', 'warranty-manager'), $site_name, $warranty->order_id);
                
                $message = $this->get_email_template('expired', array(
                    'customer_name' => $warranty->customer_name,
                    'order_id' => $warranty->order_id,
                    'product_name' => $warranty->product_name ?: __('N/A', 'warranty-manager'),
                    'expired_date' => date_i18n(get_option('date_format'), strtotime($warranty->expiry_date)),
                    'site_name' => $site_name,
                    'site_url' => $site_url
                ));
                break;
        }
        
        return wp_mail($warranty->customer_email, $subject, $message, $headers);
    }
    
    /**
     * Get email template
     */
    private function get_email_template($type, $vars) {
        $template_path = WM_PLUGIN_PATH . 'templates/emails/' . $type . '.php';
        
        if (file_exists($template_path)) {
            ob_start();
            extract($vars);
            include $template_path;
            return ob_get_clean();
        }
        
        // Fallback to simple text template
        return $this->get_simple_email_template($type, $vars);
    }
    
    /**
     * Get simple email template (fallback)
     */
    private function get_simple_email_template($type, $vars) {
        extract($vars);
        
        $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9;">';
        $message .= '<div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">';
        $message .= '<h2 style="color: #333; margin-bottom: 20px;">' . esc_html($site_name) . '</h2>';
        
        switch ($type) {
            case 'activated':
                $message .= '<h3 style="color: #28a745;">Warranty Activated Successfully!</h3>';
                $message .= '<p>Dear ' . esc_html($customer_name) . ',</p>';
                $message .= '<p>Your warranty has been successfully activated for Order #' . esc_html($order_id) . '.</p>';
                $message .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">';
                $message .= '<h4 style="margin: 0 0 10px 0; color: #333;">Warranty Details:</h4>';
                $message .= '<p><strong>Product:</strong> ' . esc_html($product_name) . '</p>';
                $message .= '<p><strong>Warranty Period:</strong> ' . $warranty_months . ' months</p>';
                $message .= '<p><strong>Activation Date:</strong> ' . $activation_date . '</p>';
                $message .= '<p><strong>Expiry Date:</strong> ' . $expiry_date . '</p>';
                $message .= '</div>';
                $message .= '<p>You can check your warranty status anytime at: <a href="' . esc_url($warranty_check_url) . '">Warranty Check</a></p>';
                break;
                
            case 'expiring':
                $message .= '<h3 style="color: #ffc107;">Warranty Expiring Soon</h3>';
                $message .= '<p>Dear ' . esc_html($customer_name) . ',</p>';
                $message .= '<p>Your warranty for Order #' . esc_html($order_id) . ' is expiring in ' . $days_remaining . ' days.</p>';
                $message .= '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #ffeaa7;">';
                $message .= '<h4 style="margin: 0 0 10px 0; color: #856404;">Warranty Details:</h4>';
                $message .= '<p><strong>Product:</strong> ' . esc_html($product_name) . '</p>';
                $message .= '<p><strong>Expiry Date:</strong> ' . $expiry_date . '</p>';
                $message .= '<p><strong>Days Remaining:</strong> ' . $days_remaining . '</p>';
                $message .= '</div>';
                $message .= '<p>If you need to make a warranty claim, please do so before the expiry date.</p>';
                break;
                
            case 'expired':
                $message .= '<h3 style="color: #dc3545;">Warranty Expired</h3>';
                $message .= '<p>Dear ' . esc_html($customer_name) . ',</p>';
                $message .= '<p>Your warranty for Order #' . esc_html($order_id) . ' has expired on ' . $expired_date . '.</p>';
                $message .= '<p>Thank you for choosing ' . esc_html($site_name) . '. We hope you had a great experience with our product!</p>';
                break;
        }
        
        $message .= '<hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">';
        $message .= '<p style="color: #666; font-size: 14px;">Best regards,<br>' . esc_html($site_name) . ' Team</p>';
        $message .= '<p style="color: #999; font-size: 12px;">Visit us at: <a href="' . esc_url($site_url) . '">' . esc_html($site_url) . '</a></p>';
        $message .= '</div>';
        $message .= '</div>';
        
        return $message;
    }
    
    /**
     * Get warranty status message
     */
    public function get_warranty_status_message($status, $expiry_date = '') {
        switch ($status) {
            case 'active':
                if (!empty($expiry_date)) {
                    $expiry = new DateTime($expiry_date);
                    $now = new DateTime();
                    
                    if ($expiry <= $now) {
                        return array(
                            'message' => __('Your warranty has expired.', 'warranty-manager'),
                            'class' => 'expired',
                            'icon' => 'alert-circle'
                        );
                    }
                    
                    $days_remaining = $now->diff($expiry)->days;
                    
                    if ($days_remaining <= 30) {
                        return array(
                            'message' => sprintf(__('Your warranty is active but expiring in %d days.', 'warranty-manager'), $days_remaining),
                            'class' => 'expiring',
                            'icon' => 'alert-triangle'
                        );
                    }
                }
                
                return array(
                    'message' => __('Your warranty is active and valid.', 'warranty-manager'),
                    'class' => 'active',
                    'icon' => 'check-circle'
                );
                
            case 'pending':
                return array(
                    'message' => __('Your warranty activation is pending approval. We will notify you once it\'s processed.', 'warranty-manager'),
                    'class' => 'pending',
                    'icon' => 'clock'
                );
                
            case 'expired':
                return array(
                    'message' => __('Your warranty has expired. Thank you for choosing our products.', 'warranty-manager'),
                    'class' => 'expired',
                    'icon' => 'x-circle'
                );
                
            case 'cancelled':
                return array(
                    'message' => __('Your warranty has been cancelled. Please contact us if you believe this is an error.', 'warranty-manager'),
                    'class' => 'cancelled',
                    'icon' => 'x-circle'
                );
                
            default:
                return array(
                    'message' => __('Unknown warranty status. Please contact support.', 'warranty-manager'),
                    'class' => 'unknown',
                    'icon' => 'help-circle'
                );
        }
    }
    
    /**
     * Generate warranty QR code (if needed)
     */
    public function generate_warranty_qr_code($warranty) {
        // This would require a QR code library like phpqrcode
        // For now, return a placeholder URL
        $verify_data = base64_encode($warranty->order_id . '|' . $warranty->phone_number . '|' . $warranty->id);
        $verify_url = get_permalink(get_page_by_path('warranty-check')) . '?verify=' . $verify_data;
        
        return array(
            'url' => $verify_url,
            'data' => $verify_data
        );
    }
    
    /**
     * Verify warranty from QR code or verification link
     */
    public function verify_warranty_from_code($verification_code) {
        $decoded = base64_decode($verification_code);
        $parts = explode('|', $decoded);
        
        if (count($parts) >= 2) {
            $order_id = $parts[0];
            $phone_number = $parts[1];
            
            return $this->database->get_warranty_by_order_and_phone($order_id, $phone_number);
        }
        
        return false;
    }
    
    /**
     * Get warranty statistics for customer
     */
    public function get_customer_warranty_stats($customer_email_or_phone) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'warranty_records';
        
        // Check if input is email or phone
        $field = strpos($customer_email_or_phone, '@') !== false ? 'customer_email' : 'phone_number';
        
        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count 
             FROM {$table_name} 
             WHERE {$field} = %s 
             GROUP BY status",
            $customer_email_or_phone
        ), ARRAY_A);
        
        $result = array(
            'total' => 0,
            'active' => 0,
            'pending' => 0,
            'expired' => 0,
            'cancelled' => 0
        );
        
        foreach ($stats as $stat) {
            $result[$stat['status']] = (int) $stat['count'];
            $result['total'] += (int) $stat['count'];
        }
        
        return $result;
    }
    
    /**
     * Check if user can access warranty
     */
    public function can_user_access_warranty($warranty, $user_input) {
        if (!$warranty) {
            return false;
        }
        
        // Check if input matches order ID
        if ($warranty->order_id === $user_input) {
            return true;
        }
        
        // Check if input matches phone number
        $clean_input = preg_replace('/[^0-9]/', '', $user_input);
        $clean_warranty_phone = preg_replace('/[^0-9]/', '', $warranty->phone_number);
        
        if ($clean_input === $clean_warranty_phone) {
            return true;
        }
        
        // Check last 10 digits for phone match
        if (strlen($clean_input) >= 10 && strlen($clean_warranty_phone) >= 10) {
            $input_last_10 = substr($clean_input, -10);
            $warranty_last_10 = substr($clean_warranty_phone, -10);
            if ($input_last_10 === $warranty_last_10) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate warranty reminder schedule
     */
    public function schedule_warranty_reminders() {
        // This would be called by a cron job to send expiry reminders
        global $wpdb;
        $table_name = $wpdb->prefix . 'warranty_records';
        
        // Get warranties expiring in 30 days
        $expiring_warranties = $wpdb->get_results(
            "SELECT * FROM {$table_name} 
             WHERE status = 'active' 
             AND expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
             AND expiry_date > DATE_ADD(NOW(), INTERVAL 29 DAY)"
        );
        
        foreach ($expiring_warranties as $warranty) {
            $this->send_warranty_notification($warranty, 'expiring');
        }
        
        // Update expired warranties
        $this->database->update_expired_warranties();
        
        // Get newly expired warranties to send notifications
        $expired_warranties = $wpdb->get_results(
            "SELECT * FROM {$table_name} 
             WHERE status = 'expired' 
             AND expiry_date BETWEEN DATE_SUB(NOW(), INTERVAL 1 DAY) AND NOW()"
        );
        
        foreach ($expired_warranties as $warranty) {
            $this->send_warranty_notification($warranty, 'expired');
        }
        
        return array(
            'expiring_sent' => count($expiring_warranties),
            'expired_sent' => count($expired_warranties)
        );
    }
    
    /**
     * Get warranty display data for templates
     */
    public function get_warranty_display_data($warranty) {
        if (!$warranty) {
            return null;
        }
        
        $status_info = $this->get_warranty_status_message($warranty->status, $warranty->expiry_date);
        
        return array(
            'id' => $warranty->id,
            'order_id' => $warranty->order_id,
            'customer_name' => $warranty->customer_name,
            'phone_number' => $warranty->phone_number,
            'product_name' => $warranty->product_name ?: __('See Order Details', 'warranty-manager'),
            'warranty_months' => $warranty->warranty_months,
            'purchase_date' => date_i18n(get_option('date_format'), strtotime($warranty->purchase_date)),
            'activation_date' => !empty($warranty->activation_date) ? date_i18n(get_option('date_format'), strtotime($warranty->activation_date)) : '',
            'expiry_date' => !empty($warranty->expiry_date) ? date_i18n(get_option('date_format'), strtotime($warranty->expiry_date)) : '',
            'status' => $warranty->status,
            'status_label' => ucfirst($warranty->status),
            'status_message' => $status_info['message'],
            'status_class' => $status_info['class'],
            'status_icon' => $status_info['icon'],
            'warranty_remaining' => $this->format_warranty_remaining($warranty->expiry_date),
            'is_active' => $warranty->status === 'active',
            'is_expired' => $warranty->status === 'expired' || (!empty($warranty->expiry_date) && new DateTime($warranty->expiry_date) <= new DateTime()),
            'is_expiring_soon' => $warranty->status === 'active' && !empty($warranty->expiry_date) && (new DateTime())->diff(new DateTime($warranty->expiry_date))->days <= 30,
            'certificate_available' => $warranty->status === 'active'
        );
    }
}