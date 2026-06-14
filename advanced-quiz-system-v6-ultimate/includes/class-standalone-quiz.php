<?php
/**
 * Standalone Quiz Module Manager
 * نظام كويز مستقل مع تايمر ورفرنس
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQS_Standalone_Quiz {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register custom post type for standalone quizzes
        add_action('init', array($this, 'register_quiz_post_type'));
        
        // Add meta boxes
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_quiz_settings'));
        
        // Frontend display
        add_filter('the_content', array($this, 'display_quiz_content'));
        add_filter('template_include', array($this, 'quiz_template'));
        
        // AJAX handlers
        add_action('wp_ajax_aqs_start_quiz', array($this, 'ajax_start_quiz'));
        add_action('wp_ajax_aqs_submit_quiz_answer', array($this, 'ajax_submit_answer'));
        add_action('wp_ajax_aqs_finish_quiz', array($this, 'ajax_finish_quiz'));
        add_action('wp_ajax_aqs_get_quiz_state', array($this, 'ajax_get_quiz_state'));
        
        // Shortcode
        add_shortcode('aqs_standalone_quiz', array($this, 'quiz_shortcode'));
    }
    
    /**
     * Register standalone quiz post type
     */
    public function register_quiz_post_type() {
        $labels = array(
            'name' => __('الامتحانات المستقلة', 'advanced-quiz-system'),
            'singular_name' => __('امتحان مستقل', 'advanced-quiz-system'),
            'add_new' => __('إضافة امتحان', 'advanced-quiz-system'),
            'add_new_item' => __('إضافة امتحان جديد', 'advanced-quiz-system'),
            'edit_item' => __('تعديل امتحان', 'advanced-quiz-system'),
            'new_item' => __('امتحان جديد', 'advanced-quiz-system'),
            'view_item' => __('عرض امتحان', 'advanced-quiz-system'),
            'search_items' => __('بحث عن امتحان', 'advanced-quiz-system'),
            'not_found' => __('لا توجد امتحانات', 'advanced-quiz-system')
        );
        
        $args = array(
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-clipboard',
            'supports' => array('title', 'editor', 'thumbnail'),
            'show_in_menu' => 'tutor',
            'capability_type' => 'post',
            'rewrite' => array('slug' => 'standalone-quiz')
        );
        
        register_post_type('aqs_standalone_quiz', $args);
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'aqs_quiz_settings',
            __('إعدادات الامتحان', 'advanced-quiz-system'),
            array($this, 'render_settings_meta_box'),
            'aqs_standalone_quiz',
            'normal',
            'high'
        );
        
        add_meta_box(
            'aqs_quiz_questions',
            __('الأسئلة', 'advanced-quiz-system'),
            array($this, 'render_questions_meta_box'),
            'aqs_standalone_quiz',
            'normal',
            'high'
        );
        
        add_meta_box(
            'aqs_quiz_reference',
            __('المرجع والملاحظات', 'advanced-quiz-system'),
            array($this, 'render_reference_meta_box'),
            'aqs_standalone_quiz',
            'side',
            'default'
        );
    }
    
    /**
     * Render settings meta box
     */
    public function render_settings_meta_box($post) {
        wp_nonce_field('aqs_quiz_settings', 'aqs_quiz_settings_nonce');
        
        $time_limit = get_post_meta($post->ID, '_aqs_time_limit', true) ?: 60;
        $passing_score = get_post_meta($post->ID, '_aqs_passing_score', true) ?: 70;
        $show_answers = get_post_meta($post->ID, '_aqs_show_answers', true) ?: 'after';
        $attempts_allowed = get_post_meta($post->ID, '_aqs_attempts_allowed', true) ?: 3;
        $randomize_questions = get_post_meta($post->ID, '_aqs_randomize_questions', true) ?: 'no';
        ?>
        <table class="form-table">
            <tr>
                <th><label for="aqs_time_limit"><?php _e('وقت الامتحان (دقيقة)', 'advanced-quiz-system'); ?></label></th>
                <td>
                    <input type="number" id="aqs_time_limit" name="aqs_time_limit" value="<?php echo esc_attr($time_limit); ?>" min="1" class="regular-text" />
                    <p class="description"><?php _e('المدة المسموحة لإنهاء الامتحان', 'advanced-quiz-system'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="aqs_passing_score"><?php _e('درجة النجاح (%)', 'advanced-quiz-system'); ?></label></th>
                <td>
                    <input type="number" id="aqs_passing_score" name="aqs_passing_score" value="<?php echo esc_attr($passing_score); ?>" min="0" max="100" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="aqs_attempts_allowed"><?php _e('عدد المحاولات المسموحة', 'advanced-quiz-system'); ?></label></th>
                <td>
                    <input type="number" id="aqs_attempts_allowed" name="aqs_attempts_allowed" value="<?php echo esc_attr($attempts_allowed); ?>" min="1" class="regular-text" />
                    <p class="description"><?php _e('0 = غير محدود', 'advanced-quiz-system'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="aqs_show_answers"><?php _e('إظهار الإجابات', 'advanced-quiz-system'); ?></label></th>
                <td>
                    <select id="aqs_show_answers" name="aqs_show_answers">
                        <option value="never" <?php selected($show_answers, 'never'); ?>><?php _e('أبداً', 'advanced-quiz-system'); ?></option>
                        <option value="after" <?php selected($show_answers, 'after'); ?>><?php _e('بعد الانتهاء', 'advanced-quiz-system'); ?></option>
                        <option value="immediately" <?php selected($show_answers, 'immediately'); ?>><?php _e('فوراً', 'advanced-quiz-system'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="aqs_randomize_questions"><?php _e('ترتيب عشوائي للأسئلة', 'advanced-quiz-system'); ?></label></th>
                <td>
                    <input type="checkbox" id="aqs_randomize_questions" name="aqs_randomize_questions" value="yes" <?php checked($randomize_questions, 'yes'); ?> />
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render questions meta box
     */
    public function render_questions_meta_box($post) {
        $questions = get_post_meta($post->ID, '_aqs_questions', true) ?: array();
        ?>
        <div id="aqs-questions-container">
            <div class="aqs-questions-list">
                <?php
                if (!empty($questions)) {
                    foreach ($questions as $index => $question) {
                        $this->render_question_item($index, $question);
                    }
                }
                ?>
            </div>
            <button type="button" class="button button-primary" id="aqs-add-question">
                <?php _e('+ إضافة سؤال', 'advanced-quiz-system'); ?>
            </button>
        </div>
        
        <script type="text/html" id="aqs-question-template">
            <?php $this->render_question_item('{{INDEX}}', array()); ?>
        </script>
        
        <style>
            .aqs-question-item {
                border: 1px solid #ddd;
                padding: 15px;
                margin-bottom: 15px;
                background: #f9f9f9;
                border-radius: 4px;
            }
            .aqs-question-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
                font-weight: bold;
            }
            .aqs-answer-option {
                margin: 5px 0;
                padding: 8px;
                background: white;
                border: 1px solid #ddd;
                border-radius: 3px;
            }
            .aqs-question-explanation {
                margin-top: 10px;
                padding: 10px;
                background: #e8f5e9;
                border-left: 3px solid #4caf50;
            }
        </style>
        <?php
    }
    
    /**
     * Render single question item
     */
    private function render_question_item($index, $question = array()) {
        $q_text = isset($question['text']) ? $question['text'] : '';
        $q_type = isset($question['type']) ? $question['type'] : 'multiple_choice';
        $q_options = isset($question['options']) ? $question['options'] : array('', '', '', '');
        $q_correct = isset($question['correct']) ? $question['correct'] : array();
        $q_explanation = isset($question['explanation']) ? $question['explanation'] : '';
        $q_points = isset($question['points']) ? $question['points'] : 1;
        ?>
        <div class="aqs-question-item" data-index="<?php echo esc_attr($index); ?>">
            <div class="aqs-question-header">
                <span><?php printf(__('السؤال %s', 'advanced-quiz-system'), '<span class="question-number">' . ($index + 1) . '</span>'); ?></span>
                <button type="button" class="button button-small aqs-remove-question"><?php _e('حذف', 'advanced-quiz-system'); ?></button>
            </div>
            
            <div class="aqs-question-content">
                <p>
                    <label><?php _e('نص السؤال:', 'advanced-quiz-system'); ?></label>
                    <textarea name="aqs_questions[<?php echo $index; ?>][text]" class="large-text" rows="3" required><?php echo esc_textarea($q_text); ?></textarea>
                </p>
                
                <p>
                    <label><?php _e('نوع السؤال:', 'advanced-quiz-system'); ?></label>
                    <select name="aqs_questions[<?php echo $index; ?>][type]" class="aqs-question-type">
                        <option value="multiple_choice" <?php selected($q_type, 'multiple_choice'); ?>><?php _e('اختيار متعدد', 'advanced-quiz-system'); ?></option>
                        <option value="true_false" <?php selected($q_type, 'true_false'); ?>><?php _e('صح أو خطأ', 'advanced-quiz-system'); ?></option>
                        <option value="short_answer" <?php selected($q_type, 'short_answer'); ?>><?php _e('إجابة قصيرة', 'advanced-quiz-system'); ?></option>
                    </select>
                </p>
                
                <p>
                    <label><?php _e('النقاط:', 'advanced-quiz-system'); ?></label>
                    <input type="number" name="aqs_questions[<?php echo $index; ?>][points]" value="<?php echo esc_attr($q_points); ?>" min="1" step="0.5" />
                </p>
                
                <div class="aqs-options-container">
                    <label><?php _e('الخيارات:', 'advanced-quiz-system'); ?></label>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="aqs-answer-option">
                            <input type="text" name="aqs_questions[<?php echo $index; ?>][options][<?php echo $i; ?>]" 
                                   value="<?php echo isset($q_options[$i]) ? esc_attr($q_options[$i]) : ''; ?>" 
                                   placeholder="<?php printf(__('الخيار %d', 'advanced-quiz-system'), $i + 1); ?>" 
                                   class="regular-text" />
                            <label>
                                <input type="checkbox" name="aqs_questions[<?php echo $index; ?>][correct][]" 
                                       value="<?php echo $i; ?>" 
                                       <?php echo in_array($i, (array)$q_correct) ? 'checked' : ''; ?> />
                                <?php _e('صحيح', 'advanced-quiz-system'); ?>
                            </label>
                        </div>
                    <?php endfor; ?>
                </div>
                
                <div class="aqs-question-explanation">
                    <label><?php _e('التفسير (اختياري):', 'advanced-quiz-system'); ?></label>
                    <textarea name="aqs_questions[<?php echo $index; ?>][explanation]" class="large-text" rows="2"><?php echo esc_textarea($q_explanation); ?></textarea>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render reference meta box
     */
    public function render_reference_meta_box($post) {
        $reference = get_post_meta($post->ID, '_aqs_reference', true);
        $reference_url = get_post_meta($post->ID, '_aqs_reference_url', true);
        ?>
        <p>
            <label for="aqs_reference"><?php _e('مرجع الامتحان:', 'advanced-quiz-system'); ?></label>
            <textarea id="aqs_reference" name="aqs_reference" class="widefat" rows="4"><?php echo esc_textarea($reference); ?></textarea>
        </p>
        <p>
            <label for="aqs_reference_url"><?php _e('رابط المرجع:', 'advanced-quiz-system'); ?></label>
            <input type="url" id="aqs_reference_url" name="aqs_reference_url" value="<?php echo esc_url($reference_url); ?>" class="widefat" />
        </p>
        <?php
    }
    
    /**
     * Save quiz settings
     */
    public function save_quiz_settings($post_id) {
        if (!isset($_POST['aqs_quiz_settings_nonce']) || 
            !wp_verify_nonce($_POST['aqs_quiz_settings_nonce'], 'aqs_quiz_settings')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save settings
        update_post_meta($post_id, '_aqs_time_limit', intval($_POST['aqs_time_limit']));
        update_post_meta($post_id, '_aqs_passing_score', intval($_POST['aqs_passing_score']));
        update_post_meta($post_id, '_aqs_show_answers', sanitize_text_field($_POST['aqs_show_answers']));
        update_post_meta($post_id, '_aqs_attempts_allowed', intval($_POST['aqs_attempts_allowed']));
        update_post_meta($post_id, '_aqs_randomize_questions', isset($_POST['aqs_randomize_questions']) ? 'yes' : 'no');
        
        // Save questions
        if (isset($_POST['aqs_questions'])) {
            $questions = array();
            foreach ($_POST['aqs_questions'] as $q) {
                $questions[] = array(
                    'text' => wp_kses_post($q['text']),
                    'type' => sanitize_text_field($q['type']),
                    'options' => array_map('sanitize_text_field', $q['options']),
                    'correct' => isset($q['correct']) ? array_map('intval', $q['correct']) : array(),
                    'explanation' => wp_kses_post($q['explanation']),
                    'points' => floatval($q['points'])
                );
            }
            update_post_meta($post_id, '_aqs_questions', $questions);
        }
        
        // Save reference
        update_post_meta($post_id, '_aqs_reference', wp_kses_post($_POST['aqs_reference']));
        update_post_meta($post_id, '_aqs_reference_url', esc_url_raw($_POST['aqs_reference_url']));
    }
    
    /**
     * Display quiz content
     */
    public function display_quiz_content($content) {
        if (!is_singular('aqs_standalone_quiz')) {
            return $content;
        }
        
        global $post;
        
        ob_start();
        include AQS_PLUGIN_DIR . 'templates/standalone-quiz.php';
        $quiz_content = ob_get_clean();
        
        return $content . $quiz_content;
    }
    
    /**
     * Custom quiz template
     */
    public function quiz_template($template) {
        if (is_singular('aqs_standalone_quiz')) {
            $custom_template = AQS_PLUGIN_DIR . 'templates/single-quiz.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }
    
    /**
     * Quiz shortcode
     */
    public function quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0
        ), $atts);
        
        if (!$atts['id']) {
            return '<p>' . __('معرف الامتحان مطلوب', 'advanced-quiz-system') . '</p>';
        }
        
        global $post;
        $post = get_post($atts['id']);
        
        if (!$post || $post->post_type !== 'aqs_standalone_quiz') {
            return '<p>' . __('الامتحان غير موجود', 'advanced-quiz-system') . '</p>';
        }
        
        setup_postdata($post);
        
        ob_start();
        include AQS_PLUGIN_DIR . 'templates/standalone-quiz.php';
        $content = ob_get_clean();
        
        wp_reset_postdata();
        
        return $content;
    }
    
    // AJAX handlers would go here...
    // (Similar to the existing quiz handlers but for standalone quizzes)
}
