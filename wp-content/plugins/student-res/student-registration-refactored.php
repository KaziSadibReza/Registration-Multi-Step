<?php
/*
Plugin Name: Registration Multi-Step (WooCommerce Payment + Admin View + Statuses)
Description: 3-step registration with class limits, signature (white bg), email, WooCommerce payment integration, Elementor-like admin list & single view with class management. Random 4-char order ID, editable Payment Status (Pending/Paid/Hold/Cancel) and Registration Status (Pending/Active/Cancel/Course Complete).
Version: 2.9.3
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

/**
 * Main plugin class that orchestrates all functionality
 */
class Genius_Multistep_Registration {
    private $database;
    private $woocommerce;
    private $frontend;
    private $admin;
    private $opt_emails = 'gm_reg_emails';

    public function __construct() {
        $this->init();
    }

    /**
     * Initialize the plugin
     */
    private function init() {
        // Load required classes
        $this->load_dependencies();
        
        // Initialize components
        $this->database = new GM_Database();
        $this->woocommerce = new GM_WooCommerce($this->database);
        $this->frontend = new GM_Frontend($this->database, $this->woocommerce);
        $this->admin = new GM_Admin($this->database, $this->woocommerce);

        // Plugin hooks
        register_activation_hook(__FILE__, array($this, 'install'));
        
        // Initialize default email setting
        if (get_option($this->opt_emails) === false) {
            add_option($this->opt_emails, get_option('admin_email'));
        }
    }

    /**
     * Load all required class files
     */
    private function load_dependencies() {
        require_once plugin_dir_path(__FILE__) . 'includes/class-database.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-woocommerce.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-frontend.php';
        require_once plugin_dir_path(__FILE__) . 'admin/class-admin.php';
    }

    /**
     * Plugin activation - install database tables and default data
     */
    public function install() {
        $this->database->install();
    }

    /**
     * Get database instance (for backward compatibility or external access)
     */
    public function get_database() {
        return $this->database;
    }

    /**
     * Get WooCommerce instance
     */
    public function get_woocommerce() {
        return $this->woocommerce;
    }

    /**
     * Get frontend instance
     */
    public function get_frontend() {
        return $this->frontend;
    }

    /**
     * Get admin instance
     */
    public function get_admin() {
        return $this->admin;
    }
}

// Initialize the plugin
new Genius_Multistep_Registration();
