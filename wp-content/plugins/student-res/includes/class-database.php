<?php
/**
 * Database management class for Student Registration Plugin
 * Handles table creation, database operations, and class management
 */

if (!defined('ABSPATH')) exit;

class GM_Database {
    private $table;
    private $classes_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'gm_registrations';
        $this->classes_table = $wpdb->prefix . 'gm_classes';
    }

    /**
     * Install database tables and default data
     */
    public function install() {
        $this->maybe_create_table();
        $this->maybe_create_classes_table();
        $this->setup_default_classes();
    }

    /**
     * Create the main registrations table
     */
    private function maybe_create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_code VARCHAR(8) NOT NULL,
            created_at DATETIME NOT NULL,
            parent_first_name VARCHAR(100) NULL,
            parent_last_name  VARCHAR(100) NULL,
            parent_email      VARCHAR(190) NULL,
            parent_phone      VARCHAR(40) NULL,
            student_first_name VARCHAR(100) NULL,
            student_last_name  VARCHAR(100) NULL,
            location          VARCHAR(100) NULL,
            current_grades    VARCHAR(50)  NULL,
            year_group        VARCHAR(20)  NULL,
            classes_json      LONGTEXT NULL,
            monthly_total     DECIMAL(10,2) DEFAULT 0,
            payment_method    VARCHAR(20) NULL,
            payment_status    VARCHAR(20) DEFAULT 'pending',
            payment_provider  VARCHAR(20) NULL,
            payment_account   VARCHAR(80) NULL,
            payment_phone     VARCHAR(40) NULL,
            payment_trx       VARCHAR(120) NULL,
            payment_amount    DECIMAL(10,2) DEFAULT 0,
            reg_status        VARCHAR(30) DEFAULT 'pending',
            accepted_terms    TINYINT(1) DEFAULT 0,
            signature_url     TEXT NULL,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            UNIQUE KEY order_code (order_code)
        ) $charset;";
        
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Backfill columns for older installs
        $this->backfill_columns();
    }

    /**
     * Create the classes management table
     */
    private function maybe_create_classes_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->classes_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            class_id VARCHAR(50) NOT NULL,
            title VARCHAR(100) NOT NULL,
            description TEXT NULL,
            price DECIMAL(10,2) DEFAULT 0,
            max_seats INT DEFAULT 14,
            status_override VARCHAR(20) DEFAULT 'auto',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY class_id (class_id)
        ) $charset;";
        
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Backfill columns for existing installations
     */
    private function backfill_columns() {
        global $wpdb;
        
        $cols = $wpdb->get_col("DESC {$this->table}", 0);
        $add = function($name, $def) use($cols, $wpdb) {
            if (!in_array($name, $cols, true)) {
                $wpdb->query("ALTER TABLE {$this->table} ADD COLUMN {$def}");
            }
        };

        $add('payment_status', "payment_status VARCHAR(20) DEFAULT 'pending'");
        $add('payment_provider', "payment_provider VARCHAR(20) NULL");
        $add('payment_account', "payment_account VARCHAR(80) NULL");
        $add('payment_phone', "payment_phone VARCHAR(40) NULL");
        $add('payment_trx', "payment_trx VARCHAR(120) NULL");
        $add('payment_amount', "payment_amount DECIMAL(10,2) DEFAULT 0");
        $add('reg_status', "reg_status VARCHAR(30) DEFAULT 'pending'");
        $add('order_code', "order_code VARCHAR(8) NOT NULL DEFAULT '0000'");

        // Ensure unique codes for existing rows
        $this->ensure_unique_order_codes();
    }

    /**
     * Ensure all registrations have unique order codes
     */
    private function ensure_unique_order_codes() {
        global $wpdb;
        
        $rows = $wpdb->get_results("SELECT id,order_code FROM {$this->table}");
        foreach ($rows as $r) {
            if (!$r->order_code || strlen($r->order_code) < 4) {
                $code = $this->generate_order_code();
                $wpdb->update($this->table, ['order_code' => $code], ['id' => $r->id], ['%s'], ['%d']);
            }
        }
    }

    /**
     * Setup default classes
     */
    private function setup_default_classes() {
        global $wpdb;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->classes_table}");
        if ($count == 0) {
            $default_classes = [
                ['class_id' => 'sat-morning', 'title' => 'Saturday Morning', 'price' => 79.99, 'max_seats' => 14, 'status_override' => 'auto'],
                ['class_id' => 'sat-afternoon', 'title' => 'Saturday Afternoon', 'price' => 79.99, 'max_seats' => 1, 'status_override' => 'auto'],
                ['class_id' => 'sun-morning', 'title' => 'Sunday Morning', 'price' => 79.99, 'max_seats' => 14, 'status_override' => 'auto'],
                ['class_id' => 'sun-afternoon', 'title' => 'Sunday Afternoon', 'price' => 79.99, 'max_seats' => 14, 'status_override' => 'auto']
            ];
            
            foreach ($default_classes as $class) {
                $wpdb->insert($this->classes_table, $class, ['%s', '%s', '%f', '%d', '%s']);
            }
        }
    }

    /**
     * Generate unique order code
     */
    public function generate_order_code() {
        global $wpdb;
        
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4));
            $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE order_code=%s", $code));
        } while ($exists > 0);
        
        return $code;
    }

    /**
     * Get all classes
     */
    public function get_classes() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->classes_table} ORDER BY title ASC");
    }

    /**
     * Get class by ID
     */
    public function get_class_by_id($class_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->classes_table} WHERE class_id=%s", $class_id));
    }

    /**
     * Get registered count for a class
     */
    public function get_registered_count($class_id) {
        global $wpdb;
        $like_pattern = '%"id":"' . $wpdb->esc_like($class_id) . '"%';
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$this->table} 
            WHERE classes_json LIKE %s AND reg_status != 'cancel'
        ", $like_pattern));
        return intval($count);
    }

    /**
     * Check if class is available
     */
    public function is_class_available($class_id) {
        $class = $this->get_class_by_id($class_id);
        if (!$class) return false;
        
        if ($class->status_override === 'full') return false;
        if ($class->status_override === 'available') return true;
        
        // Auto mode - check seat count
        $registered = $this->get_registered_count($class_id);
        return $registered < $class->max_seats;
    }

    /**
     * Update class
     */
    public function update_class($class_id, $data) {
        global $wpdb;
        
        $formats = [];
        $sanitized_data = [];
        
        if (isset($data['title'])) {
            $sanitized_data['title'] = sanitize_text_field($data['title']);
            $formats[] = '%s';
        }
        if (isset($data['price'])) {
            $sanitized_data['price'] = floatval($data['price']);
            $formats[] = '%f';
        }
        if (isset($data['max_seats'])) {
            $sanitized_data['max_seats'] = intval($data['max_seats']);
            $formats[] = '%d';
        }
        if (isset($data['description'])) {
            $sanitized_data['description'] = sanitize_textarea_field($data['description']);
            $formats[] = '%s';
        }
        if (isset($data['status_override'])) {
            $sanitized_data['status_override'] = sanitize_text_field($data['status_override']);
            $formats[] = '%s';
        }
        
        if (!empty($sanitized_data)) {
            return $wpdb->update($this->classes_table, $sanitized_data, ['class_id' => $class_id], $formats, ['%s']);
        }
        
        return false;
    }

    /**
     * Get table names
     */
    public function get_table_name() {
        return $this->table;
    }

    public function get_classes_table_name() {
        return $this->classes_table;
    }
}
