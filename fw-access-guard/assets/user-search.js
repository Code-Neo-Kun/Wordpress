(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize Select2 for user selects if available
        if ($.fn.select2) {
            $('.fwag-user-select').select2({
                placeholder: fwagUserSearch.searchPlaceholder,
                allowClear: true,
                ajax: {
                    url: fwagUserSearch.ajaxUrl,
                    dataType: 'json',
                    delay: 300,
                    data: function (params) {
                        return {
                            action: 'fwag_search_users',
                            search: params.term,
                            nonce: fwagUserSearch.nonce,
                            exclude: $(this).val() || []
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 2,
                escapeMarkup: function (markup) {
                    return markup;
                },
                templateResult: function (user) {
                    if (user.loading) return user.text;
                    return user.text;
                },
                templateSelection: function (user) {
                    return user.text || user.display_name;
                }
            });
        }

        // Fallback for browsers without Select2
        if (!$.fn.select2) {
            $('.fwag-user-select').each(function() {
                var $select = $(this);
                var $searchInput = $('<input type="text" class="fwag-user-search-input" placeholder="' + fwagUserSearch.searchPlaceholder + '" style="width: 100%; margin-bottom: 5px;">');
                var $results = $('<div class="fwag-user-search-results" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; display: none;"></div>');

                $select.hide().after($searchInput).after($results);

                var searchTimeout;

                $searchInput.on('input', function() {
                    var query = $(this).val().trim();

                    clearTimeout(searchTimeout);
                    if (query.length < 2) {
                        $results.hide();
                        return;
                    }

                    searchTimeout = setTimeout(function() {
                        $.ajax({
                            url: fwagUserSearch.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'fwag_search_users',
                                search: query,
                                nonce: fwagUserSearch.nonce,
                                exclude: $select.val() || []
                            },
                            success: function(response) {
                                $results.empty();

                                if (response.length === 0) {
                                    $results.append('<div style="padding: 10px;">' + fwagUserSearch.noUsersFound + '</div>');
                                } else {
                                    $.each(response, function(index, user) {
                                        var $userItem = $('<div class="fwag-user-item" style="padding: 8px; cursor: pointer; border-bottom: 1px solid #eee;" data-user-id="' + user.id + '">' + user.text + '</div>');
                                        $userItem.on('click', function() {
                                            var userId = $(this).data('user-id');
                                            var userText = $(this).text();

                                            // Add to select
                                            if ($select.find('option[value="' + userId + '"]').length === 0) {
                                                $select.append('<option value="' + userId + '" selected>' + userText + '</option>');
                                            }

                                            // Clear search
                                            $searchInput.val('');
                                            $results.hide();

                                            // Update display
                                            updateSelectedUsers($select);
                                        });
                                        $results.append($userItem);
                                    });
                                }

                                $results.show();
                            }
                        });
                    }, 300);
                });

                // Update display of selected users
                function updateSelectedUsers($select) {
                    var $selectedDisplay = $('<div class="fwag-selected-users" style="margin-top: 10px;"></div>');
                    $select.siblings('.fwag-selected-users').remove();

                    $select.find('option:selected').each(function() {
                        var $userTag = $('<span class="fwag-user-tag" style="display: inline-block; background: #f0f0f0; padding: 4px 8px; margin: 2px; border-radius: 3px;">' + $(this).text() + ' <span class="fwag-remove-user" style="cursor: pointer; color: #999;" data-user-id="' + $(this).val() + '">×</span></span>');
                        $selectedDisplay.append($userTag);
                    });

                    $select.after($selectedDisplay);
                }

                // Remove user
                $(document).on('click', '.fwag-remove-user', function() {
                    var userId = $(this).data('user-id');
                    $select.find('option[value="' + userId + '"]').remove();
                    $(this).closest('.fwag-user-tag').remove();
                });

                // Initialize display
                updateSelectedUsers($select);
            });
        }
    });

})(jQuery);