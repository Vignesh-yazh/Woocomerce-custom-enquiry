<?php
if (!defined('ABSPATH')) exit;

// Get cart items
$cart_items = WC()->cart->get_cart();
?>

<div class="mce-enquiry-form-wrapper">
    <?php if (!empty($cart_items)) : ?>
        <form id="mce-enquiry-form" class="mce-enquiry-form" method="post">
            <div id="mce-response-message" class="mce-message" style="display: none;"></div>
            
            <div class="form-row">
                <label for="customer_name"><?php _e('Your Name', 'my-custom-enquiry'); ?> <span class="required">*</span></label>
                <input type="text" name="customer_name" id="customer_name" required>
            </div>

            <div class="form-row">
                <label for="customer_email"><?php _e('Your Email', 'my-custom-enquiry'); ?> <span class="required">*</span></label>
                <input type="email" name="customer_email" id="customer_email" required>
            </div>

            <div class="form-row">
                <label for="customer_phone"><?php _e('Phone Number', 'my-custom-enquiry'); ?></label>
                <input type="tel" name="customer_phone" id="customer_phone">
            </div>

            <div class="form-row">
                <label for="customer_message"><?php _e('Your Message', 'my-custom-enquiry'); ?> <span class="required">*</span></label>
                <textarea name="customer_message" id="customer_message" rows="5" required></textarea>
            </div>

            <?php wp_nonce_field('mce_enquiry_nonce', 'mce_nonce'); ?>

            <div class="form-row submit-row">
                <button type="submit" class="button"><?php _e('Submit Enquiry', 'my-custom-enquiry'); ?></button>
            </div>
        </form>
    <?php else : ?>
        <p class="mce-empty-cart-message">
            <?php _e('Your cart is empty. Please add some products before submitting an enquiry.', 'my-custom-enquiry'); ?>
        </p>
        <p>
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="button">
                <?php _e('Continue Shopping', 'my-custom-enquiry'); ?>
            </a>
        </p>
    <?php endif; ?>
</div>
