<?php
/**
 * Mistakes Tracker Class
 * يجمع كل الأسئلة الغلط مع الإجابات الصحيحة
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQS_Mistakes_Tracker {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Save mistakes after quiz attempt
        add_action('tutor_quiz/attempt/ended', array($this, 'save_mistakes'), 10, 1);
        
        // Shortcode to display mistakes
        add_shortcode('aqs_mistakes', array($this, 'mistakes_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_aqs_get_mistakes', array($this, 'ajax_get_mistakes'));
        add_action('wp_ajax_aqs_clear_mistakes', array($this, 'ajax_clear_mistakes'));
        
        // Add mistakes page to Tutor dashboard
        add_filter('tutor_dashboard/nav_items', array($this, 'add_mistakes_nav'), 20);
        
        // Register custom query var
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // Handle custom dashboard page
        add_action('template_redirect', array($this, 'handle_mistakes_page'));
    }
    
    /**
     * Create mistakes table on activation
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_mistakes';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            quiz_id bigint(20) NOT NULL,
            question_id bigint(20) NOT NULL,
            question_title text NOT NULL,
            question_type varchar(50) NOT NULL,
            user_answer text,
            correct_answer text NOT NULL,
            explanation text,
            course_id bigint(20) NOT NULL,
            attempt_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY quiz_id (quiz_id),
            KEY course_id (course_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Save mistakes after quiz attempt
     */
    public function save_mistakes($attempt_id) {
        global $wpdb;
        
        $attempt = tutor_utils()->get_attempt($attempt_id);
        if (!$attempt) {
            return;
        }
        
        $user_id = $attempt->user_id;
        $quiz_id = $attempt->quiz_id;
        $course_id = tutor_utils()->get_course_id_by_quiz($quiz_id);
        
        // Get all answers for this attempt
        $answers = tutor_utils()->get_quiz_answers_by_attempt_id($attempt_id);
        
        if (!$answers) {
            return;
        }
        
        $table_name = $wpdb->prefix . 'aqs_mistakes';
        
        foreach ($answers as $answer) {
            // Only save wrong answers
            if ($answer->is_correct == 0) {
                $question = tutor_utils()->get_qa_question($answer->question_id);
                
                if (!$question) {
                    continue;
                }
                
                // Get correct answer based on question type
                $correct_answer = $this->get_correct_answer($answer->question_id, $question->question_type);
                
                // Get explanation if available
                $explanation = get_post_meta($answer->question_id, '_question_explanation', true);
                
                // Insert mistake
                $wpdb->insert(
                    $table_name,
                    array(
                        'user_id' => $user_id,
                        'quiz_id' => $quiz_id,
                        'question_id' => $answer->question_id,
                        'question_title' => $question->question_title,
                        'question_type' => $question->question_type,
                        'user_answer' => $answer->given_answer,
                        'correct_answer' => $correct_answer,
                        'explanation' => $explanation,
                        'course_id' => $course_id,
                        'attempt_id' => $attempt_id,
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
                );
            }
        }
    }
    
    /**
     * Get correct answer for a question
     */
    private function get_correct_answer($question_id, $question_type) {
        global $wpdb;
        
        switch ($question_type) {
            case 'true_false':
            case 'single_choice':
                $correct = $wpdb->get_var($wpdb->prepare(
                    "SELECT answer_title FROM {$wpdb->prefix}tutor_quiz_question_answers 
                     WHERE belongs_question_id = %d AND is_correct = 1",
                    $question_id
                ));
                return $correct ? $correct : '';
                
            case 'multiple_choice':
                $correct_answers = $wpdb->get_col($wpdb->prepare(
                    "SELECT answer_title FROM {$wpdb->prefix}tutor_quiz_question_answers 
                     WHERE belongs_question_id = %d AND is_correct = 1",
                    $question_id
                ));
                return !empty($correct_answers) ? implode(', ', $correct_answers) : '';
                
            case 'fill_in_the_blank':
            case 'short_answer':
            case 'open_ended':
                $answer = get_post_meta($question_id, '_question_answer', true);
                return $answer ? $answer : '';
                
            default:
                return '';
        }
    }
    
    /**
     * Get user mistakes
     */
    public function get_user_mistakes($user_id, $course_id = 0, $limit = 50) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_mistakes';
        
        $where = array($wpdb->prepare('user_id = %d', $user_id));
        
        if ($course_id > 0) {
            $where[] = $wpdb->prepare('course_id = %d', $course_id);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $mistakes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE $where_clause 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));
        
        return $mistakes;
    }
    
    /**
     * Get mistakes count
     */
    public function get_mistakes_count($user_id, $course_id = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_mistakes';
        
        $where = array($wpdb->prepare('user_id = %d', $user_id));
        
        if ($course_id > 0) {
            $where[] = $wpdb->prepare('course_id = %d', $course_id);
        }
        
        $where_clause = implode(' AND ', $where);
        
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $where_clause");
    }
    
    /**
     * Add mistakes nav to Tutor dashboard
     */
    public function add_mistakes_nav($items) {
        $user_id = get_current_user_id();
        $mistakes_count = $this->get_mistakes_count($user_id);
        
        $items['mistakes'] = array(
            'title' => sprintf(__('الأخطاء (%d)', 'advanced-quiz-system'), $mistakes_count),
            'icon' => 'tutor-icon-warning',
            'url' => tutor_utils()->get_tutor_dashboard_url('mistakes')
        );
        
        return $items;
    }
    
    /**
     * Add query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'tutor_dashboard_page';
        $vars[] = 'tutor_dashboard_sub_page';
        return $vars;
    }
    
    /**
     * Handle mistakes page
     */
    public function handle_mistakes_page() {
        if (get_query_var('tutor_dashboard_page') === 'mistakes') {
            include AQS_PLUGIN_DIR . 'templates/mistakes-page.php';
            exit;
        }
    }
    
    /**
     * Shortcode to display mistakes
     */
    public function mistakes_shortcode($atts) {
        $atts = shortcode_atts(array(
            'course_id' => 0,
            'limit' => 50
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<p>' . __('يجب تسجيل الدخول لعرض الأخطاء', 'advanced-quiz-system') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $mistakes = $this->get_user_mistakes($user_id, $atts['course_id'], $atts['limit']);
        
        ob_start();
        include AQS_PLUGIN_DIR . 'templates/mistakes-list.php';
        return ob_get_clean();
    }
    
    /**
     * AJAX: Get mistakes
     */
    public function ajax_get_mistakes() {
        check_ajax_referer('aqs_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('غير مصرح', 'advanced-quiz-system')));
        }
        
        $user_id = get_current_user_id();
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        
        $mistakes = $this->get_user_mistakes($user_id, $course_id, $limit);
        
        wp_send_json_success(array(
            'mistakes' => $mistakes,
            'count' => count($mistakes)
        ));
    }
    
    /**
     * AJAX: Clear mistakes
     */
    public function ajax_clear_mistakes() {
        check_ajax_referer('aqs_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('غير مصرح', 'advanced-quiz-system')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_mistakes';
        $user_id = get_current_user_id();
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        
        if ($course_id > 0) {
            $wpdb->delete(
                $table_name,
                array('user_id' => $user_id, 'course_id' => $course_id),
                array('%d', '%d')
            );
        } else {
            $wpdb->delete(
                $table_name,
                array('user_id' => $user_id),
                array('%d')
            );
        }
        
        wp_send_json_success(array('message' => __('تم مسح الأخطاء بنجاح', 'advanced-quiz-system')));
    }
}
