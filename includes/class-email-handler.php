<?php
if (!defined('ABSPATH')) exit;

class MCE_Email_Handler {
    private $from_email;
    private $from_name;
    private $recipient;
    private $security;

    public function __construct() {
        $this->from_email = get_option('admin_email');
        $this->from_name = get_bloginfo('name');
        $this->recipient = get_option('mce_email_recipient', get_option('admin_email'));
        $this->security = MCE_Security::get_instance();
        
        // Add email filters
        add_filter('wp_mail_from', array($this, 'get_from_email'));
        add_filter('wp_mail_from_name', array($this, 'get_from_name'));
        add_filter('wp_mail_content_type', array($this, 'get_content_type'));

        // Add SPF/DKIM notice
        add_action('admin_notices', array($this, 'spf_dkim_notice'));
    }

    public function spf_dkim_notice() {
        $screen = get_current_screen();
        if ($screen->id === 'settings_page_mce-settings') {
            $message = sprintf(
                __('To improve email deliverability, please configure SPF and DKIM records for your domain. <a href="%s" target="_blank">Learn more</a>', 'my-custom-enquiry'),
                'https://www.mailgun.com/blog/email-authentication-explained-spf-dkim-records/'
            );
            echo '<div class="notice notice-info is-dismissible"><p>' . wp_kses_post($message) . '</p></div>';
        }
    }

    public function get_from_email() {
        return $this->from_email;
    }

    public function get_from_name() {
        return $this->from_name;
    }

    public function get_content_type() {
        return 'text/html';
    }

    public function send_enquiry_notification($post_id) {
        // Send admin notification
        $this->send_admin_notification($post_id);
        
        // Send customer confirmation
        $this->send_customer_notification($post_id);
        
        return true;
    }

    private function send_admin_notification($post_id) {
        $to = get_option('admin_email');
        $subject = sprintf(__('New Enquiry from %s', 'my-custom-enquiry'), get_post_meta($post_id, '_customer_name', true));

        $customer_name = get_post_meta($post_id, '_customer_name', true);
        $customer_email = get_post_meta($post_id, '_customer_email', true);
        $customer_phone = get_post_meta($post_id, '_customer_phone', true);
        $customer_message = get_post_meta($post_id, '_customer_message', true);
        $cart_items = get_post_meta($post_id, '_cart_items', true);

        // Build HTML email
        $message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $message .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
        $message .= '<h2 style="color: #0073aa;">New Enquiry Received</h2>';
        
        // Customer Details Table
        $message .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
        $message .= '<tr><th colspan="2" style="background: #f8f9fa; padding: 10px; text-align: left; border: 1px solid #dee2e6;">Customer Details</th></tr>';
        $message .= sprintf('<tr><td style="padding: 10px; border: 1px solid #dee2e6; width: 30%%;">Name</td><td style="padding: 10px; border: 1px solid #dee2e6;">%s</td></tr>', esc_html($customer_name));
        $message .= sprintf('<tr><td style="padding: 10px; border: 1px solid #dee2e6;">Email</td><td style="padding: 10px; border: 1px solid #dee2e6;">%s</td></tr>', esc_html($customer_email));
        if (!empty($customer_phone)) {
            $message .= sprintf('<tr><td style="padding: 10px; border: 1px solid #dee2e6;">Phone</td><td style="padding: 10px; border: 1px solid #dee2e6;">%s</td></tr>', esc_html($customer_phone));
        }
        $message .= '</table>';

        // Customer Message
        $message .= '<div style="margin-bottom: 20px;">';
        $message .= '<h3 style="color: #0073aa;">Customer Message</h3>';
        $message .= sprintf('<div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">%s</div>', nl2br(esc_html($customer_message)));
        $message .= '</div>';

        // Products Table
        $message .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
        $message .= '<tr><th colspan="3" style="background: #f8f9fa; padding: 10px; text-align: left; border: 1px solid #dee2e6;">Products</th></tr>';
        $message .= '<tr>';
        $message .= '<th style="padding: 10px; border: 1px solid #dee2e6;">Product</th>';
        $message .= '<th style="padding: 10px; border: 1px solid #dee2e6;">Quantity</th>';
        $message .= '<th style="padding: 10px; border: 1px solid #dee2e6;">SKU</th>';
        $message .= '</tr>';
        
        if (is_array($cart_items)) {
            foreach ($cart_items as $item) {
                $message .= '<tr>';
                $message .= sprintf('<td style="padding: 10px; border: 1px solid #dee2e6;">%s</td>', esc_html($item['name']));
                $message .= sprintf('<td style="padding: 10px; border: 1px solid #dee2e6;">%d</td>', $item['quantity']);
                $message .= sprintf('<td style="padding: 10px; border: 1px solid #dee2e6;">%s</td>', !empty($item['sku']) ? esc_html($item['sku']) : '-');
                $message .= '</tr>';
            }
        }
        $message .= '</table>';

        // Admin Link
        $message .= sprintf('<p><a href="%s" style="background: #0073aa; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 4px;">View Enquiry Details</a></p>', 
            admin_url('post.php?post=' . $post_id . '&action=edit')
        );

        $message .= '</div></body></html>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            'Reply-To: ' . $customer_name . ' <' . $customer_email . '>'
        );

        return wp_mail($to, $subject, $message, $headers);
    }

    private function send_customer_notification($post_id) {
        $customer_name = get_post_meta($post_id, '_customer_name', true);
        $customer_email = get_post_meta($post_id, '_customer_email', true);
        $cart_items = get_post_meta($post_id, '_cart_items', true);

        $subject = sprintf(__('Your Enquiry Confirmation - %s', 'my-custom-enquiry'), get_bloginfo('name'));

        // Build HTML email
        $message = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        $message .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
        
        $message .= sprintf('<h2 style="color: #0073aa;">Thank you for your enquiry, %s!</h2>', esc_html($customer_name));
        $message .= '<p>We have received your enquiry and will get back to you shortly.</p>';

        // Products Table
        $message .= '<h3 style="color: #0073aa;">Enquiry Details</h3>';
        $message .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
        $message .= '<tr><th colspan="2" style="background: #f8f9fa; padding: 10px; text-align: left; border: 1px solid #dee2e6;">Products</th></tr>';
        
        if (is_array($cart_items)) {
            foreach ($cart_items as $item) {
                $message .= '<tr>';
                $message .= sprintf('<td style="padding: 10px; border: 1px solid #dee2e6;">%s</td>', esc_html($item['name']));
                $message .= sprintf('<td style="padding: 10px; border: 1px solid #dee2e6;">Qty: %d</td>', $item['quantity']);
                $message .= '</tr>';
            }
        }
        $message .= '</table>';

        $message .= '<p>We will review your enquiry and contact you soon.</p>';
        $message .= sprintf('<p>Best regards,<br>%s Team</p>', get_bloginfo('name'));

        $message .= '</div></body></html>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        return wp_mail($customer_email, $subject, $message, $headers);
    }
}
