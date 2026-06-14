<?php
/**
 * Module Manager Class - NEW FEATURE!
 * Easy interface to manage adaptive modules
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQS_Module_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add metabox to quiz edit page
        add_action('add_meta_boxes', array($this, 'add_quiz_metabox'));
        add_action('save_post_tutor_quiz', array($this, 'save_quiz_meta'), 10, 2);
    }
    
    /**
     * Add admin menu under Tutor LMS
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tutor',
            __('إدارة الموديلات التكيفية', 'advanced-quiz-system'),
            __('الموديلات التكيفية', 'advanced-quiz-system'),
            'manage_tutor',
            'aqs-modules',
            array($this, 'render_modules_page')
        );
    }
    
    /**
     * Render modules management page
     */
    public function render_modules_page() {
        global $wpdb;
        
        // Get all quizzes with their module settings
        $quizzes = get_posts(array(
            'post_type' => 'tutor_quiz',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        ?>
        <div class="wrap aqs-modules-page">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-chart-area"></span>
                <?php _e('إدارة الموديلات التكيفية', 'advanced-quiz-system'); ?>
            </h1>
            <a href="<?php echo admin_url('post-new.php?post_type=tutor_quiz'); ?>" class="page-title-action">
                <?php _e('إضافة امتحان جديد', 'advanced-quiz-system'); ?>
            </a>
            
            <hr class="wp-header-end">
            
            <div class="aqs-modules-intro">
                <div class="aqs-intro-card">
                    <h2>📚 <?php _e('ما هي الموديلات التكيفية؟', 'advanced-quiz-system'); ?></h2>
                    <p><?php _e('الموديلات التكيفية هي سلسلة من الاختبارات المترابطة التي تتكيف مع مستوى الطالب. Module 1 يحدد مستوى الطالب، ثم Module 2 يعرض أسئلة سهلة أو صعبة بناءً على الأداء.', 'advanced-quiz-system'); ?></p>
                    
                    <div class="aqs-workflow">
                        <div class="aqs-workflow-step">
                            <div class="aqs-step-number">1️⃣</div>
                            <div class="aqs-step-content">
                                <strong><?php _e('Module 1 - تحديد المستوى', 'advanced-quiz-system'); ?></strong>
                                <p><?php _e('اختبار تحديد مستوى الطالب', 'advanced-quiz-system'); ?></p>
                            </div>
                        </div>
                        <div class="aqs-workflow-arrow">→</div>
                        <div class="aqs-workflow-step">
                            <div class="aqs-step-number">2️⃣</div>
                            <div class="aqs-step-content">
                                <strong><?php _e('النتيجة تُحفظ', 'advanced-quiz-system'); ?></strong>
                                <p><?php _e('النظام يحفظ أداء الطالب', 'advanced-quiz-system'); ?></p>
                            </div>
                        </div>
                        <div class="aqs-workflow-arrow">→</div>
                        <div class="aqs-workflow-step">
                            <div class="aqs-step-number">3️⃣</div>
                            <div class="aqs-step-content">
                                <strong><?php _e('Module 2 - التكيف', 'advanced-quiz-system'); ?></strong>
                                <p><?php _e('يعرض أسئلة مناسبة للمستوى', 'advanced-quiz-system'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="aqs-modules-content">
                <h2><?php _e('جميع الامتحانات', 'advanced-quiz-system'); ?></h2>
                
                <?php if (empty($quizzes)): ?>
                    <div class="aqs-empty-state">
                        <div class="aqs-empty-icon">📝</div>
                        <h3><?php _e('لا توجد امتحانات بعد', 'advanced-quiz-system'); ?></h3>
                        <p><?php _e('ابدأ بإنشاء أول امتحان لك', 'advanced-quiz-system'); ?></p>
                        <a href="<?php echo admin_url('post-new.php?post_type=tutor_quiz'); ?>" class="button button-primary button-large">
                            <?php _e('إنشاء امتحان جديد', 'advanced-quiz-system'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped aqs-modules-table">
                        <thead>
                            <tr>
                                <th><?php _e('عنوان الامتحان', 'advanced-quiz-system'); ?></th>
                                <th><?php _e('نوع الموديل', 'advanced-quiz-system'); ?></th>
                                <th><?php _e('الكورس المرتبط', 'advanced-quiz-system'); ?></th>
                                <th><?php _e('عدد الأسئلة', 'advanced-quiz-system'); ?></th>
                                <th><?php _e('الحالة', 'advanced-quiz-system'); ?></th>
                                <th><?php _e('الإجراءات', 'advanced-quiz-system'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quizzes as $quiz): 
                                $module_type = get_post_meta($quiz->ID, 'aqs_quiz_module', true);
                                $related_course = get_post_meta($quiz->ID, 'aqs_related_course_id', true);
                                $question_count = $this->get_quiz_question_count($quiz->ID);
                            ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo get_edit_post_link($quiz->ID); ?>">
                                            <?php echo esc_html($quiz->post_title); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <?php echo $this->get_module_badge($module_type); ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($related_course) {
                                        $course = get_post($related_course);
                                        echo $course ? esc_html($course->post_title) : '—';
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="aqs-question-count"><?php echo $question_count; ?> سؤال</span>
                                </td>
                                <td>
                                    <?php echo $this->get_status_badge($quiz->post_status); ?>
                                </td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($quiz->ID); ?>" class="button button-small">
                                        <?php _e('تعديل', 'advanced-quiz-system'); ?>
                                    </a>
                                    <a href="<?php echo get_permalink($quiz->ID); ?>" class="button button-small" target="_blank">
                                        <?php _e('معاينة', 'advanced-quiz-system'); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Module Statistics -->
                    <div class="aqs-module-stats">
                        <h2><?php _e('إحصائيات الموديلات', 'advanced-quiz-system'); ?></h2>
                        <div class="aqs-stats-grid">
                            <?php echo $this->get_module_statistics(); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .aqs-modules-page {
            max-width: 1400px;
        }
        .aqs-intro-card {
            background: #f0f6fc;
            border: 2px solid #2271b1;
            border-radius: 8px;
            padding: 30px;
            margin: 20px 0;
        }
        .aqs-intro-card h2 {
            margin-top: 0;
            color: #2271b1;
        }
        .aqs-workflow {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        .aqs-workflow-step {
            flex: 1;
            min-width: 200px;
            background: white;
            border: 2px solid #2271b1;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        .aqs-step-number {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .aqs-workflow-arrow {
            font-size: 2em;
            color: #2271b1;
            font-weight: bold;
        }
        .aqs-modules-table th {
            background: #f0f6fc;
            font-weight: 600;
        }
        .aqs-empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #f9f9f9;
            border-radius: 8px;
            margin: 20px 0;
        }
        .aqs-empty-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        .aqs-module-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .aqs-module-1 {
            background: #e7f5ff;
            color: #1971c2;
        }
        .aqs-module-2 {
            background: #d3f9d8;
            color: #2f9e44;
        }
        .aqs-module-none {
            background: #f3f4f6;
            color: #6b7280;
        }
        .aqs-status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .aqs-status-publish {
            background: #d4edda;
            color: #155724;
        }
        .aqs-status-draft {
            background: #fff3cd;
            color: #856404;
        }
        .aqs-question-count {
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .aqs-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .aqs-stat-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        .aqs-stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #2271b1;
            margin: 10px 0;
        }
        .aqs-stat-label {
            color: #666;
            font-size: 14px;
        }
        </style>
        <?php
    }
    
    /**
     * Add metabox to quiz edit page
     */
    public function add_quiz_metabox() {
        add_meta_box(
            'aqs_module_settings',
            __('⚙️ إعدادات الموديل التكيفي', 'advanced-quiz-system'),
            array($this, 'render_quiz_metabox'),
            'tutor_quiz',
            'side',
            'high'
        );
    }
    
    /**
     * Render quiz metabox
     */
    public function render_quiz_metabox($post) {
        wp_nonce_field('aqs_save_quiz_meta', 'aqs_quiz_meta_nonce');
        
        $module_type = get_post_meta($post->ID, 'aqs_quiz_module', true);
        $related_course = get_post_meta($post->ID, 'aqs_related_course_id', true);
        
        // Get all courses
        $courses = get_posts(array(
            'post_type' => 'courses',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        ?>
        <div class="aqs-metabox">
            <p>
                <label for="aqs_quiz_module">
                    <strong><?php _e('نوع الموديل:', 'advanced-quiz-system'); ?></strong>
                </label>
                <select name="aqs_quiz_module" id="aqs_quiz_module" class="widefat" style="margin-top: 8px;">
                    <option value="" <?php selected($module_type, ''); ?>>
                        <?php _e('— غير محدد —', 'advanced-quiz-system'); ?>
                    </option>
                    <option value="module_1" <?php selected($module_type, 'module_1'); ?>>
                        <?php _e('Module 1 (تحديد المستوى)', 'advanced-quiz-system'); ?>
                    </option>
                    <option value="module_2" <?php selected($module_type, 'module_2'); ?>>
                        <?php _e('Module 2 (التكيفي)', 'advanced-quiz-system'); ?>
                    </option>
                </select>
            </p>
            
            <p>
                <label for="aqs_related_course_id">
                    <strong><?php _e('الكورس المرتبط:', 'advanced-quiz-system'); ?></strong>
                </label>
                <select name="aqs_related_course_id" id="aqs_related_course_id" class="widefat" style="margin-top: 8px;">
                    <option value=""><?php _e('— اختر الكورس —', 'advanced-quiz-system'); ?></option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course->ID; ?>" <?php selected($related_course, $course->ID); ?>>
                            <?php echo esc_html($course->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <div class="aqs-metabox-help" style="background: #f0f6fc; padding: 15px; border-radius: 4px; margin-top: 15px;">
                <p style="margin: 0; font-size: 12px;">
                    <strong>💡 ملاحظة:</strong><br>
                    <?php _e('Module 1 يجب أن يُحل أولاً لتحديد مستوى الطالب، ثم Module 2 يعرض الأسئلة المناسبة.', 'advanced-quiz-system'); ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save quiz metadata
     */
    public function save_quiz_meta($post_id, $post) {
        // Check nonce
        if (!isset($_POST['aqs_quiz_meta_nonce']) || !wp_verify_nonce($_POST['aqs_quiz_meta_nonce'], 'aqs_save_quiz_meta')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save module type
        if (isset($_POST['aqs_quiz_module'])) {
            update_post_meta($post_id, 'aqs_quiz_module', sanitize_text_field($_POST['aqs_quiz_module']));
        }
        
        // Save related course
        if (isset($_POST['aqs_related_course_id'])) {
            update_post_meta($post_id, 'aqs_related_course_id', intval($_POST['aqs_related_course_id']));
        }
    }
    
    /**
     * Helper functions
     */
    private function get_module_badge($type) {
        switch ($type) {
            case 'module_1':
                return '<span class="aqs-module-badge aqs-module-1">Module 1 - تحديد المستوى</span>';
            case 'module_2':
                return '<span class="aqs-module-badge aqs-module-2">Module 2 - تكيفي</span>';
            default:
                return '<span class="aqs-module-badge aqs-module-none">—</span>';
        }
    }
    
    private function get_status_badge($status) {
        $class = $status === 'publish' ? 'aqs-status-publish' : 'aqs-status-draft';
        $label = $status === 'publish' ? 'منشور' : 'مسودة';
        return '<span class="aqs-status-badge ' . $class . '">' . $label . '</span>';
    }
    
    private function get_quiz_question_count($quiz_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_quiz_questions WHERE quiz_id = %d",
            $quiz_id
        ));
    }
    
    private function get_module_statistics() {
        global $wpdb;
        
        $total_quizzes = wp_count_posts('tutor_quiz')->publish;
        $module_1_count = count(get_posts(array(
            'post_type' => 'tutor_quiz',
            'meta_key' => 'aqs_quiz_module',
            'meta_value' => 'module_1',
            'posts_per_page' => -1,
            'fields' => 'ids'
        )));
        $module_2_count = count(get_posts(array(
            'post_type' => 'tutor_quiz',
            'meta_key' => 'aqs_quiz_module',
            'meta_value' => 'module_2',
            'posts_per_page' => -1,
            'fields' => 'ids'
        )));
        
        ob_start();
        ?>
        <div class="aqs-stat-card">
            <div class="aqs-stat-icon">📝</div>
            <div class="aqs-stat-value"><?php echo $total_quizzes; ?></div>
            <div class="aqs-stat-label"><?php _e('إجمالي الامتحانات', 'advanced-quiz-system'); ?></div>
        </div>
        <div class="aqs-stat-card">
            <div class="aqs-stat-icon">1️⃣</div>
            <div class="aqs-stat-value"><?php echo $module_1_count; ?></div>
            <div class="aqs-stat-label"><?php _e('Module 1 (تحديد)', 'advanced-quiz-system'); ?></div>
        </div>
        <div class="aqs-stat-card">
            <div class="aqs-stat-icon">2️⃣</div>
            <div class="aqs-stat-value"><?php echo $module_2_count; ?></div>
            <div class="aqs-stat-label"><?php _e('Module 2 (تكيفي)', 'advanced-quiz-system'); ?></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
