(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize datepickers for time restrictions
        if ($.fn.datepicker) {
            $('.fwag-datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: 0,
                changeMonth: true,
                changeYear: true
            });
        }

        // Time validation
        function validateTimeRestrictions() {
            var $startDate = $('input[name="fwag_start_date"]');
            var $endDate = $('input[name="fwag_end_date"]');
            var $startTime = $('input[name="fwag_start_time"]');
            var $endTime = $('input[name="fwag_end_time"]');

            if ($startDate.val() && $endDate.val()) {
                var startDate = new Date($startDate.val());
                var endDate = new Date($endDate.val());

                if (startDate > endDate) {
                    alert('Start date cannot be after end date.');
                    $startDate.focus();
                    return false;
                }
            }

            if ($startTime.val() && $endTime.val()) {
                if ($startTime.val() >= $endTime.val()) {
                    alert('Start time must be before end time.');
                    $startTime.focus();
                    return false;
                }
            }

            return true;
        }

        // Validate on form submission
        $('#post').on('submit', function(e) {
            if ($('input[name="fwag_time_restrictions_enabled"]:checked').length > 0) {
                if (!validateTimeRestrictions()) {
                    e.preventDefault();
                    return false;
                }
            }
        });

        // Show/hide time fields based on date selection
        $('input[name="fwag_start_date"], input[name="fwag_end_date"]').on('change', function() {
            var hasDateRestriction = $('input[name="fwag_start_date"]').val() || $('input[name="fwag_end_date"]').val();
            var $timeFields = $('input[name="fwag_start_time"], input[name="fwag_end_time"]').closest('p');

            if (hasDateRestriction) {
                $timeFields.show();
            } else {
                $timeFields.hide();
            }
        }).trigger('change');
    });

})(jQuery);