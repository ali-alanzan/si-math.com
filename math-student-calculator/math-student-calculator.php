<?php
/**
 * Plugin Name: Math Student Scientific Calculator
 * Description: A floating scientific calculator for e-learning courses (Tutor LMS / Learndash).
 * Version: 2.0.0
 * Author: Gemini AI
 */

if (!defined('ABSPATH')) exit;

class MathStudentCalc {
    
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_footer', [$this, 'render_ui']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets() {
        // We inject styles here to ensure high priority over theme styles
        wp_register_style('math-calc-style', false);
        wp_enqueue_style('math-calc-style');
        wp_add_inline_style('math-calc-style', "
            #msc-wrapper { position: fixed; bottom: 20px; right: 20px; z-index: 999999; font-family: 'Segoe UI', Tahoma, Geneva, sans-serif; direction: ltr; }
            .msc-toggle { background: #2271b1; color: white; border: none; padding: 12px 20px; border-radius: 50px; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-weight: bold; display: flex; align-items: center; gap: 8px; }
            .msc-container { 
                position: absolute; bottom: 65px; right: 0; width: 320px; 
                background: #fdfdfd; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); 
                display: none; flex-direction: column; overflow: hidden; border: 1px solid #ddd;
            }
            .msc-header { background: #2271b1; color: white; padding: 12px; display: flex; justify-content: space-between; align-items: center; }
            .msc-display-box { padding: 15px; background: #fff; border-bottom: 1px solid #eee; }
            #msc-history { font-size: 12px; color: #888; text-align: right; min-height: 18px; margin-bottom: 5px; }
            #msc-main-input { width: 100%; border: none; font-size: 24px; text-align: right; outline: none; color: #333; font-weight: 500; }
            .msc-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1px; background: #eee; }
            .msc-grid button { 
                border: none; background: #fff; padding: 15px 5px; cursor: pointer; 
                font-size: 14px; transition: background 0.2s; color: #444;
            }
            .msc-grid button:hover { background: #f5f5f5; }
            .msc-grid .msc-op { background: #f9f9f9; color: #2271b1; font-weight: bold; }
            .msc-grid .msc-sci { background: #eef7ff; color: #00457c; font-size: 12px; }
            .msc-grid .msc-equals { background: #2271b1; color: #fff; grid-column: span 2; font-size: 18px; }
            .msc-grid .msc-clear { color: #d63638; }
        ");
    }

    public function render_ui() {
        // Restrict to Courses/Quizzes to keep the site light
        if (!is_singular(['tutor_quiz', 'courses', 'lesson', 'topic'])) return;
        ?>
        <div id="msc-wrapper">
            <button class="msc-toggle" onclick="mscLogic.toggle()">
                <span>🧮</span> <?php _e('آلة حاسبة', 'math-calc'); ?>
            </button>

            <div class="msc-container" id="msc-ui">
                <div class="msc-header">
                    <span><?php _e('آلة حاسبة علمية', 'math-calc'); ?></span>
                    <button onclick="mscLogic.toggle()" style="background:none; border:none; color:white; cursor:pointer; font-size:20px;">×</button>
                </div>
                <div class="msc-display-box">
                    <div id="msc-history"></div>
                    <input type="text" id="msc-main-input" value="0" readonly>
                </div>
                <div class="msc-grid">
                    <button class="msc-sci" onclick="mscLogic.sci('sin')">sin</button>
                    <button class="msc-sci" onclick="mscLogic.sci('cos')">cos</button>
                    <button class="msc-sci" onclick="mscLogic.sci('tan')">tan</button>
                    <button class="msc-sci" onclick="mscLogic.sci('log')">log</button>
                    
                    <button class="msc-sci" onclick="mscLogic.append('Math.PI')">π</button>
                    <button class="msc-sci" onclick="mscLogic.sci('sqrt')">√</button>
                    <button class="msc-sci" onclick="mscLogic.append('**')">xʸ</button>
                    <button class="msc-sci" onclick="mscLogic.append('(')">(</button>

                    <button class="msc-op msc-clear" onclick="mscLogic.clear()">C</button>
                    <button class="msc-op" onclick="mscLogic.backspace()">⌫</button>
                    <button class="msc-op" onclick="mscLogic.append(')')">)</button>
                    <button class="msc-op" onclick="mscLogic.append('/')">÷</button>

                    <button onclick="mscLogic.append('7')">7</button>
                    <button onclick="mscLogic.append('8')">8</button>
                    <button onclick="mscLogic.append('9')">9</button>
                    <button class="msc-op" onclick="mscLogic.append('*')">×</button>

                    <button onclick="mscLogic.append('4')">4</button>
                    <button onclick="mscLogic.append('5')">5</button>
                    <button onclick="mscLogic.append('6')">6</button>
                    <button class="msc-op" onclick="mscLogic.append('-')">−</button>

                    <button onclick="mscLogic.append('1')">1</button>
                    <button onclick="mscLogic.append('2')">2</button>
                    <button onclick="mscLogic.append('3')">3</button>
                    <button class="msc-op" onclick="mscLogic.append('+')">+</button>

                    <button onclick="mscLogic.append('0')">0</button>
                    <button onclick="mscLogic.append('.')">.</button>
                    <button class="msc-equals" onclick="mscLogic.solve()">=</button>
                </div>
            </div>
        </div>

        <script>
        const mscLogic = (function() {
            const display = () => document.getElementById('msc-main-input');
            const history = () => document.getElementById('msc-history');
            let isEvaluated = false;

            return {
                toggle: () => {
                    const ui = document.getElementById('msc-ui');
                    ui.style.display = (ui.style.display === 'flex') ? 'none' : 'flex';
                },
                append: (val) => {
                    if (display().value === '0' || isEvaluated) {
                        display().value = (val === 'Math.PI') ? Math.PI : val;
                        isEvaluated = false;
                    } else {
                        display().value += (val === 'Math.PI') ? Math.PI : val;
                    }
                },
                clear: () => {
                    display().value = '0';
                    history().innerText = '';
                },
                backspace: () => {
                    display().value = display().value.length > 1 ? display().value.slice(0, -1) : '0';
                },
                sci: (type) => {
                    try {
                        let v = eval(display().value);
                        let res;
                        switch(type) {
                            case 'sin': res = Math.sin(v * Math.PI / 180); break;
                            case 'cos': res = Math.cos(v * Math.PI / 180); break;
                            case 'tan': res = Math.tan(v * Math.PI / 180); break;
                            case 'log': res = Math.log10(v); break;
                            case 'sqrt': res = Math.sqrt(v); break;
                        }
                        history().innerText = type + '(' + v + ')';
                        display().value = parseFloat(res.toFixed(8));
                        isEvaluated = true;
                    } catch(e) { display().value = 'Error'; }
                },
                solve: () => {
                    try {
                        let equation = display().value;
                        let result = eval(equation);
                        history().innerText = equation + ' =';
                        display().value = parseFloat(result.toFixed(8));
                        isEvaluated = true;
                    } catch(e) { display().value = 'Error'; }
                }
            };
        })();
        </script>
        <?php
    }
}

// Initialize
MathStudentCalc::get_instance();