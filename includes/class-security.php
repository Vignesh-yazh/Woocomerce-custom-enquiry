<?php
if (!defined('ABSPATH')) exit;

class MCE_Security {
    private static $instance = null;
    private $rate_limit_duration = 300; // 5 minutes
    private $max_attempts = 5; // Maximum attempts per duration
    private $email_rate_limit_duration = 3600; // 1 hour
    private $max_email_attempts = 10; // Maximum email attempts per hour

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Add security headers
        add_action('send_headers', array($this, 'add_security_headers'));
    }

    public function add_security_headers() {
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    public function check_rate_limit($type = 'general', $key = '') {
        $user_ip = $this->get_client_ip();
        $transient_key = empty($key) ? 
            "mce_rate_limit_{$type}_{$user_ip}" : 
            "mce_rate_limit_{$type}_{$key}_{$user_ip}";
            
        $attempts = get_transient($transient_key);

        if (false === $attempts) {
            set_transient($transient_key, 1, $this->rate_limit_duration);
            return true;
        }

        if ($attempts >= $this->max_attempts) {
            return false;
        }

        set_transient($transient_key, $attempts + 1, $this->rate_limit_duration);
        return true;
    }

    public function validate_email($email) {
        if (!is_email($email)) {
            return false;
        }

        // Extended list of disposable email domains
        $disposable_domains = array(
            'tempmail.com', 'throwawaymail.com', 'temp-mail.org', 
            'guerrillamail.com', 'yopmail.com', 'mailinator.com',
            'tempmail.net', '10minutemail.com', 'trashmail.com',
            'disposablemail.com', 'sharklasers.com', 'spam4.me',
            'tempmail.de', 'tempr.email', 'discard.email',
            'maildrop.cc', 'mailnesia.com', 'tempmailaddress.com'
        );

        $email_domain = substr(strrchr($email, "@"), 1);
        return !in_array(strtolower($email_domain), $disposable_domains);
    }

    public function validate_phone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if phone number is between 10 and 15 digits
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }

    public function sanitize_cart_items($cart_items) {
        $sanitized_items = array();
        
        foreach ($cart_items as $cart_item_key => $cart_item) {
            if (!isset($cart_item['data']) || !is_object($cart_item['data'])) {
                continue;
            }

            $product = $cart_item['data'];
            
            $sanitized_items[] = array(
                'id' => absint($product->get_id()),
                'name' => sanitize_text_field($product->get_name()),
                'quantity' => absint($cart_item['quantity']),
                'sku' => sanitize_text_field($product->get_sku())
            );
        }

        return $sanitized_items;
    }

    public function verify_cart_items($cart_items) {
        if (!is_array($cart_items) || empty($cart_items)) {
            return false;
        }

        foreach ($cart_items as $item) {
            if (!isset($item['id']) || !isset($item['quantity'])) {
                return false;
            }

            $product = wc_get_product($item['id']);
            if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
                return false;
            }

            if (!empty($item['variation_id'])) {
                $variation = wc_get_product($item['variation_id']);
                if (!$variation || $variation->get_parent_id() !== $item['id']) {
                    return false;
                }
            }
        }

        return true;
    }

    public function sanitize_html_content($content) {
        $allowed_html = array(
            'a' => array(
                'href' => array(),
                'title' => array(),
                'class' => array(),
                'rel' => array()
            ),
            'br' => array(),
            'p' => array('class' => array()),
            'div' => array('class' => array()),
            'span' => array('class' => array()),
            'strong' => array(),
            'em' => array(),
            'h2' => array(),
            'h3' => array(),
            'ul' => array(),
            'li' => array(),
            'table' => array('class' => array()),
            'tr' => array(),
            'td' => array(),
            'th' => array(),
            'img' => array(
                'src' => array(),
                'alt' => array(),
                'class' => array(),
                'width' => array(),
                'height' => array()
            )
        );

        return wp_kses($content, $allowed_html);
    }

    public function get_client_ip() {
        $ip_headers = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', $_SERVER[$header]);
                $ip = trim($ip[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    public function verify_request_method($method = 'POST') {
        if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
            wp_die(__('Invalid request method.', 'my-custom-enquiry'));
        }
    }

    public function verify_request_origin() {
        // Skip check if running from CLI or unit tests
        if (php_sapi_name() === 'cli') {
            return true;
        }

        // For AJAX requests
        if (wp_doing_ajax()) {
            // Get the nonce from the request
            $nonce = isset($_REQUEST['nonce']) ? $_REQUEST['nonce'] : '';
            
            // Verify the nonce
            return wp_verify_nonce($nonce, 'mce_cart_enquiry_nonce');
        }

        return true;
    }

    public function sanitize_enquiry_title($title) {
        return wp_strip_all_tags(sanitize_text_field($title));
    }

    public function verify_file_size($file, $max_size = 5242880) { // 5MB default
        if (!isset($file['size']) || $file['size'] > $max_size) {
            return false;
        }
        return true;
    }

    public function generate_csrf_token() {
        if (!session_id()) {
            session_start();
        }
        if (!isset($_SESSION['mce_csrf_token']) || empty($_SESSION['mce_csrf_token'])) {
            $_SESSION['mce_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['mce_csrf_token'];
    }

    public function verify_csrf_token($token) {
        if (!session_id()) {
            session_start();
        }
        $valid = isset($_SESSION['mce_csrf_token']) && hash_equals($_SESSION['mce_csrf_token'], $token);
        // Rotate token after verification
        $_SESSION['mce_csrf_token'] = bin2hex(random_bytes(32));
        return $valid;
    }
}
