(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle teaser read more button
        $(document).on('click', '.fwag-read-more-btn', function(e) {
            e.preventDefault();

            var $button = $(this);
            var postId = $button.data('post-id');
            var $teaserContent = $button.closest('.fwag-teaser-content');
            var $overlay = $button.closest('.fwag-teaser-content').siblings('.fwag-teaser-overlay');

            if (!postId) {
                return;
            }

            // Show loading state
            $button.text(fwagTeaser.loadingText).prop('disabled', true);

            // Check if user is logged in
            if (!fwagTeaser.isLoggedIn) {
                // Show login form
                $overlay.show();
                $button.text($button.data('original-text') || 'Read More').prop('disabled', false);
                return;
            }

            // Load full content via AJAX
            $.ajax({
                url: fwagTeaser.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fwag_load_full_content',
                    post_id: postId,
                    nonce: fwagTeaser.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Replace teaser with full content
                        $teaserContent.html(response.data.content);
                    } else {
                        alert('Error loading content. Please try again.');
                        $button.text($button.data('original-text') || 'Read More').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Error loading content. Please try again.');
                    $button.text($button.data('original-text') || 'Read More').prop('disabled', false);
                }
            });
        });

        // Store original button text
        $('.fwag-read-more-btn').each(function() {
            $(this).data('original-text', $(this).text());
        });

        // Handle login form submission in overlay
        $(document).on('submit', '#fwag-teaser-login', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $submitBtn = $form.find('input[type="submit"]');
            var originalText = $submitBtn.val();

            $submitBtn.val('Logging in...').prop('disabled', true);

            $.ajax({
                url: fwagTeaser.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=fwag_log_access_attempt&action_type=login_attempt',
                success: function(response) {
                    if (response.success) {
                        // Reload the page to show full content
                        window.location.reload();
                    } else {
                        $submitBtn.val(originalText).prop('disabled', false);
                        // Show error message
                        if (!$form.find('.fwag-login-error').length) {
                            $form.prepend('<div class="fwag-login-error" style="color: red; margin-bottom: 10px;">Invalid username or password.</div>');
                        }
                    }
                },
                error: function() {
                    $submitBtn.val(originalText).prop('disabled', false);
                    if (!$form.find('.fwag-login-error').length) {
                        $form.prepend('<div class="fwag-login-error" style="color: red; margin-bottom: 10px;">Login failed. Please try again.</div>');
                    }
                }
            });
        });

        // Close overlay when clicking outside
        $(document).on('click', '.fwag-teaser-overlay', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
    });

})(jQuery);