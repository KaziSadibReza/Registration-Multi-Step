<?php
/**
 * WooCommerce integration class for Student Registration Plugin
 * Handles order creation and payment status synchronization
 */

if (!defined('ABSPATH')) exit;

class GM_WooCommerce {
    private $db;
    private $table;

    public function __construct($database_instance) {
        $this->db = $database_instance;
        $this->table = $this->db->get_table_name();
        
        // Hook into WooCommerce order status changes
        add_action('woocommerce_order_status_completed', array($this, 'handle_payment_complete'));
        add_action('woocommerce_order_status_processing', array($this, 'handle_payment_complete'));
        add_action('woocommerce_order_status_on-hold', array($this, 'handle_payment_hold'));
        add_action('woocommerce_order_status_cancelled', array($this, 'handle_payment_cancelled'));
        add_action('woocommerce_order_status_refunded', array($this, 'handle_payment_cancelled'));
    }

    /**
     * Create WooCommerce order for registration
     */
    public function create_order($registration_id, $data, $classes) {
        error_log('GM WC: create_order called with registration_id: ' . $registration_id);
        
        if (!class_exists('WooCommerce')) {
            error_log('GM WC: WooCommerce class not found');
            return false;
        }

        if (!function_exists('wc_create_order')) {
            error_log('GM WC: wc_create_order function not found');
            return false;
        }

        try {
            error_log('GM WC: About to call wc_create_order');
            
            // Create order
            $order = wc_create_order(array(
                'customer_note' => 'Registration ID: ' . $registration_id,
                'created_via'   => 'Genius Registration Form'
            ));
            
            if (!$order || is_wp_error($order)) {
                error_log('GM WC: wc_create_order failed: ' . (is_wp_error($order) ? $order->get_error_message() : 'null returned'));
                return false;
            }
            
            error_log('GM WC: Order created successfully, ID: ' . $order->get_id());
            
            // Add registration as line items
            if (is_array($classes) && count($classes) > 0) {
                error_log('GM WC: Adding ' . count($classes) . ' class items to order');
                
                foreach ($classes as $class) {
                    error_log('GM WC: Adding class: ' . print_r($class, true));
                    
                    // Create a simple product line item
                    $item = new WC_Order_Item_Product();
                    $item->set_name($data['location'] . ' - ' . $class['title']);
                    $item->set_quantity(1);
                    $item->set_subtotal(floatval($class['price']));
                    $item->set_total(floatval($class['price']));
                    
                    // Add meta data
                    $item->add_meta_data('Class', $class['title']);
                    $item->add_meta_data('Location', $data['location']);
                    $item->add_meta_data('Registration ID', $registration_id);
                    
                    $order->add_item($item);
                }
            } else {
                error_log('GM WC: No classes provided or classes is not an array');
                return false;
            }

            // Set billing details
            $order->set_billing_first_name($data['parent_first_name']);
            $order->set_billing_last_name($data['parent_last_name']);
            $order->set_billing_email($data['parent_email']);
            $order->set_billing_phone($data['parent_phone']);
            
            // Add order meta
            $order->add_meta_data('_registration_id', $registration_id, true);
            $order->add_meta_data('_student_name', $data['student_first_name'] . ' ' . $data['student_last_name'], true);
            $order->add_meta_data('_year_group', $data['year_group'], true);
            
            // Calculate totals and save
            error_log('GM WC: Calculating totals and saving order');
            $order->calculate_totals();
            $order_id = $order->save();
            
            error_log('GM WC: Order saved with final ID: ' . $order_id);
            
            // Update registration with WooCommerce order ID
            $this->update_registration_order_id($registration_id, $order_id);
            
            return $order_id;
            
        } catch (Exception $e) {
            error_log('GM WC: Exception in create_order: ' . $e->getMessage());
            error_log('GM WC: Exception stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Update registration with WooCommerce order ID
     */
    private function update_registration_order_id($registration_id, $order_id) {
        global $wpdb;
        
        $wpdb->update(
            $this->table,
            array(
                'payment_provider' => 'woocommerce',
                'payment_trx' => $order_id
            ),
            array('id' => $registration_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    /**
     * Handle payment completion
     */
    public function handle_payment_complete($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $registration_id = $order->get_meta('_registration_id');
        if ($registration_id) {
            $this->update_registration_status($registration_id, 'paid', 'active');
        }
    }

    /**
     * Handle payment on hold
     */
    public function handle_payment_hold($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $registration_id = $order->get_meta('_registration_id');
        if ($registration_id) {
            $this->update_registration_status($registration_id, 'hold', null);
        }
    }

    /**
     * Handle payment cancellation/refund
     */
    public function handle_payment_cancelled($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $registration_id = $order->get_meta('_registration_id');
        if ($registration_id) {
            $this->update_registration_status($registration_id, 'cancel', 'cancel');
        }
    }

    /**
     * Update registration payment and status
     */
    private function update_registration_status($registration_id, $payment_status, $reg_status = null) {
        global $wpdb;
        
        $data = array('payment_status' => $payment_status);
        $formats = array('%s');
        
        if ($reg_status !== null) {
            $data['reg_status'] = $reg_status;
            $formats[] = '%s';
        }
        
        $wpdb->update(
            $this->table,
            $data,
            array('id' => $registration_id),
            $formats,
            array('%d')
        );
    }

    /**
     * Get WooCommerce order URL for admin
     */
    public function get_order_admin_url($order_id) {
        if (!$order_id) return null;
        return admin_url('post.php?post=' . absint($order_id) . '&action=edit');
    }
}
