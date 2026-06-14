<?php
/**
 * Admin Chat Viewer Class
 * Allows admins to view all chat messages from all courses
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQS_Admin_Chat_Viewer {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (get_option('aqs_chat_enabled', '1') == '1') {
            add_action('admin_menu', array($this, 'add_admin_menu'), 100);
            add_action('wp_ajax_aqs_admin_load_chats', array($this, 'ajax_load_chats'));
            add_action('wp_ajax_aqs_admin_delete_message', array($this, 'ajax_delete_message'));
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tutor',
            __('مراقبة المحادثات', 'advanced-quiz-system'),
            __('💬 مراقبة المحادثات', 'advanced-quiz-system'),
            'manage_options',
            'aqs-chat-viewer',
            array($this, 'render_chat_viewer_page')
        );
    }
    
    /**
     * Render chat viewer page
     */
    public function render_chat_viewer_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('ليس لديك صلاحية للوصول لهذه الصفحة', 'advanced-quiz-system'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_chat_messages';
        
        // Get statistics
        $total_messages = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $today_messages = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = CURDATE()");
        $unique_users = $wpdb->get_var("SELECT COUNT(DISTINCT sender_id) FROM $table_name");
        
        ?>
        <div class="wrap aqs-admin-chat-viewer">
            <h1 class="wp-heading-inline">
                💬 <?php _e('مراقبة محادثات الطلاب', 'advanced-quiz-system'); ?>
            </h1>
            
            <div class="aqs-chat-stats">
                <div class="aqs-stat-card">
                    <div class="aqs-stat-icon">📊</div>
                    <div class="aqs-stat-content">
                        <h3><?php echo number_format($total_messages); ?></h3>
                        <p><?php _e('إجمالي الرسائل', 'advanced-quiz-system'); ?></p>
                    </div>
                </div>
                
                <div class="aqs-stat-card">
                    <div class="aqs-stat-icon">📅</div>
                    <div class="aqs-stat-content">
                        <h3><?php echo number_format($today_messages); ?></h3>
                        <p><?php _e('رسائل اليوم', 'advanced-quiz-system'); ?></p>
                    </div>
                </div>
                
                <div class="aqs-stat-card">
                    <div class="aqs-stat-icon">👥</div>
                    <div class="aqs-stat-content">
                        <h3><?php echo number_format($unique_users); ?></h3>
                        <p><?php _e('طلاب نشطون', 'advanced-quiz-system'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="aqs-chat-viewer-controls">
                <div class="aqs-filter-group">
                    <label for="aqs-filter-course"><?php _e('فلترة حسب الكورس:', 'advanced-quiz-system'); ?></label>
                    <select id="aqs-filter-course">
                        <option value=""><?php _e('جميع الكورسات', 'advanced-quiz-system'); ?></option>
                        <?php
                        $courses = get_posts(array(
                            'post_type' => 'courses',
                            'posts_per_page' => -1,
                            'orderby' => 'title',
                            'order' => 'ASC'
                        ));
                        
                        foreach ($courses as $course) {
                            echo '<option value="' . $course->ID . '">' . esc_html($course->post_title) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="aqs-filter-group">
                    <label for="aqs-filter-date"><?php _e('فلترة حسب التاريخ:', 'advanced-quiz-system'); ?></label>
                    <select id="aqs-filter-date">
                        <option value="all"><?php _e('كل الأوقات', 'advanced-quiz-system'); ?></option>
                        <option value="today"><?php _e('اليوم', 'advanced-quiz-system'); ?></option>
                        <option value="week"><?php _e('هذا الأسبوع', 'advanced-quiz-system'); ?></option>
                        <option value="month"><?php _e('هذا الشهر', 'advanced-quiz-system'); ?></option>
                    </select>
                </div>
                
                <button type="button" id="aqs-load-chats-btn" class="button button-primary">
                    <?php _e('تحميل المحادثات', 'advanced-quiz-system'); ?>
                </button>
                
                <button type="button" id="aqs-refresh-chats-btn" class="button">
                    🔄 <?php _e('تحديث', 'advanced-quiz-system'); ?>
                </button>
            </div>
            
            <div id="aqs-chat-messages-container" class="aqs-chat-messages-container">
                <div class="aqs-empty-state">
                    <div class="aqs-empty-icon">💬</div>
                    <h3><?php _e('اضغط "تحميل المحادثات" لعرض الرسائل', 'advanced-quiz-system'); ?></h3>
                </div>
            </div>
        </div>
        
        <style>
        .aqs-admin-chat-viewer {
            padding: 20px;
        }
        
        .aqs-chat-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .aqs-stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .aqs-stat-icon {
            font-size: 40px;
        }
        
        .aqs-stat-content h3 {
            margin: 0;
            font-size: 32px;
            font-weight: bold;
        }
        
        .aqs-stat-content p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
        
        .aqs-chat-viewer-controls {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .aqs-filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .aqs-filter-group label {
            font-weight: 600;
            font-size: 13px;
        }
        
        .aqs-filter-group select {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
            min-width: 200px;
        }
        
        .aqs-chat-messages-container {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            min-height: 400px;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .aqs-empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .aqs-empty-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .aqs-chat-message {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            position: relative;
        }
        
        .aqs-chat-message:hover {
            background: #efefef;
        }
        
        .aqs-message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            align-items: center;
        }
        
        .aqs-sender-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .aqs-sender-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        
        .aqs-sender-name {
            font-weight: bold;
            color: #333;
        }
        
        .aqs-message-time {
            font-size: 12px;
            color: #999;
        }
        
        .aqs-message-text {
            padding: 10px;
            background: white;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .aqs-message-actions {
            position: absolute;
            top: 10px;
            left: 10px;
        }
        
        .aqs-delete-message-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .aqs-delete-message-btn:hover {
            background: #c82333;
        }
        
        .aqs-loading-spinner {
            text-align: center;
            padding: 40px;
        }
        
        .aqs-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var loadingChats = false;
            
            // Load chats button
            $('#aqs-load-chats-btn, #aqs-refresh-chats-btn').on('click', function() {
                loadChats();
            });
            
            function loadChats() {
                if (loadingChats) return;
                
                loadingChats = true;
                var $container = $('#aqs-chat-messages-container');
                
                $container.html('<div class="aqs-loading-spinner"><div class="aqs-spinner"></div><p>جاري تحميل المحادثات...</p></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'aqs_admin_load_chats',
                        nonce: aqsAdminData.nonce,
                        course_id: $('#aqs-filter-course').val(),
                        date_filter: $('#aqs-filter-date').val()
                    },
                    success: function(response) {
                        loadingChats = false;
                        
                        if (response.success && response.data.messages) {
                            displayMessages(response.data.messages);
                        } else {
                            $container.html('<div class="aqs-empty-state"><div class="aqs-empty-icon">😔</div><h3>لا توجد رسائل</h3></div>');
                        }
                    },
                    error: function() {
                        loadingChats = false;
                        $container.html('<div class="aqs-empty-state"><div class="aqs-empty-icon">❌</div><h3>حدث خطأ أثناء التحميل</h3></div>');
                    }
                });
            }
            
            function displayMessages(messages) {
                var html = '';
                
                messages.forEach(function(msg) {
                    html += '<div class="aqs-chat-message" data-message-id="' + msg.id + '">';
                    html += '<div class="aqs-message-header">';
                    html += '<div class="aqs-sender-info">';
                    html += '<img src="' + msg.avatar + '" class="aqs-sender-avatar" alt="' + msg.sender_name + '">';
                    html += '<div>';
                    html += '<div class="aqs-sender-name">' + msg.sender_name + '</div>';
                    html += '<div class="aqs-message-time">' + msg.time + '</div>';
                    html += '</div>';
                    html += '</div>';
                    html += '<div class="aqs-message-actions">';
                    html += '<button class="aqs-delete-message-btn" data-message-id="' + msg.id + '">🗑️ حذف</button>';
                    html += '</div>';
                    html += '</div>';
                    html += '<div class="aqs-message-text">' + msg.message + '</div>';
                    html += '</div>';
                });
                
                $('#aqs-chat-messages-container').html(html);
            }
            
            // Delete message
            $(document).on('click', '.aqs-delete-message-btn', function() {
                if (!confirm('هل أنت متأكد من حذف هذه الرسالة؟')) {
                    return;
                }
                
                var $btn = $(this);
                var messageId = $btn.data('message-id');
                
                $btn.prop('disabled', true).text('جاري الحذف...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'aqs_admin_delete_message',
                        nonce: aqsAdminData.nonce,
                        message_id: messageId
                    },
                    success: function(response) {
                        if (response.success) {
                            $btn.closest('.aqs-chat-message').fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            alert('فشل الحذف');
                            $btn.prop('disabled', false).text('🗑️ حذف');
                        }
                    },
                    error: function() {
                        alert('حدث خطأ');
                        $btn.prop('disabled', false).text('🗑️ حذف');
                    }
                });
            });
            
            // Auto-refresh every 30 seconds
            setInterval(function() {
                if (!loadingChats) {
                    loadChats();
                }
            }, 30000);
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Load chats
     */
    public function ajax_load_chats() {
        check_ajax_referer('aqs_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_chat_messages';
        
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $date_filter = isset($_POST['date_filter']) ? sanitize_text_field($_POST['date_filter']) : 'all';
        
        $where = array('1=1');
        
        if ($course_id > 0) {
            $where[] = $wpdb->prepare('course_id = %d', $course_id);
        }
        
        switch ($date_filter) {
            case 'today':
                $where[] = 'DATE(created_at) = CURDATE()';
                break;
            case 'week':
                $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $messages = $wpdb->get_results("
            SELECT * FROM $table_name
            WHERE $where_clause
            ORDER BY created_at DESC
            LIMIT 100
        ");
        
        $formatted_messages = array();
        
        foreach ($messages as $msg) {
            $user = get_userdata($msg->sender_id);
            
            if (!$user) {
                continue;
            }
            
            $formatted_messages[] = array(
                'id' => $msg->id,
                'sender_id' => $msg->sender_id,
                'sender_name' => $user->display_name,
                'avatar' => get_avatar_url($msg->sender_id, array('size' => 40)),
                'message' => esc_html($msg->message),
                'time' => date_i18n('Y-m-d H:i', strtotime($msg->created_at))
            );
        }
        
        wp_send_json_success(array('messages' => $formatted_messages));
    }
    
    /**
     * AJAX: Delete message
     */
    public function ajax_delete_message() {
        check_ajax_referer('aqs_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
        
        if ($message_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid message ID'));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_chat_messages';
        
        $result = $wpdb->delete($table_name, array('id' => $message_id), array('%d'));
        
        if ($result) {
            wp_send_json_success(array('message' => 'Message deleted'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete'));
        }
    }
}
