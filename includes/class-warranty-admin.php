/**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->handle_settings_save();
        }
        
        $settings = WooCommerceWarrantyManager::get_settings();
        $attributes = wc_get_attribute_taxonomies();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Warranty Settings', 'warranty-manager'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('warranty_settings', 'warranty_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="warranty_attribute"><?php _e('Warranty Attribute', 'warranty-manager'); ?></label>
                        </th>
                        <td>
                            <select name="warranty_attribute" id="warranty_attribute">
                                <option value=""><?php _e('Select Warranty Attribute', 'warranty-manager'); ?></option>
                                <?php foreach ($attributes as $attribute): ?>
                                    <option value="<?php echo esc_attr($attribute->attribute_name); ?>" 
                                            <?php selected($settings['warranty_attribute'], $attribute->attribute_name); ?>>
                                        <?php echo esc_html($attribute->attribute_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('Select the product attribute that contains warranty information.', 'warranty-manager'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="auto_activate"><?php _e('Auto Activate Warranties', 'warranty-manager'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="auto_activate" name="auto_activate" value="yes" 
                                       <?php checked($settings['auto_activate'], 'yes'); ?>>
                                <?php _e('Automatically activate warranties when customers submit activation form', 'warranty-manager'); ?>
                            </label>
                            <p class="description">
                                <?php _e('If unchecked, warranties will be set to pending status for manual review.', 'warranty-manager'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="email_notifications"><?php _e('Email Notifications', 'warranty-manager'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="email_notifications" name="email_notifications" value="yes" 
                                       <?php checked($settings['email_notifications'], 'yes'); ?>>
                                <?php _e('Send email notifications to customers when warranty status changes', 'warranty-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="default_warranty_months"><?php _e('Default Warranty Periods', 'warranty-manager'); ?></label>
                        </th>
                        <td>
                            <?php
                            $default_months = $settings['default_warranty_months'];
                            $available_months = array(3, 6, 9, 12, 15, 18, 24, 30, 36, 48, 60);
                            ?>
                            <fieldset>
                                <legend class="screen-reader-text"><?php _e('Select default warranty periods', 'warranty-manager'); ?></legend>
                                <?php foreach ($available_months as $months): ?>
                                    <label>
                                        <input type="checkbox" name="default_warranty_months[]" value="<?php echo $months; ?>" 
                                               <?php checked(in_array($months, $default_months)); ?>>
                                        <?php echo sprintf(__('%d months', 'warranty-manager'), $months); ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description">
                                <?php _e('These warranty periods will be available when no warranty attribute is selected.', 'warranty-manager'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h2><?php _e('System Information', 'warranty-manager'); ?></h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <th><?php _e('Plugin Version:', 'warranty-manager'); ?></th>
                        <td><?php echo WM_PLUGIN_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('WordPress Version:', 'warranty-manager'); ?></th>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('WooCommerce Version:', 'warranty-manager'); ?></th>
                        <td><?php echo defined('WC_VERSION') ? WC_VERSION : __('Not installed', 'warranty-manager'); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Database Version:', 'warranty-manager'); ?></th>
                        <td><?php echo get_option('warranty_manager_db_version', '1.0'); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Total Warranties:', 'warranty-manager'); ?></th>
                        <td><?php echo number_format($this->database->get_warranty_count()); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Handle settings save
     */
    private function handle_settings_save() {
        if (!wp_verify_nonce($_POST['warranty_settings_nonce'], 'warranty_settings')) {
            wp_die(__('Security check failed', 'warranty-manager'));
        }
        
        $settings = array(
            'warranty_attribute' => sanitize_text_field($_POST['warranty_attribute'] ?? ''),
            'auto_activate' => isset($_POST['auto_activate']) ? 'yes' : 'no',
            'email_notifications' => isset($_POST['email_notifications']) ? 'yes' : 'no',
            'default_warranty_months' => array_map('intval', $_POST['default_warranty_months'] ?? array())
        );
        
        WooCommerceWarrantyManager::update_settings($settings);
        
        $this->add_admin_notice(
            __('Settings saved successfully!', 'warranty-manager'),
            'success'
        );
    }
    
    /**
     * Import/Export page
     */
    public function import_export_page() {
        if (isset($_POST['export_warranties'])) {
            $this->handle_export();
        }
        
        if (isset($_POST['import_warranties'])) {
            $this->handle_import();
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Import/Export Warranties', 'warranty-manager'); ?></h1>
            
            <div class="warranty-import-export-container">
                <!-- Export Section -->
                <div class="warranty-export-section">
                    <h2><?php _e('Export Warranties', 'warranty-manager'); ?></h2>
                    <p><?php _e('Export warranty data to CSV format for backup or analysis.', 'warranty-manager'); ?></p>
                    
                    <form method="post">
                        <?php wp_nonce_field('warranty_export', 'warranty_export_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="export_status"><?php _e('Status Filter', 'warranty-manager'); ?></label>
                                </th>
                                <td>
                                    <select name="export_status" id="export_status">
                                        <option value=""><?php _e('All Statuses', 'warranty-manager'); ?></option>
                                        <option value="pending"><?php _e('Pending', 'warranty-manager'); ?></option>
                                        <option value="active"><?php _e('Active', 'warranty-manager'); ?></option>
                                        <option value="expired"><?php _e('Expired', 'warranty-manager'); ?></option>
                                        <option value="cancelled"><?php _e('Cancelled', 'warranty-manager'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(__('Export to CSV', 'warranty-manager'), 'primary', 'export_warranties'); ?>
                    </form>
                </div>
                
                <hr>
                
                <!-- Import Section -->
                <div class="warranty-import-section">
                    <h2><?php _e('Import Warranties', 'warranty-manager'); ?></h2>
                    <p><?php _e('Import warranty data from CSV file. The CSV should contain the following columns:', 'warranty-manager'); ?></p>
                    
                    <ul>
                        <li><code>order_id</code> - <?php _e('WooCommerce Order ID', 'warranty-manager'); ?></li>
                        <li><code>customer_name</code> - <?php _e('Customer Name', 'warranty-manager'); ?></li>
                        <li><code>customer_email</code> - <?php _e('Customer Email', 'warranty-manager'); ?></li>
                        <li><code>phone_number</code> - <?php _e('Phone Number', 'warranty-manager'); ?></li>
                        <li><code>product_name</code> - <?php _e('Product Name (optional)', 'warranty-manager'); ?></li>
                        <li><code>warranty_months</code> - <?php _e('Warranty Period in Months', 'warranty-manager'); ?></li>
                        <li><code>status</code> - <?php _e('Status (pending, active, expired, cancelled)', 'warranty-manager'); ?></li>
                        <li><code>purchase_date</code> - <?php _e('Purchase Date (YYYY-MM-DD format)', 'warranty-manager'); ?></li>
                    </ul>
                    
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('warranty_import', 'warranty_import_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="import_file"><?php _e('CSV File', 'warranty-manager'); ?></label>
                                </th>
                                <td>
                                    <input type="file" id="import_file" name="import_file" accept=".csv" required>
                                    <p class="description"><?php _e('Select a CSV file to import', 'warranty-manager'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="update_existing"><?php _e('Update Existing', 'warranty-manager'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="update_existing" name="update_existing" value="1">
                                        <?php _e('Update existing warranties if order ID and phone number match', 'warranty-manager'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(__('Import CSV', 'warranty-manager'), 'primary', 'import_warranties'); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle export
     */
    private function handle_export() {
        if (!wp_verify_nonce($_POST['warranty_export_nonce'], 'warranty_export')) {
            wp_die(__('Security check failed', 'warranty-manager'));
        }
        
        $status = sanitize_text_field($_POST['export_status'] ?? '');
        $warranties = $this->database->get_export_data($status);
        
        if (empty($warranties)) {
            $this->add_admin_notice(
                __('No warranties found to export.', 'warranty-manager'),
                'error'
            );
            return;
        }
        
        $filename = 'warranties-export-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel compatibility
        fputs($output, "\xEF\xBB\xBF");
        
        // Headers
        $headers = array(
            'Order ID',
            'Customer Name',
            'Customer Email',
            'Phone Number',
            'Product Name',
            'Warranty Months',
            'Purchase Date',
            'Activation Date',
            'Expiry Date',
            'Status',
            'Created Date'
        );
        
        fputcsv($output, $headers);
        
        // Data rows
        foreach ($warranties as $warranty) {
            $row = array(
                $warranty['order_id'],
                $warranty['customer_name'],
                $warranty['customer_email'],
                $warranty['phone_number'],
                $warranty['product_name'],
                $warranty['warranty_months'],
                $warranty['purchase_date'],
                $warranty['activation_date'],
                $warranty['expiry_date'],
                $warranty['status'],
                $warranty['created_at']
            );
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Handle import
     */
    private function handle_import() {
        if (!wp_verify_nonce($_POST['warranty_import_nonce'], 'warranty_import')) {
            wp_die(__('Security check failed', 'warranty-manager'));
        }
        
        if (empty($_FILES['import_file']['tmp_name'])) {
            $this->add_admin_notice(
                __('Please select a CSV file to import.', 'warranty-manager'),
                'error'
            );
            return;
        }
        
        $file = $_FILES['import_file']['tmp_name'];
        $update_existing = isset($_POST['update_existing']);
        
        $imported = 0;
        $updated = 0;
        $errors = array();
        
        if (($handle = fopen($file, 'r')) !== false) {
            $headers = fgetcsv($handle, 1000, ',');
            
            // Map headers to expected columns
            $header_map = array();
            foreach ($headers as $index => $header) {
                $header_map[strtolower(trim($header))] = $index;
            }
            
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $row_data = array();
                
                // Required fields
                $required_fields = array('order_id', 'customer_name', 'phone_number', 'warranty_months');
                $has_required = true;
                
                foreach ($required_fields as $field) {
                    if (!isset($header_map[$field]) || empty($data[$header_map[$field]])) {
                        $has_required = false;
                        break;
                    }
                }
                
                if (!$has_required) {
                    $errors[] = sprintf(__('Row %d: Missing required fields', 'warranty-manager'), $imported + $updated + count($errors) + 2);
                    continue;
                }
                
                // Map data
                $row_data['order_id'] = sanitize_text_field($data[$header_map['order_id']]);
                $row_data['customer_name'] = sanitize_text_field($data[$header_map['customer_name']]);
                $row_data['phone_number'] = sanitize_text_field($data[$header_map['phone_number']]);
                $row_data['warranty_months'] = intval($data[$header_map['warranty_months']]);
                
                if (isset($header_map['customer_email']) && !empty($data[$header_map['customer_email']])) {
                    $row_data['customer_email'] = sanitize_email($data[$header_map['customer_email']]);
                }
                
                if (isset($header_map['product_name']) && !empty($data[$header_map['product_name']])) {
                    $row_data['product_name'] = sanitize_text_field($data[$header_map['product_name']]);
                }
                
                if (isset($header_map['status']) && !empty($data[$header_map['status']])) {
                    $status = strtolower(sanitize_text_field($data[$header_map['status']]));
                    if (in_array($status, array('pending', 'active', 'expired', 'cancelled'))) {
                        $row_data['status'] = $status;
                    }
                }
                
                if (isset($header_map['purchase_date']) && !empty($data[$header_map['purchase_date']])) {
                    $row_data['purchase_date'] = sanitize_text_field($data[$header_map['purchase_date']]);
                }
                
                // Check if warranty exists
                $existing = $this->database->get_warranty_by_order_and_phone($row_data['order_id'], $row_data['phone_number']);
                
                if ($existing && $update_existing) {
                    // Update existing warranty
                    if ($this->database->update_warranty($existing->id, $row_data)) {
                        $updated++;
                    } else {
                        $errors[] = sprintf(__('Row %d: Failed to update warranty', 'warranty-manager'), $imported + $updated + count($errors) + 2);
                    }
                } elseif (!$existing) {
                    // Insert new warranty
                    if ($this->database->insert_warranty($row_data)) {
                        $imported++;
                    } else {
                        $errors[] = sprintf(__('Row %d: Failed to insert warranty', 'warranty-manager'), $imported + $updated + count($errors) + 2);
                    }
                }
            }
            
            fclose($handle);
        }
        
        // Show results
        $messages = array();
        
        if ($imported > 0) {
            $messages[] = sprintf(__('%d warranties imported successfully.', 'warranty-manager'), $imported);
        }
        
        if ($updated > 0) {
            $messages[] = sprintf(__('%d warranties updated successfully.', 'warranty-manager'), $updated);
        }
        
        if (!empty($errors)) {
            $messages[] = sprintf(__('%d errors occurred:', 'warranty-manager'), count($errors));
            $messages = array_merge($messages, $errors);
        }
        
        if (!empty($messages)) {
            $notice_type = empty($errors) ? 'success' : 'warning';
            $this->add_admin_notice(implode('<br>', $messages), $notice_type);
        }
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('warranty_manager', 'warranty_manager_settings');
    }
    
    /**
     * Add admin notice
     */
    private function add_admin_notice($message, $type = 'info') {
        add_settings_error('warranty_manager_notices', 'warranty_manager_notice', $message, $type);
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        settings_errors('warranty_manager_notices');
    }
}
<?php
/**
 * Admin functionality for Warranty Manager
 * 
 * Path: /wp-content/plugins/woocommerce-warranty-manager/includes/class-warranty-admin.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WM_Admin {
    
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
     * Initialize admin hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_notices', array($this, 'admin_notices'));
        add_filter('set-screen-option', array($this, 'set_screen_options'), 10, 3);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        $hook = add_menu_page(
            __('Warranty Manager', 'warranty-manager'),
            __('Warranty Manager', 'warranty-manager'),
            'manage_options',
            'warranty-manager',
            array($this, 'admin_page'),
            'dashicons-shield',
            30
        );
        
        add_submenu_page(
            'warranty-manager',
            __('All Warranties', 'warranty-manager'),
            __('All Warranties', 'warranty-manager'),
            'manage_options',
            'warranty-manager',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'warranty-manager',
            __('Add New Warranty', 'warranty-manager'),
            __('Add New', 'warranty-manager'),
            'manage_options',
            'warranty-add-new',
            array($this, 'add_warranty_page')
        );
        
        add_submenu_page(
            'warranty-manager',
            __('Settings', 'warranty-manager'),
            __('Settings', 'warranty-manager'),
            'manage_options',
            'warranty-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'warranty-manager',
            __('Import/Export', 'warranty-manager'),
            __('Import/Export', 'warranty-manager'),
            'manage_options',
            'warranty-import-export',
            array($this, 'import_export_page')
        );
        
        // Add screen options
        add_action("load-$hook", array($this, 'screen_options'));
    }
    
    /**
     * Screen options
     */
    public function screen_options() {
        $option = 'per_page';
        $args = array(
            'label' => __('Warranties per page', 'warranty-manager'),
            'default' => 20,
            'option' => 'warranties_per_page'
        );
        add_screen_option($option, $args);
    }
    
    /**
     * Set screen options
     */
    public function set_screen_options($status, $option, $value) {
        return $value;
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'all';
        $per_page = (int) get_user_option('warranties_per_page') ?: 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Handle bulk actions
        if (isset($_POST['action']) || isset($_POST['action2'])) {
            $this->handle_bulk_actions();
        }
        
        $args = array(
            'limit' => $per_page,
            'offset' => $offset,
            'search' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : ''
        );
        
        if ($current_tab !== 'all') {
            $args['status'] = $current_tab;
        }
        
        $warranties = $this->database->get_warranties($args);
        $total_items = $this->database->get_warranty_count($current_tab === 'all' ? '' : $current_tab);
        $stats = $this->database->get_warranty_stats();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Warranty Manager', 'warranty-manager'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=warranty-add-new'); ?>" class="page-title-action">
                <?php _e('Add New', 'warranty-manager'); ?>
            </a>
            <hr class="wp-header-end">
            
            <!-- Statistics Cards -->
            <div class="warranty-stats-grid">
                <div class="warranty-stat-card">
                    <div class="warranty-stat-number"><?php echo number_format($stats['total']); ?></div>
                    <div class="warranty-stat-label"><?php _e('Total Warranties', 'warranty-manager'); ?></div>
                </div>
                <div class="warranty-stat-card active">
                    <div class="warranty-stat-number"><?php echo number_format($stats['active']); ?></div>
                    <div class="warranty-stat-label"><?php _e('Active', 'warranty-manager'); ?></div>
                </div>
                <div class="warranty-stat-card pending">
                    <div class="warranty-stat-number"><?php echo number_format($stats['pending']); ?></div>
                    <div class="warranty-stat-label"><?php _e('Pending', 'warranty-manager'); ?></div>
                </div>
                <div class="warranty-stat-card expired">
                    <div class="warranty-stat-number"><?php echo number_format($stats['expired']); ?></div>
                    <div class="warranty-stat-label"><?php _e('Expired', 'warranty-manager'); ?></div>
                </div>
                <div class="warranty-stat-card warning">
                    <div class="warranty-stat-number"><?php echo number_format($stats['expiring_soon']); ?></div>
                    <div class="warranty-stat-label"><?php _e('Expiring Soon', 'warranty-manager'); ?></div>
                </div>
            </div>
            
            <!-- Tabs -->
            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="<?php echo admin_url('admin.php?page=warranty-manager&tab=all'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'all' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('All', 'warranty-manager'); ?> (<?php echo number_format($stats['total']); ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=warranty-manager&tab=active'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'active' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Active', 'warranty-manager'); ?> (<?php echo number_format($stats['active']); ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=warranty-manager&tab=pending'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'pending' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Pending', 'warranty-manager'); ?> (<?php echo number_format($stats['pending']); ?>)
                </a>
                <a href="<?php echo admin_url('admin.php?page=warranty-manager&tab=expired'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'expired' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Expired', 'warranty-manager'); ?> (<?php echo number_format($stats['expired']); ?>)
                </a>
            </nav>
            
            <!-- Search Form -->
            <form method="get">
                <input type="hidden" name="page" value="warranty-manager">
                <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>">
                <?php
                $search_terms = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
                ?>
                <p class="search-box">
                    <label class="screen-reader-text" for="warranty-search-input"><?php _e('Search Warranties:', 'warranty-manager'); ?></label>
                    <input type="search" id="warranty-search-input" name="s" value="<?php echo esc_attr($search_terms); ?>" placeholder="<?php _e('Search warranties...', 'warranty-manager'); ?>">
                    <?php submit_button(__('Search', 'warranty-manager'), '', '', false, array('id' => 'search-submit')); ?>
                </p>
            </form>
            
            <!-- Warranties Table -->
            <form method="post">
                <?php wp_nonce_field('warranty_bulk_action', 'warranty_bulk_nonce'); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Select bulk action', 'warranty-manager'); ?></label>
                        <select name="action" id="bulk-action-selector-top">
                            <option value="-1"><?php _e('Bulk Actions', 'warranty-manager'); ?></option>
                            <option value="activate"><?php _e('Activate', 'warranty-manager'); ?></option>
                            <option value="expire"><?php _e('Mark as Expired', 'warranty-manager'); ?></option>
                            <option value="delete"><?php _e('Delete', 'warranty-manager'); ?></option>
                        </select>
                        <?php submit_button(__('Apply', 'warranty-manager'), 'action', '', false, array('id' => 'doaction')); ?>
                    </div>
                    <?php
                    // Pagination
                    $pagination_args = array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => ceil($total_items / $per_page),
                        'current' => $current_page
                    );
                    
                    $pagination_links = paginate_links($pagination_args);
                    if ($pagination_links) {
                        echo '<div class="tablenav-pages">' . $pagination_links . '</div>';
                    }
                    ?>
                </div>
                
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column">
                                <label class="screen-reader-text" for="cb-select-all-1"><?php _e('Select All', 'warranty-manager'); ?></label>
                                <input id="cb-select-all-1" type="checkbox">
                            </td>
                            <th scope="col" class="manage-column column-order sortable">
                                <a href="#"><span><?php _e('Order ID', 'warranty-manager'); ?></span></a>
                            </th>
                            <th scope="col" class="manage-column column-customer">
                                <?php _e('Customer', 'warranty-manager'); ?>
                            </th>
                            <th scope="col" class="manage-column column-product">
                                <?php _e('Product', 'warranty-manager'); ?>
                            </th>
                            <th scope="col" class="manage-column column-warranty">
                                <?php _e('Warranty', 'warranty-manager'); ?>
                            </th>
                            <th scope="col" class="manage-column column-status">
                                <?php _e('Status', 'warranty-manager'); ?>
                            </th>
                            <th scope="col" class="manage-column column-dates">
                                <?php _e('Dates', 'warranty-manager'); ?>
                            </th>
                            <th scope="col" class="manage-column column-actions">
                                <?php _e('Actions', 'warranty-manager'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($warranties)): ?>
                            <tr class="no-items">
                                <td class="colspanchange" colspan="8">
                                    <?php _e('No warranties found.', 'warranty-manager'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($warranties as $warranty): ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="warranty_ids[]" value="<?php echo $warranty->id; ?>">
                                    </th>
                                    <td class="column-order">
                                        <strong>
                                            <a href="<?php echo admin_url('post.php?post=' . $warranty->order_id . '&action=edit'); ?>" target="_blank">
                                                #<?php echo esc_html($warranty->order_id); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td class="column-customer">
                                        <strong><?php echo esc_html($warranty->customer_name); ?></strong><br>
                                        <small>
                                            <?php if (!empty($warranty->customer_email)): ?>
                                                <a href="mailto:<?php echo esc_attr($warranty->customer_email); ?>">
                                                    <?php echo esc_html($warranty->customer_email); ?>
                                                </a><br>
                                            <?php endif; ?>
                                            <a href="tel:<?php echo esc_attr($warranty->phone_number); ?>">
                                                <?php echo esc_html($warranty->phone_number); ?>
                                            </a>
                                        </small>
                                    </td>
                                    <td class="column-product">
                                        <?php if (!empty($warranty->product_name)): ?>
                                            <strong><?php echo esc_html($warranty->product_name); ?></strong>
                                        <?php else: ?>
                                            <span class="na">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-warranty">
                                        <?php echo sprintf(__('%d months', 'warranty-manager'), $warranty->warranty_months); ?><br>
                                        <?php if ($warranty->status === 'active' && !empty($warranty->expiry_date)): ?>
                                            <?php
                                            $expiry_date = new DateTime($warranty->expiry_date);
                                            $now = new DateTime();
                                            $remaining = $now->diff($expiry_date);
                                            
                                            if ($expiry_date > $now) {
                                                if ($remaining->days <= 30) {
                                                    echo '<small class="warranty-expiring">';
                                                    echo sprintf(__('%d days left', 'warranty-manager'), $remaining->days);
                                                    echo '</small>';
                                                } else {
                                                    echo '<small>';
                                                    echo sprintf(__('%d days left', 'warranty-manager'), $remaining->days);
                                                    echo '</small>';
                                                }
                                            } else {
                                                echo '<small class="warranty-expired">' . __('Expired', 'warranty-manager') . '</small>';
                                            }
                                            ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-status">
                                        <span class="warranty-status warranty-status-<?php echo esc_attr($warranty->status); ?>">
                                            <?php echo esc_html(ucfirst($warranty->status)); ?>
                                        </span>
                                    </td>
                                    <td class="column-dates">
                                        <strong><?php _e('Purchase:', 'warranty-manager'); ?></strong><br>
                                        <small><?php echo date_i18n(get_option('date_format'), strtotime($warranty->purchase_date)); ?></small><br>
                                        
                                        <?php if (!empty($warranty->activation_date)): ?>
                                            <strong><?php _e('Activated:', 'warranty-manager'); ?></strong><br>
                                            <small><?php echo date_i18n(get_option('date_format'), strtotime($warranty->activation_date)); ?></small><br>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($warranty->expiry_date)): ?>
                                            <strong><?php _e('Expires:', 'warranty-manager'); ?></strong><br>
                                            <small><?php echo date_i18n(get_option('date_format'), strtotime($warranty->expiry_date)); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-actions">
                                        <?php if ($warranty->status === 'pending'): ?>
                                            <button type="button" class="button button-primary button-small activate-warranty" 
                                                    data-id="<?php echo $warranty->id; ?>">
                                                <?php _e('Activate', 'warranty-manager'); ?>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="button button-small edit-warranty" 
                                                data-id="<?php echo $warranty->id; ?>">
                                            <?php _e('Edit', 'warranty-manager'); ?>
                                        </button>
                                        
                                        <button type="button" class="button button-small button-link-delete delete-warranty" 
                                                data-id="<?php echo $warranty->id; ?>">
                                            <?php _e('Delete', 'warranty-manager'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="tablenav bottom">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-bottom" class="screen-reader-text"><?php _e('Select bulk action', 'warranty-manager'); ?></label>
                        <select name="action2" id="bulk-action-selector-bottom">
                            <option value="-1"><?php _e('Bulk Actions', 'warranty-manager'); ?></option>
                            <option value="activate"><?php _e('Activate', 'warranty-manager'); ?></option>
                            <option value="expire"><?php _e('Mark as Expired', 'warranty-manager'); ?></option>
                            <option value="delete"><?php _e('Delete', 'warranty-manager'); ?></option>
                        </select>
                        <?php submit_button(__('Apply', 'warranty-manager'), 'action', '', false, array('id' => 'doaction2')); ?>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        if (!wp_verify_nonce($_POST['warranty_bulk_nonce'], 'warranty_bulk_action')) {
            wp_die(__('Security check failed', 'warranty-manager'));
        }
        
        $action = '';
        if (!empty($_POST['action']) && $_POST['action'] != '-1') {
            $action = $_POST['action'];
        } elseif (!empty($_POST['action2']) && $_POST['action2'] != '-1') {
            $action = $_POST['action2'];
        }
        
        if (empty($action) || empty($_POST['warranty_ids'])) {
            return;
        }
        
        $warranty_ids = array_map('intval', $_POST['warranty_ids']);
        
        switch ($action) {
            case 'activate':
                $count = 0;
                foreach ($warranty_ids as $id) {
                    $data = array(
                        'status' => 'active',
                        'activation_date' => current_time('mysql')
                    );
                    if ($this->database->update_warranty($id, $data)) {
                        $count++;
                    }
                }
                $this->add_admin_notice(
                    sprintf(__('%d warranties activated successfully.', 'warranty-manager'), $count),
                    'success'
                );
                break;
                
            case 'expire':
                $count = $this->database->bulk_update_status($warranty_ids, 'expired');
                $this->add_admin_notice(
                    sprintf(__('%d warranties marked as expired.', 'warranty-manager'), $count),
                    'success'
                );
                break;
                
            case 'delete':
                $count = 0;
                foreach ($warranty_ids as $id) {
                    if ($this->database->delete_warranty($id)) {
                        $count++;
                    }
                }
                $this->add_admin_notice(
                    sprintf(__('%d warranties deleted successfully.', 'warranty-manager'), $count),
                    'success'
                );
                break;
        }
        
        // Redirect to avoid resubmission
        wp_redirect(remove_query_arg(array('action', 'action2', 'warranty_ids', 'warranty_bulk_nonce')));
        exit;
    }
    
    /**
     * Add new warranty page
     */
    public function add_warranty_page() {
        if (isset($_POST['submit'])) {
            $this->handle_add_warranty();
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Add New Warranty', 'warranty-manager'); ?></h1>
            
            <form method="post" class="warranty-form-admin">
                <?php wp_nonce_field('add_warranty', 'add_warranty_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="order_id"><?php _e('Order ID', 'warranty-manager'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="order_id" name="order_id" class="regular-text" required>
                            <p class="description"><?php _e('WooCommerce order ID', 'warranty-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="customer_name"><?php _e('Customer Name', 'warranty-manager'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="customer_name" name="customer_name" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="customer_email"><?php _e('Customer Email', 'warranty-manager'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="customer_email" name="customer_email" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="phone_number"><?php _e('Phone Number', 'warranty-manager'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="tel" id="phone_number" name="phone_number" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="product_name"><?php _e('Product Name', 'warranty-manager'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="product_name" name="product_name" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="warranty_months"><?php _e('Warranty Period', 'warranty-manager'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <select id="warranty_months" name="warranty_months" required>
                                <option value=""><?php _e('Select warranty period', 'warranty-manager'); ?></option>
                                <?php
                                $settings = WooCommerceWarrantyManager::get_settings();
                                foreach ($settings['default_warranty_months'] as $months) {
                                    echo '<option value="' . $months . '">' . sprintf(__('%d months', 'warranty-manager'), $months) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="status"><?php _e('Status', 'warranty-manager'); ?></label>
                        </th>
                        <td>
                            <select id="status" name="status">
                                <option value="pending"><?php _e('Pending', 'warranty-manager'); ?></option>
                                <option value="active"><?php _e('Active', 'warranty-manager'); ?></option>
                                <option value="expired"><?php _e('Expired', 'warranty-manager'); ?></option>
                                <option value="cancelled"><?php _e('Cancelled', 'warranty-manager'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="purchase_date"><?php _e('Purchase Date', 'warranty-manager'); ?></label>
                        </th>
                        <td>
                            <input type="datetime-local" id="purchase_date" name="purchase_date" class="regular-text">
                            <p class="description"><?php _e('Leave empty to use current date', 'warranty-manager'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="notes"><?php _e('Notes', 'warranty-manager'); ?></label>
                        </th>
                        <td>
                            <textarea id="notes" name="notes" class="large-text" rows="4"></textarea>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Add Warranty', 'warranty-manager')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle add warranty form submission
     */
    private function handle_add_warranty() {
        if (!wp_verify_nonce($_POST['add_warranty_nonce'], 'add_warranty')) {
            wp_die(__('Security check failed', 'warranty-manager'));
        }
        
        $data = array(
            'order_id' => sanitize_text_field($_POST['order_id']),
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'phone_number' => sanitize_text_field($_POST['phone_number']),
            'product_name' => sanitize_text_field($_POST['product_name']),
            'warranty_months' => intval($_POST['warranty_months']),
            'status' => sanitize_text_field($_POST['status']),
            'notes' => sanitize_textarea_field($_POST['notes'])
        );
        
        if (!empty($_POST['purchase_date'])) {
            $data['purchase_date'] = sanitize_text_field($_POST['purchase_date']);
        } else {
            $data['purchase_date'] = current_time('mysql');
        }
        
        // If status is active, set activation date
        if ($data['status'] === 'active') {
            $data['activation_date'] = current_time('mysql');
        }
        
        $result = $this->database->insert_warranty($data);
        
        if ($result) {
            $this->add_admin_notice(
                __('Warranty added successfully!', 'warranty-manager'),
                'success'
            );
            
            // Redirect to prevent resubmission
            wp_redirect(admin_url('admin.php?page=warranty-manager'));
            exit;
        } else {
            $this->add_admin_notice(
                __('Error adding warranty. Please try again.', 'warranty-manager'),
                'error'
            );
        }
    }