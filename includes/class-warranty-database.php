<?php
/**
 * Database operations for Warranty Manager
 * 
 * Path: /wp-content/plugins/woocommerce-warranty-manager/includes/class-warranty-database.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WM_Database {
    
    /**
     * Table name
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'warranty_records';
    }
    
    /**
     * Create warranty tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id varchar(100) NOT NULL,
            customer_name varchar(200) NOT NULL,
            customer_email varchar(200) NOT NULL,
            phone_number varchar(20) NOT NULL,
            product_name varchar(500),
            product_id int(11),
            warranty_months int(11) NOT NULL,
            purchase_date datetime DEFAULT CURRENT_TIMESTAMP,
            activation_date datetime,
            expiry_date datetime,
            status enum('pending','active','expired','cancelled') DEFAULT 'pending',
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_phone (order_id, phone_number),
            KEY status_idx (status),
            KEY order_idx (order_id),
            KEY phone_idx (phone_number),
            KEY expiry_idx (expiry_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Update database version
        update_option('warranty_manager_db_version', '1.0');
    }
    
    /**
     * Insert warranty record
     */
    public function insert_warranty($data) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Calculate expiry date if activation date is set
        if (!empty($data['activation_date']) && !empty($data['warranty_months'])) {
            $activation_date = new DateTime($data['activation_date']);
            $activation_date->add(new DateInterval('P' . intval($data['warranty_months']) . 'M'));
            $data['expiry_date'] = $activation_date->format('Y-m-d H:i:s');
        }
        
        $result = $wpdb->insert($this->table_name, $data);
        
        if ($result !== false) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update warranty record
     */
    public function update_warranty($id, $data) {
        global $wpdb;
        
        $data['updated_at'] = current_time('mysql');
        
        // Calculate expiry date if activation date is updated
        if (!empty($data['activation_date']) && !empty($data['warranty_months'])) {
            $activation_date = new DateTime($data['activation_date']);
            $activation_date->add(new DateInterval('P' . intval($data['warranty_months']) . 'M'));
            $data['expiry_date'] = $activation_date->format('Y-m-d H:i:s');
        }
        
        return $wpdb->update($this->table_name, $data, array('id' => $id));
    }
    
    /**
     * Get warranty by ID
     */
    public function get_warranty($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get warranty by order ID
     */
    public function get_warranty_by_order($order_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE order_id = %s",
            $order_id
        ));
    }
    
    /**
     * Get warranty by phone number
     */
    public function get_warranties_by_phone($phone) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE phone_number = %s ORDER BY created_at DESC",
            $phone
        ));
    }
    
    /**
     * Get warranty by order and phone
     */
    public function get_warranty_by_order_and_phone($order_id, $phone) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE order_id = %s AND phone_number = %s",
            $order_id, $phone
        ));
    }
    
    /**
     * Get all warranties with pagination
     */
    public function get_warranties($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'order_by' => 'created_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0,
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clauses = array();
        $where_values = array();
        
        // Status filter
        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        // Search filter
        if (!empty($args['search'])) {
            $where_clauses[] = "(customer_name LIKE %s OR order_id LIKE %s OR phone_number LIKE %s OR product_name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $order_by = sanitize_sql_orderby($args['order_by'] . ' ' . $args['order']);
        $limit = intval($args['limit']);
        $offset = intval($args['offset']);
        
        $sql = "SELECT * FROM {$this->table_name} {$where_sql} ORDER BY {$order_by} LIMIT {$limit} OFFSET {$offset}";
        
        if (!empty($where_values)) {
            return $wpdb->get_results($wpdb->prepare($sql, $where_values));
        } else {
            return $wpdb->get_results($sql);
        }
    }
    
    /**
     * Get warranty count
     */
    public function get_warranty_count($status = '') {
        global $wpdb;
        
        if (empty($status)) {
            return $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        }
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
            $status
        ));
    }
    
    /**
     * Get warranty statistics
     */
    public function get_warranty_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total warranties
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        // By status
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->table_name} GROUP BY status",
            ARRAY_A
        );
        
        foreach ($status_counts as $row) {
            $stats[$row['status']] = $row['count'];
        }
        
        // Set defaults for missing statuses
        $default_statuses = array('pending', 'active', 'expired', 'cancelled');
        foreach ($default_statuses as $status) {
            if (!isset($stats[$status])) {
                $stats[$status] = 0;
            }
        }
        
        // Expiring soon (within 30 days)
        $stats['expiring_soon'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE status = 'active' 
            AND expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)"
        );
        
        // This month activations
        $stats['this_month_activations'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
            WHERE activation_date >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        );
        
        return $stats;
    }
    
    /**
     * Delete warranty
     */
    public function delete_warranty($id) {
        global $wpdb;
        
        return $wpdb->delete($this->table_name, array('id' => $id));
    }
    
    /**
     * Bulk update warranty status
     */
    public function bulk_update_status($ids, $status) {
        global $wpdb;
        
        if (empty($ids) || !is_array($ids)) {
            return false;
        }
        
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        
        $sql = "UPDATE {$this->table_name} 
                SET status = %s, updated_at = %s 
                WHERE id IN ($ids_placeholder)";
        
        $values = array_merge(array($status, current_time('mysql')), $ids);
        
        return $wpdb->query($wpdb->prepare($sql, $values));
    }
    
    /**
     * Update expired warranties
     */
    public function update_expired_warranties() {
        global $wpdb;
        
        return $wpdb->query(
            "UPDATE {$this->table_name} 
            SET status = 'expired', updated_at = NOW() 
            WHERE status = 'active' AND expiry_date < NOW()"
        );
    }
    
    /**
     * Clean old warranty records
     */
    public function cleanup_old_records($days = 365) {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} 
            WHERE status = 'expired' 
            AND expiry_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
    
    /**
     * Get warranty export data
     */
    public function get_export_data($status = '') {
        global $wpdb;
        
        $where_sql = '';
        if (!empty($status)) {
            $where_sql = $wpdb->prepare("WHERE status = %s", $status);
        }
        
        return $wpdb->get_results(
            "SELECT 
                order_id,
                customer_name,
                customer_email,
                phone_number,
                product_name,
                warranty_months,
                purchase_date,
                activation_date,
                expiry_date,
                status,
                created_at
            FROM {$this->table_name} 
            {$where_sql}
            ORDER BY created_at DESC",
            ARRAY_A
        );
    }
}