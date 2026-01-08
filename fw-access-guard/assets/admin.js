(function($) {
    'use strict';

    // FWAG Admin UI Enhancements
    const FWAG_Admin = {
        init: function() {
            this.setupMediaUploader();
            this.setupMetaboxToggles();
            this.setupFormValidation();
            this.setupFeatureOptions();
            this.setupHelpTooltips();
            this.setupLivePreview();
        },

        // Media uploader for logo
        setupMediaUploader: function() {
            var mediaUploader;

            $('#fwag_upload_logo').on('click', function(e) {
                e.preventDefault();

                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                mediaUploader = wp.media({
                    title: 'Choose Logo',
                    button: {
                        text: 'Use this logo'
                    },
                    multiple: false
                });

                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#fwag_logo').val(attachment.id);
                    $('#fwag_logo_preview').html('<img src="' + attachment.url + '" alt="Logo" class="fwag-logo-preview">');
                    $('#fwag_remove_logo').show();
                });

                mediaUploader.open();
            });

            $('#fwag_remove_logo').on('click', function(e) {
                e.preventDefault();
                $('#fwag_logo').val('');
                $('#fwag_logo_preview').empty();
                $(this).hide();
            });
        },

        // Enhanced metabox toggle functionality
        setupMetaboxToggles: function() {
            // Base protection toggle
            $('input[name="fwag_override_enabled"]').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#fwag_protection_option').slideDown(300);
                } else {
                    $('#fwag_protection_option').slideUp(300);
                }
            });

            // Teaser toggle
            $('input[name="fwag_teaser_enabled"]').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#fwag_teaser_options').slideDown(300);
                } else {
                    $('#fwag_teaser_options').slideUp(300);
                }
            });

            // Time restrictions toggle
            $('input[name="fwag_time_restrictions_enabled"]').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#fwag_time_options').slideDown(300);
                } else {
                    $('#fwag_time_options').slideUp(300);
                }
            });

            // User restrictions toggle
            $('input[name="fwag_user_restrictions_enabled"]').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#fwag_user_options').slideDown(300);
                } else {
                    $('#fwag_user_options').slideUp(300);
                }
            });

            // User restriction type switcher
            $('input[name="fwag_user_restriction_type"]').on('change', function() {
                if ($(this).val() === 'whitelist') {
                    $('#fwag_allowed_users_section').show(200);
                    $('#fwag_blocked_users_section').hide(200);
                } else {
                    $('#fwag_allowed_users_section').hide(200);
                    $('#fwag_blocked_users_section').show(200);
                }
            });
        },

        // Form validation
        setupFormValidation: function() {
            $('form').on('submit', function(e) {
                var isValid = true;

                // Validate protected pages input
                var protectedPages = $('#fwag_protected_pages').val();
                if (protectedPages && !FWAG_Admin.validatePageIds(protectedPages)) {
                    alert('Please enter valid page IDs separated by commas (e.g., 1,2,3)');
                    isValid = false;
                }

                return isValid;
            });
        },

        validatePageIds: function(input) {
            var ids = input.split(',').map(function(id) {
                return id.trim();
            });
            return ids.every(function(id) {
                return /^\d+$/.test(id);
            });
        },

        // Feature options visibility
        setupFeatureOptions: function() {
            // Show/hide feature-specific option panels
            $('.fwag-feature-toggle input[type="checkbox"]').on('change', function() {
                var optionsPanel = $(this).closest('.fwag-metabox-section').find('.fwag-feature-options');
                if ($(this).is(':checked')) {
                    optionsPanel.addClass('open');
                } else {
                    optionsPanel.removeClass('open');
                }
            });
        },

        // Help tooltips
        setupHelpTooltips: function() {
            $('.fwag-help-icon').each(function() {
                var helpText = $(this).data('help');
                if (helpText) {
                    $(this).attr('title', helpText);
                }
            });
        },

        // Live preview for overlay settings
        setupLivePreview: function() {
            // Monitor relevant input fields
            $(document).on('change input', 
                '#fwag_overlay_title, #fwag_overlay_message, #fwag_button_label, #fwag_blur_level, #fwag_overlay_opacity',
                function() {
                    FWAG_Admin.updatePreview();
                }
            );
        },

        updatePreview: function() {
            var title = $('#fwag_overlay_title').val() || 'Access Restricted';
            var message = $('#fwag_overlay_message').val() || 'This content is restricted.';
            var button = $('#fwag_button_label').val() || 'Log In';
            var blur = $('#fwag_blur_level').val() || 5;
            var opacity = $('#fwag_overlay_opacity').val() || 0.95;

            // Update preview if preview panel exists
            var preview = $('#fwag-overlay-preview');
            if (preview.length) {
                preview.find('.overlay-title').text(title);
                preview.find('.overlay-message').text(message);
                preview.find('.overlay-button').text(button);
                preview.css({
                    '--fwag-blur-level': blur + 'px',
                    '--fwag-overlay-opacity': opacity
                });
            }
        }
    };

    $(document).ready(function() {
        FWAG_Admin.init();

        $('.fwag-checkbox-item').on('mouseenter', function() {
            $(this).addClass('hover');
        }).on('mouseleave', function() {
            $(this).removeClass('hover');
        });

        // Enhanced form validation feedback
        $('input[type="number"]').on('input', function() {
            var val = parseFloat($(this).val());
            var min = parseFloat($(this).attr('min'));
            var max = parseFloat($(this).attr('max'));

            if (val < min || val > max) {
                $(this).addClass('invalid');
            } else {
                $(this).removeClass('invalid');
            }
        });

        // Auto-save draft functionality for settings
        var autoSaveTimer;
        $('form[action="options.php"]').on('input change', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                // Could implement auto-save here if needed
                console.log('Settings changed - ready to save');
            }, 1000);
        });
    });

})(jQuery);