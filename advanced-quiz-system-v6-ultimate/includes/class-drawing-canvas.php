<?php
/**
 * Drawing Canvas Class - Enhanced with Chart.js
 * Now with graphing capabilities and better drawing tools
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQS_Drawing_Canvas {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (get_option('aqs_drawing_enabled', '1') == '1') {
            add_action('wp_footer', array($this, 'render_canvas'));
        }
    }
    
    public function render_canvas() {
        // Only show on quiz/course pages
        if (!is_singular('tutor_quiz') && !is_singular('courses')) {
            return;
        }
        ?>
        <div id="aqs-drawing-board" class="aqs-drawing-widget">
            <button class="aqs-toggle-btn aqs-canvas-toggle" type="button" onclick="aqsToggleCanvas()">
                <span class="aqs-canvas-icon">📊</span>
                <span class="aqs-canvas-label"><?php _e('رسم بياني', 'advanced-quiz-system'); ?></span>
            </button>
            
            <div class="aqs-canvas-container" style="display:none;">
                <div class="aqs-canvas-header">
                    <span class="aqs-canvas-title">
                        <span class="aqs-canvas-icon">📊</span>
                        <?php _e('لوحة الرسم والرسوم البيانية', 'advanced-quiz-system'); ?>
                    </span>
                    <button class="aqs-close-btn" type="button" onclick="aqsToggleCanvas()">×</button>
                </div>
                
                <!-- Mode Selector -->
                <div class="aqs-canvas-mode-selector">
                    <button type="button" class="aqs-mode-btn active" data-mode="draw" onclick="aqsSwitchCanvasMode('draw')">
                        <span class="aqs-mode-icon">✏️</span>
                        <?php _e('رسم حر', 'advanced-quiz-system'); ?>
                    </button>
                    <button type="button" class="aqs-mode-btn" data-mode="chart" onclick="aqsSwitchCanvasMode('chart')">
                        <span class="aqs-mode-icon">📈</span>
                        <?php _e('رسم بياني', 'advanced-quiz-system'); ?>
                    </button>
                </div>
                
                <!-- Drawing Mode -->
                <div id="aqs-draw-mode" class="aqs-canvas-mode">
                    <div class="aqs-canvas-wrapper">
                        <canvas id="aqs-grid-canvas" width="600" height="400"></canvas>
                        <canvas id="aqs-drawing-canvas" width="600" height="400"></canvas>
                    </div>
                    
                    <div class="aqs-canvas-controls">
                        <div class="aqs-control-group">
                            <label class="aqs-control-label">
                                <span class="aqs-control-icon">📐</span>
                                <?php _e('الشبكة:', 'advanced-quiz-system'); ?>
                            </label>
                            <select id="aqs-grid-type" onchange="aqsChangeGrid(this.value)">
                                <option value="graph"><?php _e('ورق مسطر (جراف)', 'advanced-quiz-system'); ?></option>
                                <option value="dots"><?php _e('نقاط', 'advanced-quiz-system'); ?></option>
                                <option value="none"><?php _e('بدون شبكة', 'advanced-quiz-system'); ?></option>
                            </select>
                        </div>
                        
                        <div class="aqs-control-group">
                            <label class="aqs-control-label">
                                <span class="aqs-control-icon">🎨</span>
                                <?php _e('اللون:', 'advanced-quiz-system'); ?>
                            </label>
                            <input type="color" id="aqs-pen-color" value="#2271b1">
                            
                            <div class="aqs-color-presets">
                                <button type="button" class="aqs-color-preset" style="background:#000000" onclick="aqsSetColor('#000000')"></button>
                                <button type="button" class="aqs-color-preset" style="background:#2271b1" onclick="aqsSetColor('#2271b1')"></button>
                                <button type="button" class="aqs-color-preset" style="background:#dc3232" onclick="aqsSetColor('#dc3232')"></button>
                                <button type="button" class="aqs-color-preset" style="background:#46b450" onclick="aqsSetColor('#46b450')"></button>
                                <button type="button" class="aqs-color-preset" style="background:#ffb900" onclick="aqsSetColor('#ffb900')"></button>
                            </div>
                        </div>
                        
                        <div class="aqs-control-group">
                            <label class="aqs-control-label">
                                <span class="aqs-control-icon">📏</span>
                                <?php _e('السمك:', 'advanced-quiz-system'); ?>
                            </label>
                            <input type="range" id="aqs-pen-size" min="1" max="30" value="3" oninput="aqsUpdatePenSize(this.value)">
                            <span id="aqs-pen-size-value" class="aqs-size-value">3px</span>
                        </div>
                        
                        <div class="aqs-control-buttons">
                            <button type="button" onclick="aqsEraserMode()" id="aqs-eraser-btn" class="aqs-tool-btn">
                                🧹 <?php _e('ممحاة', 'advanced-quiz-system'); ?>
                            </button>
                            <button type="button" onclick="aqsClearCanvas()" class="aqs-tool-btn aqs-clear">
                                🗑️ <?php _e('مسح الكل', 'advanced-quiz-system'); ?>
                            </button>
                            <button type="button" onclick="aqsSaveDrawing()" class="aqs-tool-btn aqs-save">
                                💾 <?php _e('حفظ', 'advanced-quiz-system'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Chart Mode -->
                <div id="aqs-chart-mode" class="aqs-canvas-mode" style="display:none;">
                    <div class="aqs-chart-container">
                        <canvas id="aqs-chart-canvas"></canvas>
                    </div>
                    
                    <div class="aqs-chart-controls">
                        <div class="aqs-chart-type-selector">
                            <button type="button" class="aqs-chart-type active" data-type="line" onclick="aqsChangeChartType('line')">
                                📈 <?php _e('خطي', 'advanced-quiz-system'); ?>
                            </button>
                            <button type="button" class="aqs-chart-type" data-type="bar" onclick="aqsChangeChartType('bar')">
                                📊 <?php _e('أعمدة', 'advanced-quiz-system'); ?>
                            </button>
                            <button type="button" class="aqs-chart-type" data-type="pie" onclick="aqsChangeChartType('pie')">
                                🥧 <?php _e('دائري', 'advanced-quiz-system'); ?>
                            </button>
                        </div>
                        
                        <div class="aqs-chart-data-input">
                            <h4><?php _e('أدخل البيانات:', 'advanced-quiz-system'); ?></h4>
                            
                            <div class="aqs-data-row">
                                <label><?php _e('التسميات (مفصولة بفاصلة):', 'advanced-quiz-system'); ?></label>
                                <input type="text" id="aqs-chart-labels" placeholder="يناير, فبراير, مارس, أبريل">
                            </div>
                            
                            <div class="aqs-data-row">
                                <label><?php _e('القيم (مفصولة بفاصلة):', 'advanced-quiz-system'); ?></label>
                                <input type="text" id="aqs-chart-values" placeholder="10, 20, 15, 30">
                            </div>
                            
                            <div class="aqs-chart-buttons">
                                <button type="button" onclick="aqsCreateChart()" class="aqs-btn-primary">
                                    ✨ <?php _e('إنشاء الرسم', 'advanced-quiz-system'); ?>
                                </button>
                                <button type="button" onclick="aqsSaveChart()" class="aqs-btn-secondary">
                                    💾 <?php _e('حفظ الرسم', 'advanced-quiz-system'); ?>
                                </button>
                            </div>
                            
                            <div class="aqs-chart-examples">
                                <p><strong><?php _e('أمثلة سريعة:', 'advanced-quiz-system'); ?></strong></p>
                                <button type="button" onclick="aqsLoadExample('grades')" class="aqs-example-btn">
                                    📚 <?php _e('درجات الطلاب', 'advanced-quiz-system'); ?>
                                </button>
                                <button type="button" onclick="aqsLoadExample('sales')" class="aqs-example-btn">
                                    💰 <?php _e('المبيعات الشهرية', 'advanced-quiz-system'); ?>
                                </button>
                                <button type="button" onclick="aqsLoadExample('temperature')" class="aqs-example-btn">
                                    🌡️ <?php _e('درجات الحرارة', 'advanced-quiz-system'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        (function() {
            var drawCanvas = null;
            var drawCtx = null;
            var drawing = false;
            var eraserMode = false;
            var currentChart = null;
            var currentChartType = 'line';
            
            // Initialize when canvas is opened
            window.aqsToggleCanvas = function() {
                jQuery('.aqs-canvas-container').slideToggle(300, function() {
                    if (jQuery(this).is(':visible')) {
                        aqsInitDrawCanvas();
                    }
                });
            };
            
            // Switch between draw and chart modes
            window.aqsSwitchCanvasMode = function(mode) {
                jQuery('.aqs-mode-btn').removeClass('active');
                jQuery('.aqs-mode-btn[data-mode="' + mode + '"]').addClass('active');
                
                if (mode === 'draw') {
                    jQuery('#aqs-draw-mode').show();
                    jQuery('#aqs-chart-mode').hide();
                    aqsInitDrawCanvas();
                } else if (mode === 'chart') {
                    jQuery('#aqs-draw-mode').hide();
                    jQuery('#aqs-chart-mode').show();
                }
            };
            
            // === DRAWING MODE FUNCTIONS ===
            
            var gridCanvas, gridCtx;
            var currentGridType = 'graph';
            
            function aqsInitDrawCanvas() {
                drawCanvas = document.getElementById('aqs-drawing-canvas');
                gridCanvas = document.getElementById('aqs-grid-canvas');
                
                if (!drawCanvas || drawCtx) return;
                
                drawCtx = drawCanvas.getContext('2d');
                gridCtx = gridCanvas.getContext('2d');
                
                // Set white background on drawing canvas
                drawCtx.fillStyle = '#ffffff';
                drawCtx.fillRect(0, 0, drawCanvas.width, drawCanvas.height);
                
                // Draw initial grid
                aqsDrawGrid('graph');
                
                // Add event listeners
                drawCanvas.addEventListener('mousedown', aqsStartDrawing);
                drawCanvas.addEventListener('mousemove', aqsDraw);
                drawCanvas.addEventListener('mouseup', aqsStopDrawing);
                drawCanvas.addEventListener('mouseout', aqsStopDrawing);
                
                // Touch support
                drawCanvas.addEventListener('touchstart', handleTouch);
                drawCanvas.addEventListener('touchmove', handleTouch);
                drawCanvas.addEventListener('touchend', aqsStopDrawing);
            }
            
            // Draw grid background
            window.aqsDrawGrid = function(type) {
                if (!gridCtx) return;
                
                currentGridType = type;
                var width = gridCanvas.width;
                var height = gridCanvas.height;
                
                // Clear grid
                gridCtx.clearRect(0, 0, width, height);
                gridCtx.fillStyle = '#ffffff';
                gridCtx.fillRect(0, 0, width, height);
                
                if (type === 'none') return;
                
                if (type === 'graph') {
                    // Draw graph paper (مسطر)
                    var gridSize = 20; // حجم المربعات
                    var majorGridSize = gridSize * 5; // الخطوط السميكة كل 5 مربعات
                    
                    gridCtx.strokeStyle = '#e0e0e0';
                    gridCtx.lineWidth = 0.5;
                    
                    // رسم الخطوط العمودية
                    for (var x = 0; x <= width; x += gridSize) {
                        gridCtx.beginPath();
                        if (x % majorGridSize === 0) {
                            gridCtx.strokeStyle = '#b0b0b0';
                            gridCtx.lineWidth = 1;
                        } else {
                            gridCtx.strokeStyle = '#e0e0e0';
                            gridCtx.lineWidth = 0.5;
                        }
                        gridCtx.moveTo(x, 0);
                        gridCtx.lineTo(x, height);
                        gridCtx.stroke();
                    }
                    
                    // رسم الخطوط الأفقية
                    for (var y = 0; y <= height; y += gridSize) {
                        gridCtx.beginPath();
                        if (y % majorGridSize === 0) {
                            gridCtx.strokeStyle = '#b0b0b0';
                            gridCtx.lineWidth = 1;
                        } else {
                            gridCtx.strokeStyle = '#e0e0e0';
                            gridCtx.lineWidth = 0.5;
                        }
                        gridCtx.moveTo(0, y);
                        gridCtx.lineTo(width, y);
                        gridCtx.stroke();
                    }
                    
                    // رسم المحاور (X و Y) بلون مميز
                    var centerX = width / 2;
                    var centerY = height / 2;
                    
                    gridCtx.strokeStyle = '#4a90e2';
                    gridCtx.lineWidth = 2;
                    
                    // المحور X
                    gridCtx.beginPath();
                    gridCtx.moveTo(0, centerY);
                    gridCtx.lineTo(width, centerY);
                    gridCtx.stroke();
                    
                    // المحور Y
                    gridCtx.beginPath();
                    gridCtx.moveTo(centerX, 0);
                    gridCtx.lineTo(centerX, height);
                    gridCtx.stroke();
                    
                    // إضافة أرقام على المحاور
                    gridCtx.fillStyle = '#666';
                    gridCtx.font = '10px Arial';
                    gridCtx.textAlign = 'center';
                    
                    // أرقام المحور X
                    for (var i = -Math.floor(width / (2 * majorGridSize)); i <= Math.floor(width / (2 * majorGridSize)); i++) {
                        if (i !== 0) {
                            var xPos = centerX + (i * majorGridSize);
                            gridCtx.fillText(i, xPos, centerY + 15);
                        }
                    }
                    
                    // أرقام المحور Y
                    gridCtx.textAlign = 'right';
                    for (var j = -Math.floor(height / (2 * majorGridSize)); j <= Math.floor(height / (2 * majorGridSize)); j++) {
                        if (j !== 0) {
                            var yPos = centerY - (j * majorGridSize);
                            gridCtx.fillText(j, centerX - 5, yPos + 5);
                        }
                    }
                    
                    // كتابة 0 عند نقطة الأصل
                    gridCtx.fillText('0', centerX - 10, centerY + 15);
                    
                } else if (type === 'dots') {
                    // Draw dot grid
                    var dotSize = 20;
                    gridCtx.fillStyle = '#cccccc';
                    
                    for (var x = 0; x <= width; x += dotSize) {
                        for (var y = 0; y <= height; y += dotSize) {
                            gridCtx.beginPath();
                            gridCtx.arc(x, y, 1, 0, 2 * Math.PI);
                            gridCtx.fill();
                        }
                    }
                }
            };
            
            window.aqsChangeGrid = function(type) {
                aqsDrawGrid(type);
            };
            
            function handleTouch(e) {
                e.preventDefault();
                var touch = e.touches[0];
                var rect = drawCanvas.getBoundingClientRect();
                var mouseEvent = new MouseEvent(e.type === 'touchstart' ? 'mousedown' : 'mousemove', {
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                drawCanvas.dispatchEvent(mouseEvent);
            }
            
            function aqsStartDrawing(e) {
                drawing = true;
                drawCtx.beginPath();
                var rect = drawCanvas.getBoundingClientRect();
                drawCtx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
            }
            
            function aqsDraw(e) {
                if (!drawing) return;
                
                var rect = drawCanvas.getBoundingClientRect();
                var x = e.clientX - rect.left;
                var y = e.clientY - rect.top;
                
                drawCtx.lineWidth = jQuery('#aqs-pen-size').val();
                drawCtx.lineCap = 'round';
                drawCtx.lineJoin = 'round';
                
                if (eraserMode) {
                    drawCtx.globalCompositeOperation = 'destination-out';
                    drawCtx.lineWidth = parseInt(jQuery('#aqs-pen-size').val()) * 3;
                } else {
                    drawCtx.globalCompositeOperation = 'source-over';
                    drawCtx.strokeStyle = jQuery('#aqs-pen-color').val();
                }
                
                drawCtx.lineTo(x, y);
                drawCtx.stroke();
            }
            
            function aqsStopDrawing() {
                drawing = false;
                if (drawCtx) {
                    drawCtx.beginPath();
                }
            }
            
            window.aqsSetColor = function(color) {
                jQuery('#aqs-pen-color').val(color);
                eraserMode = false;
                jQuery('#aqs-eraser-btn').removeClass('active');
            };
            
            window.aqsUpdatePenSize = function(size) {
                jQuery('#aqs-pen-size-value').text(size + 'px');
            };
            
            window.aqsClearCanvas = function() {
                if (!drawCtx) return;
                
                if (confirm('<?php _e('هل تريد مسح اللوحة؟', 'advanced-quiz-system'); ?>')) {
                    // Clear only the drawing canvas, keep the grid
                    drawCtx.clearRect(0, 0, drawCanvas.width, drawCanvas.height);
                    drawCtx.fillStyle = 'rgba(0,0,0,0)'; // Transparent
                    drawCtx.fillRect(0, 0, drawCanvas.width, drawCanvas.height);
                }
            };
            
            window.aqsEraserMode = function() {
                eraserMode = !eraserMode;
                var btn = jQuery('#aqs-eraser-btn');
                
                if (eraserMode) {
                    btn.addClass('active');
                } else {
                    btn.removeClass('active');
                }
            };
            
            window.aqsSaveDrawing = function() {
                if (!drawCanvas) return;
                
                var dataURL = drawCanvas.toDataURL('image/png');
                var link = document.createElement('a');
                link.download = 'drawing-' + Date.now() + '.png';
                link.href = dataURL;
                link.click();
            };
            
            // === CHART MODE FUNCTIONS ===
            
            window.aqsChangeChartType = function(type) {
                currentChartType = type;
                jQuery('.aqs-chart-type').removeClass('active');
                jQuery('.aqs-chart-type[data-type="' + type + '"]').addClass('active');
                
                if (currentChart) {
                    aqsCreateChart();
                }
            };
            
            window.aqsCreateChart = function() {
                var labels = jQuery('#aqs-chart-labels').val().split(',').map(s => s.trim());
                var values = jQuery('#aqs-chart-values').val().split(',').map(s => parseFloat(s.trim()));
                
                if (labels.length === 0 || values.length === 0) {
                    alert('<?php _e('الرجاء إدخال التسميات والقيم', 'advanced-quiz-system'); ?>');
                    return;
                }
                
                // Destroy previous chart
                if (currentChart) {
                    currentChart.destroy();
                }
                
                var ctx = document.getElementById('aqs-chart-canvas');
                
                var colors = [
                    '#2271b1', '#dc3232', '#46b450', '#ffb900', '#00a0d2',
                    '#826eb4', '#f56e28', '#1abc9c', '#e91e63', '#9c27b0'
                ];
                
                currentChart = new Chart(ctx, {
                    type: currentChartType,
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '<?php _e('البيانات', 'advanced-quiz-system'); ?>',
                            data: values,
                            backgroundColor: currentChartType === 'pie' ? colors : 'rgba(34, 113, 177, 0.2)',
                            borderColor: currentChartType === 'pie' ? colors : 'rgba(34, 113, 177, 1)',
                            borderWidth: 2,
                            fill: currentChartType === 'line'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: currentChartType === 'pie',
                                position: 'right',
                                rtl: true,
                                labels: {
                                    font: {
                                        family: 'Arial, Tahoma, sans-serif'
                                    }
                                }
                            }
                        },
                        scales: currentChartType !== 'pie' ? {
                            y: {
                                beginAtZero: true
                            }
                        } : {}
                    }
                });
            };
            
            window.aqsSaveChart = function() {
                if (!currentChart) {
                    alert('<?php _e('يجب إنشاء رسم بياني أولاً', 'advanced-quiz-system'); ?>');
                    return;
                }
                
                var canvas = document.getElementById('aqs-chart-canvas');
                var dataURL = canvas.toDataURL('image/png');
                var link = document.createElement('a');
                link.download = 'chart-' + Date.now() + '.png';
                link.href = dataURL;
                link.click();
            };
            
            window.aqsLoadExample = function(type) {
                var examples = {
                    'grades': {
                        labels: 'أحمد, سارة, محمد, فاطمة, علي',
                        values: '85, 92, 78, 95, 88'
                    },
                    'sales': {
                        labels: 'يناير, فبراير, مارس, أبريل, مايو',
                        values: '50000, 65000, 55000, 80000, 75000'
                    },
                    'temperature': {
                        labels: 'الإثنين, الثلاثاء, الأربعاء, الخميس, الجمعة',
                        values: '28, 30, 32, 29, 31'
                    }
                };
                
                if (examples[type]) {
                    jQuery('#aqs-chart-labels').val(examples[type].labels);
                    jQuery('#aqs-chart-values').val(examples[type].values);
                    aqsCreateChart();
                }
            };
        })();
        </script>
        <?php
    }
}
