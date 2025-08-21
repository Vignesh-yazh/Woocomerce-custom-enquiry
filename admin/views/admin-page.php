<div class="wrap">
    <h1><?php _e('Enquiry Settings', 'my-custom-enquiry'); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('mce_settings');
        do_settings_sections('mce-settings');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="mce_email_recipient">
                        <?php _e('Notification Email', 'my-custom-enquiry'); ?>
                    </label>
                </th>
                <td>
                    <input type="email" 
                           id="mce_email_recipient" 
                           name="mce_email_recipient" 
                           value="<?php echo esc_attr(get_option('mce_email_recipient', get_option('admin_email'))); ?>" 
                           class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="mce_email_subject">
                        <?php _e('Email Subject', 'my-custom-enquiry'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" 
                           id="mce_email_subject" 
                           name="mce_email_subject" 
                           value="<?php echo esc_attr(get_option('mce_email_subject', __('New Enquiry Received', 'my-custom-enquiry'))); ?>" 
                           class="regular-text">
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
</div>
