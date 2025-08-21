jQuery(document).ready(function($) {
    // Handle settings form submission
    $('#mce-settings-form').on('submit', function(e) {
        // Add loading state
        const submitButton = $(this).find('input[type="submit"]');
        submitButton.prop('disabled', true);
    });

    // Handle enquiry deletion confirmation
    $('.mce-delete-enquiry').on('click', function(e) {
        if (!confirm(mceAdmin.deleteConfirmMessage)) {
            e.preventDefault();
        }
    });

    // Handle bulk actions confirmation
    $('#doaction, #doaction2').on('click', function(e) {
        const selectedAction = $(this).prev('select').val();
        if (selectedAction === 'trash' || selectedAction === 'delete') {
            if (!confirm(mceAdmin.bulkDeleteConfirmMessage)) {
                e.preventDefault();
            }
        }
    });

    // Handle settings tabs if any
    $('.mce-settings-tab').on('click', function(e) {
        e.preventDefault();
        const targetTab = $(this).data('tab');
        
        // Update active tab
        $('.mce-settings-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show target tab content
        $('.mce-settings-content').hide();
        $('#' + targetTab).show();
    });
});
