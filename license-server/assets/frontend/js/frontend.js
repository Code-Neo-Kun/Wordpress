// Frontend JavaScript
jQuery(document).ready(function($) {
    // Copy license key
    $(document).on('click', '.copy-license-key', function(e) {
        e.preventDefault();
        var key = $(this).data('key');
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(key).select();
        document.execCommand('copy');
        $temp.remove();
        $(this).text('Copied!');
        var $btn = $(this);
        setTimeout(function() {
            $btn.text('Copy License Key');
        }, 2000);
    });

    // Deactivate domain
    $(document).on('click', '.deactivate-domain', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to deactivate this domain?')) {
            return;
        }

        var domain = $(this).data('domain');
        var plugin = $(this).data('plugin');
        var licenseId = $(this).data('license-id');
        var $item = $(this).closest('li');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'deactivate_domain',
                domain: domain,
                plugin: plugin,
                license_id: licenseId,
                nonce: lsFrontendNonce
            },
            success: function(response) {
                if (response.success) {
                    $item.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred');
            }
        });
    });

    // Renew license
    $(document).on('click', '.renew-license', function(e) {
        e.preventDefault();
        var licenseId = $(this).data('license-id');
        var $btn = $(this);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'renew_license',
                license_id: licenseId,
                nonce: lsFrontendNonce
            },
            beforeSend: function() {
                $btn.prop('disabled', true).text('Renewing...');
            },
            success: function(response) {
                if (response.success) {
                    alert('License renewed successfully until ' + response.data.expires_at);
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Renew License');
            }
        });
    });
});
