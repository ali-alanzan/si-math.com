<?php
/**
 * Adaptive Quiz System Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQS_Adaptive_Quiz {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (get_option('aqs_adaptive_quiz_enabled', '1') == '1') {
            add_filter('tutor_quiz_questions', array($this, 'adaptive_questions'), 10, 2);
            add_action('tutor_quiz/attempt/ended', array($this, 'save_module_score'), 10, 1);
            
            // ربط الكويزات بالكورس تلقائياً
            add_action('tutor_course_contents_after', array($this, 'auto_add_module_quizzes'), 10, 1);
            
            // إضافة Meta Box للكويز لتحديد Module
            add_action('add_meta_boxes', array($this, 'add_quiz_module_metabox'));
            add_action('save_post', array($this, 'save_quiz_module_meta'));
        }
    }
    
    /**
     * إضافة Meta Box للكويز
     */
    public function add_quiz_module_metabox() {
        add_meta_box(
            'aqs_quiz_module',
            __('إعدادات الموديل التكيفي', 'advanced-quiz-system'),
            array($this, 'quiz_module_metabox_callback'),
            'tutor_quiz',
            'side',
            'high'
        );
    }
    
    /**
     * محتوى Meta Box
     */
    public function quiz_module_metabox_callback($post) {
        wp_nonce_field('aqs_quiz_module_nonce', 'aqs_quiz_module_nonce');
        
        $quiz_module = get_post_meta($post->ID, 'quiz_module', true);
        $related_course = get_post_meta($post->ID, 'related_course_id', true);
        
        // الحصول على جميع الكورسات
        $courses = get_posts(array(
            'post_type' => 'courses',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        ?>
        <p>
            <label for="quiz_module"><strong><?php _e('نوع الموديل:', 'advanced-quiz-system'); ?></strong></label>
            <select name="quiz_module" id="quiz_module" class="widefat">
                <option value=""><?php _e('اختر...', 'advanced-quiz-system'); ?></option>
                <option value="module_1" <?php selected($quiz_module, 'module_1'); ?>><?php _e('Module 1 (الأساسي)', 'advanced-quiz-system'); ?></option>
                <option value="module_2" <?php selected($quiz_module, 'module_2'); ?>><?php _e('Module 2 (تكيفي)', 'advanced-quiz-system'); ?></option>
            </select>
        </p>
        
        <p>
            <label for="related_course_id"><strong><?php _e('الكورس المرتبط:', 'advanced-quiz-system'); ?></strong></label>
            <select name="related_course_id" id="related_course_id" class="widefat">
                <option value=""><?php _e('اختر الكورس...', 'advanced-quiz-system'); ?></option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course->ID; ?>" <?php selected($related_course, $course->ID); ?>>
                        <?php echo esc_html($course->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        
        <p class="description">
            <?php _e('💡 Module 1: الامتحان الأساسي الذي يحدد مستوى الطالب', 'advanced-quiz-system'); ?><br>
            <?php _e('💡 Module 2: امتحان تكيفي يعتمد على نتيجة Module 1', 'advanced-quiz-system'); ?>
        </p>
        <?php
    }
    
    /**
     * حفظ بيانات Meta Box
     */
    public function save_quiz_module_meta($post_id) {
        if (!isset($_POST['aqs_quiz_module_nonce']) || !wp_verify_nonce($_POST['aqs_quiz_module_nonce'], 'aqs_quiz_module_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['quiz_module'])) {
            update_post_meta($post_id, 'quiz_module', sanitize_text_field($_POST['quiz_module']));
        }
        
        if (isset($_POST['related_course_id'])) {
            update_post_meta($post_id, 'related_course_id', intval($_POST['related_course_id']));
        }
    }
    
    /**
     * عرض الموديلات تلقائياً في صفحة الكورس
     */
    public function auto_add_module_quizzes($course_id) {
        global $wpdb;
        
        // البحث عن جميع الكويزات المرتبطة بهذا الكورس
        $quizzes = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, pm.meta_value as quiz_module
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'tutor_quiz'
            AND pm.meta_key = 'quiz_module'
            AND p.ID IN (
                SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = 'related_course_id' 
                AND meta_value = %d
            )
            ORDER BY pm.meta_value ASC
        ", $course_id));
        
        if (empty($quizzes)) {
            return;
        }
        
        echo '<div class="aqs-adaptive-modules">';
        echo '<h3 class="aqs-modules-title">' . __('📝 الامتحانات التكيفية', 'advanced-quiz-system') . '</h3>';
        echo '<div class="aqs-modules-list">';
        
        foreach ($quizzes as $quiz) {
            $quiz_post = get_post($quiz->ID);
            $module_name = $quiz->quiz_module == 'module_1' ? __('الموديل الأساسي', 'advanced-quiz-system') : __('الموديل التكيفي', 'advanced-quiz-system');
            $quiz_url = get_permalink($quiz->ID);
            
            echo '<div class="aqs-module-item aqs-' . esc_attr($quiz->quiz_module) . '">';
            echo '<span class="aqs-module-badge">' . esc_html($module_name) . '</span>';
            echo '<h4>' . esc_html($quiz_post->post_title) . '</h4>';
            echo '<a href="' . esc_url($quiz_url) . '" class="aqs-start-quiz-btn">' . __('ابدأ الامتحان', 'advanced-quiz-system') . '</a>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Get adaptive questions based on previous module score
     */
    public function adaptive_questions($questions, $quiz_id) {
        $user_id = get_current_user_id();
        
        // Check if this is Module 2
        $quiz_module = get_post_meta($quiz_id, 'quiz_module', true);
        
        if ($quiz_module != 'module_2') {
            return $questions;
        }
        
        // Get Module 1 score
        $module1_score = get_user_meta($user_id, 'aqs_module_1_score_' . get_post_meta($quiz_id, 'related_course_id', true), true);
        
        if (!$module1_score) {
            return $questions;
        }
        
        $threshold = get_option('aqs_easy_threshold', 20);
        
        // If score is below threshold, return easy questions
        if ($module1_score < $threshold) {
            $filtered_questions = array();
            foreach ($questions as $question) {
                $difficulty = get_post_meta($question->question_id, 'question_difficulty', true);
                if ($difficulty == 'easy' || empty($difficulty)) {
                    $filtered_questions[] = $question;
                }
            }
            return !empty($filtered_questions) ? $filtered_questions : $questions;
        } else {
            // Return hard questions
            $filtered_questions = array();
            foreach ($questions as $question) {
                $difficulty = get_post_meta($question->question_id, 'question_difficulty', true);
                if ($difficulty == 'hard') {
                    $filtered_questions[] = $question;
                }
            }
            return !empty($filtered_questions) ? $filtered_questions : $questions;
        }
    }
    
    /**
     * Save module score
     */
    public function save_module_score($attempt_id) {
        $attempt = tutor_utils()->get_attempt($attempt_id);
        
        if (!$attempt) {
            return;
        }
        
        $user_id = $attempt->user_id;
        $quiz_id = $attempt->quiz_id;
        $quiz_module = get_post_meta($quiz_id, 'quiz_module', true);
        
        // Only save Module 1 scores
        if ($quiz_module == 'module_1') {
            $total_marks = $attempt->total_marks;
            $earned_marks = $attempt->earned_marks;
            
            if ($total_marks > 0) {
                $percentage = ($earned_marks / $total_marks) * 100;
                $course_id = get_post_meta($quiz_id, 'related_course_id', true);
                update_user_meta($user_id, 'aqs_module_1_score_' . $course_id, $percentage);
            }
        }
    }
}
