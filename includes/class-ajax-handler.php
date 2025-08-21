<?php
if (!defined('ABSPATH')) exit;

class MCE_Ajax_Handler {
    private $security;
    private $email_handler;

    public function __construct() {
        add_action('wp_ajax_submit_cart_enquiry', array($this, 'handle_cart_enquiry'));
        add_action('wp_ajax_nopriv_submit_cart_enquiry', array($this, 'handle_cart_enquiry'));
        
        $this->security = MCE_Security::get_instance();
        $this->email_handler = new MCE_Email_Handler();

        // Add AJAX action for cart enquiry submission
        add_action('wp_ajax_mce_submit_enquiry', array($this, 'handle_cart_enquiry'));
        add_action('wp_ajax_nopriv_mce_submit_enquiry', array($this, 'handle_cart_enquiry'));
    }

    public function handle_cart_enquiry() {
        try {
            // Verify nonce
            if (!check_ajax_referer('mce_cart_enquiry_nonce', 'nonce', false)) {
                throw new Exception(__('Security check failed.', 'my-custom-enquiry'));
            }

            // Get and validate form data
            $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
            $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
            $customer_phone = isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : '';
            $customer_message = isset($_POST['customer_message']) ? sanitize_textarea_field($_POST['customer_message']) : '';

            // Validate required fields
            if (empty($customer_name) || empty($customer_email) || empty($customer_message)) {
                throw new Exception(__('Please fill in all required fields.', 'my-custom-enquiry'));
            }

            // Validate email format
            if (!is_email($customer_email)) {
                throw new Exception(__('Please enter a valid email address.', 'my-custom-enquiry'));
            }

            // Validate phone format (optional field)
            if (!empty($customer_phone) && !preg_match('/^[0-9+\-\s()]*$/', $customer_phone)) {
                throw new Exception(__('Please enter a valid phone number.', 'my-custom-enquiry'));
            }

            // Get cart items and validate
            $cart_items = WC()->cart->get_cart();
            if (empty($cart_items)) {
                throw new Exception(__('Your cart is empty.', 'my-custom-enquiry'));
            }

            // Sanitize cart items
            $cart_items = $this->security->sanitize_cart_items($cart_items);

            // Create enquiry post
            $post_data = array(
                'post_title'    => sprintf(__('Enquiry from %s', 'my-custom-enquiry'), $customer_name),
                'post_content'  => $customer_message,
                'post_status'   => 'publish',
                'post_type'     => 'mce_enquiry'
            );

            $post_id = wp_insert_post($post_data);

            if (is_wp_error($post_id)) {
                throw new Exception(__('Failed to create enquiry. Please try again.', 'my-custom-enquiry'));
            }

            // Save meta data
            update_post_meta($post_id, '_customer_name', $customer_name);
            update_post_meta($post_id, '_customer_email', $customer_email);
            update_post_meta($post_id, '_customer_phone', $customer_phone);
            update_post_meta($post_id, '_cart_items', $cart_items);
            update_post_meta($post_id, '_enquiry_date', current_time('mysql'));

            // Send email notification
            $this->email_handler->send_enquiry_notification($post_id);

            // Clear cart properly
            global $woocommerce;
            $woocommerce->cart->empty_cart();
            $woocommerce->session->set('cart', array());
            
            // Force cart session update
            $woocommerce->cart->persistent_cart_update();
            $woocommerce->session->save_data();

            wp_send_json_success(array(
                'message' => __('Thank you for your enquiry. We will get back to you soon.', 'my-custom-enquiry'),
                'showThankYou' => true
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
}
