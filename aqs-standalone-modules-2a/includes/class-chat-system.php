<?php
/**
 * Chat System Class - SUPER FIXED VERSION
 * حل مشكلة عدم إرسال الرسائل نهائياً
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQS_Chat_System {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (get_option('aqs_chat_enabled', '1') == '1') {
            add_action('wp_footer', array($this, 'render_chat_widget'));
            
            // FIX: Make sure AJAX handlers are registered for both logged in and non-logged in users
            add_action('wp_ajax_aqs_send_message', array($this, 'ajax_send_message'));
            add_action('wp_ajax_nopriv_aqs_send_message', array($this, 'ajax_send_message'));
            
            add_action('wp_ajax_aqs_get_messages', array($this, 'ajax_get_messages'));
            add_action('wp_ajax_nopriv_aqs_get_messages', array($this, 'ajax_get_messages'));
            
            add_action('wp_ajax_aqs_get_online_users', array($this, 'ajax_get_online_users'));
            add_action('wp_ajax_nopriv_aqs_get_online_users', array($this, 'ajax_get_online_users'));
            
            add_action('tutor_after_enrolled', array($this, 'enable_chat_for_user'), 10, 2);
            add_action('init', array($this, 'update_user_online_status'));
            
            // FIX: Enqueue scripts properly
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        }
    }
    
    /**
     * FIX: Enqueue necessary scripts
     */
    public function enqueue_scripts() {
        if ($this->can_access_chat()) {
            wp_enqueue_script('jquery');
        }
    }
    
    /**
     * Update user online status
     */
    public function update_user_online_status() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            update_user_meta($user_id, 'aqs_last_active', current_time('timestamp'));
        }
    }
    
    /**
     * Check if user can access chat
     */
    private function can_access_chat($user_id = 0) {
        if ($user_id == 0) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        // Admins can always access
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Check if Tutor LMS is active
        if (!function_exists('tutor_utils')) {
            return false;
        }
        
        // Check if user has any enrolled courses
        $enrolled_courses = tutor_utils()->get_enrolled_courses_by_user($user_id);
        
        return !empty($enrolled_courses);
    }
    
    /**
     * Enable chat for user after enrollment
     */
    public function enable_chat_for_user($course_id, $user_id) {
        update_user_meta($user_id, 'aqs_chat_enabled', true);
    }
    
    /**
     * Render chat widget
     */
    public function render_chat_widget() {
        // Only show on course pages or if user is enrolled in any course
        if (!$this->can_access_chat()) {
            return;
        }
        
        $current_user = wp_get_current_user();
        
        // FIX: Get AJAX URL and nonce properly
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('aqs_nonce');
        ?>
        <div id="aqs-chat-widget" class="aqs-chat-widget aqs-chat-minimized">
            <div class="aqs-chat-header" onclick="aqsToggleChat()">
                <span class="aqs-chat-title">
                    💬 <?php _e('دردشة الطلاب', 'advanced-quiz-system'); ?>
                    <span id="aqs-online-count" class="aqs-online-count"></span>
                </span>
                <button class="aqs-chat-minimize" type="button">−</button>
            </div>
            
            <div class="aqs-chat-body">
                <div class="aqs-chat-users-panel">
                    <div class="aqs-chat-panel-header">
                        <?php _e('متصلون الآن', 'advanced-quiz-system'); ?>
                    </div>
                    <div id="aqs-online-users" class="aqs-online-users-list">
                        <div class="aqs-loading">
                            <div class="aqs-spinner"></div>
                            <?php _e('جاري التحميل...', 'advanced-quiz-system'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="aqs-chat-messages-panel">
                    <div id="aqs-chat-messages" class="aqs-chat-messages">
                        <div class="aqs-welcome-message">
                            <div class="aqs-welcome-icon">👋</div>
                            <h4><?php _e('مرحباً بك!', 'advanced-quiz-system'); ?></h4>
                            <p><?php _e('يمكنك الآن التواصل مع زملائك في الدراسة', 'advanced-quiz-system'); ?></p>
                        </div>
                    </div>
                    
                    <div class="aqs-chat-input-area">
                        <textarea 
                            id="aqs-chat-input" 
                            placeholder="<?php _e('اكتب رسالتك هنا...', 'advanced-quiz-system'); ?>" 
                            rows="2"></textarea>
                        <button id="aqs-chat-send-btn" type="button">
                            <span class="aqs-send-text"><?php _e('إرسال', 'advanced-quiz-system'); ?></span>
                            <span class="aqs-send-icon">➤</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        // FIX: Define global variables BEFORE using them
        var aqsAjaxUrl = <?php echo json_encode($ajax_url); ?>;
        var aqsNonce = <?php echo json_encode($nonce); ?>;
        
        (function() {
            var aqsChatInterval;
            var aqsCurrentUser = <?php echo json_encode(array(
                'id' => $current_user->ID,
                'name' => $current_user->display_name,
                'avatar' => get_avatar_url($current_user->ID, array('size' => 40))
            )); ?>;
            
            jQuery(document).ready(function($) {
                console.log('🚀 AQS Chat: Initializing...');
                console.log('📍 AJAX URL:', aqsAjaxUrl);
                console.log('🔑 Nonce:', aqsNonce);
                
                // Initialize chat
                aqsInitChat();
                
                // Enter to send
                $('#aqs-chat-input').on('keypress', function(e) {
                    if (e.which === 13 && !e.shiftKey) {
                        e.preventDefault();
                        console.log('⌨️ Enter pressed - sending message');
                        aqsSendMessage();
                    }
                });
                
                // Button click handler - FIX: Use proper event delegation
                $(document).on('click', '#aqs-chat-send-btn', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('🖱️ Send button clicked!');
                    aqsSendMessage();
                    return false;
                });
                
                // Auto-expand textarea
                $('#aqs-chat-input').on('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
                
                console.log('✅ AQS Chat: Event handlers attached successfully');
            });
            
            window.aqsInitChat = function() {
                aqsLoadMessages();
                aqsLoadOnlineUsers();
                
                // Auto refresh every 5 seconds
                if (aqsChatInterval) {
                    clearInterval(aqsChatInterval);
                }
                
                aqsChatInterval = setInterval(function() {
                    if (!jQuery('#aqs-chat-widget').hasClass('aqs-chat-minimized')) {
                        aqsLoadMessages();
                    }
                    aqsLoadOnlineUsers();
                }, 5000);
                
                console.log('✅ Chat initialized with auto-refresh');
            };
            
            window.aqsToggleChat = function() {
                jQuery('#aqs-chat-widget').toggleClass('aqs-chat-minimized');
                if (!jQuery('#aqs-chat-widget').hasClass('aqs-chat-minimized')) {
                    aqsLoadMessages();
                    setTimeout(function() {
                        jQuery('#aqs-chat-input').focus();
                    }, 300);
                }
            };
            
            // FIX: Completely rewritten send message function
            window.aqsSendMessage = function() {
                var $input = jQuery('#aqs-chat-input');
                var message = $input.val().trim();
                
                console.log('📤 Attempting to send message:', message);
                
                if (!message) {
                    console.warn('⚠️ Empty message, not sending');
                    return;
                }
                
                // Disable button while sending
                var $btn = jQuery('#aqs-chat-send-btn');
                $btn.prop('disabled', true).text('جاري الإرسال...');
                
                console.log('🌐 Sending AJAX request to:', aqsAjaxUrl);
                console.log('📦 Data:', {
                    action: 'aqs_send_message',
                    nonce: aqsNonce,
                    message: message
                });
                
                jQuery.ajax({
                    url: aqsAjaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aqs_send_message',
                        nonce: aqsNonce,
                        message: message
                    },
                    success: function(response) {
                        console.log('✅ AJAX Success:', response);
                        
                        if (response.success) {
                            console.log('✅ Message sent successfully!');
                            $input.val('');
                            $input.css('height', 'auto');
                            aqsLoadMessages(); // Reload messages immediately
                        } else {
                            console.error('❌ Server returned error:', response.data);
                            alert('خطأ: ' + (response.data ? response.data.message : 'فشل إرسال الرسالة'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ AJAX Error!');
                        console.error('Status:', status);
                        console.error('Error:', error);
                        console.error('Response:', xhr.responseText);
                        alert('حدث خطأ في الاتصال. الرجاء المحاولة مرة أخرى.');
                    },
                    complete: function() {
                        // Re-enable button
                        $btn.prop('disabled', false).html('<span class="aqs-send-text">إرسال</span><span class="aqs-send-icon">➤</span>');
                        console.log('🏁 Request completed');
                    }
                });
            };
            
            window.aqsLoadMessages = function() {
                jQuery.ajax({
                    url: aqsAjaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aqs_get_messages',
                        nonce: aqsNonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            aqsDisplayMessages(response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading messages:', error);
                    }
                });
            };
            
            window.aqsLoadOnlineUsers = function() {
                jQuery.ajax({
                    url: aqsAjaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aqs_get_online_users',
                        nonce: aqsNonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            aqsDisplayOnlineUsers(response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading online users:', error);
                    }
                });
            };
            
            window.aqsDisplayMessages = function(messages) {
                var $container = jQuery('#aqs-chat-messages');
                var html = '';
                
                if (!messages || messages.length === 0) {
                    html = '<div class="aqs-welcome-message">';
                    html += '<div class="aqs-welcome-icon">💬</div>';
                    html += '<h4>لا توجد رسائل بعد</h4>';
                    html += '<p>كن أول من يبدأ المحادثة!</p>';
                    html += '</div>';
                } else {
                    messages.forEach(function(msg) {
                        var isOwn = msg.sender_id == aqsCurrentUser.id;
                        var msgClass = isOwn ? 'aqs-message-own' : 'aqs-message-other';
                        
                        html += '<div class="aqs-message ' + msgClass + '">';
                        
                        if (!isOwn) {
                            html += '<img src="' + msg.avatar + '" class="aqs-message-avatar" alt="' + msg.sender_name + '">';
                        }
                        
                        html += '<div class="aqs-message-content">';
                        html += '<div class="aqs-message-header">';
                        html += '<span class="aqs-message-author">' + msg.sender_name + '</span>';
                        html += '<span class="aqs-message-time">' + msg.time + '</span>';
                        html += '</div>';
                        html += '<div class="aqs-message-text">' + msg.message + '</div>';
                        html += '</div>';
                        
                        if (isOwn) {
                            html += '<img src="' + msg.avatar + '" class="aqs-message-avatar" alt="' + msg.sender_name + '">';
                        }
                        
                        html += '</div>';
                    });
                }
                
                $container.html(html);
                
                // Scroll to bottom
                $container[0].scrollTop = $container[0].scrollHeight;
            };
            
            window.aqsDisplayOnlineUsers = function(users) {
                var $container = jQuery('#aqs-online-users');
                var $count = jQuery('#aqs-online-count');
                var html = '';
                
                if (!users || users.length === 0) {
                    html = '<div class="aqs-no-users">';
                    html += '<p>لا يوجد طلاب متصلون حالياً</p>';
                    html += '</div>';
                    $count.text('');
                } else {
                    $count.text('(' + users.length + ')');
                    
                    users.forEach(function(user) {
                        var isCurrentUser = user.id == aqsCurrentUser.id;
                        var userClass = isCurrentUser ? 'aqs-current-user' : '';
                        
                        html += '<div class="aqs-online-user ' + userClass + '">';
                        html += '<img src="' + user.avatar + '" class="aqs-user-avatar-small" alt="' + user.name + '">';
                        html += '<div class="aqs-user-details">';
                        html += '<span class="aqs-user-name">' + user.name;
                        if (isCurrentUser) html += ' <span class="aqs-you-badge">(أنت)</span>';
                        html += '</span>';
                        html += '</div>';
                        html += '<span class="aqs-user-status aqs-status-online"></span>';
                        html += '</div>';
                    });
                }
                
                $container.html(html);
            };
        })();
        </script>
        <?php
    }
    
    /**
     * AJAX: Send message - SUPER FIXED VERSION
     */
    public function ajax_send_message() {
        // FIX: Better error logging
        error_log('🔵 AQS Chat: Send message called');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aqs_nonce')) {
            error_log('❌ AQS Chat: Nonce verification failed');
            wp_send_json_error(array('message' => __('خطأ في التحقق الأمني', 'advanced-quiz-system')));
            return;
        }
        
        if (!$this->can_access_chat()) {
            error_log('❌ AQS Chat: User cannot access chat');
            wp_send_json_error(array('message' => __('ليس لديك صلاحية للدردشة', 'advanced-quiz-system')));
            return;
        }
        
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        
        if (empty($message)) {
            error_log('❌ AQS Chat: Empty message');
            wp_send_json_error(array('message' => __('الرسالة فارغة', 'advanced-quiz-system')));
            return;
        }
        
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_chat_messages';
        
        // FIX: Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log('❌ AQS Chat: Table does not exist');
            wp_send_json_error(array('message' => __('جدول الدردشة غير موجود', 'advanced-quiz-system')));
            return;
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'sender_id' => $user_id,
                'message' => $message,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('❌ AQS Chat: Database insert failed: ' . $wpdb->last_error);
            wp_send_json_error(array('message' => __('فشل حفظ الرسالة: ', 'advanced-quiz-system') . $wpdb->last_error));
            return;
        }
        
        error_log('✅ AQS Chat: Message saved successfully');
        wp_send_json_success(array('message' => __('تم إرسال الرسالة', 'advanced-quiz-system')));
    }
    
    /**
     * AJAX: Get messages
     */
    public function ajax_get_messages() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aqs_nonce')) {
            wp_send_json_error();
            return;
        }
        
        if (!$this->can_access_chat()) {
            wp_send_json_error();
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_chat_messages';
        
        // Get last 50 messages from last 24 hours
        $messages = $wpdb->get_results("
            SELECT * FROM $table_name
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC
            LIMIT 50
        ");
        
        if ($wpdb->last_error) {
            error_log('AQS Chat SQL Error: ' . $wpdb->last_error);
        }
        
        $formatted_messages = array();
        
        foreach (array_reverse($messages) as $msg) {
            $user = get_userdata($msg->sender_id);
            
            if (!$user) {
                continue;
            }
            
            $formatted_messages[] = array(
                'sender_id' => $msg->sender_id,
                'sender_name' => $user->display_name,
                'avatar' => get_avatar_url($msg->sender_id, array('size' => 40)),
                'message' => esc_html($msg->message),
                'time' => human_time_diff(strtotime($msg->created_at), current_time('timestamp')) . ' ' . __('مضت', 'advanced-quiz-system')
            );
        }
        
        wp_send_json_success($formatted_messages);
    }
    
    /**
     * AJAX: Get online users
     */
    public function ajax_get_online_users() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aqs_nonce')) {
            wp_send_json_error();
            return;
        }
        
        if (!$this->can_access_chat()) {
            wp_send_json_error();
            return;
        }
        
        // Update current user's last active time
        $current_user_id = get_current_user_id();
        update_user_meta($current_user_id, 'aqs_last_active', current_time('timestamp'));
        
        // Get online users (active in last 10 minutes)
        $online_users = array();
        $cutoff_time = current_time('timestamp') - 600; // 10 minutes ago
        
        // Get all users with chat enabled
        $enrolled_users = get_users(array(
            'meta_key' => 'aqs_chat_enabled',
            'meta_value' => true,
            'fields' => array('ID', 'display_name')
        ));
        
        foreach ($enrolled_users as $user) {
            $last_active = get_user_meta($user->ID, 'aqs_last_active', true);
            
            if ($last_active && $last_active >= $cutoff_time) {
                $online_users[] = array(
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'avatar' => get_avatar_url($user->ID, array('size' => 40))
                );
            }
        }
        
        wp_send_json_success($online_users);
    }
}
