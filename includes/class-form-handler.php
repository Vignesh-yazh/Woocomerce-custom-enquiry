<?php
class MCE_Form_Handler {
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    public function init() {
        add_filter('mce_form_validation', array($this, 'validate_form'), 10, 1);
    }

    public function validate_form($data) {
        $errors = array();

        // Validate name
        if (empty($data['customer_name'])) {
            $errors[] = __('Name is required', 'my-custom-enquiry');
        }

        // Validate email
        if (empty($data['customer_email'])) {
            $errors[] = __('Email is required', 'my-custom-enquiry');
        } elseif (!is_email($data['customer_email'])) {
            $errors[] = __('Please enter a valid email address', 'my-custom-enquiry');
        }

        // Validate message
        if (empty($data['customer_message'])) {
            $errors[] = __('Message is required', 'my-custom-enquiry');
        }

        return $errors;
    }

    public function sanitize_form_data($data) {
        return array(
            'customer_name' => sanitize_text_field($data['customer_name']),
            'customer_email' => sanitize_email($data['customer_email']),
            'customer_phone' => sanitize_text_field($data['customer_phone']),
            'customer_message' => sanitize_textarea_field($data['customer_message'])
        );
    }
}
