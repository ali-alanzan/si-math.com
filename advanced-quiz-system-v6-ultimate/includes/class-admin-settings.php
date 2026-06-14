<?php
/**
 * Admin Settings Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQS_Admin_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_chat_monitor_menu'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Advanced Quiz System', 'advanced-quiz-system'),
            __('Quiz System', 'advanced-quiz-system'),
            'manage_options',
            'aqs-settings',
            array($this, 'settings_page'),
            'dashicons-welcome-learn-more',
            30
        );
        
        add_submenu_page(
            'aqs-settings',
            __('Chat Monitor', 'advanced-quiz-system'),
            __('Chat Monitor', 'advanced-quiz-system'),
            'manage_options',
            'aqs-chat-monitor',
            array($this, 'chat_monitor_page')
        );
    }
    
    /**
     * Add chat monitor submenu
     */
    public function add_chat_monitor_menu() {
        // Already added in add_admin_menu
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('aqs_settings', 'aqs_calculator_enabled');
        register_setting('aqs_settings', 'aqs_drawing_enabled');
        register_setting('aqs_settings', 'aqs_adaptive_quiz_enabled');
        register_setting('aqs_settings', 'aqs_leaderboard_enabled');
        register_setting('aqs_settings', 'aqs_chat_enabled');
        register_setting('aqs_settings', 'aqs_easy_threshold');
        register_setting('aqs_settings', 'aqs_leaderboard_limit');
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('إعدادات نظام الامتحانات المتقدم', 'advanced-quiz-system'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('aqs_settings'); ?>
                
                <div class="aqs-admin-panel">
                    <h2><?php _e('الميزات الأساسية', 'advanced-quiz-system'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('تفعيل الآلة الحاسبة', 'advanced-quiz-system'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="aqs_calculator_enabled" value="1" <?php checked(get_option('aqs_calculator_enabled', '1'), '1'); ?>>
                                    <?php _e('عرض آلة حاسبة في صفحة الامتحان', 'advanced-quiz-system'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('تفعيل لوحة الرسم', 'advanced-quiz-system'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="aqs_drawing_enabled" value="1" <?php checked(get_option('aqs_drawing_enabled', '1'), '1'); ?>>
                                    <?php _e('عرض لوحة رسم في صفحة الامتحان', 'advanced-quiz-system'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('تفعيل الامتحانات التكيفية', 'advanced-quiz-system'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="aqs_adaptive_quiz_enabled" value="1" <?php checked(get_option('aqs_adaptive_quiz_enabled', '1'), '1'); ?>>
                                    <?php _e('تغيير صعوبة الأسئلة حسب أداء الطالب', 'advanced-quiz-system'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('عتبة الأسئلة السهلة (%)', 'advanced-quiz-system'); ?></th>
                            <td>
                                <input type="number" name="aqs_easy_threshold" value="<?php echo esc_attr(get_option('aqs_easy_threshold', '20')); ?>" min="0" max="100">
                                <p class="description"><?php _e('إذا حصل الطالب على درجة أقل من هذه النسبة في Module 1، سيحصل على أسئلة سهلة في Module 2', 'advanced-quiz-system'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <h2><?php _e('لوحة المتصدرين', 'advanced-quiz-system'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('تفعيل لوحة المتصدرين', 'advanced-quiz-system'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="aqs_leaderboard_enabled" value="1" <?php checked(get_option('aqs_leaderboard_enabled', '1'), '1'); ?>>
                                    <?php _e('عرض لوحة المتصدرين', 'advanced-quiz-system'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('عدد الطلاب في القائمة', 'advanced-quiz-system'); ?></th>
                            <td>
                                <input type="number" name="aqs_leaderboard_limit" value="<?php echo esc_attr(get_option('aqs_leaderboard_limit', '5')); ?>" min="1" max="20">
                                <p class="description"><?php _e('عدد الطلاب المعروضين في لوحة المتصدرين', 'advanced-quiz-system'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <h2><?php _e('نظام الدردشة', 'advanced-quiz-system'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('تفعيل الدردشة', 'advanced-quiz-system'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="aqs_chat_enabled" value="1" <?php checked(get_option('aqs_chat_enabled', '1'), '1'); ?>>
                                    <?php _e('السماح للطلاب المسجلين بالدردشة', 'advanced-quiz-system'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </div>
            </form>
            
            <div class="aqs-admin-panel" style="margin-top: 30px;">
                <h2><?php _e('استخدام Shortcodes', 'advanced-quiz-system'); ?></h2>
                
                <div class="aqs-shortcode-examples">
                    <h3><?php _e('لوحة المتصدرين', 'advanced-quiz-system'); ?></h3>
                    <code>[aqs_leaderboard]</code>
                    <p><?php _e('عرض أفضل 5 طلاب على مستوى جميع الكورسات', 'advanced-quiz-system'); ?></p>
                    
                    <code>[aqs_leaderboard course_id="123" period="week" limit="10"]</code>
                    <p><?php _e('عرض أفضل 10 طلاب لكورس معين هذا الأسبوع', 'advanced-quiz-system'); ?></p>
                    
                    <h4><?php _e('المعاملات المتاحة:', 'advanced-quiz-system'); ?></h4>
                    <ul>
                        <li><strong>course_id:</strong> <?php _e('رقم الكورس (اختياري)', 'advanced-quiz-system'); ?></li>
                        <li><strong>period:</strong> week, month, all (<?php _e('افتراضي: week', 'advanced-quiz-system'); ?>)</li>
                        <li><strong>limit:</strong> <?php _e('عدد الطلاب (افتراضي: 5)', 'advanced-quiz-system'); ?></li>
                        <li><strong>show_avatar:</strong> yes, no (<?php _e('افتراضي: yes', 'advanced-quiz-system'); ?>)</li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Chat monitor page
     */
    public function chat_monitor_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_chat_messages';
        
        // Get all chat messages
        $messages = $wpdb->get_results("
            SELECT * FROM $table_name
            ORDER BY created_at DESC
            LIMIT 100
        ");
        
        ?>
        <div class="wrap">
            <h1><?php _e('مراقبة الدردشة', 'advanced-quiz-system'); ?></h1>
            
            <div class="aqs-chat-monitor">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('الوقت', 'advanced-quiz-system'); ?></th>
                            <th><?php _e('المرسل', 'advanced-quiz-system'); ?></th>
                            <th><?php _e('الرسالة', 'advanced-quiz-system'); ?></th>
                            <th><?php _e('إجراءات', 'advanced-quiz-system'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($messages)): ?>
                            <tr>
                                <td colspan="4"><?php _e('لا توجد رسائل', 'advanced-quiz-system'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): 
                                $user = get_userdata($msg->sender_id);
                            ?>
                            <tr>
                                <td><?php echo esc_html($msg->created_at); ?></td>
                                <td>
                                    <?php echo get_avatar($msg->sender_id, 32); ?>
                                    <?php echo $user ? esc_html($user->display_name) : __('مستخدم محذوف', 'advanced-quiz-system'); ?>
                                </td>
                                <td><?php echo esc_html($msg->message); ?></td>
                                <td>
                                    <a href="#" class="button button-small" onclick="return confirm('<?php _e('هل أنت متأكد من الحذف؟', 'advanced-quiz-system'); ?>');">
                                        <?php _e('حذف', 'advanced-quiz-system'); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
