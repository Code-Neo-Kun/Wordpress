(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle file protection toggle in media library
        $(document).on('change', '.fwag-file-protection-toggle', function() {
            var $checkbox = $(this);
            var fileId = $checkbox.data('file-id');
            var protect = $checkbox.is(':checked');

            $checkbox.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fwag_toggle_file_protection',
                    file_id: fileId,
                    protect: protect,
                    nonce: fwagFileProtection ? fwagFileProtection.nonce : ''
                },
                success: function(response) {
                    $checkbox.prop('disabled', false);

                    if (response.success) {
                        // Update UI
                        var $row = $checkbox.closest('tr');
                        if (protect) {
                            $row.addClass('fwag-protected-file');
                        } else {
                            $row.removeClass('fwag-protected-file');
                        }
                    } else {
                        alert(response.data.message || 'Error updating file protection.');
                        $checkbox.prop('checked', !protect);
                    }
                },
                error: function() {
                    $checkbox.prop('disabled', false);
                    alert('Error updating file protection.');
                    $checkbox.prop('checked', !protect);
                }
            });
        });

        // Add protection status to media library items
        if (wp.media) {
            var originalAttachmentDetails = wp.media.view.Attachment.Details;

            wp.media.view.Attachment.Details = originalAttachmentDetails.extend({
                render: function() {
                    originalAttachmentDetails.prototype.render.apply(this, arguments);

                    var attachment = this.model;
                    var $el = this.$el;

                    // Check if file is protected
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fwag_check_file_protection',
                            file_id: attachment.get('id')
                        },
                        success: function(response) {
                            if (response.protected) {
                                $el.find('.attachment-info').append('<div class="fwag-protection-status" style="color: #d63638; font-weight: bold; margin-top: 10px;">🔒 Protected File</div>');
                            }
                        }
                    });
                }
            });
        }
    });

})(jQuery);