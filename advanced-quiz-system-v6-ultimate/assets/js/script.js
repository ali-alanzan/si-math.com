/**
 * Advanced Quiz System - Main JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';
    
    /* ==========================================================================
       Calculator Functions
       ========================================================================== */
    
    window.aqsToggleCalculator = function() {
        $('.aqs-calculator-container').slideToggle(300);
    };
    
    window.aqsAppendCalc = function(value) {
        $('#aqs-calc-display').val($('#aqs-calc-display').val() + value);
    };
    
    window.aqsCalculate = function() {
        try {
            var expression = $('#aqs-calc-display').val();
            var result = eval(expression);
            $('#aqs-calc-display').val(result);
        } catch(e) {
            alert(aqsData.strings.error || 'خطأ في الحساب');
            aqsClearCalc();
        }
    };
    
    window.aqsClearCalc = function() {
        $('#aqs-calc-display').val('');
    };
    
    // Keyboard support for calculator
    $(document).on('keydown', function(e) {
        if ($('.aqs-calculator-container').is(':visible')) {
            var key = e.key;
            
            if (/[0-9\+\-\*\/\.]/.test(key)) {
                aqsAppendCalc(key);
                e.preventDefault();
            } else if (key === 'Enter') {
                aqsCalculate();
                e.preventDefault();
            } else if (key === 'Escape') {
                aqsClearCalc();
                e.preventDefault();
            }
        }
    });
    
    /* ==========================================================================
       Drawing Canvas Functions
       ========================================================================== */
    
    var canvas = null;
    var ctx = null;
    var drawing = false;
    var eraserMode = false;
    
    window.aqsToggleCanvas = function() {
        $('.aqs-canvas-container').slideToggle(300, function() {
            if ($(this).is(':visible')) {
                aqsInitCanvas();
            }
        });
    };
    
    function aqsInitCanvas() {
        canvas = document.getElementById('aqs-drawing-canvas');
        if (!canvas) return;
        
        ctx = canvas.getContext('2d');
        
        // Remove old event listeners
        canvas.onmousedown = null;
        canvas.onmousemove = null;
        canvas.onmouseup = null;
        canvas.onmouseout = null;
        
        // Add event listeners
        canvas.addEventListener('mousedown', aqsStartDrawing);
        canvas.addEventListener('mousemove', aqsDraw);
        canvas.addEventListener('mouseup', aqsStopDrawing);
        canvas.addEventListener('mouseout', aqsStopDrawing);
        
        // Touch support
        canvas.addEventListener('touchstart', function(e) {
            e.preventDefault();
            var touch = e.touches[0];
            var mouseEvent = new MouseEvent('mousedown', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        });
        
        canvas.addEventListener('touchmove', function(e) {
            e.preventDefault();
            var touch = e.touches[0];
            var mouseEvent = new MouseEvent('mousemove', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        });
        
        canvas.addEventListener('touchend', function(e) {
            e.preventDefault();
            var mouseEvent = new MouseEvent('mouseup', {});
            canvas.dispatchEvent(mouseEvent);
        });
        
        // Update pen size display
        $('#aqs-pen-size').on('input', function() {
            $('#aqs-pen-size-value').text($(this).val());
        });
    }
    
    function aqsStartDrawing(e) {
        drawing = true;
        ctx.beginPath();
        var rect = canvas.getBoundingClientRect();
        ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
    }
    
    function aqsDraw(e) {
        if (!drawing) return;
        
        var rect = canvas.getBoundingClientRect();
        var x = e.clientX - rect.left;
        var y = e.clientY - rect.top;
        
        ctx.lineWidth = $('#aqs-pen-size').val();
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        if (eraserMode) {
            ctx.globalCompositeOperation = 'destination-out';
        } else {
            ctx.globalCompositeOperation = 'source-over';
            ctx.strokeStyle = $('#aqs-pen-color').val();
        }
        
        ctx.lineTo(x, y);
        ctx.stroke();
    }
    
    function aqsStopDrawing() {
        drawing = false;
        ctx.beginPath();
    }
    
    window.aqsClearCanvas = function() {
        if (!ctx) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    };
    
    window.aqsEraserMode = function() {
        eraserMode = !eraserMode;
        var btn = $('#aqs-eraser-btn');
        
        if (eraserMode) {
            btn.css('background', '#dc3232');
            btn.text('🧹 ' + (aqsData.strings.pen || 'قلم'));
        } else {
            btn.css('background', '#ffb900');
            btn.text('🧹 ' + (aqsData.strings.eraser || 'ممحاة'));
        }
    };
    
    window.aqsSaveDrawing = function() {
        if (!canvas) return;
        
        var dataURL = canvas.toDataURL('image/png');
        var link = document.createElement('a');
        link.download = 'drawing-' + Date.now() + '.png';
        link.href = dataURL;
        link.click();
    };
    
    /* ==========================================================================
       Quiz Question Tracker
       ========================================================================== */
    
    window.aqsInitQuestionTracker = function() {
        // Already implemented in class-question-tracker.php
        // This is just a placeholder for additional functionality
    };
    
    /* ==========================================================================
       Chat Functions
       ========================================================================== */
    
    window.aqsToggleChat = function() {
        $('#aqs-chat-widget').toggleClass('aqs-chat-minimized');
    };
    
    window.aqsLoadMessages = function() {
        // Implemented in class-chat-system.php
    };
    
    window.aqsLoadOnlineUsers = function() {
        // Implemented in class-chat-system.php
    };
    
    window.aqsSendMessage = function() {
        // Implemented in class-chat-system.php
    };
    
    window.aqsDisplayMessages = function(messages) {
        // Implemented in class-chat-system.php
    };
    
    window.aqsDisplayOnlineUsers = function(users) {
        // Implemented in class-chat-system.php
    };
    
    /* ==========================================================================
       Document Ready
       ========================================================================== */
    
    $(document).ready(function() {
        
        // Close widgets when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.aqs-calculator-widget').length) {
                $('.aqs-calculator-container').slideUp(300);
            }
            
            if (!$(e.target).closest('.aqs-drawing-widget').length) {
                $('.aqs-canvas-container').slideUp(300);
            }
        });
        
        // Prevent widget close when clicking inside
        $('.aqs-calculator-widget, .aqs-drawing-widget').on('click', function(e) {
            e.stopPropagation();
        });
        
        // Auto-hide question counter on scroll (mobile)
        if ($(window).width() < 768) {
            var questionCounter = $('.aqs-question-counter');
            var scrollTimer;
            
            $(window).on('scroll', function() {
                questionCounter.css('opacity', '0.3');
                
                clearTimeout(scrollTimer);
                scrollTimer = setTimeout(function() {
                    questionCounter.css('opacity', '1');
                }, 1000);
            });
        }
        
        // Smooth scroll to unanswered questions
        $('.aqs-remaining-questions').on('click', function() {
            var firstUnanswered = $('.tutor-quiz-question').not('.aqs-answered').first();
            if (firstUnanswered.length) {
                $('html, body').animate({
                    scrollTop: firstUnanswered.offset().top - 100
                }, 500);
            }
        });
        
        // Add keyboard shortcuts hint
        if ($('.aqs-calculator-widget').length) {
            var hint = $('<div class="aqs-keyboard-hint">💡 اضغط Esc للمسح، Enter للحساب</div>');
            hint.css({
                'position': 'absolute',
                'bottom': '-30px',
                'right': '0',
                'font-size': '12px',
                'color': '#666',
                'white-space': 'nowrap'
            });
            $('.aqs-calculator-widget').append(hint);
        }
        
        // Accessibility improvements
        $('.aqs-toggle-btn').attr('aria-label', 'Toggle widget');
        $('.aqs-calc-buttons button').each(function() {
            $(this).attr('aria-label', 'Calculator button ' + $(this).text());
        });
        
        // Auto-focus chat input when opened
        $(document).on('click', '.aqs-chat-header', function() {
            setTimeout(function() {
                if (!$('#aqs-chat-widget').hasClass('aqs-chat-minimized')) {
                    $('#aqs-chat-input').focus();
                }
            }, 300);
        });
        
    });
    
})(jQuery);
