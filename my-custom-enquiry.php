<?php
/**
 * Plugin Name: My Custom Enquiry
 * Description: A custom enquiry plugin for WooCommerce products
 * Version: 1.0.0
 * Author: Vignesh Kumar M
 * Text Domain: my-custom-enquiry
 */

if (!defined('ABSPATH')) exit;

// Define plugin constants
define('MCE_VERSION', '1.0.0');
define('MCE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MCE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the license class
require_once MCE_PLUGIN_DIR . 'includes/class-mce-license.php';

class My_Custom_Enquiry {
    private static $instance = null;
    private $security;
    private $ajax_handler;
    private $email_handler;
    private $woocommerce_modifications;
    private $license;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize license system
        $this->license = new MCE_License();
        
        // Only load plugin functionality if license is valid
        if (MCE_License::is_active()) {
            $this->load_dependencies();
            $this->setup_hooks();
        } else {
            add_action('admin_notices', array($this, 'show_license_notice'));
        }
    }

    public function show_license_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php 
            printf(
                __('My Custom Enquiry plugin requires a valid license key to function. Please enter your license key in the <a href="%s">License Settings</a>.', 'my-custom-enquiry'),
                admin_url('options-general.php?page=mce-license')
            ); 
            ?></p>
        </div>
        <?php
    }

    private function setup_hooks() {
        // Initialize the plugin
        add_action('init', array($this, 'init'));
        
        // Admin initialization
        add_action('admin_init', array($this, 'admin_init'));
        
        // Save post meta
        add_action('save_post_mce_enquiry', array($this, 'save_enquiry_meta'));
        add_action('manage_mce_enquiry_posts_custom_column', array($this, 'custom_enquiry_column'), 10, 2);
        add_filter('manage_mce_enquiry_posts_columns', array($this, 'add_enquiry_columns'));
        
        // Scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_add_to_enquiry_cart', array($this->ajax_handler, 'handle_add_to_enquiry_cart'));
        add_action('wp_ajax_nopriv_add_to_enquiry_cart', array($this->ajax_handler, 'handle_add_to_enquiry_cart'));
        add_action('wp_ajax_submit_enquiry_form', array($this->ajax_handler, 'handle_submit_enquiry_form'));
        add_action('wp_ajax_nopriv_submit_enquiry_form', array($this->ajax_handler, 'handle_submit_enquiry_form'));

        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    private function load_dependencies() {
        require_once MCE_PLUGIN_DIR . 'includes/class-security.php';
        require_once MCE_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        require_once MCE_PLUGIN_DIR . 'includes/class-email-handler.php';
        require_once MCE_PLUGIN_DIR . 'includes/class-woocommerce-modifications.php';
    }

    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Initialize WooCommerce modifications
        $this->woocommerce_modifications = new MCE_WooCommerce_Modifications();

        // Register post type
        $this->register_post_type();
        
        // Register shortcode
        add_shortcode('mce_enquiry_form', array($this, 'render_enquiry_form'));
        
        // Remove quick edit
        add_filter('post_row_actions', array($this, 'modify_list_row_actions'), 10, 2);
        
        // Load translations
        load_plugin_textdomain('my-custom-enquiry', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function register_post_type() {
        register_post_type('mce_enquiry', array(
            'labels' => array(
                'name' => __('Enquiries', 'my-custom-enquiry'),
                'singular_name' => __('Enquiry', 'my-custom-enquiry'),
                'menu_name' => __('Enquiries', 'my-custom-enquiry'),
                'add_new' => __('Add New', 'my-custom-enquiry'),
                'add_new_item' => __('Add New Enquiry', 'my-custom-enquiry'),
                'edit_item' => __('View Enquiry', 'my-custom-enquiry'),
                'new_item' => __('New Enquiry', 'my-custom-enquiry'),
                'view_item' => __('View Enquiry', 'my-custom-enquiry'),
                'search_items' => __('Search Enquiries', 'my-custom-enquiry'),
                'not_found' => __('No enquiries found', 'my-custom-enquiry'),
                'not_found_in_trash' => __('No enquiries found in trash', 'my-custom-enquiry'),
                'all_items' => __('All Enquiries', 'my-custom-enquiry')
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 25,
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'supports' => array('title'),
            'menu_icon' => 'dashicons-email-alt',
            'register_meta_box_cb' => array($this, 'add_enquiry_meta_boxes')
        ));
    }

    public function modify_list_row_actions($actions, $post) {
        if ($post->post_type === 'mce_enquiry') {
            // Keep only the view action
            $actions = array(
                'view' => sprintf(
                    '<a href="%s">%s</a>',
                    get_edit_post_link($post->ID),
                    __('View', 'my-custom-enquiry')
                )
            );
        }
        return $actions;
    }

    public function add_enquiry_meta_boxes() {
        add_meta_box(
            'mce_enquiry_details',
            __('Enquiry Details', 'my-custom-enquiry'),
            array($this, 'render_enquiry_details_meta_box'),
            'mce_enquiry',
            'normal',
            'high'
        );
    }

    public function render_enquiry_details_meta_box($post) {
        // Get enquiry details
        $customer_name = get_post_meta($post->ID, '_customer_name', true);
        $customer_email = get_post_meta($post->ID, '_customer_email', true);
        $customer_phone = get_post_meta($post->ID, '_customer_phone', true);
        $customer_message = get_post_meta($post->ID, '_customer_message', true);
        $cart_items = get_post_meta($post->ID, '_cart_items', true);
        $enquiry_date = get_post_meta($post->ID, '_enquiry_date', true);
        $enquiry_status = get_post_meta($post->ID, '_enquiry_status', true) ?: 'pending';
        ?>
        <style>
            .mce-meta-box-wrapper {
                margin: 10px 0;
            }
            .mce-meta-box-wrapper table {
                width: 100%;
                border-collapse: collapse;
            }
            .mce-meta-box-wrapper th,
            .mce-meta-box-wrapper td {
                padding: 8px;
                text-align: left;
                border: 1px solid #ddd;
            }
            .mce-meta-box-wrapper th {
                width: 150px;
                background: #f5f5f5;
            }
            .mce-products-table {
                margin-top: 20px;
            }
            .mce-status {
                padding: 3px 8px;
                border-radius: 3px;
                display: inline-block;
            }
            .mce-status.pending {
                background: #ffeeba;
                color: #856404;
            }
            .mce-status.completed {
                background: #d4edda;
                color: #155724;
            }
            /* Hide the publish box and other unnecessary elements */
            #minor-publishing,
            #delete-action,
            #publish {
                display: none !important;
            }
            #major-publishing-actions {
                background: none !important;
                border-top: none !important;
            }
        </style>

        <div class="mce-meta-box-wrapper">
            <table>
                <tr>
                    <th><?php _e('Status', 'my-custom-enquiry'); ?></th>
                    <td>
                        <select name="_enquiry_status" id="_enquiry_status">
                            <option value="pending" <?php selected($enquiry_status, 'pending'); ?>><?php _e('Pending', 'my-custom-enquiry'); ?></option>
                            <option value="completed" <?php selected($enquiry_status, 'completed'); ?>><?php _e('Completed', 'my-custom-enquiry'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Date', 'my-custom-enquiry'); ?></th>
                    <td><?php echo esc_html($enquiry_date ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($enquiry_date)) : '—'); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Customer Name', 'my-custom-enquiry'); ?></th>
                    <td><?php echo esc_html($customer_name); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Email', 'my-custom-enquiry'); ?></th>
                    <td><a href="mailto:<?php echo esc_attr($customer_email); ?>"><?php echo esc_html($customer_email); ?></a></td>
                </tr>
                <?php if (!empty($customer_phone)) : ?>
                <tr>
                    <th><?php _e('Phone', 'my-custom-enquiry'); ?></th>
                    <td><?php echo esc_html($customer_phone); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php _e('Message', 'my-custom-enquiry'); ?></th>
                    <td><?php echo nl2br(esc_html($customer_message)); ?></td>
                </tr>
            </table>

            <?php if (!empty($cart_items) && is_array($cart_items)) : ?>
                <h3><?php _e('Products', 'my-custom-enquiry'); ?></h3>
                <table class="mce-products-table">
                    <thead>
                        <tr>
                            <th><?php _e('Product', 'my-custom-enquiry'); ?></th>
                            <th><?php _e('SKU', 'my-custom-enquiry'); ?></th>
                            <th><?php _e('Quantity', 'my-custom-enquiry'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item) : ?>
                            <tr>
                                <td><?php echo esc_html($item['name']); ?></td>
                                <td><?php echo esc_html($item['sku']); ?></td>
                                <td><?php echo esc_html($item['quantity']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function save_enquiry_meta($post_id) {
        // If this is an autosave, our form has not been submitted, so we don't want to do anything
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions
        if (!current_user_can('edit_posts')) {
            return;
        }

        // Update the status if it's set
        if (isset($_POST['_enquiry_status'])) {
            $status = sanitize_text_field($_POST['_enquiry_status']);
            if (in_array($status, array('pending', 'completed'))) {
                update_post_meta($post_id, '_enquiry_status', $status);
            }
        }
    }

    public function admin_init() {
        // Register settings
        register_setting('mce_settings', 'mce_email_recipient', 'sanitize_email');
        register_setting('mce_settings', 'mce_email_subject', 'sanitize_text_field');
        register_setting('mce_settings', 'mce_success_message', 'wp_kses_post');

        // Remove bulk actions
        add_filter('bulk_actions-edit-mce_enquiry', array($this, 'modify_bulk_actions'));
    }

    public function modify_bulk_actions($actions) {
        return array(); // Remove all bulk actions
    }

    public function add_enquiry_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns[$key] = __('Enquiry ID', 'my-custom-enquiry');
                $new_columns['customer_name'] = __('Customer Name', 'my-custom-enquiry');
                $new_columns['customer_email'] = __('Email', 'my-custom-enquiry');
                $new_columns['enquiry_date'] = __('Date', 'my-custom-enquiry');
                $new_columns['status'] = __('Status', 'my-custom-enquiry');
            } else {
                $new_columns[$key] = $value;
            }
        }
        return $new_columns;
    }

    public function custom_enquiry_column($column, $post_id) {
        switch ($column) {
            case 'customer_name':
                echo esc_html(get_post_meta($post_id, '_customer_name', true));
                break;
            case 'customer_email':
                $email = get_post_meta($post_id, '_customer_email', true);
                echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                break;
            case 'enquiry_date':
                $date = get_post_meta($post_id, '_enquiry_date', true);
                echo esc_html($date ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date)) : '—');
                break;
            case 'status':
                $status = get_post_meta($post_id, '_enquiry_status', true) ?: 'pending';
                $status_label = $status === 'completed' ? __('Completed', 'my-custom-enquiry') : __('Pending', 'my-custom-enquiry');
                $status_class = $status === 'completed' ? 'completed' : 'pending';
                echo '<span class="mce-status ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span>';
                break;
        }
    }

    public function render_enquiries_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Enquiries', 'my-custom-enquiry'); ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'my-custom-enquiry'); ?></th>
                        <th><?php _e('Date', 'my-custom-enquiry'); ?></th>
                        <th><?php _e('Customer Name', 'my-custom-enquiry'); ?></th>
                        <th><?php _e('Email', 'my-custom-enquiry'); ?></th>
                        <th><?php _e('Status', 'my-custom-enquiry'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $enquiries = get_posts(array(
                        'post_type' => 'mce_enquiry',
                        'posts_per_page' => -1
                    ));

                    foreach ($enquiries as $enquiry) :
                        $customer_name = get_post_meta($enquiry->ID, '_customer_name', true);
                        $customer_email = get_post_meta($enquiry->ID, '_customer_email', true);
                        $enquiry_date = get_post_meta($enquiry->ID, '_enquiry_date', true);
                        $enquiry_status = get_post_meta($enquiry->ID, '_enquiry_status', true);
                        ?>
                        <tr>
                            <td><?php echo esc_html($enquiry->ID); ?></td>
                            <td><?php echo esc_html($enquiry_date ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($enquiry_date)) : '—'); ?></td>
                            <td><?php echo esc_html($customer_name); ?></td>
                            <td><a href="mailto:<?php echo esc_attr($customer_email); ?>"><?php echo esc_html($customer_email); ?></a></td>
                            <td>
                                <?php if ($enquiry_status === 'pending') : ?>
                                    <span class="mce-status pending"><?php _e('Pending', 'my-custom-enquiry'); ?></span>
                                <?php elseif ($enquiry_status === 'completed') : ?>
                                    <span class="mce-status completed"><?php _e('Completed', 'my-custom-enquiry'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function enqueue_scripts() {
        wp_enqueue_style('mce-public-style', plugin_dir_url(__FILE__) . 'public/css/public-style.css', array(), '1.0.0');
        
        wp_enqueue_script('mce-public-script', plugin_dir_url(__FILE__) . 'public/js/public-script.js', array('jquery'), '1.0.0', true);
        
        wp_localize_script('mce-public-script', 'mce_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mce_cart_enquiry_nonce')
        ));
    }

    public function admin_enqueue_scripts($hook) {
        if ('mce_enquiry' !== get_post_type()) {
            return;
        }

        wp_enqueue_style('mce-admin-style', plugin_dir_url(__FILE__) . 'admin/css/admin-style.css', array(), '1.0.0');
        wp_enqueue_script('mce-admin-script', plugin_dir_url(__FILE__) . 'admin/js/admin-script.js', array('jquery'), '1.0.0', true);
        
        wp_localize_script('mce-admin-script', 'mce_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mce_admin_nonce')
        ));
    }

    public function activate() {
        global $wp_roles;

        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }

        // Add capabilities to administrator role
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('edit_posts');
            $role->add_cap('edit_others_posts');
            $role->add_cap('publish_posts');
            $role->add_cap('read_private_posts');
            $role->add_cap('manage_options');
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('My Custom Enquiry requires WooCommerce to be installed and active.', 'my-custom-enquiry'); ?></p>
        </div>
        <?php
    }

    public function render_enquiry_form() {
        // Debug output
        error_log('Rendering enquiry form');
        
        ob_start();
        include plugin_dir_path(__FILE__) . 'public/views/enquiry-form.php';
        return ob_get_clean();
    }

    public function add_enquiry_button_to_cart() {
        // Removed the cart button
    }
}

// Initialize the plugin using singleton pattern
My_Custom_Enquiry::get_instance();
