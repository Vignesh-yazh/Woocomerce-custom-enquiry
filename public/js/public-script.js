jQuery(document).ready(function($) {
    'use strict';

    // Check if we're on cart page
    if (!$('#mce-show-enquiry-form').length) {
        return;
    }

    // Toggle enquiry form
    $('#mce-show-enquiry-form').on('click', function() {
        if ($('.mce-thank-you-message').length) {
            return; // Don't toggle if thank you message is showing
        }
        $('#mce-cart-enquiry-form-wrapper').slideToggle();
    });

    // Handle enquiry form submission
    $(document).on('submit', '#mce-enquiry-form', function(e) {
        e.preventDefault();

        // Check if mceAjax is defined
        if (typeof mceAjax === 'undefined') {
            console.error('mceAjax not defined');
            return;
        }

        var $form = $(this);
        var $formWrapper = $('#mce-cart-enquiry-form-wrapper');
        var $submitButton = $form.find('button[type="submit"]');
        var $responseDiv = $('#mce-response-message');
        var $enquiryButton = $('#mce-show-enquiry-form');
        var $cartForm = $('.woocommerce-cart-form');
        var $cartTotals = $('.cart_totals');
        
        // If form is already processing, return
        if ($submitButton.prop('disabled')) {
            return;
        }
        
        // Clear previous messages
        $responseDiv.removeClass('error success loading').empty().show();
        
        // Show loading message
        $responseDiv.addClass('loading').html('<div class="loading">Processing your enquiry...</div>');
        
        // Disable submit button and show loading
        $submitButton.prop('disabled', true);
        var originalButtonText = $submitButton.html();
        $submitButton.html('<span class="spinner"></span> ' + (mceAjax.processing || 'Processing...'));

        // Get form data
        var formData = new FormData(this);
        formData.append('action', 'submit_cart_enquiry');
        formData.append('nonce', mceAjax.nonce);

        // Make AJAX request
        $.ajax({
            url: mceAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-WP-Nonce': mceAjax.nonce
            },
            success: function(response) {
                console.log('Response:', response);
                
                if (response.success) {
                    // Hide cart elements with fade effect
                    $cartForm.fadeOut(400);
                    $cartTotals.fadeOut(400);
                    $form.fadeOut(400);
                    $enquiryButton.fadeOut(400);

                    // Show thank you message
                    var thankYouHtml = '<div class="mce-thank-you-message">';
                    thankYouHtml += '<div class="mce-thank-you-icon">✓</div>';
                    thankYouHtml += '<h3>' + response.data.message + '</h3>';
                    thankYouHtml += '<div class="mce-thank-you-details">';
                    thankYouHtml += '<p>Your enquiry has been successfully submitted and your cart has been cleared.</p>';
                    thankYouHtml += '<p>We will review your enquiry and get back to you shortly via email.</p>';
                    thankYouHtml += '</div>';
                    thankYouHtml += '<div class="mce-thank-you-actions">';
                    thankYouHtml += '<a href="' + mceAjax.shopUrl + '" class="button continue-shopping">Continue Shopping</a>';
                    thankYouHtml += '</div>';
                    thankYouHtml += '</div>';
                    
                    // Show thank you message with fade in effect
                    $formWrapper.hide().html(thankYouHtml).fadeIn(400);

                    // Refresh cart fragments
                    $(document.body).trigger('wc_fragment_refresh');
                } else {
                    var errorMessage = response.data ? response.data.message : (mceAjax.error || 'An error occurred');
                    $responseDiv.removeClass('success loading')
                              .addClass('error')
                              .html(errorMessage);
                    
                    // Re-enable submit button
                    $submitButton.prop('disabled', false)
                               .html(originalButtonText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', xhr.responseText);
                $responseDiv.removeClass('success loading')
                          .addClass('error')
                          .html(mceAjax.error || 'An error occurred. Please try again.');
                
                // Re-enable submit button
                $submitButton.prop('disabled', false)
                           .html(originalButtonText);
            }
        });
    });

    // Add to enquiry cart functionality
    $(document).on('click', '.add-to-enquiry-cart', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        
        // If button is already processing, return
        if ($button.prop('disabled')) {
            return;
        }
        
        var productId = $button.data('product-id');
        var $quantityInput = $button.closest('form').find('input[name="quantity"]');
        var quantity = $quantityInput.length ? parseInt($quantityInput.val()) : 1;
        
        if (isNaN(quantity) || quantity < 1) {
            quantity = 1;
        }
        
        // Disable button and show loading state
        $button.prop('disabled', true);
        var originalText = $button.html();
        $button.html('<span class="spinner"></span> ' + mceAjax.processing);
        
        var data = {
            action: 'add_to_enquiry_cart',
            product_id: productId,
            quantity: quantity,
            nonce: mceAjax.nonce
        };
        
        $.ajax({
            url: mceAjax.ajaxurl,
            type: 'POST',
            data: data,
            headers: {
                'X-WP-Nonce': mceAjax.nonce
            },
            success: function(response) {
                console.log('Add to cart response:', response); // Debug log
                
                if (response.success) {
                    // Update cart count if available
                    if (response.data.cart_count !== undefined) {
                        $('.mce-cart-count').text(response.data.cart_count);
                    }
                    
                    // Show success message
                    var $message = $('<div class="mce-message success" />')
                        .text(response.data.message)
                        .insertAfter($button)
                        .fadeIn();
                } else {
                    // Show error message
                    var errorMessage = response.data ? response.data.message : mceAjax.error;
                    var $message = $('<div class="mce-message error" />')
                        .text(errorMessage)
                        .insertAfter($button)
                        .fadeIn();
                }
                
                // Remove message after delay
                setTimeout(function() {
                    $message.fadeOut(function() {
                        $(this).remove();
                    });
                }, 3000);
                
            },
            error: function(xhr, status, error) {
                console.error('Add to cart AJAX Error:', status, error); // Debug log
                
                // Show error message
                var $message = $('<div class="mce-message error" />')
                    .text(mceAjax.error)
                    .insertAfter($button)
                    .fadeIn();
                    
                setTimeout(function() {
                    $message.fadeOut(function() {
                        $(this).remove();
                    });
                }, 3000);
                
            },
            complete: function() {
                // Restore button state
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
});
