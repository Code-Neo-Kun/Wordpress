// Admin JavaScript
jQuery(document).ready(function($) {
    // Select all checkbox
    $('#select-all').on('change', function() {
        $('input[name="license_ids[]"]').prop('checked', $(this).is(':checked'));
    });

    // Add plugin to license
    $('#add-plugin-btn').on('click', function(e) {
        e.preventDefault();
        var plugin = $('#new-plugin-slug').val();
        if (!plugin) {
            alert('Please enter a plugin slug');
            return;
        }

        // Add to list
        var html = '<div class="ls-plugin-item">' +
            '<span>' + plugin + '</span>' +
            '<a href="#" class="remove-plugin" data-plugin="' + plugin + '">Remove</a>' +
            '</div>';
        $('.ls-plugins-list').append(html);
        $('#new-plugin-slug').val('');
    });

    // Remove plugin
    $(document).on('click', '.remove-plugin', function(e) {
        e.preventDefault();
        $(this).closest('.ls-plugin-item').remove();
    });
});
