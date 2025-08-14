<?php
/**
 * Frontend class for Student Registration Plugin
 * Handles shortcode rendering, form processing, and AJAX operations
 */

if (!defined('ABSPATH')) exit;

class GM_Frontend {
    private $db;
    private $woocommerce;
    private $opt_emails = 'gm_reg_emails';

    public function __construct($database_instance, $woocommerce_instance) {
        $this->db = $database_instance;
        $this->woocommerce = $woocommerce_instance;
        
        // WordPress hooks
        add_shortcode('genius_registration', array($this, 'render_form'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_gm_save_registration', array($this, 'ajax_save'));
        add_action('wp_ajax_nopriv_gm_save_registration', array($this, 'ajax_save'));
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        // Google Fonts
        wp_register_style('gm-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap', array(), null);
        wp_enqueue_style('gm-inter');

        // Plugin CSS
        wp_enqueue_style(
            'gm-frontend-css',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/frontend.css',
            array(),
            '2.9.3'
        );

        // Plugin JavaScript
        wp_enqueue_script(
            'gm-frontend-js',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/frontend.js',
            array(),
            '2.9.3',
            true
        );

        // Localize script for AJAX
        wp_localize_script('gm-frontend-js', 'GM_AJAX', array(
            'url'   => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gm_reg_nonce'),
        ));
    }

    /**
     * Generate form field HTML
     */
    private function field($args = array()) {
        $a = wp_parse_args($args, array(
            'label' => '', 'name' => '', 'type' => 'text', 'placeholder' => '',
            'value' => '', 'required' => false, 'classes' => '', 'attr' => ''
        ));
        
        $req = $a['required'] ? 'required' : '';
        $label = $a['label'] ? '<label class="gm-label">'.$a['label'].'</label>' : '';
        $classes = esc_attr($a['classes']);
        $attr = $a['attr'];

        if ($a['type'] === 'select' && is_array($a['value'])) {
            $opts = '';
            foreach ($a['value'] as $val => $text) {
                $opts .= '<option value="'.esc_attr($val).'">'.esc_html($text).'</option>';
            }
            return '<div class="gm-field '.$classes.'">'.$label.'<div class="gm-input-wrap"><select name="'.esc_attr($a['name']).'" '.$req.' '.$attr.'>'.$opts.'</select></div></div>';
        }
        
        return '<div class="gm-field '.$classes.'">'.$label.'<div class="gm-input-wrap"><input type="'.esc_attr($a['type']).'" name="'.esc_attr($a['name']).'" placeholder="'.esc_attr($a['placeholder']).'" value="'.esc_attr($a['value']).'" '.$req.' '.$attr.'></div></div>';
    }

    /**
     * Render the registration form shortcode
     */
    public function render_form() {
        ob_start();
        ?>
<div class="gm-wrap">
    <div class="gm-container">
        <div class="gm-title">Registration</div>

        <div class="gm-stepper" id="gm-stepper">
            <div class="gm-step active"><span class="gm-badge">1</span> <span>Details</span></div>
            <div class="gm-step"><span class="gm-badge">2</span> <span>Class Selection</span></div>
            <div class="gm-step"><span class="gm-badge">3</span> <span>Payment</span></div>
        </div>
        <div class="gm-progress"><span id="gm-progress-bar" style="width:33%;"></span></div>

        <form id="gm-form" onsubmit="return false;">
            <!-- STEP 1: Details -->
            <section class="gm-step-pane" data-step="1">
                <div class="gm-card">
                    <h3>Parent/Guardian Details</h3>
                    <div class="gm-grid">
                        <?php
                                echo $this->field(['label'=>'First Name','name'=>'parent_first_name','placeholder'=>'e.g. Maruf','required'=>true]);
                                echo $this->field(['label'=>'Last Name','name'=>'parent_last_name','placeholder'=>'e.g. Hasan','required'=>true]);
                                echo $this->field(['label'=>'Email','name'=>'parent_email','type'=>'email','placeholder'=>'test@gmail.com','required'=>true]);
                                echo $this->field(['label'=>'Phone','name'=>'parent_phone','type'=>'tel','placeholder'=>'017xxxxxxxx','required'=>true]);
                                ?>
                    </div>
                </div>

                <div class="gm-card">
                    <h3>Student Details</h3>
                    <div class="gm-grid">
                        <?php
                                echo $this->field(['label'=>'First Name','name'=>'student_first_name','placeholder'=>'e.g. Maruf','required'=>true]);
                                echo $this->field(['label'=>'Last Name','name'=>'student_last_name','placeholder'=>'e.g. Hasan','required'=>true]);
                                echo $this->field([
                                    'label'=>'Location','name'=>'location','type'=>'select',
                                    'value'=>array(''=>'Select location','Nottingham'=>'Nottingham','London'=>'London','Birmingham'=>'Birmingham','Manchester'=>'Manchester'),
                                    'required'=>true
                                ]);
                                echo $this->field(['label'=>'Current Grades (Optional)','name'=>'current_grades','placeholder'=>'e.g. 24']);
                                echo $this->field([
                                    'label'=>'Year Group','name'=>'year_group','type'=>'select',
                                    'value'=>array(
                                        ''=>'Select year group','Year 1'=>'Year 1','Year 2'=>'Year 2','Year 3'=>'Year 3','Year 4'=>'Year 4','Year 5'=>'Year 5','Year 6'=>'Year 6',
                                        'Year 7'=>'Year 7','Year 8'=>'Year 8','Year 9'=>'Year 9','Year 10'=>'Year 10','Year 11'=>'Year 11'
                                    ),
                                    'required'=>true, 'classes'=>'gm-span-2'
                                ]);
                                ?>
                    </div>
                    <div class="gm-tip">Fields marked required must be filled to go next.</div>
                </div>

                <div class="gm-actions">
                    <span></span>
                    <button type="button" class="gm-btn" id="gm-next-1">Next</button>
                </div>
            </section>

            <!-- STEP 2: Class Selection -->
            <section class="gm-step-pane gm-hidden" data-step="2">
                <div class="gm-card">
                    <h3 style="text-align:center;margin-bottom:6px;">Class Selection</h3>
                    <div class="gm-kicker">
                        Location: <strong id="gm-kicker-location">—</strong> |
                        Year: <strong id="gm-kicker-year">—</strong>
                    </div>
                    <div class="gm-note"><strong>Note:</strong> Years 10–11 students must select <strong>2 days</strong>
                        of attendance.</div>
                </div>

                <div class="gm-classes">
                    <?php
                            $classes = $this->db->get_classes();
                            foreach ($classes as $class) {
                                $registered = $this->db->get_registered_count($class->class_id);
                                $is_available = $this->db->is_class_available($class->class_id);
                                $available_text = $is_available ? 'Spaces Available' : 'Class Full';
                                $available_class = $is_available ? 'selectable' : 'full';
                                $cta_text = $is_available ? 'Click to select' : 'Full';
                                $status_class = $is_available ? 'open' : 'full';
                                
                                echo '<div class="gm-class-card '.$available_class.'" data-id="'.esc_attr($class->class_id).'" data-title="'.esc_attr($class->title).'" data-price="'.esc_attr($class->price).'">';
                                echo '<div class="gm-class-head">';
                                echo '<div><div class="gm-class-title">'.esc_html($class->title).'</div>';
                                echo '<div class="gm-status '.$status_class.'">'.$available_text.'</div></div>';
                                echo '<div class="gm-class-price">£'.number_format((float)$class->price, 2).'</div>';
                                echo '</div>';
                                echo '<ul class="gm-features">';
                                echo '<li>Weekly classes</li>';
                                echo '<li>2 hours per session</li>';
                                echo '<li>Maths, English, Science</li>';
                                echo '<li>Max '.intval($class->max_seats).' students per class</li>';
                                if ($class->description) {
                                    echo '<li>'.esc_html($class->description).'</li>';
                                }
                                echo '</ul>';
                                echo '<div class="gm-cta">'.$cta_text.'</div>';
                                echo '</div>';
                            }
                            ?>
                </div>

                <div class="gm-card gm-selected-list">
                    <h4>Selected Classes:</h4>
                    <ul id="gm-selected-ul"></ul>
                </div>

                <div class="gm-actions">
                    <button type="button" class="gm-btn secondary" id="gm-prev-2">Back</button>
                    <button type="button" class="gm-btn" id="gm-next-2" disabled>Next</button>
                </div>
            </section>

            <!-- STEP 3: Payment -->
            <section class="gm-step-pane gm-hidden" data-step="3">
                <div class="gm-card">
                    <h3 style="text-align:center;">Payment Information</h3>
                    <div style="margin-top:14px;" class="gm-summary">
                        <table class="gm-summary-table" id="gm-summary-table"></table>
                        <div class="gm-total" id="gm-total" style="margin-top:6px;">Monthly Total: £0.00</div>
                    </div>
                </div>

                <div class="gm-card">
                    <h3 style="text-align:center;">Payment Method</h3>
                    <input type="hidden" name="payment_method" value="online">
                    <div class="gm-note" style="text-align:center; margin-top: 14px;">You will be redirected to our
                        secure online payment gateway to complete your registration.</div>
                </div>

                <div class="gm-card">
                    <h3 style="text-align:center;">Terms &amp; Conditions</h3>
                    <label class="gm-terms" style="margin-top:8px;">
                        <input type="checkbox" id="gm-terms" style="accent-color:#20d3d2;">
                        <span>I accept the <a href="#" target="_blank">Terms and Conditions</a> and <a href="#"
                                target="_blank">Privacy Policy</a></span>
                    </label>
                </div>

                <div class="gm-card">
                    <h3 style="text-align:center;">Signature</h3>
                    <div class="gm-sign">
                        <div class="gm-sign-hint">Please sign below to confirm registration</div>
                        <canvas id="gm-sign-canvas"></canvas>
                        <button type="button" class="gm-btn secondary gm-clear" id="gm-sign-clear">Clear
                            Signature</button>
                    </div>
                </div>

                <div class="gm-actions">
                    <button type="button" class="gm-btn secondary" id="gm-prev-3">Back</button>
                    <button type="submit" class="gm-btn" id="gm-submit" disabled>Proceed to Payment</button>
                </div>
            </section>
        </form>
    </div>
</div>
<?php
        return ob_get_clean();
    }

    /**
     * AJAX handler for saving registration
     */
    public function ajax_save() {        
        try {
            // Simple test - log that we got to this point
            error_log('GM Registration AJAX handler called');
            
            // Quick test response
            if (isset($_POST['test']) || isset($_GET['test'])) {
                wp_send_json_success(array('message' => 'AJAX endpoint is working!'));
                return;
            }
            
            // Get data from POST (form data is preferred)
            $data = $_POST;
            
            // Handle classes data - it might be JSON string
            if (isset($data['classes']) && is_string($data['classes'])) {
                $data['classes'] = json_decode(stripslashes($data['classes']), true);
            }
            
            // Fallback to JSON if no POST data
            if (empty($data) || !isset($data['action'])) {
                $json_payload = file_get_contents('php://input');
                if (!empty($json_payload)) {
                    $json_data = json_decode($json_payload, true);
                    if ($json_data !== null) {
                        $data = $json_data;
                    }
                }
            }

            // Debug log
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GM Registration AJAX Data: ' . print_r($data, true));
            }

            // Nonce check 
            if (!isset($data['_ajax_nonce']) || !wp_verify_nonce($data['_ajax_nonce'], 'gm_reg_nonce')) {
                wp_send_json_error(array('message' => 'Nonce verification failed. Nonce: ' . ($data['_ajax_nonce'] ?? 'missing')), 403);
                return;
            }

            if (empty($data) || empty($data['parent_email']) || empty($data['student_first_name']) || empty($data['year_group'])) {
                wp_send_json_error(array('message' => 'Required fields missing or invalid data format.'));
                return;
            }

            if (empty($data['classes']) || !is_array($data['classes'])) {
                wp_send_json_error(array('message' => 'No classes selected or invalid class data.'));
                return;
            }

            // Validate class selection based on year group
            $year_group = strtolower($data['year_group'] ?? '');
            $class_count = count($data['classes']);
            
            if ($year_group === 'year 10' || $year_group === 'year 11') {
                if ($class_count !== 2) {
                    wp_send_json_error(array('message' => 'Year 10 and Year 11 students must select exactly 2 classes. You selected ' . $class_count . ' classes.'));
                    return;
                }
            } else {
                if ($class_count < 1) {
                    wp_send_json_error(array('message' => 'Please select at least 1 class.'));
                    return;
                }
            }

            // Save signature
            $signature_url = $this->save_signature($data['signature_data'] ?? '');

            // Save to database
            $save_result = $this->save_registration($data, $signature_url);
            if (!$save_result) {
                global $wpdb;
                $error_msg = $wpdb->last_error ? $wpdb->last_error : 'Database insert failed';
                wp_send_json_error(array('message' => 'Database error: ' . $error_msg));
                return;
            }
            
            $insert_id = $save_result['insert_id'];
            $order_code = $save_result['order_code'];

            // Send notification email
            $this->send_notification_email($insert_id, $data);

            // Create WooCommerce order
            $response_data = array('success' => true, 'id' => $insert_id, 'order_code' => $order_code);
            
            // Debug: Check WooCommerce availability
            $wc_debug = array(
                'wc_class_exists' => class_exists('WooCommerce'),
                'wc_create_order_exists' => function_exists('wc_create_order'),
                'woocommerce_instance_type' => gettype($this->woocommerce),
                'woocommerce_instance_class' => is_object($this->woocommerce) ? get_class($this->woocommerce) : 'not an object'
            );
            $response_data['wc_debug'] = $wc_debug;
            
            error_log('GM: WooCommerce class exists: ' . (class_exists('WooCommerce') ? 'YES' : 'NO'));
            error_log('GM: wc_create_order function exists: ' . (function_exists('wc_create_order') ? 'YES' : 'NO'));
            error_log('GM: WooCommerce instance type: ' . gettype($this->woocommerce));
            error_log('GM: WooCommerce instance class: ' . (is_object($this->woocommerce) ? get_class($this->woocommerce) : 'not an object'));
            
            if (class_exists('WooCommerce') && function_exists('wc_create_order')) {
                try {
                    $classes = $data['classes'] ?? array();
                    error_log('GM: Creating WooCommerce order for registration ' . $insert_id . ' with ' . count($classes) . ' classes');
                    error_log('GM: Classes data: ' . print_r($classes, true));
                    
                    $response_data['wc_debug']['classes_count'] = count($classes);
                    $response_data['wc_debug']['classes_data'] = $classes;
                    
                    $wc_order_id = $this->woocommerce->create_order($insert_id, $data, $classes);
                    error_log('GM: WooCommerce order creation result: ' . ($wc_order_id ? $wc_order_id : 'FAILED'));
                    
                    $response_data['wc_debug']['order_creation_result'] = $wc_order_id ? $wc_order_id : 'FAILED';
                    
                    if ($wc_order_id) {
                        $order = wc_get_order($wc_order_id);
                        if ($order) {
                            $checkout_url = $order->get_checkout_payment_url();
                            error_log('GM: Order checkout URL: ' . $checkout_url);
                            
                            $response_data['wc_order_id'] = $wc_order_id;
                            $response_data['checkout_url'] = $checkout_url;
                            $response_data['order_status'] = $order->get_status();
                            $response_data['wc_debug']['checkout_url'] = $checkout_url;
                        } else {
                            error_log('GM: Could not retrieve WooCommerce order ' . $wc_order_id);
                            $response_data['wc_debug']['order_retrieval'] = 'FAILED';
                        }
                    } else {
                        error_log('GM: WooCommerce order creation returned false/null');
                        $response_data['wc_debug']['order_creation'] = 'returned false/null';
                    }
                } catch (Exception $e) {
                    // Don't fail the whole process if WooCommerce fails
                    error_log('WooCommerce order creation failed: ' . $e->getMessage());
                    error_log('WooCommerce order creation stack trace: ' . $e->getTraceAsString());
                    $response_data['wc_error'] = $e->getMessage();
                    $response_data['wc_debug']['exception'] = $e->getMessage();
                }
            } else {
                error_log('GM: WooCommerce not available (class_exists: ' . (class_exists('WooCommerce') ? 'yes' : 'no') . ', wc_create_order: ' . (function_exists('wc_create_order') ? 'yes' : 'no') . ')');
                $response_data['wc_debug']['availability'] = 'WooCommerce not available';
            }

            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Server error: ' . $e->getMessage()));
        } catch (Error $e) {
            wp_send_json_error(array('message' => 'Fatal error: ' . $e->getMessage()));
        }
    }

    /**
     * Save signature image
     */
    private function save_signature($signature_data) {
        if (empty($signature_data) || strpos($signature_data, 'data:image/') !== 0) {
            return '';
        }

        if (preg_match('#^data:image/(png|jpeg);base64,#i', $signature_data, $m)) {
            $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
            $raw = base64_decode(preg_replace('#^data:image/(png|jpeg);base64,#i', '', $signature_data));
            
            if ($raw !== false) {
                $filename = 'signature-' . time() . '-' . wp_generate_password(6, false, false) . '.' . $ext;
                $upload = wp_upload_bits($filename, null, $raw);
                if (empty($upload['error'])) {
                    return $upload['url'];
                }
            }
        }

        return '';
    }

    /**
     * Save registration to database
     */
    private function save_registration($data, $signature_url) {
        global $wpdb;
        
        $code = $this->db->generate_order_code();
        $classes_json = !empty($data['classes']) ? wp_json_encode($data['classes']) : '[]';

        $result = $wpdb->insert(
            $this->db->get_table_name(),
            array(
                'order_code'         => $code,
                'created_at'         => current_time('mysql'),
                'parent_first_name'  => sanitize_text_field($data['parent_first_name']),
                'parent_last_name'   => sanitize_text_field($data['parent_last_name']),
                'parent_email'       => sanitize_email($data['parent_email']),
                'parent_phone'       => sanitize_text_field($data['parent_phone']),
                'student_first_name' => sanitize_text_field($data['student_first_name']),
                'student_last_name'  => sanitize_text_field($data['student_last_name']),
                'location'           => sanitize_text_field($data['location']),
                'current_grades'     => sanitize_text_field($data['current_grades']),
                'year_group'         => sanitize_text_field($data['year_group']),
                'classes_json'       => $classes_json,
                'monthly_total'      => floatval($data['monthly_total']),
                'payment_method'     => 'online',
                'payment_status'     => 'pending',
                'payment_amount'     => floatval($data['monthly_total']),
                'reg_status'         => 'pending',
                'accepted_terms'     => intval($data['accepted_terms']),
                'signature_url'      => esc_url_raw($signature_url),
            ),
            array('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%f','%s','%s','%f','%s','%d','%s')
        );

        if ($result) {
            return array(
                'insert_id' => $wpdb->insert_id,
                'order_code' => $code
            );
        }
        
        return false;
    }

    /**
     * Send notification email
     */
    private function send_notification_email($registration_id, $data) {
        global $wpdb;
        
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->db->get_table_name()} WHERE id=%d", $registration_id));
        if (!$row) return;

        $to_raw = get_option($this->opt_emails, get_option('admin_email'));
        $recipients = array_filter(array_map('trim', explode(',', $to_raw)));
        if (empty($recipients)) $recipients = array(get_option('admin_email'));

        $classes = json_decode($row->classes_json, true);
        $rows = '';
        
        if (is_array($classes)) {
            foreach ($classes as $c) {
                $rows .= '<tr><td style="padding:6px 8px;border:1px solid #eee;">'.esc_html($data['location']).' - '.esc_html($c['title']).'</td>'.
                         '<td style="padding:6px 8px;border:1px solid #eee;text-align:right;">£'.number_format((float)$c['price'],2).'</td></tr>';
            }
        }

        $total = number_format((float)$data['monthly_total'], 2);
        $html = $this->build_email_html($row, $data, $rows, $total);

        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($recipients, 'New Registration #' . $row->order_code, $html, $headers);
    }

    /**
     * Build email HTML
     */
    private function build_email_html($row, $data, $rows, $total) {
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#111">';
        $html .= '<h2 style="margin:0 0 10px;">New Registration (#'.esc_html($row->order_code).')</h2>';
        $html .= '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:10px 0;width:100%;">';
        $html .= '<tr><td style="padding:6px 8px;border:1px solid #eee;">Parent</td><td style="padding:6px 8px;border:1px solid #eee;">'.esc_html($data['parent_first_name'].' '.$data['parent_last_name']).'</td></tr>';
        $html .= '<tr><td style="padding:6px 8px;border:1px solid #eee;">Parent Email</td><td style="padding:6px 8px;border:1px solid #eee;">'.esc_html($data['parent_email']).'</td></tr>';
        $html .= '<tr><td style="padding:6px 8px;border:1px solid #eee;">Phone</td><td style="padding:6px 8px;border:1px solid #eee;">'.esc_html($data['parent_phone']).'</td></tr>';
        $html .= '<tr><td style="padding:6px 8px;border:1px solid #eee;">Student</td><td style="padding:6px 8px;border:1px solid #eee;">'.esc_html($data['student_first_name'].' '.$data['student_last_name']).'</td></tr>';
        $html .= '<tr><td style="padding:6px 8px;border:1px solid #eee;">Location</td><td style="padding:6px 8px;border:1px solid #eee;">'.esc_html($data['location']).'</td></tr>';
        $html .= '<tr><td style="padding:6px 8px;border:1px solid #eee;">Year Group</td><td style="padding:6px 8px;border:1px solid #eee;">'.esc_html($data['year_group']).'</td></tr>';
        $html .= '</table>';
        $html .= '<h3 style="margin:12px 0 6px;">Selected Classes</h3>';
        $html .= '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;">'.$rows.'</table>';
        $html .= '<p style="text-align:right;"><strong>Monthly Total:</strong> £'.$total.'</p>';
        $html .= '<p><strong>Payment Method:</strong> Online Payment (WooCommerce)</p>';
        if ($row->signature_url) $html .= '<p><strong>Signature:</strong> <a href="'.esc_url($row->signature_url).'" target="_blank">View</a></p>';
        $html .= '</div>';

        return $html;
    }
}