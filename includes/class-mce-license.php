<?php
if (!defined('ABSPATH')) exit;

class MCE_License {
    private $option_name = 'mce_license_key';
    private $page_slug = 'mce-license';
    private $salt = 'NTf5g8#mK9$pL2@vX'; // Custom salt for additional security
    
    private function get_valid_license_hashes() {
        // Define the valid license keys
        $valid_keys = array(
            'NTPLVIGNESH824',
            'NTPLVIGNESH847',
            'NTPLVIGNESH6609'  // Updated from 660 to 6609
        );
        
        // Generate hashes for each license key
        $hashes = array();
        foreach ($valid_keys as $key) {
            $hashes[] = hash('sha224', $key . $this->salt . get_site_url());
        }
        return $hashes;
    }
    
    private function hash_license($license) {
        return hash('sha224', $license . $this->salt . get_site_url());
    }
    
    public function __construct() {
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_license_menu'));
            add_action('admin_init', array($this, 'register_license_settings'));
            
            $plugin_basename = plugin_basename(MCE_PLUGIN_DIR . 'my-custom-enquiry.php');
            add_filter("plugin_action_links_{$plugin_basename}", array($this, 'add_settings_link'));
        }
        
        add_action('admin_init', array($this, 'check_license'));
    }

    public function add_license_menu() {
        add_options_page(
            __('My Custom Enquiry License', 'my-custom-enquiry'),
            __('MCE License', 'my-custom-enquiry'),
            'manage_options',
            $this->page_slug,
            array($this, 'license_page')
        );
    }

    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=' . $this->page_slug),
            __('License Settings', 'my-custom-enquiry')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    public function register_license_settings() {
        register_setting(
            'mce_license_settings',
            $this->option_name,
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_license'),
                'default' => ''
            )
        );
    }

    public function sanitize_license($input) {
        $license = strtoupper(trim($input));
        
        if (empty($license)) {
            return '';
        }

        // Hash the license for comparison
        $license_hash = $this->hash_license($license);
        $valid_hashes = $this->get_valid_license_hashes();

        // Check if license is valid
        if (!in_array($license_hash, $valid_hashes)) {
            add_settings_error(
                'mce_license_settings',
                'invalid_license',
                __('Invalid license key. Please contact Vignesh Kumar M (8248476609) for a valid license.', 'my-custom-enquiry')
            );
            return '';
        }
        
        // Check if license is already in use on another domain
        $used_licenses = get_option('mce_used_licenses', array());
        $current_domain = $_SERVER['HTTP_HOST'];
        
        // If this license is already used by this domain, it's okay
        if (isset($used_licenses[$current_domain]) && $this->hash_license($used_licenses[$current_domain]) === $license_hash) {
            update_option('mce_license_status', 'valid');
            return $license;
        }
        
        // Check if license is used by another domain
        foreach ($used_licenses as $domain => $used_license) {
            if ($this->hash_license($used_license) === $license_hash && $domain !== $current_domain) {
                add_settings_error(
                    'mce_license_settings',
                    'license_in_use',
                    __('This license key is already in use on another domain. Each license can only be used on one domain.', 'my-custom-enquiry')
                );
                return '';
            }
        }
        
        // Save the domain-license association
        $used_licenses[$current_domain] = $license;
        update_option('mce_used_licenses', $used_licenses);
        update_option('mce_license_status', 'valid');
        
        return $license;
    }

    public function check_license() {
        $license = get_option($this->option_name);
        $current_domain = $_SERVER['HTTP_HOST'];
        $used_licenses = get_option('mce_used_licenses', array());
        
        if (empty($license)) {
            update_option('mce_license_status', 'invalid');
            return false;
        }

        // Hash the license for comparison
        $license_hash = $this->hash_license($license);
        $valid_hashes = $this->get_valid_license_hashes();
        
        // If license is not in valid list
        if (!in_array($license_hash, $valid_hashes)) {
            update_option('mce_license_status', 'invalid');
            return false;
        }
        
        // If this domain has this license, it's valid
        if (isset($used_licenses[$current_domain]) && $this->hash_license($used_licenses[$current_domain]) === $license_hash) {
            update_option('mce_license_status', 'valid');
            return true;
        }
        
        // If license is used by another domain
        foreach ($used_licenses as $domain => $used_license) {
            if ($this->hash_license($used_license) === $license_hash && $domain !== $current_domain) {
                update_option('mce_license_status', 'invalid');
                return false;
            }
        }
        
        // If we get here, license is valid and not used
        $used_licenses[$current_domain] = $license;
        update_option('mce_used_licenses', $used_licenses);
        update_option('mce_license_status', 'valid');
        return true;
    }

    public function license_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $license = get_option($this->option_name);
        $status = get_option('mce_license_status');
        ?>
        <div class="wrap">
            <h1><?php _e('My Custom Enquiry License Settings', 'my-custom-enquiry'); ?></h1>
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('mce_license_settings');
                do_settings_sections('mce_license_settings');
                ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php _e('License Key', 'my-custom-enquiry'); ?></th>
                        <td>
                            <input type="text" 
                                id="<?php echo esc_attr($this->option_name); ?>" 
                                name="<?php echo esc_attr($this->option_name); ?>" 
                                value="<?php echo esc_attr($license); ?>" 
                                class="regular-text"
                                placeholder="NTPLVIGNESHXXX"
                            />
                            <?php if($status == 'valid'): ?>
                                <span style="color:green; margin-left:10px;"><strong>✓ <?php _e('License Active', 'my-custom-enquiry'); ?></strong></span>
                            <?php else: ?>
                                <span style="color:red; margin-left:10px;"><strong>✗ <?php _e('License Inactive', 'my-custom-enquiry'); ?></strong></span>
                            <?php endif; ?>
                            <p class="description">
                                <?php _e('Enter your license key in the format NTPLVIGNESHXXX', 'my-custom-enquiry'); ?><br>
                                <?php _e('Each license key can only be used on one domain.', 'my-custom-enquiry'); ?><br>
                                <?php _e('Need a license? Contact Vignesh Kumar M (8248476609) for purchase.', 'my-custom-enquiry'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save License', 'my-custom-enquiry')); ?>
            </form>
        </div>
        <?php
    }

    public static function is_active() {
        return get_option('mce_license_status') === 'valid';
    }
}
