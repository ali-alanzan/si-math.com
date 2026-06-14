<?php
/**
 * Plugin Name: Advanced Quiz & Leaderboard System Pro - ULTIMATE V6
 * Plugin URI: https://concretegroup.eg
 * Description: 🎯 نظام امتحانات احترافي متكامل - مع Mistakes Review + Score Predictor + Standalone Quiz + Private Messaging + Desmos Calculator
 * Version: 6.0.0
 * Author: Concrete Group Elite Team
 * Author URI: https://concretegroup.eg
 * Text Domain: advanced-quiz-system
 * Domain Path: /languages
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AQS_VERSION', '6.0.0');
define('AQS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AQS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AQS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class - Version 6.0 ULTIMATE
 * 
 * ALL FEATURES:
 * ✅ Mistakes Review System
 * ✅ Score Predictor with AI
 * ✅ Standalone Quiz Module
 * ✅ Fixed Chat System with Private Messaging (NEW!)
 * ✅ Desmos Graphing Calculator (NEW!)
 * ✅ Drawing Canvas
 * ✅ Updated Leaderboard Color (#0B417C)
 */
class Advanced_Quiz_System {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }
    
    private function includes() {
        // Core modules
        require_once AQS_PLUGIN_DIR . 'includes/class-calculator.php';
        require_once AQS_PLUGIN_DIR . 'includes/class-drawing-canvas.php';
        require_once AQS_PLUGIN_DIR . 'includes/class-adaptive-quiz.php';
        require_once AQS_PLUGIN_DIR . 'includes/class-question-tracker.php';
        require_once AQS_PLUGIN_DIR . 'includes/class-leaderboard.php';
        
        // V6 ULTIMATE - Enhanced Chat with Private Messaging
        require_once AQS_PLUGIN_DIR . 'includes/class-chat-system-ultimate.php';
        
        // V6 ULTIMATE - Desmos Calculator
        require_once AQS_PLUGIN_DIR . 'includes/class-desmos-calculator.php';
        
        // Admin modules
        require_once AQS_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once AQS_PLUGIN_DIR . 'includes/class-module-manager.php';
        require_once AQS_PLUGIN_DIR . 'includes/class-admin-chat-viewer.php';
        
        // V5 modules
        require_once AQS_PLUGIN_DIR . 'includes/class-mistakes-tracker.php';
        require_once AQS_PLUGIN_DIR . 'includes/class-score-predictor.php';
        require_once AQS_PLUGIN_DIR . 'includes/class-standalone-quiz.php';
    }
    
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Initialize core modules
        AQS_Calculator::get_instance();
        AQS_Drawing_Canvas::get_instance();
        AQS_Adaptive_Quiz::get_instance();
        AQS_Question_Tracker::get_instance();
        AQS_Leaderboard::get_instance();
        
        // V6 ULTIMATE - Enhanced Chat with Private Messaging
        AQS_Chat_System::get_instance();
        
        // V6 ULTIMATE - Desmos Calculator
        AQS_Desmos_Calculator::get_instance();
        
        // Admin modules
        AQS_Admin_Settings::get_instance();
        AQS_Module_Manager::get_instance();
        AQS_Admin_Chat_Viewer::get_instance();
        
        // V5 modules
        AQS_Mistakes_Tracker::get_instance();
        AQS_Score_Predictor::get_instance();
        AQS_Standalone_Quiz::get_instance();
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('advanced-quiz-system', false, dirname(AQS_PLUGIN_BASENAME) . '/languages');
    }
    
    public function enqueue_scripts() {
        // Enhanced styles with new leaderboard color
        wp_enqueue_style('aqs-style', AQS_PLUGIN_URL . 'assets/css/enhanced-style.css', array(), AQS_VERSION);
        
        // Enhanced scripts with fixed chat
        wp_enqueue_script('aqs-script', AQS_PLUGIN_URL . 'assets/js/enhanced-script.js', array('jquery'), AQS_VERSION, true);
        
        // Chart.js for drawing canvas and score predictor
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true);
        
        // Localize script with all needed data
        wp_localize_script('aqs-script', 'aqsData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aqs_nonce'),
            'userId' => get_current_user_id(),
            'strings' => array(
                'calculator' => __('آلة حاسبة', 'advanced-quiz-system'),
                'drawing' => __('رسم بياني', 'advanced-quiz-system'),
                'clear' => __('مسح', 'advanced-quiz-system'),
                'error' => __('خطأ في الحساب', 'advanced-quiz-system'),
                'send' => __('إرسال', 'advanced-quiz-system'),
                'sending' => __('جاري الإرسال...', 'advanced-quiz-system'),
                'messageSent' => __('تم الإرسال', 'advanced-quiz-system'),
                'messageError' => __('فشل الإرسال', 'advanced-quiz-system'),
                'no_messages' => __('لا توجد رسائل بعد', 'advanced-quiz-system'),
                'pen' => __('قلم', 'advanced-quiz-system'),
                'eraser' => __('ممحاة', 'advanced-quiz-system')
            )
        ));
    }
    
    public function enqueue_admin_scripts($hook) {
        // Load on settings page
        if (strpos($hook, 'aqs-settings') !== false || 
            strpos($hook, 'aqs-modules') !== false || 
            strpos($hook, 'aqs-chat-viewer') !== false) {
            wp_enqueue_style('aqs-admin-style', AQS_PLUGIN_URL . 'assets/css/admin-style.css', array(), AQS_VERSION);
            wp_enqueue_script('aqs-admin-script', AQS_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), AQS_VERSION, true);
            
            wp_localize_script('aqs-admin-script', 'aqsAdminData', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aqs_admin_nonce')
            ));
        }
        
        // Load on quiz edit page
        if ('post.php' === $hook || 'post-new.php' === $hook) {
            global $post_type;
            if ('tutor_quiz' === $post_type || 'aqs_standalone_quiz' === $post_type) {
                wp_enqueue_style('aqs-quiz-admin', AQS_PLUGIN_URL . 'assets/css/admin-style.css', array(), AQS_VERSION);
                wp_enqueue_script('aqs-quiz-admin', AQS_PLUGIN_URL . 'assets/js/admin-quiz.js', array('jquery'), AQS_VERSION, true);
            }
        }
    }
    
    public function activate() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create leaderboard table
        $table_leaderboard = $wpdb->prefix . 'aqs_leaderboard';
        $sql_leaderboard = "CREATE TABLE IF NOT EXISTS $table_leaderboard (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            quiz_id bigint(20) NOT NULL,
            score decimal(10,2) NOT NULL,
            date_recorded datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY date_recorded (date_recorded),
            KEY user_course (user_id, course_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_leaderboard);
        
        // Create chat messages table - ENHANCED
        $table_chat = $wpdb->prefix . 'aqs_chat_messages';
        $sql_chat = "CREATE TABLE IF NOT EXISTS $table_chat (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) NOT NULL,
            receiver_id bigint(20) DEFAULT NULL,
            course_id bigint(20) DEFAULT NULL,
            message text NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY sender_id (sender_id),
            KEY receiver_id (receiver_id),
            KEY course_id (course_id),
            KEY created_at (created_at),
            KEY is_read (is_read)
        ) $charset_collate;";
        
        dbDelta($sql_chat);
        
        // Create quiz attempts tracking table
        $table_attempts = $wpdb->prefix . 'aqs_quiz_attempts';
        $sql_attempts = "CREATE TABLE IF NOT EXISTS $table_attempts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            quiz_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            module_type varchar(20) DEFAULT NULL,
            score decimal(10,2) NOT NULL,
            total_questions int(11) NOT NULL,
            answered_questions int(11) NOT NULL,
            attempt_started datetime DEFAULT CURRENT_TIMESTAMP,
            attempt_ended datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY quiz_id (quiz_id),
            KEY course_id (course_id),
            KEY module_type (module_type)
        ) $charset_collate;";
        
        dbDelta($sql_attempts);
        
        // Create mistakes table - NEW IN V5.0
        AQS_Mistakes_Tracker::create_table();
        
        // Set default options if not exist
        add_option('aqs_calculator_enabled', '1');
        add_option('aqs_drawing_enabled', '1');
        add_option('aqs_adaptive_quiz_enabled', '1');
        add_option('aqs_leaderboard_enabled', '1');
        add_option('aqs_chat_enabled', '1');
        add_option('aqs_tracker_enabled', '1');
        add_option('aqs_mistakes_enabled', '1'); // NEW
        add_option('aqs_predictor_enabled', '1'); // NEW
        add_option('aqs_standalone_quiz_enabled', '1'); // NEW
        add_option('aqs_easy_threshold', '20');
        add_option('aqs_leaderboard_limit', '5');
        add_option('aqs_leaderboard_auto_display', '1');
        add_option('aqs_leaderboard_weekly_enabled', '1');
        add_option('aqs_leaderboard_monthly_enabled', '1');
        add_option('aqs_leaderboard_color', '#0B417C'); // NEW COLOR
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
}

/**
 * Initialize the plugin
 */
function aqs_init() {
    return Advanced_Quiz_System::get_instance();
}

// Start the plugin
aqs_init();
