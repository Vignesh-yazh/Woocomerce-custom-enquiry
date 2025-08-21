<?php
if (!defined('ABSPATH')) exit;

class MCE_Public {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('mce_enquiry_form', array($this, 'render_enquiry_form'));
    }

    public function enqueue_scripts() {
        wp_enqueue_style(
            'mce-public-style',
            plugin_dir_url(dirname(__FILE__)) . 'public/css/public-style.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'mce-public-script',
            plugin_dir_url(dirname(__FILE__)) . 'public/js/public-script.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script(
            'mce-public-script',
            'mceAjax',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mce_cart_enquiry_nonce'),
                'processing' => __('Processing...', 'my-custom-enquiry'),
                'error' => __('An error occurred. Please try again.', 'my-custom-enquiry'),
                'thank_you' => __('Thank You!', 'my-custom-enquiry'),
                'back_to_shop' => __('Back to Products', 'my-custom-enquiry'),
                'shop_url' => wc_get_page_permalink('shop')
            )
        );
    }

    public function render_enquiry_form() {
        ob_start();
        include plugin_dir_path(dirname(__FILE__)) . 'public/views/enquiry-form.php';
        return ob_get_clean();
    }
}
