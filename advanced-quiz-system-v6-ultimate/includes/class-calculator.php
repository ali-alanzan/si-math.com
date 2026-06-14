<?php
/**
 * Calculator Widget Class - Enhanced & Fixed
 * Now with scientific calculator capabilities
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQS_Calculator {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (get_option('aqs_calculator_enabled', '1') == '1') {
            add_action('wp_footer', array($this, 'render_calculator'));
        }
    }
    
    public function render_calculator() {
        // Only show on quiz pages or when user is taking a quiz
        if (!is_singular('tutor_quiz') && !is_singular('courses')) {
            return;
        }
        ?>
        <div id="aqs-calculator" class="aqs-calculator-widget">
            <button class="aqs-toggle-btn aqs-calc-toggle" type="button" onclick="aqsToggleCalculator()">
                <span class="aqs-calc-icon">🧮</span>
                <span class="aqs-calc-label"><?php _e('آلة حاسبة', 'advanced-quiz-system'); ?></span>
            </button>
            
            <div class="aqs-calculator-container" style="display:none;">
                <div class="aqs-calc-header">
                    <span class="aqs-calc-title">
                        <span class="aqs-calc-icon">🧮</span>
                        <?php _e('آلة حاسبة علمية', 'advanced-quiz-system'); ?>
                    </span>
                    <button class="aqs-close-btn" type="button" onclick="aqsToggleCalculator()">×</button>
                </div>
                
                <div class="aqs-calc-display-wrapper">
                    <div id="aqs-calc-history" class="aqs-calc-history"></div>
                    <input type="text" id="aqs-calc-display" class="aqs-calc-display" readonly value="0">
                </div>
                
                <div class="aqs-calc-buttons">
                    <!-- Scientific Functions Row -->
                    <button onclick="aqsAppendCalc('(')" class="aqs-function">(</button>
                    <button onclick="aqsAppendCalc(')')" class="aqs-function">)</button>
                    <button onclick="aqsCalcFunction('sqrt')" class="aqs-function">√</button>
                    <button onclick="aqsCalcFunction('power')" class="aqs-function">x²</button>
                    
                    <!-- Row 1 -->
                    <button onclick="aqsClearCalc()" class="aqs-clear-btn">C</button>
                    <button onclick="aqsBackspace()" class="aqs-backspace">⌫</button>
                    <button onclick="aqsAppendCalc('%')" class="aqs-operator">%</button>
                    <button onclick="aqsAppendCalc('/')" class="aqs-operator">÷</button>
                    
                    <!-- Row 2 -->
                    <button onclick="aqsAppendCalc('7')" class="aqs-number">7</button>
                    <button onclick="aqsAppendCalc('8')" class="aqs-number">8</button>
                    <button onclick="aqsAppendCalc('9')" class="aqs-number">9</button>
                    <button onclick="aqsAppendCalc('*')" class="aqs-operator">×</button>
                    
                    <!-- Row 3 -->
                    <button onclick="aqsAppendCalc('4')" class="aqs-number">4</button>
                    <button onclick="aqsAppendCalc('5')" class="aqs-number">5</button>
                    <button onclick="aqsAppendCalc('6')" class="aqs-number">6</button>
                    <button onclick="aqsAppendCalc('-')" class="aqs-operator">−</button>
                    
                    <!-- Row 4 -->
                    <button onclick="aqsAppendCalc('1')" class="aqs-number">1</button>
                    <button onclick="aqsAppendCalc('2')" class="aqs-number">2</button>
                    <button onclick="aqsAppendCalc('3')" class="aqs-number">3</button>
                    <button onclick="aqsAppendCalc('+')" class="aqs-operator">+</button>
                    
                    <!-- Row 5 -->
                    <button onclick="aqsAppendCalc('0')" class="aqs-number aqs-zero">0</button>
                    <button onclick="aqsAppendCalc('.')" class="aqs-number">.</button>
                    <button onclick="aqsCalculate()" class="aqs-equals">=</button>
                </div>
                
                <div class="aqs-calc-footer">
                    <small class="aqs-calc-hint">
                        💡 <?php _e('استخدم لوحة المفاتيح للكتابة السريعة', 'advanced-quiz-system'); ?>
                    </small>
                </div>
            </div>
        </div>
        
        <script>
        (function() {
            var calcHistory = [];
            
            window.aqsToggleCalculator = function() {
                jQuery('.aqs-calculator-container').slideToggle(300, function() {
                    if (jQuery(this).is(':visible')) {
                        jQuery('#aqs-calc-display').focus();
                    }
                });
            };
            
            window.aqsAppendCalc = function(value) {
                var display = jQuery('#aqs-calc-display');
                var currentValue = display.val();
                
                // If display shows result or 0, replace it
                if (currentValue === '0' || display.data('result')) {
                    if (['+', '-', '*', '/', '%'].indexOf(value) === -1) {
                        display.val(value);
                    } else {
                        display.val(currentValue + value);
                    }
                    display.data('result', false);
                } else {
                    display.val(currentValue + value);
                }
            };
            
            window.aqsBackspace = function() {
                var display = jQuery('#aqs-calc-display');
                var value = display.val();
                
                if (value.length > 1) {
                    display.val(value.slice(0, -1));
                } else {
                    display.val('0');
                }
            };
            
            window.aqsCalcFunction = function(func) {
                var display = jQuery('#aqs-calc-display');
                var value = parseFloat(display.val()) || 0;
                var result;
                
                switch(func) {
                    case 'sqrt':
                        result = Math.sqrt(value);
                        break;
                    case 'power':
                        result = value * value;
                        break;
                    default:
                        return;
                }
                
                // Add to history
                aqsAddToHistory(value + ' ' + func + ' = ' + result);
                
                display.val(result);
                display.data('result', true);
            };
            
            window.aqsCalculate = function() {
                try {
                    var display = jQuery('#aqs-calc-display');
                    var expression = display.val();
                    
                    // Replace × and ÷ with * and /
                    expression = expression.replace(/×/g, '*').replace(/÷/g, '/');
                    
                    // Validate expression
                    if (!/^[0-9+\-*\/.()%\s]+$/.test(expression)) {
                        throw new Error('تعبير غير صالح');
                    }
                    
                    // Calculate using Function constructor (safer than eval)
                    var result = new Function('return ' + expression)();
                    
                    // Round to 10 decimal places
                    result = Math.round(result * 10000000000) / 10000000000;
                    
                    // Add to history
                    aqsAddToHistory(expression + ' = ' + result);
                    
                    display.val(result);
                    display.data('result', true);
                    
                } catch(e) {
                    console.error('Calculator error:', e);
                    alert(aqsData.strings.error || 'خطأ في الحساب');
                    aqsClearCalc();
                }
            };
            
            window.aqsClearCalc = function() {
                jQuery('#aqs-calc-display').val('0').data('result', false);
            };
            
            function aqsAddToHistory(entry) {
                calcHistory.unshift(entry);
                if (calcHistory.length > 5) {
                    calcHistory.pop();
                }
                
                var html = calcHistory.map(function(item) {
                    return '<div class="aqs-history-item">' + item + '</div>';
                }).join('');
                
                jQuery('#aqs-calc-history').html(html);
            }
            
            // Keyboard support
            jQuery(document).on('keydown', function(e) {
                if (!jQuery('.aqs-calculator-container').is(':visible')) {
                    return;
                }
                
                var key = e.key;
                
                // Numbers and operators
                if (/[0-9+\-*\/.()%]/.test(key)) {
                    aqsAppendCalc(key);
                    e.preventDefault();
                }
                // Enter to calculate
                else if (key === 'Enter') {
                    aqsCalculate();
                    e.preventDefault();
                }
                // Escape to clear
                else if (key === 'Escape') {
                    aqsClearCalc();
                    e.preventDefault();
                }
                // Backspace
                else if (key === 'Backspace') {
                    aqsBackspace();
                    e.preventDefault();
                }
            });
            
            // Focus on display when calculator opens
            jQuery(document).on('click', '.aqs-calc-toggle', function() {
                setTimeout(function() {
                    if (jQuery('.aqs-calculator-container').is(':visible')) {
                        jQuery('#aqs-calc-display').focus();
                    }
                }, 350);
            });
        })();
        </script>
        <?php
    }
}
