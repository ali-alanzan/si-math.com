/**
 * Advanced Quiz System - Admin JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Confirm before deleting chat messages
        $('.aqs-chat-monitor').on('click', '.button', function(e) {
            if ($(this).text().includes('حذف') || $(this).text().includes('Delete')) {
                if (!confirm('هل أنت متأكد من حذف هذه الرسالة؟')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // Show/hide related settings based on feature toggles
        $('input[name="aqs_adaptive_quiz_enabled"]').on('change', function() {
            var row = $('input[name="aqs_easy_threshold"]').closest('tr');
            if ($(this).is(':checked')) {
                row.slideDown();
            } else {
                row.slideUp();
            }
        }).trigger('change');
        
        $('input[name="aqs_leaderboard_enabled"]').on('change', function() {
            var row = $('input[name="aqs_leaderboard_limit"]').closest('tr');
            if ($(this).is(':checked')) {
                row.slideDown();
            } else {
                row.slideUp();
            }
        }).trigger('change');
        
        // Copy shortcode to clipboard
        $('.aqs-shortcode-examples code').on('click', function() {
            var text = $(this).text();
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            
            // Show feedback
            var $feedback = $('<span class="aqs-copy-feedback">تم النسخ! ✓</span>');
            $feedback.css({
                'position': 'absolute',
                'background': '#46b450',
                'color': '#fff',
                'padding': '5px 10px',
                'border-radius': '4px',
                'font-size': '12px',
                'margin-left': '10px'
            });
            $(this).after($feedback);
            
            setTimeout(function() {
                $feedback.fadeOut(function() {
                    $(this).remove();
                });
            }, 2000);
        });
        
        // Add hover effect to shortcodes
        $('.aqs-shortcode-examples code').css('cursor', 'pointer')
            .attr('title', 'اضغط للنسخ');
        
        // Settings validation
        $('form').on('submit', function(e) {
            var threshold = $('input[name="aqs_easy_threshold"]').val();
            var limit = $('input[name="aqs_leaderboard_limit"]').val();
            
            if (threshold < 0 || threshold > 100) {
                alert('عتبة الأسئلة السهلة يجب أن تكون بين 0 و 100');
                e.preventDefault();
                return false;
            }
            
            if (limit < 1 || limit > 20) {
                alert('عدد الطلاب في لوحة المتصدرين يجب أن يكون بين 1 و 20');
                e.preventDefault();
                return false;
            }
        });
        
        // Auto-refresh chat monitor every 10 seconds
        if ($('.aqs-chat-monitor').length) {
            setInterval(function() {
                location.reload();
            }, 10000);
        }
        
    });
    
})(jQuery);
