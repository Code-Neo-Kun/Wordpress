(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var overlay = document.querySelector('.fwag-overlay');
        
        if (!overlay) {
            return;
        }

        var focusableElements = overlay.querySelectorAll(
            'a[href], button, textarea, input, select, [tabindex]:not([tabindex="-1"])'
        );
        
        if (focusableElements.length === 0) {
            return;
        }

        var firstFocusable = focusableElements[0];
        var lastFocusable = focusableElements[focusableElements.length - 1];

        firstFocusable.focus();

        overlay.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                e.preventDefault();
                return;
            }

            if (e.key === 'Tab' || e.keyCode === 9) {
                if (e.shiftKey) {
                    if (document.activeElement === firstFocusable) {
                        e.preventDefault();
                        lastFocusable.focus();
                    }
                } else {
                    if (document.activeElement === lastFocusable) {
                        e.preventDefault();
                        firstFocusable.focus();
                    }
                }
            }
        });

        document.body.style.overflow = 'hidden';
    });
})();