<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all enquiry posts
$enquiries = get_posts(array(
    'post_type' => 'mce_enquiry',
    'numberposts' => -1,
    'post_status' => 'any'
));

foreach ($enquiries as $enquiry) {
    wp_delete_post($enquiry->ID, true);
}

// Delete plugin options
delete_option('mce_email_recipient');
delete_option('mce_email_subject');
delete_option('mce_form_success_message');
