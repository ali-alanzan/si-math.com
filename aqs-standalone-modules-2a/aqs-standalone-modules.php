<?php
/**
 * Plugin Name: AQS Standalone Modules
 * Plugin URI: https://yoursite.com
 * Description: Complete system for creating and managing adaptive modules for Tutor LMS from one place
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * Text Domain: aqs-standalone-modules
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('AQS_STANDALONE_VERSION', '1.0.0');
define('AQS_STANDALONE_FILE', __FILE__);
define('AQS_STANDALONE_PATH', plugin_dir_path(__FILE__));
define('AQS_STANDALONE_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class AQS_Standalone_Modules {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Check if Tutor LMS is active
        add_action('admin_init', array($this, 'check_dependencies'));
        
        // Load plugin files
        add_action('plugins_loaded', array($this, 'load_plugin'));
        
        // Activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Check if required plugins are active
     */
    public function check_dependencies() {
        if (!function_exists('tutor')) {
            add_action('admin_notices', array($this, 'tutor_missing_notice'));
            deactivate_plugins(plugin_basename(__FILE__));
            return;
        }
    }
    
    /**
     * Admin notice for missing Tutor LMS
     */
    public function tutor_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('نظام الموديلات التكيفية المستقل', 'aqs-standalone-modules'); ?></strong>
                <?php _e('يتطلب تثبيت وتفعيل إضافة Tutor LMS للعمل بشكل صحيح.', 'aqs-standalone-modules'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Load plugin files
     */
    public function load_plugin() {
        // Only load if Tutor LMS is active
        if (!function_exists('tutor')) {
            return;
        }
        
        // Load classes
        $this->load_classes();
        
        // Initialize modules
        $this->init_modules();
    }
    
    /**
     * Load all class files
     */
    private function load_classes() {
        // Core classes
        require_once AQS_STANDALONE_PATH . 'includes/class-module-manager-standalone.php';
        require_once AQS_STANDALONE_PATH . 'includes/class-adaptive-quiz.php';
        require_once AQS_STANDALONE_PATH . 'includes/class-chat-system.php';
        require_once AQS_STANDALONE_PATH . 'includes/class-drawing-canvas.php';
    }
    
    /**
     * Initialize all modules
     */
    private function init_modules() {
        // Initialize Module Manager
        AQS_Module_Manager::get_instance();
        
        // Initialize Adaptive Quiz System
        AQS_Adaptive_Quiz::get_instance();
        
        // Initialize Chat System
        AQS_Chat_System::get_instance();
        
        // Initialize Drawing Canvas
        AQS_Drawing_Canvas::get_instance();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_database_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set default options
        $this->set_default_options();
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Module attempts table
        $table_name = $wpdb->prefix . 'aqs_module_attempts';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            module_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            score decimal(5,2) NOT NULL,
            total_questions int(11) NOT NULL,
            correct_answers int(11) NOT NULL,
            time_taken int(11) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY module_id (module_id),
            KEY course_id (course_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Chat messages table
        $table_name = $wpdb->prefix . 'aqs_chat_messages';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) NOT NULL,
            message text NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY sender_id (sender_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        // Module system options
        add_option('aqs_adaptive_quiz_enabled', '1');
        add_option('aqs_chat_enabled', '1');
        add_option('aqs_drawing_enabled', '1');
        add_option('aqs_easy_threshold', 50);
        
        // Save activation time
        add_option('aqs_standalone_activated', current_time('mysql'));
        add_option('aqs_standalone_version', AQS_STANDALONE_VERSION);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
function aqs_standalone_init() {
    return AQS_Standalone_Modules::get_instance();
}

// Start the plugin
aqs_standalone_init();

/**
 * Display admin notice on activation
 */
function aqs_standalone_activation_notice() {
    if (get_transient('aqs_standalone_activation_notice')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong>🎉 <?php _e('نظام الموديلات التكيفية المستقل', 'aqs-standalone-modules'); ?></strong>
                <?php _e('تم التفعيل بنجاح! يمكنك الآن إنشاء موديلات من', 'aqs-standalone-modules'); ?>
                <a href="<?php echo admin_url('admin.php?page=aqs-modules'); ?>">
                    <?php _e('هنا', 'aqs-standalone-modules'); ?>
                </a>
            </p>
        </div>
        <?php
        delete_transient('aqs_standalone_activation_notice');
    }
}
add_action('admin_notices', 'aqs_standalone_activation_notice');

// Set activation notice transient
register_activation_hook(__FILE__, function() {
    set_transient('aqs_standalone_activation_notice', true, 5);
});
