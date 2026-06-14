<?php
/**
 * Desmos Calculator Widget
 * Advanced graphing calculator using Desmos API
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQS_Desmos_Calculator {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (get_option('aqs_calculator_enabled', '1') == '1') {
            add_action('wp_footer', array($this, 'render_calculator_widget'));
        }
    }
    
    /**
     * Check if user can access calculator
     */
    private function can_access_calculator() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user_id = get_current_user_id();
        
        // Admins can always access
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Check if Tutor LMS is active
        if (!function_exists('tutor_utils')) {
            return false;
        }
        
        // Check if user has any enrolled courses
        $enrolled_courses = tutor_utils()->get_enrolled_courses_by_user($user_id);
        
        return !empty($enrolled_courses);
    }
    
    /**
     * Render Desmos calculator widget
     */
    public function render_calculator_widget() {
        if (!$this->can_access_calculator()) {
            return;
        }
        ?>
        
        <div id="aqs-desmos-widget" class="aqs-desmos-widget">
            <button class="aqs-desmos-toggle" onclick="aqsToggleDesmos()">
                <span class="aqs-calc-icon">📊</span>
                <span class="aqs-calc-text"><?php _e('الآلة الحاسبة', 'advanced-quiz-system'); ?></span>
            </button>
            
            <div class="aqs-desmos-container" id="aqs-desmos-container" style="display: none;">
                <div class="aqs-desmos-header">
                    <span class="aqs-desmos-title">
                        📊 <?php _e('الآلة الحاسبة المتقدمة - Desmos', 'advanced-quiz-system'); ?>
                    </span>
                    <div class="aqs-desmos-controls">
                        <button class="aqs-desmos-btn" onclick="aqsResetDesmos()" title="إعادة تعيين">
                            🔄
                        </button>
                        <button class="aqs-desmos-close" onclick="aqsToggleDesmos()">
                            ✕
                        </button>
                    </div>
                </div>
                
                <div class="aqs-desmos-body">
                    <div id="aqs-calculator" class="aqs-desmos-calculator"></div>
                </div>
                
                <div class="aqs-desmos-footer">
                    <div class="aqs-desmos-info">
                        <span>💡 استخدم الماوس للتكبير والتصغير</span>
                        <span>⌨️ اضغط على المربعات لإدخال المعادلات</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Load Desmos API -->
        <script src="https://www.desmos.com/api/v1.9/calculator.js?apiKey=dcb31709b452b1cf9dc26972add0fda6"></script>
        
        <script>
        (function() {
            var calculator = null;
            var isInitialized = false;
            
            window.aqsToggleDesmos = function() {
                var container = document.getElementById('aqs-desmos-container');
                
                if (container.style.display === 'none') {
                    container.style.display = 'block';
                    
                    // Initialize Desmos calculator if not already initialized
                    if (!isInitialized) {
                        aqsInitDesmos();
                    }
                } else {
                    container.style.display = 'none';
                }
            };
            
            window.aqsInitDesmos = function() {
                if (isInitialized) {
                    return;
                }
                
                var elt = document.getElementById('aqs-calculator');
                
                // Desmos calculator options
                var options = {
                    keypad: true,              // Show keypad
                    expressions: true,         // Show expressions list
                    settingsMenu: true,        // Show settings
                    zoomButtons: true,         // Show zoom buttons
                    expressionsTopbar: true,   // Show expressions toolbar
                    pointsOfInterest: true,    // Show points of interest
                    trace: true,               // Enable trace mode
                    border: false,             // No border
                    lockViewport: false,       // Allow viewport changes
                    expressionsCollapsed: false, // Show expressions expanded
                    administerSecretFolders: false,
                    images: true,              // Allow images
                    folders: true,             // Allow folders
                    notes: true,               // Allow notes
                    sliders: true,             // Allow sliders
                    links: true,               // Allow links
                    qwertyKeyboard: false,     // Arabic-friendly
                    restrictedFunctions: false, // Allow all functions
                    pasteGraphLink: true,      // Allow paste graph links
                    capExpressionSize: false   // No expression size limit
                };
                
                // Create calculator instance
                calculator = Desmos.GraphingCalculator(elt, options);
                
                // Set initial expressions (optional examples)
                calculator.setExpression({ 
                    id: 'example1', 
                    latex: 'y=x^2',
                    color: Desmos.Colors.BLUE 
                });
                
                calculator.setExpression({ 
                    id: 'example2', 
                    latex: 'y=\\sin(x)',
                    color: Desmos.Colors.RED,
                    hidden: true // Hidden by default
                });
                
                // Set Arabic-friendly settings
                calculator.updateSettings({
                    language: 'ar',
                    invertedColors: false,
                    projectorMode: false,
                    brailleMode: 'none',
                    sixKeyInput: false
                });
                
                isInitialized = true;
                console.log('Desmos Calculator initialized successfully');
            };
            
            window.aqsResetDesmos = function() {
                if (calculator) {
                    calculator.setBlank();
                    
                    // Add back example expressions
                    calculator.setExpression({ 
                        id: 'example1', 
                        latex: 'y=x^2',
                        color: Desmos.Colors.BLUE 
                    });
                    
                    console.log('Desmos Calculator reset');
                }
            };
            
            // Save state before page unload (optional)
            window.addEventListener('beforeunload', function() {
                if (calculator && isInitialized) {
                    var state = calculator.getState();
                    localStorage.setItem('aqs_desmos_state', JSON.stringify(state));
                }
            });
            
            // Restore state on page load (optional)
            window.addEventListener('load', function() {
                var savedState = localStorage.getItem('aqs_desmos_state');
                if (savedState && calculator && isInitialized) {
                    try {
                        calculator.setState(JSON.parse(savedState));
                    } catch (e) {
                        console.log('Could not restore Desmos state');
                    }
                }
            });
        })();
        </script>
        
        <style>
        /* Desmos Calculator Widget Styling */
        .aqs-desmos-widget {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 9998; /* Below chat widget */
        }
        
        .aqs-desmos-toggle {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            border-radius: 16px;
            padding: 14px 22px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(245, 87, 108, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .aqs-desmos-toggle:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(245, 87, 108, 0.4);
        }
        
        .aqs-calc-icon {
            font-size: 22px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        .aqs-desmos-container {
            position: absolute;
            bottom: 70px;
            left: 0;
            background: white;
            border-radius: 20px;
            box-shadow: 0 12px 48px rgba(0, 0, 0, 0.2);
            width: 600px;
            max-width: calc(100vw - 40px);
            height: 650px;
            max-height: calc(100vh - 140px);
            overflow: hidden;
            animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .aqs-desmos-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .aqs-desmos-title {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .aqs-desmos-controls {
            display: flex;
            gap: 8px;
        }
        
        .aqs-desmos-btn,
        .aqs-desmos-close {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .aqs-desmos-btn:hover,
        .aqs-desmos-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }
        
        .aqs-desmos-close:hover {
            transform: rotate(90deg) scale(1.1);
        }
        
        .aqs-desmos-body {
            height: calc(100% - 110px);
            overflow: hidden;
            background: #fafafa;
        }
        
        .aqs-desmos-calculator {
            width: 100%;
            height: 100%;
        }
        
        .aqs-desmos-footer {
            background: white;
            border-top: 1px solid #e9ecef;
            padding: 12px 20px;
        }
        
        .aqs-desmos-info {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #6c757d;
            gap: 16px;
        }
        
        .aqs-desmos-info span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .aqs-desmos-widget {
                left: 10px;
                bottom: 10px;
            }
            
            .aqs-desmos-container {
                width: calc(100vw - 20px);
                height: calc(100vh - 100px);
                bottom: 60px;
            }
            
            .aqs-desmos-info {
                flex-direction: column;
                gap: 8px;
            }
            
            .aqs-calc-text {
                display: none;
            }
        }
        
        /* Dark mode support (optional) */
        @media (prefers-color-scheme: dark) {
            .aqs-desmos-container {
                background: #1e1e1e;
            }
            
            .aqs-desmos-footer {
                background: #2d2d2d;
                border-top-color: #404040;
            }
            
            .aqs-desmos-body {
                background: #252525;
            }
        }
        </style>
        <?php
    }
}
