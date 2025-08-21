<?php
class My_Custom_Enquiry_Data_Migration {
    
    public static function migrate_old_enquiries() {
        // Get all enquiry posts
        $args = array(
            'post_type' => 'mce_enquiry',
            'posts_per_page' => -1,
            'post_status' => 'any'
        );
        
        $enquiries = get_posts($args);
        $migrated = 0;
        
        foreach ($enquiries as $enquiry) {
            $updated = false;
            
            // Check all possible meta key variations
            $meta_keys = array(
                // Without underscore
                'customer_name' => '_customer_name',
                'customer_email' => '_customer_email',
                'customer_phone' => '_customer_phone',
                'customer_message' => '_customer_message',
                'cart_items' => '_cart_items',
                'enquiry_date' => '_enquiry_date',
                'enquiry_status' => '_enquiry_status',
                // With mce prefix
                'mce_customer_name' => '_customer_name',
                'mce_customer_email' => '_customer_email',
                'mce_customer_phone' => '_customer_phone',
                'mce_customer_message' => '_customer_message',
                'mce_cart_items' => '_cart_items',
                // Form data keys
                'name' => '_customer_name',
                'email' => '_customer_email',
                'phone' => '_customer_phone',
                'message' => '_customer_message',
            );
            
            foreach ($meta_keys as $old_key => $new_key) {
                $value = get_post_meta($enquiry->ID, $old_key, true);
                if (!empty($value)) {
                    update_post_meta($enquiry->ID, $new_key, $value);
                    delete_post_meta($enquiry->ID, $old_key);
                    $updated = true;
                }
            }
            
            // If no enquiry date, set it to post date
            if (!get_post_meta($enquiry->ID, '_enquiry_date', true)) {
                update_post_meta($enquiry->ID, '_enquiry_date', $enquiry->post_date);
            }
            
            // If no status, set it to new
            if (!get_post_meta($enquiry->ID, '_enquiry_status', true)) {
                update_post_meta($enquiry->ID, '_enquiry_status', 'new');
            }
            
            if ($updated) {
                $migrated++;
            }
            
            // Debug log the meta data
            error_log('Enquiry #' . $enquiry->ID . ' meta data after migration:');
            error_log('Name: ' . get_post_meta($enquiry->ID, '_customer_name', true));
            error_log('Email: ' . get_post_meta($enquiry->ID, '_customer_email', true));
            error_log('Phone: ' . get_post_meta($enquiry->ID, '_customer_phone', true));
        }
        
        return $migrated;
    }
}
