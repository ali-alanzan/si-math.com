<?php
/**
 * Question Tracker Class - ENHANCED VERSION
 * Shows visual progress with green/red indicators for answered questions
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQS_Question_Tracker {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (get_option('aqs_tracker_enabled', '1') == '1') {
            // Hook into Tutor quiz display
            add_action('tutor_quiz/single/before/top', array($this, 'render_question_tracker'));
            add_action('wp_ajax_aqs_update_question_status', array($this, 'ajax_update_question_status'));
            add_action('wp_ajax_nopriv_aqs_update_question_status', array($this, 'ajax_update_question_status'));
        }
    }
    
    /**
     * Render question tracker
     */
    public function render_question_tracker() {
        global $post;
        
        if ($post->post_type !== 'tutor_quiz') {
            return;
        }
        
        $quiz_id = $post->ID;
        
        // Get quiz questions
        $questions = tutor_utils()->get_qa_questions($quiz_id);
        
        if (empty($questions)) {
            return;
        }
        
        $total_questions = count($questions);
        
        ?>
        <div class="aqs-question-tracker-container">
            <div class="aqs-tracker-header">
                <h3>
                    📊 <?php _e('تتبع الأسئلة', 'advanced-quiz-system'); ?>
                    <span id="aqs-tracker-count">
                        (<span id="aqs-answered-count">0</span> / <?php echo $total_questions; ?>)
                    </span>
                </h3>
                <div class="aqs-tracker-legend">
                    <span class="aqs-legend-item">
                        <span class="aqs-legend-box aqs-correct-box"></span>
                        <?php _e('إجابة صحيحة', 'advanced-quiz-system'); ?>
                    </span>
                    <span class="aqs-legend-item">
                        <span class="aqs-legend-box aqs-incorrect-box"></span>
                        <?php _e('إجابة خاطئة', 'advanced-quiz-system'); ?>
                    </span>
                    <span class="aqs-legend-item">
                        <span class="aqs-legend-box aqs-unanswered-box"></span>
                        <?php _e('لم يتم الإجابة', 'advanced-quiz-system'); ?>
                    </span>
                </div>
            </div>
            
            <div class="aqs-progress-bar-container">
                <div class="aqs-progress-bar">
                    <div class="aqs-progress-fill" id="aqs-progress-fill" style="width: 0%"></div>
                </div>
                <div class="aqs-progress-text" id="aqs-progress-text">0%</div>
            </div>
            
            <div class="aqs-question-grid" id="aqs-question-grid">
                <?php
                $question_number = 1;
                foreach ($questions as $question) {
                    ?>
                    <div class="aqs-question-item aqs-status-unanswered" 
                         id="aqs-question-<?php echo $question->question_id; ?>"
                         data-question-id="<?php echo $question->question_id; ?>"
                         data-question-number="<?php echo $question_number; ?>">
                        <div class="aqs-question-number"><?php echo $question_number; ?></div>
                        <div class="aqs-question-status-icon">
                            <span class="aqs-icon-unanswered">⏱️</span>
                            <span class="aqs-icon-correct" style="display:none;">✓</span>
                            <span class="aqs-icon-incorrect" style="display:none;">✗</span>
                        </div>
                    </div>
                    <?php
                    $question_number++;
                }
                ?>
            </div>
            
            <div class="aqs-tracker-stats">
                <div class="aqs-stat">
                    <span class="aqs-stat-label"><?php _e('صحيحة', 'advanced-quiz-system'); ?>:</span>
                    <span class="aqs-stat-value aqs-correct-text" id="aqs-correct-count">0</span>
                </div>
                <div class="aqs-stat">
                    <span class="aqs-stat-label"><?php _e('خاطئة', 'advanced-quiz-system'); ?>:</span>
                    <span class="aqs-stat-value aqs-incorrect-text" id="aqs-incorrect-count">0</span>
                </div>
                <div class="aqs-stat">
                    <span class="aqs-stat-label"><?php _e('متبقية', 'advanced-quiz-system'); ?>:</span>
                    <span class="aqs-stat-value" id="aqs-remaining-count"><?php echo $total_questions; ?></span>
                </div>
            </div>
        </div>
        
        <style>
        .aqs-question-tracker-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .aqs-tracker-header {
            margin-bottom: 20px;
        }
        
        .aqs-tracker-header h3 {
            margin: 0 0 15px 0;
            font-size: 24px;
            color: white;
        }
        
        .aqs-tracker-legend {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 13px;
        }
        
        .aqs-legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .aqs-legend-box {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            display: inline-block;
            border: 2px solid white;
        }
        
        .aqs-correct-box {
            background-color: #10b981;
        }
        
        .aqs-incorrect-box {
            background-color: #ef4444;
        }
        
        .aqs-unanswered-box {
            background-color: rgba(255,255,255,0.3);
        }
        
        .aqs-progress-bar-container {
            position: relative;
            margin-bottom: 20px;
        }
        
        .aqs-progress-bar {
            height: 30px;
            background-color: rgba(255,255,255,0.2);
            border-radius: 15px;
            overflow: hidden;
        }
        
        .aqs-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            transition: width 0.5s ease;
            border-radius: 15px;
        }
        
        .aqs-progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: bold;
            font-size: 14px;
            color: white;
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        
        .aqs-question-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .aqs-question-item {
            background-color: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .aqs-question-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .aqs-question-item.aqs-status-correct {
            background-color: #10b981;
            border-color: #059669;
        }
        
        .aqs-question-item.aqs-status-incorrect {
            background-color: #ef4444;
            border-color: #dc2626;
        }
        
        .aqs-question-number {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .aqs-question-status-icon {
            font-size: 24px;
        }
        
        .aqs-tracker-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            background-color: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
        }
        
        .aqs-stat {
            text-align: center;
        }
        
        .aqs-stat-label {
            display: block;
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .aqs-stat-value {
            display: block;
            font-size: 28px;
            font-weight: bold;
        }
        
        .aqs-correct-text {
            color: #10b981;
        }
        
        .aqs-incorrect-text {
            color: #ef4444;
        }
        
        @media (max-width: 768px) {
            .aqs-question-grid {
                grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
                gap: 8px;
            }
            
            .aqs-question-item {
                padding: 10px;
            }
            
            .aqs-question-number {
                font-size: 16px;
            }
            
            .aqs-tracker-stats {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var totalQuestions = <?php echo $total_questions; ?>;
            var answeredQuestions = 0;
            var correctAnswers = 0;
            var incorrectAnswers = 0;
            
            // Monitor answer changes (works with Tutor LMS quiz system)
            function monitorQuizAnswers() {
                // Tutor uses radio buttons and checkboxes for answers
                $('input[type="radio"], input[type="checkbox"]').on('change', function() {
                    var $questionContainer = $(this).closest('.tutor-quiz-question, .quiz-question');
                    var questionId = $questionContainer.data('question-id') || extractQuestionId($questionContainer);
                    
                    if (questionId) {
                        markQuestionAsAnswered(questionId);
                    }
                });
                
                // Monitor text inputs and textareas
                $('input[type="text"], textarea').on('blur', function() {
                    if ($(this).val().trim() !== '') {
                        var $questionContainer = $(this).closest('.tutor-quiz-question, .quiz-question');
                        var questionId = $questionContainer.data('question-id') || extractQuestionId($questionContainer);
                        
                        if (questionId) {
                            markQuestionAsAnswered(questionId);
                        }
                    }
                });
            }
            
            function extractQuestionId($container) {
                // Try to extract question ID from container classes or attributes
                var classes = $container.attr('class');
                var match = classes ? classes.match(/question-(\d+)/) : null;
                return match ? match[1] : null;
            }
            
            function markQuestionAsAnswered(questionId, isCorrect) {
                var $questionItem = $('#aqs-question-' + questionId);
                
                if ($questionItem.length === 0) {
                    return;
                }
                
                // If already answered, don't count again
                if (!$questionItem.hasClass('aqs-status-unanswered')) {
                    return;
                }
                
                answeredQuestions++;
                
                // For now, mark as answered (we'll know if correct/incorrect after submission)
                // During quiz, we just mark as answered
                $questionItem.removeClass('aqs-status-unanswered');
                $questionItem.find('.aqs-icon-unanswered').hide();
                $questionItem.find('.aqs-icon-correct').show();
                $questionItem.addClass('aqs-status-correct'); // Temporary, will update after submission
                
                updateStats();
            }
            
            function updateStats() {
                var remainingQuestions = totalQuestions - answeredQuestions;
                var percentage = Math.round((answeredQuestions / totalQuestions) * 100);
                
                $('#aqs-answered-count').text(answeredQuestions);
                $('#aqs-correct-count').text(correctAnswers);
                $('#aqs-incorrect-count').text(incorrectAnswers);
                $('#aqs-remaining-count').text(remainingQuestions);
                $('#aqs-progress-fill').css('width', percentage + '%');
                $('#aqs-progress-text').text(percentage + '%');
            }
            
            // Initialize monitoring
            monitorQuizAnswers();
            
            // Re-initialize when new questions load (for multi-page quizzes)
            $(document).on('tutor_quiz_question_loaded', function() {
                monitorQuizAnswers();
            });
            
            // Click on question box to scroll to that question
            $('.aqs-question-item').on('click', function() {
                var questionNumber = $(this).data('question-number');
                var $targetQuestion = $('.tutor-quiz-question').eq(questionNumber - 1);
                
                if ($targetQuestion.length) {
                    $('html, body').animate({
                        scrollTop: $targetQuestion.offset().top - 100
                    }, 500);
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Update question status (called after quiz submission)
     */
    public function ajax_update_question_status() {
        check_ajax_referer('aqs_nonce', 'nonce');
        
        $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        $is_correct = isset($_POST['is_correct']) ? (bool)$_POST['is_correct'] : false;
        
        if ($question_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid question ID'));
            return;
        }
        
        wp_send_json_success(array(
            'question_id' => $question_id,
            'is_correct' => $is_correct
        ));
    }
}
