<?php
/**
 * Adaptive Quiz System Class - FIXED VERSION
 * حل مشكلة 404 وإضافة صفحة إدارة للموديلات
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
            
            // FIX: إضافة flush rewrite rules عند التفعيل
            add_action('init', array($this, 'register_quiz_rewrite_rules'));
            register_activation_hook(__FILE__, array($this, 'flush_rewrite_on_activation'));
            
            // إضافة قائمة إدارة للموديلات
            add_action('admin_menu', array($this, 'add_admin_menu'));
        }
    }
    
    /**
     * تسجيل rewrite rules للكويزات - FIX لمشكلة 404
     */
    public function register_quiz_rewrite_rules() {
        // Ensure tutor_quiz post type exists
        if (!post_type_exists('tutor_quiz')) {
            return;
        }
        
        // Make sure tutor_quiz has proper permalink structure
        $post_type_object = get_post_type_object('tutor_quiz');
        if ($post_type_object && !$post_type_object->public) {
            $post_type_object->public = true;
            $post_type_object->publicly_queryable = true;
        }
    }
    
    /**
     * Flush rewrite rules on plugin activation
     */
    public function flush_rewrite_on_activation() {
        $this->register_quiz_rewrite_rules();
        flush_rewrite_rules();
    }
    
    /**
     * إضافة صفحة في Admin لإدارة الموديلات
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tutor',
            __('إدارة الموديلات التكيفية', 'advanced-quiz-system'),
            __('الموديلات التكيفية', 'advanced-quiz-system'),
            'manage_options',
            'aqs-adaptive-modules',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * رندر صفحة الإدارة
     */
    public function render_admin_page() {
        global $wpdb;
        
        // Get all courses
        $courses = get_posts(array(
            'post_type' => 'courses',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-welcome-learn-more"></span>
                <?php _e('إدارة الموديلات التكيفية', 'advanced-quiz-system'); ?>
            </h1>
            
            <div class="notice notice-info">
                <p>
                    <strong>💡 <?php _e('كيف تعمل الموديلات التكيفية؟', 'advanced-quiz-system'); ?></strong><br>
                    <?php _e('Module 1: امتحان أساسي يحدد مستوى الطالب', 'advanced-quiz-system'); ?><br>
                    <?php _e('Module 2: امتحان تكيفي يعرض أسئلة سهلة أو صعبة حسب نتيجة Module 1', 'advanced-quiz-system'); ?>
                </p>
            </div>
            
            <?php
            // Handle Flush Rewrite Rules
            if (isset($_POST['flush_rules']) && check_admin_referer('aqs_flush_rules')) {
                flush_rewrite_rules();
                echo '<div class="notice notice-success"><p>' . __('✅ تم تحديث روابط الموديلات بنجاح!', 'advanced-quiz-system') . '</p></div>';
            }
            ?>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2><?php _e('🔧 حل مشكلة 404', 'advanced-quiz-system'); ?></h2>
                <p><?php _e('إذا كانت الموديلات تعطي خطأ 404، اضغط على الزر التالي:', 'advanced-quiz-system'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('aqs_flush_rules'); ?>
                    <button type="submit" name="flush_rules" class="button button-primary">
                        🔄 <?php _e('إعادة تحميل الروابط', 'advanced-quiz-system'); ?>
                    </button>
                </form>
            </div>
            
            <h2 style="margin-top: 30px;">📚 <?php _e('الموديلات حسب الكورس', 'advanced-quiz-system'); ?></h2>
            
            <?php foreach ($courses as $course): ?>
                <?php
                // Get quizzes for this course
                $quizzes = $wpdb->get_results($wpdb->prepare("
                    SELECT p.ID, p.post_title, pm.meta_value as quiz_module
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'quiz_module'
                    WHERE p.post_type = 'tutor_quiz'
                    AND p.ID IN (
                        SELECT post_id FROM {$wpdb->postmeta} 
                        WHERE meta_key = 'related_course_id' 
                        AND meta_value = %d
                    )
                    ORDER BY pm.meta_value ASC
                ", $course->ID));
                
                if (empty($quizzes)) {
                    continue;
                }
                ?>
                
                <div class="card" style="margin-bottom: 20px;">
                    <h3><?php echo esc_html($course->post_title); ?></h3>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('اسم الكويز', 'advanced-quiz-system'); ?></th>
                                <th><?php _e('نوع الموديل', 'advanced-quiz-system'); ?></th>
                                <th><?php _e('الرابط', 'advanced-quiz-system'); ?></th>
                                <th><?php _e('الإجراءات', 'advanced-quiz-system'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quizzes as $quiz): ?>
                                <?php
                                $module_name = '';
                                $module_badge = '';
                                
                                if ($quiz->quiz_module == 'module_1') {
                                    $module_name = __('Module 1 - الأساسي', 'advanced-quiz-system');
                                    $module_badge = '<span class="aqs-badge aqs-badge-primary">M1</span>';
                                } elseif ($quiz->quiz_module == 'module_2') {
                                    $module_name = __('Module 2 - التكيفي', 'advanced-quiz-system');
                                    $module_badge = '<span class="aqs-badge aqs-badge-success">M2</span>';
                                } else {
                                    $module_name = __('غير محدد', 'advanced-quiz-system');
                                    $module_badge = '<span class="aqs-badge aqs-badge-secondary">--</span>';
                                }
                                
                                $quiz_url = get_permalink($quiz->ID);
                                $edit_url = get_edit_post_link($quiz->ID);
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($quiz->post_title); ?></strong></td>
                                    <td><?php echo $module_badge . ' ' . esc_html($module_name); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url($quiz_url); ?>" target="_blank">
                                            <?php _e('عرض', 'advanced-quiz-system'); ?> 
                                            <span class="dashicons dashicons-external"></span>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                                            <?php _e('تعديل', 'advanced-quiz-system'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            
            <style>
                .aqs-badge {
                    display: inline-block;
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: bold;
                    color: #fff;
                }
                .aqs-badge-primary { background: #2271b1; }
                .aqs-badge-success { background: #46b450; }
                .aqs-badge-secondary { background: #999; }
            </style>
        </div>
        <?php
    }
    
    /**
     * إضافة Meta Box للكويز
     */
    public function add_quiz_module_metabox() {
        add_meta_box(
            'aqs_quiz_module',
            __('⚙️ إعدادات الموديل التكيفي', 'advanced-quiz-system'),
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
        <div class="aqs-metabox">
            <p>
                <label for="quiz_module"><strong><?php _e('نوع الموديل:', 'advanced-quiz-system'); ?></strong></label>
                <select name="quiz_module" id="quiz_module" class="widefat" style="margin-top: 5px;">
                    <option value=""><?php _e('اختر...', 'advanced-quiz-system'); ?></option>
                    <option value="module_1" <?php selected($quiz_module, 'module_1'); ?>>
                        <?php _e('🎯 Module 1 (الأساسي)', 'advanced-quiz-system'); ?>
                    </option>
                    <option value="module_2" <?php selected($quiz_module, 'module_2'); ?>>
                        <?php _e('🔥 Module 2 (تكيفي)', 'advanced-quiz-system'); ?>
                    </option>
                </select>
            </p>
            
            <p>
                <label for="related_course_id"><strong><?php _e('الكورس المرتبط:', 'advanced-quiz-system'); ?></strong></label>
                <select name="related_course_id" id="related_course_id" class="widefat" style="margin-top: 5px;">
                    <option value=""><?php _e('اختر الكورس...', 'advanced-quiz-system'); ?></option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course->ID; ?>" <?php selected($related_course, $course->ID); ?>>
                            <?php echo esc_html($course->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <div class="aqs-info-box" style="background: #f0f6fc; border-right: 3px solid #2271b1; padding: 10px; margin-top: 15px;">
                <p style="margin: 0; font-size: 12px; line-height: 1.6;">
                    <strong>💡 <?php _e('ملاحظة:', 'advanced-quiz-system'); ?></strong><br>
                    <strong>Module 1:</strong> <?php _e('الامتحان الأساسي الذي يحدد مستوى الطالب', 'advanced-quiz-system'); ?><br>
                    <strong>Module 2:</strong> <?php _e('امتحان تكيفي يعتمد على نتيجة Module 1', 'advanced-quiz-system'); ?>
                </p>
            </div>
        </div>
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
        
        // FIX: Flush rewrite rules after saving
        flush_rewrite_rules();
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
        
        echo '<div class="aqs-adaptive-modules" style="margin: 30px 0; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">';
        echo '<h3 style="color: #fff; font-size: 24px; margin-bottom: 20px; text-align: center;">📝 الامتحانات التكيفية</h3>';
        echo '<div class="aqs-modules-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">';
        
        foreach ($quizzes as $quiz) {
            $quiz_post = get_post($quiz->ID);
            $module_name = $quiz->quiz_module == 'module_1' ? __('الموديل الأساسي', 'advanced-quiz-system') : __('الموديل التكيفي', 'advanced-quiz-system');
            $module_icon = $quiz->quiz_module == 'module_1' ? '🎯' : '🔥';
            $quiz_url = get_permalink($quiz->ID);
            
            if (!$quiz_url || is_wp_error($quiz_url)) {
                $quiz_url = home_url('?p=' . $quiz->ID); // Fallback URL
            }
            
            echo '<div style="background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.3s;" onmouseover="this.style.transform=\'translateY(-5px)\'" onmouseout="this.style.transform=\'translateY(0)\'">';
            echo '<span style="display: inline-block; padding: 5px 12px; background: #f0f6fc; color: #2271b1; border-radius: 20px; font-size: 12px; font-weight: bold; margin-bottom: 10px;">' . $module_icon . ' ' . esc_html($module_name) . '</span>';
            echo '<h4 style="color: #333; font-size: 18px; margin: 10px 0;">' . esc_html($quiz_post->post_title) . '</h4>';
            echo '<a href="' . esc_url($quiz_url) . '" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; transition: opacity 0.3s;" onmouseover="this.style.opacity=\'0.9\'" onmouseout="this.style.opacity=\'1\'">ابدأ الامتحان →</a>';
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
