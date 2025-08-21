<?php
if (!defined('ABSPATH')) exit;

class MCE_Post_Type {
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_meta_fields'));
    }

    public function register_post_type() {
        register_post_type('mce_enquiry', array(
            'labels' => array(
                'name' => __('Enquiries', 'my-custom-enquiry'),
                'singular_name' => __('Enquiry', 'my-custom-enquiry'),
                'menu_name' => __('Enquiries', 'my-custom-enquiry'),
                'add_new' => __('Add New', 'my-custom-enquiry'),
                'add_new_item' => __('Add New Enquiry', 'my-custom-enquiry'),
                'edit_item' => __('Edit Enquiry', 'my-custom-enquiry'),
                'new_item' => __('New Enquiry', 'my-custom-enquiry'),
                'view_item' => __('View Enquiry', 'my-custom-enquiry'),
                'search_items' => __('Search Enquiries', 'my-custom-enquiry'),
                'not_found' => __('No enquiries found', 'my-custom-enquiry'),
                'not_found_in_trash' => __('No enquiries found in trash', 'my-custom-enquiry')
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'capability_type' => 'post',
            'hierarchical' => false,
            'menu_icon' => 'dashicons-email',
            'supports' => array('title'),
            'show_in_rest' => false
        ));
    }

    public function register_meta_fields() {
        register_post_meta('mce_enquiry', 'customer_name', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => false
        ));
        register_post_meta('mce_enquiry', 'customer_email', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => false
        ));
        register_post_meta('mce_enquiry', 'customer_phone', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => false
        ));
        register_post_meta('mce_enquiry', 'customer_message', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => false
        ));
    }
}
