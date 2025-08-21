<?php
if (!defined('ABSPATH')) exit;

class MCE_Admin {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('manage_mce_enquiry_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_mce_enquiry_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        add_filter('manage_edit-mce_enquiry_sortable_columns', array($this, 'set_sortable_columns'));
        add_action('add_meta_boxes_mce_enquiry', array($this, 'add_enquiry_meta_boxes'));
        add_action('init', array($this, 'register_post_type'));
        add_filter('post_row_actions', array($this, 'modify_list_row_actions'), 10, 2);
        add_action('admin_notices', array($this, 'show_migration_notice'));
        add_action('admin_notices', array($this, 'show_migration_success'));
        add_action('admin_init', array($this, 'handle_migration'));
    }

    public function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=mce_enquiry',
            __('Enquiry Settings', 'my-custom-enquiry'),
            __('Settings', 'my-custom-enquiry'),
            'manage_options',
            'mce-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('mce_settings', 'mce_email_recipient');
        register_setting('mce_settings', 'mce_email_subject');
        register_setting('mce_settings', 'mce_customer_email_subject');
        register_setting('mce_settings', 'mce_success_message');
        
        add_settings_section(
            'mce_email_settings',
            __('Email Settings', 'my-custom-enquiry'),
            array($this, 'render_email_settings_section'),
            'mce-settings'
        );

        add_settings_field(
            'mce_email_recipient',
            __('Admin Email Recipient', 'my-custom-enquiry'),
            array($this, 'render_email_recipient_field'),
            'mce-settings',
            'mce_email_settings'
        );

        add_settings_field(
            'mce_email_subject',
            __('Admin Email Subject', 'my-custom-enquiry'),
            array($this, 'render_email_subject_field'),
            'mce-settings',
            'mce_email_settings'
        );

        add_settings_field(
            'mce_customer_email_subject',
            __('Customer Email Subject', 'my-custom-enquiry'),
            array($this, 'render_customer_email_subject_field'),
            'mce-settings',
            'mce_email_settings'
        );

        add_settings_field(
            'mce_success_message',
            __('Success Message', 'my-custom-enquiry'),
            array($this, 'render_success_message_field'),
            'mce-settings',
            'mce_email_settings'
        );
    }

    public function render_email_settings_section() {
        echo '<p>' . __('Configure email notifications for enquiries.', 'my-custom-enquiry') . '</p>';
    }

    public function render_email_recipient_field() {
        $value = get_option('mce_email_recipient', get_option('admin_email'));
        ?>
        <input type="email" 
               name="mce_email_recipient" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <p class="description">
            <?php _e('Email address where admin notifications will be sent.', 'my-custom-enquiry'); ?>
        </p>
        <?php
    }

    public function render_email_subject_field() {
        $value = get_option('mce_email_subject', __('New Enquiry Received', 'my-custom-enquiry'));
        ?>
        <input type="text" 
               name="mce_email_subject" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <p class="description">
            <?php _e('Subject line for admin notification emails.', 'my-custom-enquiry'); ?>
        </p>
        <?php
    }

    public function render_customer_email_subject_field() {
        $value = get_option('mce_customer_email_subject', __('Your Enquiry Confirmation', 'my-custom-enquiry'));
        ?>
        <input type="text" 
               name="mce_customer_email_subject" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <p class="description">
            <?php _e('Subject line for customer confirmation emails.', 'my-custom-enquiry'); ?>
        </p>
        <?php
    }

    public function render_success_message_field() {
        $value = get_option('mce_success_message', __('Thank you for your enquiry. We will get back to you soon.', 'my-custom-enquiry'));
        ?>
        <textarea name="mce_success_message" 
                  class="large-text" 
                  rows="3"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php _e('Message shown to customer after successful enquiry submission.', 'my-custom-enquiry'); ?>
        </p>
        <?php
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('mce_settings');
                do_settings_sections('mce-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_admin_scripts($hook) {
        $screen = get_current_screen();
        
        if ($screen->post_type === 'mce_enquiry' || strpos($hook, 'mce-settings') !== false) {
            wp_enqueue_style(
                'mce-admin-style',
                MCE_PLUGIN_URL . 'admin/css/admin-style.css',
                array(),
                MCE_VERSION
            );

            wp_enqueue_script(
                'mce-admin-script',
                MCE_PLUGIN_URL . 'admin/js/admin-script.js',
                array('jquery'),
                MCE_VERSION,
                true
            );
        }
    }

    public function set_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = __('Title', 'my-custom-enquiry');
        $new_columns['customer_name'] = __('Name', 'my-custom-enquiry');
        $new_columns['customer_email'] = __('Email', 'my-custom-enquiry');
        $new_columns['customer_phone'] = __('Phone', 'my-custom-enquiry');
        $new_columns['products'] = __('Products', 'my-custom-enquiry');
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    public function set_sortable_columns($columns) {
        $columns['customer_name'] = 'customer_name';
        $columns['customer_email'] = 'customer_email';
        $columns['date'] = 'date';
        return $columns;
    }

    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'customer_name':
                echo esc_html(get_post_meta($post_id, 'customer_name', true));
                break;
            case 'customer_email':
                $email = get_post_meta($post_id, 'customer_email', true);
                echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                break;
            case 'customer_phone':
                $phone = get_post_meta($post_id, 'customer_phone', true);
                echo !empty($phone) ? esc_html($phone) : '—';
                break;
            case 'products':
                $cart_items = get_post_meta($post_id, 'cart_items', true);
                if (is_array($cart_items)) {
                    echo '<ul class="mce-product-list">';
                    foreach ($cart_items as $item) {
                        echo '<li>' . esc_html($item['name']) . ' (×' . esc_html($item['quantity']) . ')</li>';
                    }
                    echo '</ul>';
                }
                break;
        }
    }

    public function add_enquiry_meta_boxes() {
        add_meta_box(
            'mce_enquiry_details',
            __('Enquiry Details', 'my-custom-enquiry'),
            array($this, 'render_enquiry_details'),
            'mce_enquiry',
            'normal',
            'high'
        );

        add_meta_box(
            'mce_products_details',
            __('Products', 'my-custom-enquiry'),
            array($this, 'render_products_details'),
            'mce_enquiry',
            'normal',
            'high'
        );
    }

    public function render_enquiry_details($post) {
        $customer_name = get_post_meta($post->ID, 'customer_name', true);
        $customer_email = get_post_meta($post->ID, 'customer_email', true);
        $customer_phone = get_post_meta($post->ID, 'customer_phone', true);
        $customer_message = get_post_meta($post->ID, 'customer_message', true);
        ?>
        <div class="mce-enquiry-details">
            <table class="form-table">
                <tr>
                    <th><?php _e('Name:', 'my-custom-enquiry'); ?></th>
                    <td><?php echo esc_html($customer_name); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Email:', 'my-custom-enquiry'); ?></th>
                    <td><?php echo esc_html($customer_email); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Phone:', 'my-custom-enquiry'); ?></th>
                    <td><?php echo esc_html($customer_phone); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Message:', 'my-custom-enquiry'); ?></th>
                    <td><?php echo nl2br(esc_html($customer_message)); ?></td>
                </tr>
            </table>
        </div>
        <?php
    }

    public function render_products_details($post) {
        $cart_items = get_post_meta($post->ID, 'cart_items', true);
        if (empty($cart_items)) {
            echo '<p>' . __('No products found in this enquiry.', 'my-custom-enquiry') . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Product', 'my-custom-enquiry') . '</th>';
        echo '<th>' . __('Quantity', 'my-custom-enquiry') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($cart_items as $item) {
            echo '<tr>';
            echo '<td>' . esc_html($item['name']) . '</td>';
            echo '<td>' . esc_html($item['quantity']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }

    public function register_post_type() {
        $labels = array(
            'name'               => __('Enquiries', 'my-custom-enquiry'),
            'singular_name'      => __('Enquiry', 'my-custom-enquiry'),
            'menu_name'          => __('Enquiries', 'my-custom-enquiry'),
            'add_new'            => __('Add New', 'my-custom-enquiry'),
            'add_new_item'       => __('Add New Enquiry', 'my-custom-enquiry'),
            'edit_item'          => __('View Enquiry', 'my-custom-enquiry'),
            'new_item'           => __('New Enquiry', 'my-custom-enquiry'),
            'view_item'          => __('View Enquiry', 'my-custom-enquiry'),
            'search_items'       => __('Search Enquiries', 'my-custom-enquiry'),
            'not_found'          => __('No enquiries found', 'my-custom-enquiry'),
            'not_found_in_trash' => __('No enquiries found in trash', 'my-custom-enquiry')
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'menu_position'       => 25,
            'menu_icon'           => 'dashicons-email-alt',
            'supports'            => array('title'),
            'capabilities' => array(
                'create_posts' => false,
                'edit_post' => 'edit_posts',
                'read_post' => 'read',
                'delete_post' => 'delete_posts',
            )
        );

        register_post_type('mce_enquiry', $args);
    }

    public function modify_list_row_actions($actions, $post) {
        if ($post->post_type === 'mce_enquiry') {
            // Remove quick edit
            unset($actions['inline hide-if-no-js']);
            
            // Change Edit to View
            if (isset($actions['edit'])) {
                $actions['edit'] = str_replace('Edit', 'View', $actions['edit']);
            }
        }
        return $actions;
    }

    public function show_migration_notice() {
        // Only show on our plugin's pages
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('edit-mce_enquiry', 'mce_enquiry'))) {
            return;
        }

        // Check if migration is needed
        $migration_done = get_option('mce_data_migration_done');
        if ($migration_done) {
            return;
        }

        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <?php _e('My Custom Enquiry plugin needs to update your database to ensure all enquiries display correctly.', 'my-custom-enquiry'); ?>
                <a href="<?php echo wp_nonce_url(admin_url('edit.php?post_type=mce_enquiry&action=mce_migrate'), 'mce_migration'); ?>" class="button button-primary">
                    <?php _e('Update Database', 'my-custom-enquiry'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    public function show_migration_success() {
        if (!isset($_GET['migration']) || $_GET['migration'] !== 'success') {
            return;
        }

        $migrated = isset($_GET['migrated']) ? intval($_GET['migrated']) : 0;
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                printf(
                    _n(
                        'Successfully migrated %d enquiry.',
                        'Successfully migrated %d enquiries.',
                        $migrated,
                        'my-custom-enquiry'
                    ),
                    $migrated
                );
                ?>
            </p>
        </div>
        <?php
    }

    public function handle_migration() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'mce_migrate') {
            return;
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'mce_migration')) {
            wp_die(__('Security check failed.', 'my-custom-enquiry'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'my-custom-enquiry'));
        }

        // Include migration class
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-data-migration.php';
        
        // Run migration
        $migrated = My_Custom_Enquiry_Data_Migration::migrate_old_enquiries();
        
        // Mark migration as done
        update_option('mce_data_migration_done', true);

        // Redirect back with success message
        wp_redirect(add_query_arg(
            array(
                'post_type' => 'mce_enquiry',
                'migration' => 'success',
                'migrated' => $migrated
            ),
            admin_url('edit.php')
        ));
        exit;
    }
}

// Initialize the admin class
MCE_Admin::get_instance();
