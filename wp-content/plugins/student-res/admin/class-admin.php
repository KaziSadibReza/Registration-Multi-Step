<?php
/**
 * Admin class for Student Registration Plugin
 * Handles admin interface, registrations list, class management, and settings
 */

if (!defined('ABSPATH')) exit;

class GM_Admin {
    private $db;
    private $woocommerce;
    private $opt_emails = 'gm_reg_emails';

    public function __construct($database_instance, $woocommerce_instance) {
        $this->db = $database_instance;
        $this->woocommerce = $woocommerce_instance;
        
        // WordPress admin hooks
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'handle_admin_delete'));
        add_action('admin_init', array($this, 'handle_settings_save'));
        add_action('admin_init', array($this, 'handle_update_single'));
        add_action('admin_init', array($this, 'handle_class_update'));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'gm_') === false) return;

        // Get plugin directory URL by going up from admin folder to plugin root
        $plugin_url = plugin_dir_url(__FILE__) . '../';

        wp_enqueue_style(
            'gm-admin-css',
            $plugin_url . 'assets/css/admin.css',
            array(),
            '2.9.3'
        );

        wp_enqueue_script(
            'gm-admin-js',
            $plugin_url . 'assets/js/admin.js',
            array('jquery'),
            '2.9.3',
            true
        );
    }

    /**
     * Add admin menu pages
     */
    public function admin_menu() {
        add_menu_page('Registrations', 'Registrations', 'manage_options', 'gm_registrations', array($this, 'admin_page'), 'dashicons-forms', 26);
        add_submenu_page('gm_registrations', 'Classes', 'Classes', 'manage_options', 'gm_classes', array($this, 'classes_page'));
        add_submenu_page('gm_registrations', 'Settings', 'Settings', 'manage_options', 'gm_reg_settings', array($this, 'settings_page'));
    }

    /**
     * Output admin styles
     */
    private function admin_styles() {
        // Admin styles are now loaded via CSS file
        echo '<style>.gm-hidden { display: none !important; }</style>';
    }

    /**
     * Main admin page - registrations list
     */
    public function admin_page() {
        if (!current_user_can('manage_options')) return;
        $this->admin_styles();

        if (isset($_GET['view']) && $_GET['view'] === 'single' && !empty($_GET['id'])) {
            $this->render_single(sanitize_text_field($_GET['id']));
            return;
        }

        $this->render_registrations_list();
    }

    /**
     * Render registrations list
     */
    private function render_registrations_list() {
        global $wpdb;
        $table = $this->db->get_table_name();

        // Filters
        $filter_year = isset($_GET['gm_year']) ? sanitize_text_field($_GET['gm_year']) : '';
        $search = isset($_GET['s']) ? trim(sanitize_text_field($_GET['s'])) : '';
        $where = 'WHERE 1=1';
        $args = array();

        if ($filter_year && $filter_year !== 'all') {
            $where .= " AND year_group=%s";
            $args[] = $filter_year;
        }

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= " AND (order_code LIKE %s OR parent_first_name LIKE %s OR parent_last_name LIKE %s OR parent_email LIKE %s OR parent_phone LIKE %s OR student_first_name LIKE %s OR student_last_name LIKE %s OR location LIKE %s)";
            array_push($args, $like, $like, $like, $like, $like, $like, $like, $like);
        }

        $per = 20;
        $paged = max(1, intval($_GET['paged'] ?? 1));
        $off = ($paged - 1) * $per;

        $total_sql = "SELECT COUNT(*) FROM {$table} $where";
        $total = (int)$wpdb->get_var($wpdb->prepare($total_sql, $args));

        $list_sql = "SELECT id,order_code,created_at,student_first_name,student_last_name,year_group,monthly_total,payment_method,payment_status,payment_provider,payment_trx,reg_status FROM {$table} $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $args2 = $args;
        $args2[] = $per;
        $args2[] = $off;
        $rows = $wpdb->get_results($wpdb->prepare($list_sql, $args2));

        echo '<div class="wrap"><h1 class="wp-heading-inline">Registrations</h1></div>';
        echo '<div class="gm-admin-card">';
        
        $this->render_toolbar($filter_year, $search, $total);
        
        if (!$rows) {
            echo '<p>No registrations found.</p></div>';
            return;
        }

        $this->render_registrations_table($rows);
        $this->render_pagination($total, $per, $paged, $filter_year, $search);
        
        echo '</div>';
        $this->render_signature_modal();
    }

    /**
     * Render toolbar with filters
     */
    private function render_toolbar($filter_year, $search, $total) {
        $years = array(
            'all' => 'All Years', 'Year 1' => 'Year 1', 'Year 2' => 'Year 2', 'Year 3' => 'Year 3',
            'Year 4' => 'Year 4', 'Year 5' => 'Year 5', 'Year 6' => 'Year 6', 'Year 7' => 'Year 7',
            'Year 8' => 'Year 8', 'Year 9' => 'Year 9', 'Year 10' => 'Year 10', 'Year 11' => 'Year 11'
        );

        echo '<div class="gm-toolbar">';
        echo '<form method="get" class="gm-filter" action=""><input type="hidden" name="page" value="gm_registrations">';
        echo '<label>Year Group: <select name="gm_year" onchange="this.form.submit()">';
        foreach ($years as $k => $v) {
            $sel = selected($filter_year ?: 'all', $k, false);
            echo '<option value="' . esc_attr($k) . '" ' . $sel . '>' . esc_html($v) . '</option>';
        }
        echo '</select></label> ';
        echo '<input type="text" name="s" value="' . esc_attr($search) . '" placeholder="Search by code/student/email..."> ';
        echo '<button class="button">Filter</button></form>';
        echo '<div><span class="gm-badge">Total: ' . intval($total) . '</span></div></div>';
    }

    /**
     * Render registrations table
     */
    private function render_registrations_table($rows) {
        echo '<table class="widefat striped gm-table"><thead><tr>
            <th>ID</th><th>Date</th><th>Student</th><th>Year</th><th>Price (£)</th><th>Payment</th><th>Reg. Status</th><th style="width:140px;">Action</th>
        </tr></thead><tbody>';

        foreach ($rows as $r) {
            $labelMethod = ($r->payment_method === 'cash') ? 'Cash' : 'WooCommerce';
            $labelStatus = ucfirst($r->payment_status ?: 'pending');
            $payLabel = $labelMethod . ' – ' . $labelStatus;
            
            if ($r->payment_provider === 'woocommerce' && !empty($r->payment_trx)) {
                $wc_order_url = $this->woocommerce->get_order_admin_url($r->payment_trx);
                $payLabel .= ' (<a href="' . esc_url($wc_order_url) . '" target="_blank">#' . esc_html($r->payment_trx) . '</a>)';
            }

            $view_url = admin_url('admin.php?page=gm_registrations&view=single&id=' . $r->id);
            $del_url = wp_nonce_url(admin_url('admin.php?page=gm_registrations&action=delete&id=' . $r->id), 'gm_del_' . $r->id);

            $rs = $r->reg_status ?: 'pending';
            $rs_map = array('pending' => 'Pending', 'active' => 'Active', 'cancel' => 'Cancel', 'course_complete' => 'Course Complete');
            $rs_text = isset($rs_map[$rs]) ? $rs_map[$rs] : ucfirst($rs);

            echo '<tr>' .
                '<td>' . esc_html($r->order_code) . '</td>' .
                '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($r->created_at))) . '</td>' .
                '<td>' . esc_html($r->student_first_name . ' ' . $r->student_last_name) . '</td>' .
                '<td>' . esc_html($r->year_group) . '</td>' .
                '<td>' . number_format((float)$r->monthly_total, 2) . '</td>' .
                '<td>' . $payLabel . '</td>' .
                '<td>' . esc_html($rs_text) . '</td>' .
                '<td class="gm-actions-cell"><a class="button button-primary" href="' . $view_url . '">View</a><a class="button" href="' . $del_url . '" onclick="return confirm(\'Delete this registration?\');">Delete</a></td>' .
            '</tr>';
        }
        echo '</tbody></table>';
    }

    /**
     * Render pagination
     */
    private function render_pagination($total, $per, $paged, $filter_year, $search) {
        if ($total > $per) {
            $pages = ceil($total / $per);
            $base = add_query_arg(array('paged' => '%#%', 'gm_year' => $filter_year ?: 'all', 's' => $search));
            echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links(array(
                'base' => $base, 'format' => '', 'prev_text' => '&laquo;', 'next_text' => '&raquo;', 'total' => $pages, 'current' => $paged
            )) . '</div></div>';
        }
    }

    /**
     * Render signature modal
     */
    private function render_signature_modal() {
        echo '<div class="gm-modal" id="gm-modal"><div class="gm-modal-box"><img id="gm-modal-img" src="" alt="Signature" style="background:#fff"><p style="text-align:right;margin-top:6px"><a href="#" class="button" id="gm-modal-close">Close</a></p></div></div>';
    }

    /**
     * Render single registration view
     */
    private function render_single($id) {
        global $wpdb;
        $table = $this->db->get_table_name();

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id));
        if (!$row) {
            echo '<div class="wrap"><h1>Registration not found</h1><p><a href="' . esc_url(admin_url('admin.php?page=gm_registrations')) . '" class="button">&laquo; Back</a></p></div>';
            return;
        }

        $this->admin_styles();
        echo '<div class="wrap"><h1 class="wp-heading-inline">Submission #' . esc_html($row->order_code) . '</h1> <a href="' . esc_url(admin_url('admin.php?page=gm_registrations')) . '" class="page-title-action">Back to list</a></div>';

        $classes_json = !empty($row->classes_json) ? stripslashes($row->classes_json) : '[]';
        $classes = json_decode($classes_json, true);

        echo '<div class="gm-admin-card">';
        $this->render_single_details($row, $classes);
        $this->render_single_payment_form($row);
        $this->render_single_signature($row);
        echo '</div>';

        $this->render_signature_modal();
    }

    /**
     * Render single registration details
     */
    private function render_single_details($row, $classes) {
        echo '<h2 class="gm-card-h">Details</h2>';
        echo '<table class="widefat striped"><tbody>';
        
        $out = function($k, $v) { echo '<tr><th style="width:220px;">' . esc_html($k) . '</th><td>' . wp_kses_post($v) . '</td></tr>'; };
        
        $out('Date', esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row->created_at))));
        $out('Registration ID', esc_html($row->order_code));
        $out('Parent', esc_html($row->parent_first_name . ' ' . $row->parent_last_name));
        $out('Parent Email', esc_html($row->parent_email));
        $out('Phone', esc_html($row->parent_phone));
        $out('Student', esc_html($row->student_first_name . ' ' . $row->student_last_name));
        $out('Location', esc_html($row->location));
        $out('Year Group', esc_html($row->year_group));
        $out('Current Grades', esc_html($row->current_grades));

        $clsText = '—';
        if (is_array($classes) && !empty($classes)) {
            $names = [];
            foreach ($classes as $c) {
                if (isset($c['title'])) {
                    $names[] = $c['title'] . ' (£' . number_format((float)($c['price'] ?? 0), 2) . ')';
                }
            }
            if (!empty($names)) {
                $clsText = esc_html(implode(', ', $names));
            }
        }
        $out('Classes', $clsText);
        $out('Monthly Total', '£' . number_format((float)$row->monthly_total, 2));
        
        echo '</tbody></table>';
    }

    /**
     * Render single registration payment form
     */
    private function render_single_payment_form($row) {
        echo '<h2 class="gm-card-h">Payment</h2>';
        echo '<form method="post" action="">';
        wp_nonce_field('gm_update_single_' . $row->id);
        echo '<table class="widefat striped"><tbody>';
        
        $out = function($k, $v) { echo '<tr><th>' . esc_html($k) . '</th><td>' . wp_kses_post($v) . '</td></tr>'; };
        $payment_method_label = ($row->payment_method === 'cash') ? 'Cash Payment' : 'Online (WooCommerce)';
        $out('Method', $payment_method_label);

        $auto_update_note = ($row->payment_method === 'cash') ? 'Manual update required for cash payments' : 'Auto-updated by WooCommerce';
        echo '<tr><th>Status</th><td><select name="gm_payment_status">
            <option value="pending" ' . selected($row->payment_status, 'pending', false) . '>Pending</option>
            <option value="paid" ' . selected($row->payment_status, 'paid', false) . '>Paid</option>
            <option value="hold" ' . selected($row->payment_status, 'hold', false) . '>Hold</option>
            <option value="cancel" ' . selected($row->payment_status, 'cancel', false) . '>Cancel</option>
        </select> <span class="gm-tag">' . $auto_update_note . '</span></td></tr>';
        
        if ($row->payment_provider === 'woocommerce' && !empty($row->payment_trx)) {
            $wc_order_url = $this->woocommerce->get_order_admin_url($row->payment_trx);
            $out('WooCommerce Order', '<a href="' . esc_url($wc_order_url) . '" target="_blank">View Order #' . esc_html($row->payment_trx) . ' &rarr;</a>');
        }

        echo '<tr><th>Amount (£)</th><td><input type="text" name="gm_payment_amount" value="' . esc_attr(number_format((float)$row->payment_amount, 2, '.', '')) . '"></td></tr>';
        echo '</tbody></table>';

        echo '<h2 class="gm-card-h">Registration Status</h2>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><th>Status</th><td><select name="gm_reg_status">
            <option value="pending" ' . selected($row->reg_status, 'pending', false) . '>Pending</option>
            <option value="active" ' . selected($row->reg_status, 'active', false) . '>Active</option>
            <option value="cancel" ' . selected($row->reg_status, 'cancel', false) . '>Cancel</option>
            <option value="course_complete" ' . selected($row->reg_status, 'course_complete', false) . '>Course Complete</option>
        </select></td></tr>';
        echo '</tbody></table>';

        echo '<p class="submit"><input type="hidden" name="gm_update_single" value="1"><input type="hidden" name="id" value="' . intval($row->id) . '"><button class="button button-primary">Save Changes</button></p>';
        echo '</form>';
    }

    /**
     * Render single registration signature section
     */
    private function render_single_signature($row) {
        echo '<h2 class="gm-card-h">Signature</h2>';
        if ($row->signature_url) {
            echo '<a href="' . esc_url($row->signature_url) . '" class="gm-view-sig button" data-src="' . esc_url($row->signature_url) . '">View Signature</a>';
        } else {
            echo '<p>—</p>';
        }
    }

    /**
     * Classes management page
     */
    public function classes_page() {
        if (!current_user_can('manage_options')) return;
        
        $this->admin_styles();
        
        $classes = $this->db->get_classes();
        
        echo '<div class="wrap"><h1 class="wp-heading-inline">Class Management</h1></div>';
        echo '<div class="gm-admin-card">';
        echo '<p>From this screen, you can manage all aspects of your classes. View seat availability, see how many students are registered, and manually override the registration status if a class needs to be closed or opened.</p>';
        
        if (!$classes) {
            echo '<p>No classes found.</p></div>';
            return;
        }
        
        $this->render_classes_table($classes);
        $this->render_class_edit_form($classes);
        
        echo '</div>';
    }

    /**
     * Render classes table
     */
    private function render_classes_table($classes) {
        echo '<table class="widefat striped gm-table" style="margin-top:16px;"><thead><tr>
            <th>Class</th><th>Price (£)</th><th>Seats Limit</th><th>Registered</th><th>Status</th><th>Override</th><th>Action</th>
        </tr></thead><tbody>';
        
        foreach ($classes as $class) {
            $registered = $this->db->get_registered_count($class->class_id);
            $is_available = $this->db->is_class_available($class->class_id);
            $status_text = $is_available ? '<span style="color:#059669;font-weight:bold;">Available</span>' : '<span style="color:#ef4444;font-weight:bold;">Full</span>';
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($class->title) . '</strong><br><small>' . esc_html($class->class_id) . '</small></td>';
            echo '<td>' . number_format((float)$class->price, 2) . '</td>';
            echo '<td>' . intval($class->max_seats) . '</td>';
            echo '<td><strong>' . $registered . '</strong> of ' . intval($class->max_seats) . '</td>';
            echo '<td>' . $status_text . '</td>';
            echo '<td>
                <form method="post" style="display:inline;">
                    ' . wp_nonce_field('update_class', '_wpnonce', true, false) . '
                    <input type="hidden" name="class_id" value="' . esc_attr($class->class_id) . '">
                    <select name="status_override" onchange="this.form.submit();">
                        <option value="auto"' . selected($class->status_override, 'auto', false) . '>Auto</option>
                        <option value="available"' . selected($class->status_override, 'available', false) . '>Force Available</option>
                        <option value="full"' . selected($class->status_override, 'full', false) . '>Force Full</option>
                    </select>
                    <input type="hidden" name="update_class" value="1">
                </form>
            </td>';
            echo '<td><a href="#" onclick="event.preventDefault(); editClass(\'' . esc_js($class->class_id) . '\');" class="button">Edit Details</a></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }

    /**
     * Render class edit form
     */
    private function render_class_edit_form($classes) {
        echo '<div id="edit-class-form" style="display:none;margin-top:20px;" class="gm-admin-card">
            <h2>Edit Class Details</h2>
            <form method="post">
                ' . wp_nonce_field('update_class', '_wpnonce', true, false) . '
                <input type="hidden" name="update_class" value="1">
                <input type="hidden" name="class_id" id="edit-class-id" value="">
                <table class="form-table">
                    <tr><th><label for="edit-title">Class Title</label></th>
                        <td><input type="text" id="edit-title" name="title" class="regular-text" required></td></tr>
                    <tr><th><label for="edit-price">Price (£)</label></th>
                        <td><input type="number" id="edit-price" name="price" step="0.01" min="0" class="small-text" required></td></tr>
                    <tr><th><label for="edit-seats">Max Seats</label></th>
                        <td><input type="number" id="edit-seats" name="max_seats" min="1" class="small-text" required></td></tr>
                    <tr><th><label for="edit-description">Description (Optional)</label></th>
                        <td><textarea id="edit-description" name="description" class="large-text" rows="3"></textarea></td></tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Update Class">
                    <button type="button" class="button" onclick="cancelEdit();">Cancel</button>
                </p>
            </form>
        </div>';
        
        // JavaScript for edit functionality
        echo '<script>
        const classesData = ' . json_encode(array_values($classes)) . ';
        const classesMap = new Map(classesData.map(c => [c.class_id, c]));
        </script>';
    }

    /**
     * Settings page
     */
    public function settings_page() {
        if (!current_user_can('manage_options')) return;
        
        $emails = esc_attr(get_option($this->opt_emails, get_option('admin_email')));
        
        echo '<div class="wrap"><h1>Registration Settings</h1><form method="post" action="">'; 
        wp_nonce_field('gm_reg_settings');
        echo '<table class="form-table">
            <tr><th><label for="gm_emails">Notification Emails</label></th>
                <td><input type="text" id="gm_emails" name="gm_emails" class="regular-text" value="'.$emails.'" placeholder="admin@example.com, team@example.com">
                <p class="description">Comma-separated emails for new registration notifications.</p></td></tr>
        </table>';
        echo '<p class="submit"><input type="submit" class="button button-primary" value="Save Changes"></p></form>';
        
        // WooCommerce integration info
        echo '<div class="gm-admin-card" style="margin-top: 20px;">
            <h2>WooCommerce Integration</h2>
            <p>Payment processing is handled through WooCommerce. Make sure you have WooCommerce installed and configured with your preferred payment gateways (e.g., Stripe, PayPal).</p>
            <p>When students complete a registration, they will be redirected to the WooCommerce checkout to complete payment.</p>
            <p>The registration status will be automatically updated based on the WooCommerce order status.</p>
        </div>';
        
        echo '</div>';
    }

    /**
     * Handle admin delete action
     */
    public function handle_admin_delete() {
        if (!is_admin() || !current_user_can('manage_options')) return;
        if (!isset($_GET['page']) || $_GET['page'] !== 'gm_registrations') return;
        
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
            check_admin_referer('gm_del_' . intval($_GET['id']));
            global $wpdb;
            $wpdb->delete($this->db->get_table_name(), array('id' => intval($_GET['id'])), array('%d'));
            wp_redirect(remove_query_arg(array('action', 'id', '_wpnonce')));
            exit;
        }
    }

    /**
     * Handle single registration update
     */
    public function handle_update_single() {
        if (!is_admin() || !current_user_can('manage_options')) return;
        if (!isset($_POST['gm_update_single'])) return;
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) return;
        
        check_admin_referer('gm_update_single_' . $id);

        global $wpdb;
        $data = array(
            'payment_status' => sanitize_text_field($_POST['gm_payment_status'] ?? 'pending'),
            'payment_amount' => floatval($_POST['gm_payment_amount'] ?? 0),
            'reg_status' => sanitize_text_field($_POST['gm_reg_status'] ?? 'pending'),
        );
        
        $wpdb->update($this->db->get_table_name(), $data, array('id' => $id), array('%s', '%f', '%s'), array('%d'));
        wp_redirect(add_query_arg(array('page' => 'gm_registrations', 'view' => 'single', 'id' => $id, 'updated' => '1'), admin_url('admin.php')));
        exit;
    }

    /**
     * Handle class update
     */
    public function handle_class_update() {
        if (!is_admin() || !current_user_can('manage_options')) return;
        if (!isset($_POST['update_class'])) return;
        
        check_admin_referer('update_class');
        
        $class_id = sanitize_text_field($_POST['class_id'] ?? '');
        if (!$class_id) return;
        
        $data = array();
        if (isset($_POST['title'])) $data['title'] = $_POST['title'];
        if (isset($_POST['price'])) $data['price'] = $_POST['price'];
        if (isset($_POST['max_seats'])) $data['max_seats'] = $_POST['max_seats'];
        if (isset($_POST['description'])) $data['description'] = $_POST['description'];
        if (isset($_POST['status_override'])) $data['status_override'] = $_POST['status_override'];
        
        if (!empty($data)) {
            $this->db->update_class($class_id, $data);
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>Class updated successfully.</p></div>';
            });
        }
    }

    /**
     * Handle settings save
     */
    public function handle_settings_save() {
        if (!is_admin() || !current_user_can('manage_options')) return;
        if (!isset($_GET['page']) || $_GET['page'] !== 'gm_reg_settings') return;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('gm_reg_settings');
            update_option($this->opt_emails, sanitize_text_field(wp_unslash($_POST['gm_emails'] ?? '')));
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
            });
        }
    }
}