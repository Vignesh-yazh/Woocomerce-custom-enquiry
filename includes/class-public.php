<?php
class MCE_Public {
    private $version;

    public function __construct() {
        $this->version = '1.0.0';
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts() {
        // Only enqueue on cart page
        if (!is_cart()) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'my-custom-enquiry',
            plugin_dir_url(dirname(__FILE__)) . 'public/css/public-style.css',
            array(),
            $this->version
        );

        // Enqueue JS
        wp_enqueue_script(
            'my-custom-enquiry',
            plugin_dir_url(dirname(__FILE__)) . 'public/js/public-script.js',
            array('jquery'),
            $this->version,
            true
        );

        // Localize script with necessary data
        wp_localize_script('my-custom-enquiry', 'mceAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mce_cart_enquiry_nonce'),
            'errorMessage' => __('Something went wrong. Please try again.', 'my-custom-enquiry'),
            'shop_url' => get_permalink(wc_get_page_id('shop'))
        ));
    }
}
