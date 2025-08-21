<?php
class MCE_Export_Import {
    public function export_enquiries() {
        $enquiries = get_posts(array(
            'post_type' => 'mce_enquiry',
            'posts_per_page' => -1
        ));
        
        $data = array();
        foreach ($enquiries as $enquiry) {
            $data[] = array(
                'id' => $enquiry->ID,
                'title' => $enquiry->post_title,
                'date' => $enquiry->post_date,
                'meta' => get_post_meta($enquiry->ID)
            );
        }
        
        return json_encode($data);
    }
    
    public function import_enquiries($json_data) {
        $data = json_decode($json_data, true);
        if (!is_array($data)) return false;
        
        foreach ($data as $enquiry) {
            $post_data = array(
                'post_title' => $enquiry['title'],
                'post_type' => 'mce_enquiry',
                'post_date' => $enquiry['date'],
                'post_status' => 'publish'
            );
            
            $post_id = wp_insert_post($post_data);
            if ($post_id) {
                foreach ($enquiry['meta'] as $key => $value) {
                    update_post_meta($post_id, $key, $value[0]);
                }
            }
        }
        
        return true;
    }
}
