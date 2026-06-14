<?php
/**
 * Module Manager - Standalone System
 * نظام مستقل تماماً لإدارة الموديلات التكيفية
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
        // Register custom post type for modules
        add_action('init', array($this, 'register_module_post_type'));
        
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menus'), 20);
        
        // AJAX handlers
        add_action('wp_ajax_aqs_create_module', array($this, 'ajax_create_module'));
        add_action('wp_ajax_aqs_delete_module', array($this, 'ajax_delete_module'));
        add_action('wp_ajax_aqs_get_module_details', array($this, 'ajax_get_module_details'));
        add_action('wp_ajax_aqs_update_module', array($this, 'ajax_update_module'));
        
        // Display modules on course page
        add_action('tutor_course/single/enrolled/after/content', array($this, 'display_modules_on_course'), 10);
        
        // Save module score
        add_action('wp_ajax_aqs_save_module_attempt', array($this, 'ajax_save_attempt'));
        add_action('wp_ajax_nopriv_aqs_save_module_attempt', array($this, 'ajax_save_attempt'));
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // Create database table on activation
        register_activation_hook(__FILE__, array($this, 'create_database_table'));
    }
    
    /**
     * Create database table for attempts
     */
    public function create_database_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aqs_module_attempts';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            module_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            score decimal(5,2) NOT NULL,
            total_questions int(11) NOT NULL,
            correct_answers int(11) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY module_id (module_id),
            KEY course_id (course_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * AJAX: Get module details
     */
    public function ajax_get_module_details() {
        check_ajax_referer('aqs_module_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('يجب تسجيل الدخول', 'advanced-quiz-system')));
        }
        
        $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;
        
        if (!$module_id) {
            wp_send_json_error(array('message' => __('معرف الموديل غير صحيح', 'advanced-quiz-system')));
        }
        
        $module = get_post($module_id);
        
        if (!$module || $module->post_type != 'aqs_module') {
            wp_send_json_error(array('message' => __('الموديل غير موجود', 'advanced-quiz-system')));
        }
        
        $module_data = array(
            'id' => $module->ID,
            'title' => $module->post_title,
            'course_id' => get_post_meta($module_id, 'aqs_course_id', true),
            'module_type' => get_post_meta($module_id, 'aqs_module_type', true),
            'pass_percentage' => get_post_meta($module_id, 'aqs_pass_percentage', true),
            'time_limit' => get_post_meta($module_id, 'aqs_time_limit', true),
            'questions' => get_post_meta($module_id, 'aqs_questions', true),
        );
        
        // Check if user can access (Module 2 requires Module 1 completion)
        if ($module_data['module_type'] == 'module_2') {
            $user_id = get_current_user_id();
            $module1_score = get_user_meta($user_id, 'aqs_module_1_score_' . $module_data['course_id'], true);
            
            if (!$module1_score) {
                wp_send_json_error(array('message' => __('يجب إكمال Module 1 أولاً', 'advanced-quiz-system')));
            }
            
            // Filter questions based on Module 1 score
            $threshold = get_option('aqs_easy_threshold', 50);
            $filtered_questions = array();
            
            foreach ($module_data['questions'] as $question) {
                if ($module1_score < $threshold && $question['difficulty'] == 'easy') {
                    $filtered_questions[] = $question;
                } elseif ($module1_score >= $threshold && $question['difficulty'] == 'hard') {
                    $filtered_questions[] = $question;
                }
            }
            
            $module_data['questions'] = !empty($filtered_questions) ? $filtered_questions : $module_data['questions'];
        }
        
        wp_send_json_success($module_data);
    }
    
    /**
     * Register custom post type for modules
     */
    public function register_module_post_type() {
        $labels = array(
            'name' => __('الموديلات التكيفية', 'advanced-quiz-system'),
            'singular_name' => __('موديل', 'advanced-quiz-system'),
            'add_new' => __('إضافة موديل', 'advanced-quiz-system'),
            'add_new_item' => __('إضافة موديل جديد', 'advanced-quiz-system'),
            'edit_item' => __('تعديل الموديل', 'advanced-quiz-system'),
            'new_item' => __('موديل جديد', 'advanced-quiz-system'),
            'view_item' => __('عرض الموديل', 'advanced-quiz-system'),
            'search_items' => __('بحث عن موديلات', 'advanced-quiz-system'),
            'not_found' => __('لم يتم العثور على موديلات', 'advanced-quiz-system'),
        );
        
        $args = array(
            'labels' => $labels,
            'public' => true,
            'has_archive' => false,
            'show_in_menu' => false, // سنعرضه في قائمة مخصصة
            'supports' => array('title'),
            'rewrite' => array('slug' => 'adaptive-module'),
        );
        
        register_post_type('aqs_module', $args);
    }
    
    /**
     * Add admin menus
     */
    public function add_admin_menus() {
        // Main menu
        add_menu_page(
            __('الموديلات التكيفية', 'advanced-quiz-system'),
            __('الموديلات التكيفية', 'advanced-quiz-system'),
            'manage_options',
            'aqs-modules',
            array($this, 'render_modules_page'),
            'dashicons-list-view',
            30
        );
        
        // Submenu - All Modules
        add_submenu_page(
            'aqs-modules',
            __('جميع الموديلات', 'advanced-quiz-system'),
            __('جميع الموديلات', 'advanced-quiz-system'),
            'manage_options',
            'aqs-modules',
            array($this, 'render_modules_page')
        );
        
        // Submenu - Add New
        add_submenu_page(
            'aqs-modules',
            __('إضافة موديل جديد', 'advanced-quiz-system'),
            __('إضافة جديد', 'advanced-quiz-system'),
            'manage_options',
            'aqs-add-module',
            array($this, 'render_add_module_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'aqs-') !== false) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_media();
            
            wp_enqueue_script('aqs-module-admin', plugins_url('assets/js/module-admin.js', dirname(__FILE__)), array('jquery'), '1.0', true);
            
            wp_localize_script('aqs-module-admin', 'aqsModuleData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aqs_module_nonce'),
                'strings' => array(
                    'confirmDelete' => __('هل أنت متأكد من حذف هذا الموديل؟', 'advanced-quiz-system'),
                    'saved' => __('تم الحفظ بنجاح', 'advanced-quiz-system'),
                    'error' => __('حدث خطأ، الرجاء المحاولة مرة أخرى', 'advanced-quiz-system'),
                )
            ));
        }
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        if (is_singular('courses')) {
            wp_enqueue_script('aqs-module-frontend', plugins_url('assets/js/module-frontend.js', dirname(__FILE__)), array('jquery'), '1.0', true);
            
            wp_localize_script('aqs-module-frontend', 'aqsModuleFrontend', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aqs_module_nonce'),
            ));
        }
    }
    
    /**
     * Render modules list page
     */
    public function render_modules_page() {
        global $wpdb;
        
        // Get all modules grouped by course
        $modules = $wpdb->get_results("
            SELECT p.ID, p.post_title, p.post_status,
                   pm1.meta_value as course_id,
                   pm2.meta_value as module_type,
                   pm3.meta_value as questions_count
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'aqs_course_id'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'aqs_module_type'
            LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'aqs_questions_count'
            WHERE p.post_type = 'aqs_module'
            AND p.post_status != 'trash'
            ORDER BY pm1.meta_value, pm2.meta_value
        ");
        
        // Group by course
        $grouped_modules = array();
        foreach ($modules as $module) {
            $course_id = $module->course_id ?: 0;
            if (!isset($grouped_modules[$course_id])) {
                $grouped_modules[$course_id] = array();
            }
            $grouped_modules[$course_id][] = $module;
        }
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                📚 <?php _e('إدارة الموديلات التكيفية', 'advanced-quiz-system'); ?>
            </h1>
            <a href="<?php echo admin_url('admin.php?page=aqs-add-module'); ?>" class="page-title-action">
                <?php _e('إضافة موديل جديد', 'advanced-quiz-system'); ?>
            </a>
            <hr class="wp-header-end">
            
            <div class="notice notice-info">
                <p>
                    <strong>💡 <?php _e('كيف يعمل النظام؟', 'advanced-quiz-system'); ?></strong><br>
                    <?php _e('قم بإنشاء موديلات تكيفية وربطها بالكورسات. سيتم عرضها تلقائياً للطلاب المسجلين.', 'advanced-quiz-system'); ?>
                </p>
            </div>
            
            <?php if (empty($grouped_modules)): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php _e('لا توجد موديلات حتى الآن.', 'advanced-quiz-system'); ?>
                        <a href="<?php echo admin_url('admin.php?page=aqs-add-module'); ?>">
                            <?php _e('ابدأ بإنشاء أول موديل', 'advanced-quiz-system'); ?>
                        </a>
                    </p>
                </div>
            <?php else: ?>
                
                <?php foreach ($grouped_modules as $course_id => $course_modules): ?>
                    <?php
                    $course = $course_id ? get_post($course_id) : null;
                    $course_title = $course ? $course->post_title : __('غير مرتبط بكورس', 'advanced-quiz-system');
                    ?>
                    
                    <div class="aqs-course-modules" style="margin: 30px 0;">
                        <h2 style="border-bottom: 3px solid #2271b1; padding-bottom: 10px; margin-bottom: 20px;">
                            📖 <?php echo esc_html($course_title); ?>
                        </h2>
                        
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th width="40%"><?php _e('اسم الموديل', 'advanced-quiz-system'); ?></th>
                                    <th width="15%"><?php _e('النوع', 'advanced-quiz-system'); ?></th>
                                    <th width="15%"><?php _e('عدد الأسئلة', 'advanced-quiz-system'); ?></th>
                                    <th width="15%"><?php _e('الحالة', 'advanced-quiz-system'); ?></th>
                                    <th width="15%"><?php _e('الإجراءات', 'advanced-quiz-system'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($course_modules as $module): ?>
                                    <?php
                                    $type_label = $module->module_type == 'module_1' 
                                        ? '<span class="aqs-badge aqs-badge-primary">🎯 Module 1</span>'
                                        : '<span class="aqs-badge aqs-badge-success">🔥 Module 2</span>';
                                    
                                    $status_label = $module->post_status == 'publish'
                                        ? '<span class="aqs-status-published">✅ منشور</span>'
                                        : '<span class="aqs-status-draft">📝 مسودة</span>';
                                    
                                    $questions_count = $module->questions_count ?: 0;
                                    ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($module->post_title); ?></strong></td>
                                        <td><?php echo $type_label; ?></td>
                                        <td><?php echo $questions_count; ?> <?php _e('سؤال', 'advanced-quiz-system'); ?></td>
                                        <td><?php echo $status_label; ?></td>
                                        <td>
                                            <a href="<?php echo admin_url('admin.php?page=aqs-add-module&action=edit&id=' . $module->ID); ?>" class="button button-small">
                                                ✏️ <?php _e('تعديل', 'advanced-quiz-system'); ?>
                                            </a>
                                            <button type="button" class="button button-small aqs-delete-module" data-id="<?php echo $module->ID; ?>">
                                                🗑️ <?php _e('حذف', 'advanced-quiz-system'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                <?php endforeach; ?>
                
            <?php endif; ?>
            
            <style>
                .aqs-badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 3px;
                    font-size: 12px;
                    font-weight: bold;
                    color: #fff;
                }
                .aqs-badge-primary { background: #2271b1; }
                .aqs-badge-success { background: #46b450; }
                .aqs-status-published { color: #46b450; font-weight: bold; }
                .aqs-status-draft { color: #999; }
            </style>
        </div>
        <?php
    }
    
    /**
     * Render add/edit module page
     */
    public function render_add_module_page() {
        $module_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $is_edit = $module_id > 0;
        
        // Get module data if editing
        $module_data = array(
            'title' => '',
            'course_id' => '',
            'module_type' => 'module_1',
            'pass_percentage' => 50,
            'time_limit' => 30,
            'questions' => array(),
        );
        
        if ($is_edit) {
            $module = get_post($module_id);
            if ($module && $module->post_type == 'aqs_module') {
                $module_data['title'] = $module->post_title;
                $module_data['course_id'] = get_post_meta($module_id, 'aqs_course_id', true);
                $module_data['module_type'] = get_post_meta($module_id, 'aqs_module_type', true);
                $module_data['pass_percentage'] = get_post_meta($module_id, 'aqs_pass_percentage', true) ?: 50;
                $module_data['time_limit'] = get_post_meta($module_id, 'aqs_time_limit', true) ?: 30;
                $module_data['questions'] = get_post_meta($module_id, 'aqs_questions', true) ?: array();
            }
        }
        
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
                <?php echo $is_edit ? '✏️ ' . __('تعديل الموديل', 'advanced-quiz-system') : '➕ ' . __('إضافة موديل جديد', 'advanced-quiz-system'); ?>
            </h1>
            
            <form id="aqs-module-form" method="post" style="max-width: 1200px;">
                <?php wp_nonce_field('aqs_module_nonce', 'aqs_nonce'); ?>
                <input type="hidden" name="module_id" value="<?php echo $module_id; ?>">
                
                <div class="aqs-module-editor">
                    <!-- Basic Info -->
                    <div class="postbox" style="margin-top: 20px;">
                        <div class="postbox-header">
                            <h2>📋 <?php _e('المعلومات الأساسية', 'advanced-quiz-system'); ?></h2>
                        </div>
                        <div class="inside">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="module_title"><?php _e('عنوان الموديل', 'advanced-quiz-system'); ?> *</label>
                                    </th>
                                    <td>
                                        <input type="text" id="module_title" name="module_title" class="regular-text" 
                                               value="<?php echo esc_attr($module_data['title']); ?>" required>
                                        <p class="description"><?php _e('مثال: Module 1 - امتحان تحديد المستوى', 'advanced-quiz-system'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="course_id"><?php _e('الكورس المرتبط', 'advanced-quiz-system'); ?> *</label>
                                    </th>
                                    <td>
                                        <select id="course_id" name="course_id" class="regular-text" required>
                                            <option value=""><?php _e('اختر الكورس...', 'advanced-quiz-system'); ?></option>
                                            <?php foreach ($courses as $course): ?>
                                                <option value="<?php echo $course->ID; ?>" 
                                                        <?php selected($module_data['course_id'], $course->ID); ?>>
                                                    <?php echo esc_html($course->post_title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="module_type"><?php _e('نوع الموديل', 'advanced-quiz-system'); ?> *</label>
                                    </th>
                                    <td>
                                        <select id="module_type" name="module_type" class="regular-text" required>
                                            <option value="module_1" <?php selected($module_data['module_type'], 'module_1'); ?>>
                                                🎯 Module 1 - <?php _e('الأساسي (تحديد المستوى)', 'advanced-quiz-system'); ?>
                                            </option>
                                            <option value="module_2" <?php selected($module_data['module_type'], 'module_2'); ?>>
                                                🔥 Module 2 - <?php _e('التكيفي (حسب الأداء)', 'advanced-quiz-system'); ?>
                                            </option>
                                        </select>
                                        <p class="description">
                                            <?php _e('Module 1 يحدد مستوى الطالب، Module 2 يتكيف مع الأداء', 'advanced-quiz-system'); ?>
                                        </p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="pass_percentage"><?php _e('نسبة النجاح %', 'advanced-quiz-system'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="pass_percentage" name="pass_percentage" 
                                               min="0" max="100" value="<?php echo esc_attr($module_data['pass_percentage']); ?>">
                                        <p class="description"><?php _e('الحد الأدنى للنجاح', 'advanced-quiz-system'); ?></p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label for="time_limit"><?php _e('الوقت المحدد (دقيقة)', 'advanced-quiz-system'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="time_limit" name="time_limit" 
                                               min="0" value="<?php echo esc_attr($module_data['time_limit']); ?>">
                                        <p class="description"><?php _e('0 = بدون حد زمني', 'advanced-quiz-system'); ?></p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Questions Section -->
                    <div class="postbox" style="margin-top: 20px;">
                        <div class="postbox-header">
                            <h2>❓ <?php _e('الأسئلة', 'advanced-quiz-system'); ?></h2>
                        </div>
                        <div class="inside">
                            <div id="aqs-questions-container">
                                <?php if (!empty($module_data['questions'])): ?>
                                    <?php foreach ($module_data['questions'] as $index => $question): ?>
                                        <?php $this->render_question_item($index, $question); ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <button type="button" id="aqs-add-question" class="button button-primary" style="margin-top: 20px;">
                                ➕ <?php _e('إضافة سؤال جديد', 'advanced-quiz-system'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Submit -->
                    <div style="margin: 30px 0;">
                        <button type="submit" class="button button-primary button-large">
                            💾 <?php echo $is_edit ? __('تحديث الموديل', 'advanced-quiz-system') : __('حفظ الموديل', 'advanced-quiz-system'); ?>
                        </button>
                        <a href="<?php echo admin_url('admin.php?page=aqs-modules'); ?>" class="button button-large">
                            <?php _e('إلغاء', 'advanced-quiz-system'); ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Question Template -->
        <script type="text/template" id="aqs-question-template">
            <?php $this->render_question_item('{INDEX}', array()); ?>
        </script>
        
        <style>
            .aqs-question-item {
                border: 1px solid #ddd;
                padding: 20px;
                margin-bottom: 20px;
                background: #f9f9f9;
                border-radius: 5px;
            }
            .aqs-question-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #2271b1;
            }
            .aqs-question-title {
                font-weight: bold;
                font-size: 16px;
            }
            .aqs-answer-option {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 10px;
            }
            .aqs-answer-option input[type="text"] {
                flex: 1;
            }
        </style>
        <?php
    }
    
    /**
     * Render question item HTML
     */
    private function render_question_item($index, $question = array()) {
        $question_text = isset($question['text']) ? $question['text'] : '';
        $question_type = isset($question['type']) ? $question['type'] : 'multiple_choice';
        $difficulty = isset($question['difficulty']) ? $question['difficulty'] : 'easy';
        $answers = isset($question['answers']) ? $question['answers'] : array(array('text' => '', 'is_correct' => false));
        
        ?>
        <div class="aqs-question-item" data-index="<?php echo $index; ?>">
            <div class="aqs-question-header">
                <span class="aqs-question-title">❓ <?php _e('السؤال رقم', 'advanced-quiz-system'); ?> <span class="question-number"><?php echo is_numeric($index) ? $index + 1 : 1; ?></span></span>
                <button type="button" class="button aqs-remove-question">🗑️ <?php _e('حذف', 'advanced-quiz-system'); ?></button>
            </div>
            
            <table class="form-table">
                <tr>
                    <th style="width: 150px;">
                        <label><?php _e('نص السؤال', 'advanced-quiz-system'); ?></label>
                    </th>
                    <td>
                        <textarea name="questions[<?php echo $index; ?>][text]" rows="3" class="large-text" required><?php echo esc_textarea($question_text); ?></textarea>
                    </td>
                </tr>
                
                <tr>
                    <th>
                        <label><?php _e('نوع السؤال', 'advanced-quiz-system'); ?></label>
                    </th>
                    <td>
                        <select name="questions[<?php echo $index; ?>][type]" class="regular-text">
                            <option value="multiple_choice" <?php selected($question_type, 'multiple_choice'); ?>><?php _e('اختيار من متعدد', 'advanced-quiz-system'); ?></option>
                            <option value="true_false" <?php selected($question_type, 'true_false'); ?>><?php _e('صح / خطأ', 'advanced-quiz-system'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th>
                        <label><?php _e('مستوى الصعوبة', 'advanced-quiz-system'); ?></label>
                    </th>
                    <td>
                        <select name="questions[<?php echo $index; ?>][difficulty]" class="regular-text">
                            <option value="easy" <?php selected($difficulty, 'easy'); ?>>✅ <?php _e('سهل', 'advanced-quiz-system'); ?></option>
                            <option value="hard" <?php selected($difficulty, 'hard'); ?>>🔥 <?php _e('صعب', 'advanced-quiz-system'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th style="vertical-align: top; padding-top: 15px;">
                        <label><?php _e('الإجابات', 'advanced-quiz-system'); ?></label>
                    </th>
                    <td>
                        <div class="aqs-answers-container">
                            <?php foreach ($answers as $ans_index => $answer): ?>
                                <div class="aqs-answer-option">
                                    <input type="radio" 
                                           name="questions[<?php echo $index; ?>][correct_answer]" 
                                           value="<?php echo $ans_index; ?>"
                                           <?php checked(isset($answer['is_correct']) && $answer['is_correct']); ?>
                                           required>
                                    <input type="text" 
                                           name="questions[<?php echo $index; ?>][answers][<?php echo $ans_index; ?>]" 
                                           value="<?php echo esc_attr(isset($answer['text']) ? $answer['text'] : $answer); ?>"
                                           placeholder="<?php _e('اكتب الإجابة...', 'advanced-quiz-system'); ?>"
                                           required>
                                    <button type="button" class="button aqs-remove-answer">❌</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button aqs-add-answer" style="margin-top: 10px;">
                            ➕ <?php _e('إضافة إجابة', 'advanced-quiz-system'); ?>
                        </button>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * AJAX: Create/Update Module
     */
    public function ajax_create_module() {
        check_ajax_referer('aqs_module_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('ليس لديك صلاحية', 'advanced-quiz-system')));
        }
        
        $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;
        $title = isset($_POST['module_title']) ? sanitize_text_field($_POST['module_title']) : '';
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $module_type = isset($_POST['module_type']) ? sanitize_text_field($_POST['module_type']) : 'module_1';
        
        if (empty($title) || empty($course_id)) {
            wp_send_json_error(array('message' => __('الرجاء ملء جميع الحقول المطلوبة', 'advanced-quiz-system')));
        }
        
        // Create or update post
        $post_data = array(
            'post_title' => $title,
            'post_type' => 'aqs_module',
            'post_status' => 'publish',
        );
        
        if ($module_id) {
            $post_data['ID'] = $module_id;
            $result = wp_update_post($post_data);
        } else {
            $result = wp_insert_post($post_data);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Save meta data
        update_post_meta($result, 'aqs_course_id', $course_id);
        update_post_meta($result, 'aqs_module_type', $module_type);
        update_post_meta($result, 'aqs_pass_percentage', intval($_POST['pass_percentage']));
        update_post_meta($result, 'aqs_time_limit', intval($_POST['time_limit']));
        
        // Save questions
        $questions = isset($_POST['questions']) ? $_POST['questions'] : array();
        $processed_questions = array();
        
        foreach ($questions as $question) {
            $processed_questions[] = array(
                'text' => sanitize_textarea_field($question['text']),
                'type' => sanitize_text_field($question['type']),
                'difficulty' => sanitize_text_field($question['difficulty']),
                'answers' => array_map('sanitize_text_field', $question['answers']),
                'correct_answer' => intval($question['correct_answer']),
            );
        }
        
        update_post_meta($result, 'aqs_questions', $processed_questions);
        update_post_meta($result, 'aqs_questions_count', count($processed_questions));
        
        wp_send_json_success(array(
            'message' => __('تم حفظ الموديل بنجاح', 'advanced-quiz-system'),
            'redirect' => admin_url('admin.php?page=aqs-modules')
        ));
    }
    
    /**
     * AJAX: Delete Module
     */
    public function ajax_delete_module() {
        check_ajax_referer('aqs_module_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('ليس لديك صلاحية', 'advanced-quiz-system')));
        }
        
        $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;
        
        if (!$module_id) {
            wp_send_json_error(array('message' => __('معرف الموديل غير صحيح', 'advanced-quiz-system')));
        }
        
        $result = wp_delete_post($module_id, true);
        
        if ($result) {
            wp_send_json_success(array('message' => __('تم حذف الموديل بنجاح', 'advanced-quiz-system')));
        } else {
            wp_send_json_error(array('message' => __('فشل حذف الموديل', 'advanced-quiz-system')));
        }
    }
    
    /**
     * Display modules on course page (Frontend)
     */
    public function display_modules_on_course() {
        global $post;
        
        if (!$post || $post->post_type != 'courses') {
            return;
        }
        
        // Check if user is enrolled
        if (!is_user_logged_in() || !tutor_utils()->is_enrolled($post->ID)) {
            return;
        }
        
        // Get modules for this course
        $modules = get_posts(array(
            'post_type' => 'aqs_module',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'aqs_course_id',
                    'value' => $post->ID,
                )
            ),
            'orderby' => 'meta_value',
            'meta_key' => 'aqs_module_type',
            'order' => 'ASC'
        ));
        
        if (empty($modules)) {
            return;
        }
        
        ?>
        <div class="aqs-course-modules-frontend" style="margin: 40px 0; padding: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; box-shadow: 0 15px 40px rgba(0,0,0,0.2);">
            <h3 style="color: #fff; font-size: 28px; margin-bottom: 25px; text-align: center; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">
                📝 الامتحانات التكيفية
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
                <?php foreach ($modules as $module): ?>
                    <?php
                    $module_type = get_post_meta($module->ID, 'aqs_module_type', true);
                    $questions_count = get_post_meta($module->ID, 'aqs_questions_count', true);
                    $time_limit = get_post_meta($module->ID, 'aqs_time_limit', true);
                    
                    $icon = $module_type == 'module_1' ? '🎯' : '🔥';
                    $badge = $module_type == 'module_1' ? 'الموديل الأساسي' : 'الموديل التكيفي';
                    ?>
                    
                    <div class="aqs-module-card" style="background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 8px 20px rgba(0,0,0,0.15); transition: all 0.3s ease; cursor: pointer;"
                         onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.25)'"
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.15)'">
                        
                        <div style="display: inline-block; padding: 6px 14px; background: #f0f6fc; color: #2271b1; border-radius: 25px; font-size: 13px; font-weight: bold; margin-bottom: 15px;">
                            <?php echo $icon . ' ' . esc_html($badge); ?>
                        </div>
                        
                        <h4 style="color: #333; font-size: 20px; margin: 12px 0 15px; line-height: 1.4;">
                            <?php echo esc_html($module->post_title); ?>
                        </h4>
                        
                        <div style="color: #666; font-size: 14px; margin-bottom: 20px;">
                            <div style="margin-bottom: 8px;">
                                <span style="display: inline-block; width: 30px;">📊</span>
                                <strong><?php echo $questions_count; ?></strong> <?php _e('سؤال', 'advanced-quiz-system'); ?>
                            </div>
                            <?php if ($time_limit): ?>
                                <div>
                                    <span style="display: inline-block; width: 30px;">⏱️</span>
                                    <strong><?php echo $time_limit; ?></strong> <?php _e('دقيقة', 'advanced-quiz-system'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <a href="#" 
                           class="aqs-start-module" 
                           data-module-id="<?php echo $module->ID; ?>"
                           style="display: block; text-align: center; padding: 14px 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; transition: opacity 0.3s; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);"
                           onmouseover="this.style.opacity='0.9'"
                           onmouseout="this.style.opacity='1'">
                            ابدأ الامتحان →
                        </a>
                    </div>
                    
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Module Modal -->
        <div id="aqs-module-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 99999; align-items: center; justify-content: center;">
            <div style="background: #fff; border-radius: 15px; max-width: 900px; width: 90%; max-height: 90vh; overflow-y: auto; padding: 40px; position: relative;">
                <button id="aqs-close-modal" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 32px; cursor: pointer; color: #999;">&times;</button>
                
                <div id="aqs-module-content"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Save module attempt
     */
    public function ajax_save_attempt() {
        check_ajax_referer('aqs_module_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('يجب تسجيل الدخول', 'advanced-quiz-system')));
        }
        
        $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;
        $answers = isset($_POST['answers']) ? $_POST['answers'] : array();
        $user_id = get_current_user_id();
        
        // Get module questions
        $questions = get_post_meta($module_id, 'aqs_questions', true);
        
        if (!$questions) {
            wp_send_json_error(array('message' => __('الأسئلة غير موجودة', 'advanced-quiz-system')));
        }
        
        // Calculate score
        $total = count($questions);
        $correct = 0;
        
        foreach ($questions as $index => $question) {
            if (isset($answers[$index]) && $answers[$index] == $question['correct_answer']) {
                $correct++;
            }
        }
        
        $percentage = ($correct / $total) * 100;
        
        // Save attempt
        $course_id = get_post_meta($module_id, 'aqs_course_id', true);
        $module_type = get_post_meta($module_id, 'aqs_module_type', true);
        
        global $wpdb;
        $table = $wpdb->prefix . 'aqs_module_attempts';
        
        $wpdb->insert($table, array(
            'user_id' => $user_id,
            'module_id' => $module_id,
            'course_id' => $course_id,
            'score' => $percentage,
            'total_questions' => $total,
            'correct_answers' => $correct,
            'created_at' => current_time('mysql')
        ));
        
        // If Module 1, save score for Module 2 adaptation
        if ($module_type == 'module_1') {
            update_user_meta($user_id, 'aqs_module_1_score_' . $course_id, $percentage);
        }
        
        wp_send_json_success(array(
            'percentage' => round($percentage, 2),
            'correct' => $correct,
            'total' => $total,
            'message' => sprintf(__('لقد أجبت على %d من %d إجابة صحيحة (%s%%)', 'advanced-quiz-system'), $correct, $total, round($percentage, 2))
        ));
    }
}

// Initialize
AQS_Module_Manager::get_instance();
