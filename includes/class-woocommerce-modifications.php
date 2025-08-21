<?php
if (!defined('ABSPATH')) exit;

class MCE_WooCommerce_Modifications {
    public function __construct() {
        // Remove prices
        add_filter('woocommerce_get_price_html', array($this, 'remove_price'), 100, 2);
        add_filter('woocommerce_variable_sale_price_html', array($this, 'remove_price'), 100, 2);
        add_filter('woocommerce_variable_price_html', array($this, 'remove_price'), 100, 2);
        add_filter('woocommerce_get_variation_price_html', array($this, 'remove_price'), 100, 2);
        
        // Remove ALL default Add to Cart buttons and related elements
        remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        remove_action('woocommerce_simple_add_to_cart', 'woocommerce_simple_add_to_cart', 30);
        remove_action('woocommerce_grouped_add_to_cart', 'woocommerce_grouped_add_to_cart', 30);
        remove_action('woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30);
        remove_action('woocommerce_external_add_to_cart', 'woocommerce_external_add_to_cart', 30);

        // Add our Enquiry buttons
        add_action('woocommerce_after_shop_loop_item', array($this, 'add_enquiry_button_loop'), 10);
        add_action('woocommerce_single_product_summary', array($this, 'add_enquiry_button_single'), 30);
        
        // Modify cart page
        remove_action('woocommerce_cart_collaterals', 'woocommerce_cart_totals', 10);
        add_action('woocommerce_after_cart', array($this, 'add_enquiry_form_cart'), 10);
        add_action('woocommerce_before_cart', array($this, 'hide_cart_totals_css'));
        
        // Redirect checkout to cart
        add_action('template_redirect', array($this, 'redirect_checkout'));
        add_action('template_redirect', array($this, 'redirect_to_cart'));
        
        // Remove coupon form and related elements
        remove_action('woocommerce_before_cart', 'woocommerce_output_all_notices', 10);
        remove_action('woocommerce_before_cart_table', 'woocommerce_output_all_notices', 10);
        remove_action('woocommerce_before_cart_contents', 'woocommerce_output_all_notices', 10);
        remove_action('woocommerce_cart_coupon', 'woocommerce_checkout_coupon_form');
        add_filter('woocommerce_coupons_enabled', '__return_false');
        
        // Remove prices from cart
        add_filter('woocommerce_cart_item_price', '__return_empty_string');
        add_filter('woocommerce_cart_item_subtotal', '__return_empty_string');
        add_filter('woocommerce_cart_subtotal', '__return_empty_string');
        add_filter('woocommerce_cart_total', '__return_empty_string');

        // Remove proceed to checkout button and related elements
        remove_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
        remove_action('woocommerce_cart_collaterals', 'woocommerce_cart_totals', 10);

        // Hide cart totals and other elements
        add_action('wp_head', array($this, 'hide_cart_totals_css'));

        // Disable checkout
        add_action('template_redirect', array($this, 'redirect_checkout'));

        // Add hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function remove_price($price, $product) {
        return '';
    }

    public function add_enquiry_button_loop() {
        global $product;
        if (!$product) return;

        echo '<a href="' . esc_url('?add-to-cart=' . $product->get_id()) . '" 
                 data-quantity="1" 
                 class="button mce-enquiry-button add_to_cart_button ajax_add_to_cart" 
                 data-product_id="' . esc_attr($product->get_id()) . '" 
                 aria-label="' . esc_attr__('Add to enquiry', 'my-custom-enquiry') . '">' . 
             esc_html__('Add to Enquiry', 'my-custom-enquiry') . '</a>';
    }

    public function add_enquiry_button_single() {
        global $product;
        if (!$product) return;

        // For variable products
        if ($product->is_type('variable')) {
            ?>
            <form class="cart" method="post" enctype="multipart/form-data">
                <?php
                do_action('woocommerce_before_variations_form');

                if (empty($product->get_available_variations()) && false !== $product->get_available_variations()) {
                    ?>
                    <p class="stock out-of-stock"><?php echo esc_html__('This product is currently out of stock and unavailable.', 'my-custom-enquiry'); ?></p>
                    <?php
                } else {
                    ?>
                    <table class="variations" cellspacing="0" role="presentation">
                        <tbody>
                            <?php foreach ($product->get_variation_attributes() as $attribute_name => $options) : ?>
                                <tr>
                                    <td class="label"><label for="<?php echo esc_attr(sanitize_title($attribute_name)); ?>"><?php echo wc_attribute_label($attribute_name); ?></label></td>
                                    <td class="value">
                                        <?php
                                        wc_dropdown_variation_attribute_options(array(
                                            'options'   => $options,
                                            'attribute' => $attribute_name,
                                            'product'   => $product,
                                        ));
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php do_action('woocommerce_before_add_to_cart_button'); ?>

                    <div class="single_variation_wrap">
                        <?php
                        do_action('woocommerce_before_single_variation');
                        do_action('woocommerce_single_variation');
                        ?>
                        <div class="woocommerce-variation-add-to-cart variations_button">
                            <?php
                            woocommerce_quantity_input(array(
                                'min_value'   => apply_filters('woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product),
                                'max_value'   => apply_filters('woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product),
                                'input_value' => isset($_POST['quantity']) ? wc_stock_amount(wp_unslash($_POST['quantity'])) : $product->get_min_purchase_quantity(),
                            ));
                            ?>
                            <button type="submit" class="single_add_to_cart_button button mce-enquiry-button">
                                <?php echo esc_html__('Add to Enquiry', 'my-custom-enquiry'); ?>
                            </button>
                            <input type="hidden" name="add-to-cart" value="<?php echo absint($product->get_id()); ?>" />
                            <input type="hidden" name="product_id" value="<?php echo absint($product->get_id()); ?>" />
                            <input type="hidden" name="variation_id" class="variation_id" value="0" />
                        </div>
                        <?php
                        do_action('woocommerce_after_single_variation');
                        ?>
                    </div>

                    <?php do_action('woocommerce_after_add_to_cart_button'); ?>
                <?php } ?>

                <?php do_action('woocommerce_after_variations_form'); ?>
            </form>
            <?php
        } else {
            // For simple products
            ?>
            <form class="cart" method="post" enctype="multipart/form-data">
                <?php do_action('woocommerce_before_add_to_cart_button'); ?>
                
                <?php
                if ($product->is_in_stock()) {
                    do_action('woocommerce_before_add_to_cart_quantity');
                    
                    woocommerce_quantity_input(array(
                        'min_value'   => apply_filters('woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product),
                        'max_value'   => apply_filters('woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product),
                        'input_value' => isset($_POST['quantity']) ? wc_stock_amount(wp_unslash($_POST['quantity'])) : $product->get_min_purchase_quantity(),
                    ));
                    
                    do_action('woocommerce_after_add_to_cart_quantity');
                    ?>
                    
                    <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" class="single_add_to_cart_button button mce-enquiry-button">
                        <?php echo esc_html__('Add to Enquiry', 'my-custom-enquiry'); ?>
                    </button>
                    
                    <?php
                }
                do_action('woocommerce_after_add_to_cart_button');
                ?>
            </form>
            <?php
        }
    }

    public function redirect_checkout() {
        if (is_checkout()) {
            wp_redirect(wc_get_cart_url());
            exit;
        }
    }

    public function add_enquiry_form_cart() {
        // Don't add form if cart is empty
        if (WC()->cart->is_empty()) {
            return;
        }
        ?>
        <div class="mce-cart-enquiry-section">
            <button type="button" class="button alt" id="mce-show-enquiry-form">
                <?php _e('Make an Enquiry', 'my-custom-enquiry'); ?>
            </button>
            
            <div id="mce-cart-enquiry-form-wrapper" style="display: none;">
                <?php include plugin_dir_path(dirname(__FILE__)) . 'public/views/enquiry-form.php'; ?>
            </div>
        </div>
        <?php
    }

    public function redirect_to_cart() {
        return wc_get_cart_url();
    }

    public function hide_cart_totals_css() {
        if (is_cart()) {
            ?>
            <style type="text/css">
                .cart-subtotal, 
                .order-total,
                .cart-collaterals .cart_totals,
                .coupon,
                .woocommerce-form-coupon-toggle,
                .cart_totals,
                .woocommerce-shipping-calculator,
                .shipping-calculator-button,
                .woocommerce-shipping-totals {
                    display: none !important;
                }
            </style>
            <?php
        }
    }

    public function enqueue_scripts() {
        if (!is_cart()) {
            return;
        }

        wp_enqueue_style(
            'mce-public-style',
            plugin_dir_url(dirname(__FILE__)) . 'public/css/public-style.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'mce-public-script',
            plugin_dir_url(dirname(__FILE__)) . 'public/js/public-script.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script(
            'mce-public-script',
            'mceAjax',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mce_cart_enquiry_nonce'),
                'processing' => __('Processing...', 'my-custom-enquiry'),
                'error' => __('An error occurred. Please try again.', 'my-custom-enquiry'),
                'shopUrl' => wc_get_page_permalink('shop'),
                'cartUrl' => wc_get_cart_url()
            )
        );
    }
}
