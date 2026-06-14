<?php
/**
 * Chat System Class - ULTIMATE VERSION V6.0
 * ✅ Fixed message sending (from v5)
 * ✅ Beautiful styling (from v4)  
 * ✅ Private messaging between students (NEW)
 * ✅ Group chat
 * ✅ Online users list
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
            
            // AJAX handlers
            add_action('wp_ajax_aqs_send_message', array($this, 'ajax_send_message'));
            add_action('wp_ajax_aqs_get_messages', array($this, 'ajax_get_messages'));
            add_action('wp_ajax_aqs_get_online_users', array($this, 'ajax_get_online_users'));
            add_action('wp_ajax_aqs_mark_messages_read', array($this, 'ajax_mark_messages_read'));
            add_action('wp_ajax_aqs_get_private_messages', array($this, 'ajax_get_private_messages'));
            add_action('wp_ajax_aqs_send_private_message', array($this, 'ajax_send_private_message'));
            add_action('wp_ajax_aqs_get_unread_count', array($this, 'ajax_get_unread_count'));
            
            // Update user activity
            add_action('init', array($this, 'update_user_online_status'));
            add_action('wp_ajax_aqs_heartbeat', array($this, 'ajax_heartbeat'));
            
            // Enrollment hook
            add_action('tutor_after_enrolled', array($this, 'enable_chat_for_user'), 10, 2);
        }
    }
    
    /**
     * Update user online status
     */
    public function update_user_online_status() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            update_user_meta($user_id, 'aqs_last_active', current_time('timestamp'));
            set_transient('aqs_user_online_' . $user_id, true, 300); // 5 minutes
        }
    }
    
    /**
     * Heartbeat to keep user status updated
     */
    public function ajax_heartbeat() {
        check_ajax_referer('aqs_nonce', 'nonce');
        $this->update_user_online_status();
        wp_send_json_success(array('timestamp' => current_time('timestamp')));
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
     * Get online users
     */
    private function get_online_users($course_id = 0) {
        global $wpdb;
        
        $online_threshold = current_time('timestamp') - 300; // Last 5 minutes
        
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = 'aqs_last_active' 
             AND meta_value > %d
             ORDER BY meta_value DESC
             LIMIT 50",
            $online_threshold
        ));
        
        $online_users = array();
        
        foreach ($user_ids as $user_id) {
            if (!get_transient('aqs_user_online_' . $user_id)) {
                continue;
            }
            
            if (!$this->can_access_chat($user_id)) {
                continue;
            }
            
            $user = get_userdata($user_id);
            if (!$user) {
                continue;
            }
            
            if ($course_id > 0) {
                if (!tutor_utils()->is_enrolled($course_id, $user_id)) {
                    continue;
                }
            }
            
            $online_users[] = array(
                'id' => $user_id,
                'name' => $user->display_name,
                'avatar' => get_avatar_url($user_id, array('size' => 32)),
                'last_active' => get_user_meta($user_id, 'aqs_last_active', true)
            );
        }
        
        return $online_users;
    }
    
    /**
     * Render chat widget with beautiful v4 styling
     */
    public function render_chat_widget() {
        if (!$this->can_access_chat()) {
            return;
        }
        
        $current_user = wp_get_current_user();
        ?>
        <div id="aqs-chat-widget" class="aqs-chat-widget aqs-chat-minimized">
            <div class="aqs-chat-header" onclick="aqsToggleChat()">
                <span class="aqs-chat-title">
                    💬 <?php _e('دردشة الطلاب', 'advanced-quiz-system'); ?>
                    <span id="aqs-unread-badge" class="aqs-unread-badge" style="display:none;">0</span>
                    <span id="aqs-online-count" class="aqs-online-count"></span>
                </span>
                <button class="aqs-chat-minimize" type="button">−</button>
            </div>
            
            <div class="aqs-chat-body">
                <!-- Chat Tabs -->
                <div class="aqs-chat-tabs">
                    <button class="aqs-chat-tab active" data-tab="group">
                        💬 الدردشة العامة
                    </button>
                    <button class="aqs-chat-tab" data-tab="private">
                        🔒 الرسائل الخاصة
                        <span id="aqs-private-unread" class="aqs-tab-badge" style="display:none;">0</span>
                    </button>
                </div>
                
                <!-- Group Chat Panel -->
                <div id="aqs-group-chat-panel" class="aqs-chat-panel active">
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
                
                <!-- Private Chat Panel -->
                <div id="aqs-private-chat-panel" class="aqs-chat-panel">
                    <div class="aqs-private-users-list-panel">
                        <div class="aqs-chat-panel-header">
                            <?php _e('اختر شخص للمحادثة', 'advanced-quiz-system'); ?>
                        </div>
                        <div id="aqs-private-users-list" class="aqs-private-users-list">
                            <div class="aqs-loading">
                                <div class="aqs-spinner"></div>
                                <?php _e('جاري التحميل...', 'advanced-quiz-system'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="aqs-private-chat-area">
                        <div class="aqs-private-chat-header" id="aqs-private-chat-header" style="display:none;">
                            <button class="aqs-back-btn" onclick="aqsClosePrivateChat()">←</button>
                            <div class="aqs-private-chat-user-info">
                                <img id="aqs-private-user-avatar" src="" alt="">
                                <span id="aqs-private-user-name"></span>
                            </div>
                        </div>
                        
                        <div id="aqs-private-messages" class="aqs-chat-messages">
                            <div class="aqs-welcome-message">
                                <div class="aqs-welcome-icon">🔒</div>
                                <h4><?php _e('الرسائل الخاصة', 'advanced-quiz-system'); ?></h4>
                                <p><?php _e('اختر شخص من القائمة لبدء محادثة خاصة', 'advanced-quiz-system'); ?></p>
                            </div>
                        </div>
                        
                        <div class="aqs-chat-input-area" id="aqs-private-input-area" style="display:none;">
                            <textarea 
                                id="aqs-private-input" 
                                placeholder="<?php _e('اكتب رسالتك الخاصة...', 'advanced-quiz-system'); ?>" 
                                rows="2"></textarea>
                            <button id="aqs-private-send-btn" type="button">
                                <span class="aqs-send-text"><?php _e('إرسال', 'advanced-quiz-system'); ?></span>
                                <span class="aqs-send-icon">➤</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        (function() {
            var aqsChatInterval;
            var aqsPrivateChatInterval;
            var aqsCurrentPrivateUser = null;
            var aqsCurrentUser = <?php echo json_encode(array(
                'id' => $current_user->ID,
                'name' => $current_user->display_name,
                'avatar' => get_avatar_url($current_user->ID, array('size' => 40))
            )); ?>;
            
            jQuery(document).ready(function($) {
                console.log('AQS Chat Ultimate: Initializing...');
                
                if (typeof aqsData === 'undefined') {
                    console.error('AQS Chat ERROR: aqsData not defined!');
                    return;
                }
                
                // Initialize chat
                aqsInitChat();
                
                // Tab switching
                $('.aqs-chat-tab').on('click', function() {
                    var tab = $(this).data('tab');
                    $('.aqs-chat-tab').removeClass('active');
                    $(this).addClass('active');
                    $('.aqs-chat-panel').removeClass('active');
                    
                    if (tab === 'group') {
                        $('#aqs-group-chat-panel').addClass('active');
                    } else if (tab === 'private') {
                        $('#aqs-private-chat-panel').addClass('active');
                        aqsLoadPrivateUsersList();
                    }
                });
                
                // Group chat send
                $('#aqs-chat-input').on('keypress', function(e) {
                    if (e.which === 13 && !e.shiftKey) {
                        e.preventDefault();
                        aqsSendMessage();
                    }
                });
                
                $(document).on('click', '#aqs-chat-send-btn', function(e) {
                    e.preventDefault();
                    aqsSendMessage();
                });
                
                // Private chat send
                $('#aqs-private-input').on('keypress', function(e) {
                    if (e.which === 13 && !e.shiftKey) {
                        e.preventDefault();
                        aqsSendPrivateMessage();
                    }
                });
                
                $(document).on('click', '#aqs-private-send-btn', function(e) {
                    e.preventDefault();
                    aqsSendPrivateMessage();
                });
                
                // Click on user for private chat
                $(document).on('click', '.aqs-private-user-item', function() {
                    var userId = $(this).data('user-id');
                    var userName = $(this).data('user-name');
                    var userAvatar = $(this).find('img').attr('src');
                    aqsOpenPrivateChat(userId, userName, userAvatar);
                });
                
                // Click on online user to start private chat
                $(document).on('click', '.aqs-user-item', function() {
                    var userId = $(this).data('user-id');
                    if (userId && userId != aqsCurrentUser.id) {
                        var userName = $(this).find('.aqs-user-name').text();
                        var userAvatar = $(this).find('img').attr('src');
                        
                        // Switch to private tab
                        $('.aqs-chat-tab[data-tab="private"]').click();
                        
                        // Open private chat
                        setTimeout(function() {
                            aqsOpenPrivateChat(userId, userName, userAvatar);
                        }, 300);
                    }
                });
                
                // Auto-expand textarea
                $('#aqs-chat-input, #aqs-private-input').on('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
                
                // Update unread count
                setInterval(aqsUpdateUnreadCount, 10000); // Every 10 seconds
                
                console.log('AQS Chat Ultimate: Initialized successfully');
            });
            
            window.aqsInitChat = function() {
                aqsLoadMessages();
                aqsLoadOnlineUsers();
                aqsUpdateUnreadCount();
                
                if (aqsChatInterval) {
                    clearInterval(aqsChatInterval);
                }
                
                aqsChatInterval = setInterval(function() {
                    if (!jQuery('#aqs-chat-widget').hasClass('aqs-chat-minimized')) {
                        if (jQuery('#aqs-group-chat-panel').hasClass('active')) {
                            aqsLoadMessages();
                        }
                    }
                    aqsLoadOnlineUsers();
                }, 5000);
                
                // Heartbeat every 30 seconds
                setInterval(function() {
                    jQuery.post(aqsData.ajaxurl, {
                        action: 'aqs_heartbeat',
                        nonce: aqsData.nonce
                    });
                }, 30000);
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
            
            window.aqsLoadMessages = function() {
                jQuery.ajax({
                    url: aqsData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'aqs_get_messages',
                        nonce: aqsData.nonce
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
                    url: aqsData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'aqs_get_online_users',
                        nonce: aqsData.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            aqsDisplayOnlineUsers(response.data);
                        }
                    }
                });
            };
            
            window.aqsSendMessage = function() {
                var $input = jQuery('#aqs-chat-input');
                var message = $input.val().trim();
                
                if (!message) {
                    return;
                }
                
                var $btn = jQuery('#aqs-chat-send-btn');
                $btn.prop('disabled', true);
                
                jQuery.ajax({
                    url: aqsData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'aqs_send_message',
                        nonce: aqsData.nonce,
                        message: message,
                        course_id: 0
                    },
                    success: function(response) {
                        if (response.success) {
                            $input.val('');
                            $input.css('height', 'auto');
                            aqsLoadMessages();
                        } else {
                            alert(response.data.message || 'فشل إرسال الرسالة');
                        }
                    },
                    error: function() {
                        alert('حدث خطأ في الإرسال');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            };
            
            window.aqsDisplayMessages = function(messages) {
                var $container = jQuery('#aqs-chat-messages');
                var scrollToBottom = $container[0].scrollHeight - $container.scrollTop() <= $container.outerHeight() + 100;
                
                $container.empty();
                
                if (!messages || messages.length === 0) {
                    $container.html(
                        '<div class="aqs-welcome-message">' +
                        '<div class="aqs-welcome-icon">👋</div>' +
                        '<h4>مرحباً بك!</h4>' +
                        '<p>لا توجد رسائل بعد. كن أول من يبدأ المحادثة!</p>' +
                        '</div>'
                    );
                    return;
                }
                
                messages.forEach(function(msg) {
                    var isOwn = msg.sender_id == aqsCurrentUser.id;
                    var messageHtml = 
                        '<div class="aqs-message ' + (isOwn ? 'aqs-message-own' : 'aqs-message-other') + '">' +
                        '<div class="aqs-message-avatar">' +
                        '<img src="' + msg.avatar + '" alt="' + msg.sender_name + '">' +
                        '</div>' +
                        '<div class="aqs-message-content">' +
                        '<div class="aqs-message-header">' +
                        '<span class="aqs-message-sender">' + msg.sender_name + '</span>' +
                        '<span class="aqs-message-time">' + aqsFormatTime(msg.created_at) + '</span>' +
                        '</div>' +
                        '<div class="aqs-message-text">' + aqsEscapeHtml(msg.message) + '</div>' +
                        '</div>' +
                        '</div>';
                    
                    $container.append(messageHtml);
                });
                
                if (scrollToBottom) {
                    $container.scrollTop($container[0].scrollHeight);
                }
            };
            
            window.aqsDisplayOnlineUsers = function(users) {
                var $container = jQuery('#aqs-online-users');
                $container.empty();
                
                if (!users || users.length === 0) {
                    $container.html('<div class="aqs-no-users">لا يوجد مستخدمون متصلون حالياً</div>');
                    jQuery('#aqs-online-count').text('(0)');
                    return;
                }
                
                var otherUsers = users.filter(function(user) {
                    return user.id != aqsCurrentUser.id;
                });
                
                jQuery('#aqs-online-count').text('(' + otherUsers.length + ')');
                
                otherUsers.forEach(function(user) {
                    var userHtml = 
                        '<div class="aqs-user-item" data-user-id="' + user.id + '" title="انقر للمحادثة الخاصة">' +
                        '<img src="' + user.avatar + '" alt="' + user.name + '">' +
                        '<span class="aqs-user-name">' + user.name + '</span>' +
                        '<span class="aqs-user-status">🟢</span>' +
                        '</div>';
                    
                    $container.append(userHtml);
                });
            };
            
            // Private Chat Functions
            window.aqsLoadPrivateUsersList = function() {
                aqsLoadOnlineUsers(); // Reuse online users for private chat list
                
                jQuery.ajax({
                    url: aqsData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'aqs_get_online_users',
                        nonce: aqsData.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            aqsDisplayPrivateUsersList(response.data);
                        }
                    }
                });
            };
            
            window.aqsDisplayPrivateUsersList = function(users) {
                var $container = jQuery('#aqs-private-users-list');
                $container.empty();
                
                if (!users || users.length === 0) {
                    $container.html('<div class="aqs-no-users">لا يوجد مستخدمون متصلون</div>');
                    return;
                }
                
                var otherUsers = users.filter(function(user) {
                    return user.id != aqsCurrentUser.id;
                });
                
                if (otherUsers.length === 0) {
                    $container.html('<div class="aqs-no-users">لا يوجد مستخدمون آخرون متصلون</div>');
                    return;
                }
                
                otherUsers.forEach(function(user) {
                    var userHtml = 
                        '<div class="aqs-private-user-item" data-user-id="' + user.id + '" data-user-name="' + user.name + '">' +
                        '<img src="' + user.avatar + '" alt="' + user.name + '">' +
                        '<div class="aqs-private-user-info">' +
                        '<span class="aqs-private-user-name">' + user.name + '</span>' +
                        '<span class="aqs-user-status">🟢 متصل</span>' +
                        '</div>' +
                        '</div>';
                    
                    $container.append(userHtml);
                });
            };
            
            window.aqsOpenPrivateChat = function(userId, userName, userAvatar) {
                aqsCurrentPrivateUser = userId;
                
                jQuery('#aqs-private-user-avatar').attr('src', userAvatar);
                jQuery('#aqs-private-user-name').text(userName);
                jQuery('#aqs-private-chat-header').show();
                jQuery('#aqs-private-input-area').show();
                
                jQuery('.aqs-private-users-list-panel').hide();
                jQuery('.aqs-private-chat-area').addClass('active');
                
                aqsLoadPrivateMessages(userId);
                
                // Start auto-refresh
                if (aqsPrivateChatInterval) {
                    clearInterval(aqsPrivateChatInterval);
                }
                
                aqsPrivateChatInterval = setInterval(function() {
                    if (aqsCurrentPrivateUser) {
                        aqsLoadPrivateMessages(aqsCurrentPrivateUser);
                    }
                }, 3000);
            };
            
            window.aqsClosePrivateChat = function() {
                aqsCurrentPrivateUser = null;
                
                jQuery('#aqs-private-chat-header').hide();
                jQuery('#aqs-private-input-area').hide();
                jQuery('.aqs-private-chat-area').removeClass('active');
                jQuery('.aqs-private-users-list-panel').show();
                
                if (aqsPrivateChatInterval) {
                    clearInterval(aqsPrivateChatInterval);
                }
                
                aqsLoadPrivateUsersList();
            };
            
            window.aqsLoadPrivateMessages = function(userId) {
                jQuery.ajax({
                    url: aqsData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'aqs_get_private_messages',
                        nonce: aqsData.nonce,
                        user_id: userId
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            aqsDisplayPrivateMessages(response.data);
                        }
                    }
                });
            };
            
            window.aqsSendPrivateMessage = function() {
                if (!aqsCurrentPrivateUser) {
                    return;
                }
                
                var $input = jQuery('#aqs-private-input');
                var message = $input.val().trim();
                
                if (!message) {
                    return;
                }
                
                var $btn = jQuery('#aqs-private-send-btn');
                $btn.prop('disabled', true);
                
                jQuery.ajax({
                    url: aqsData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'aqs_send_private_message',
                        nonce: aqsData.nonce,
                        receiver_id: aqsCurrentPrivateUser,
                        message: message
                    },
                    success: function(response) {
                        if (response.success) {
                            $input.val('');
                            $input.css('height', 'auto');
                            aqsLoadPrivateMessages(aqsCurrentPrivateUser);
                        } else {
                            alert(response.data.message || 'فشل إرسال الرسالة');
                        }
                    },
                    error: function() {
                        alert('حدث خطأ في الإرسال');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            };
            
            window.aqsDisplayPrivateMessages = function(messages) {
                var $container = jQuery('#aqs-private-messages');
                var scrollToBottom = $container[0].scrollHeight - $container.scrollTop() <= $container.outerHeight() + 100;
                
                $container.empty();
                
                if (!messages || messages.length === 0) {
                    $container.html(
                        '<div class="aqs-welcome-message">' +
                        '<div class="aqs-welcome-icon">💬</div>' +
                        '<h4>ابدأ المحادثة</h4>' +
                        '<p>لا توجد رسائل بعد. اكتب رسالتك الأولى!</p>' +
                        '</div>'
                    );
                    return;
                }
                
                messages.forEach(function(msg) {
                    var isOwn = msg.sender_id == aqsCurrentUser.id;
                    var messageHtml = 
                        '<div class="aqs-message ' + (isOwn ? 'aqs-message-own' : 'aqs-message-other') + '">' +
                        '<div class="aqs-message-avatar">' +
                        '<img src="' + msg.avatar + '" alt="' + msg.sender_name + '">' +
                        '</div>' +
                        '<div class="aqs-message-content">' +
                        '<div class="aqs-message-header">' +
                        '<span class="aqs-message-sender">' + msg.sender_name + '</span>' +
                        '<span class="aqs-message-time">' + aqsFormatTime(msg.created_at) + '</span>' +
                        '</div>' +
                        '<div class="aqs-message-text">' + aqsEscapeHtml(msg.message) + '</div>' +
                        '</div>' +
                        '</div>';
                    
                    $container.append(messageHtml);
                });
                
                if (scrollToBottom) {
                    $container.scrollTop($container[0].scrollHeight);
                }
            };
            
            window.aqsUpdateUnreadCount = function() {
                jQuery.ajax({
                    url: aqsData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'aqs_get_unread_count',
                        nonce: aqsData.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            var count = response.data.count || 0;
                            if (count > 0) {
                                jQuery('#aqs-unread-badge').text(count).show();
                                jQuery('#aqs-private-unread').text(count).show();
                            } else {
                                jQuery('#aqs-unread-badge').hide();
                                jQuery('#aqs-private-unread').hide();
                            }
                        }
                    }
                });
            };
            
            window.aqsFormatTime = function(datetime) {
                var date = new Date(datetime);
                var now = new Date();
                var diff = Math.floor((now - date) / 1000);
                
                if (diff < 60) return 'الآن';
                if (diff < 3600) return Math.floor(diff / 60) + ' د';
                if (diff < 86400) return Math.floor(diff / 3600) + ' س';
                
                return date.toLocaleDateString('ar-EG', { month: 'short', day: 'numeric' });
            };
            
            window.aqsEscapeHtml = function(text) {
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            };
        })();
        </script>
        
        <style>
        /* Modern Beautiful Chat Styling from V4 */
        .aqs-chat-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 99999;
            width: 420px;
            max-width: calc(100vw - 40px);
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        
        .aqs-chat-widget.aqs-chat-minimized {
            height: 60px;
        }
        
        .aqs-chat-widget:not(.aqs-chat-minimized) {
            height: 600px;
            max-height: calc(100vh - 100px);
        }
        
        .aqs-chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }
        
        .aqs-chat-title {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .aqs-online-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .aqs-unread-badge {
            background: #ff4757;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            min-width: 18px;
            text-align: center;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .aqs-chat-minimize {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .aqs-chat-minimize:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }
        
        .aqs-chat-body {
            display: flex;
            flex-direction: column;
            height: calc(100% - 60px);
            overflow: hidden;
        }
        
        .aqs-chat-tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .aqs-chat-tab {
            flex: 1;
            background: transparent;
            border: none;
            padding: 12px;
            font-size: 13px;
            font-weight: 500;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .aqs-chat-tab.active {
            color: #667eea;
            background: white;
        }
        
        .aqs-chat-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .aqs-tab-badge {
            background: #ff4757;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            margin-left: 4px;
        }
        
        .aqs-chat-panel {
            display: none;
            height: 100%;
            overflow: hidden;
        }
        
        .aqs-chat-panel.active {
            display: flex;
        }
        
        #aqs-group-chat-panel {
            display: flex;
        }
        
        .aqs-chat-users-panel {
            width: 140px;
            border-right: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
            background: #f8f9fa;
        }
        
        .aqs-chat-panel-header {
            padding: 12px;
            background: white;
            border-bottom: 1px solid #e9ecef;
            font-size: 12px;
            font-weight: 600;
            color: #495057;
            text-align: center;
        }
        
        .aqs-online-users-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }
        
        .aqs-user-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 4px;
            position: relative;
        }
        
        .aqs-user-item:hover {
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .aqs-user-item img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .aqs-user-name {
            flex: 1;
            font-size: 12px;
            font-weight: 500;
            color: #495057;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .aqs-user-status {
            font-size: 10px;
        }
        
        .aqs-chat-messages-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .aqs-chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .aqs-welcome-message {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .aqs-welcome-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }
        
        .aqs-welcome-message h4 {
            margin: 0 0 8px 0;
            color: #495057;
        }
        
        .aqs-welcome-message p {
            margin: 0;
            font-size: 14px;
        }
        
        .aqs-message {
            display: flex;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .aqs-message-own {
            flex-direction: row-reverse;
        }
        
        .aqs-message-avatar img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .aqs-message-content {
            max-width: 70%;
        }
        
        .aqs-message-own .aqs-message-content {
            text-align: right;
        }
        
        .aqs-message-header {
            display: flex;
            gap: 8px;
            margin-bottom: 4px;
            font-size: 12px;
        }
        
        .aqs-message-own .aqs-message-header {
            flex-direction: row-reverse;
        }
        
        .aqs-message-sender {
            font-weight: 600;
            color: #495057;
        }
        
        .aqs-message-time {
            color: #adb5bd;
            font-size: 11px;
        }
        
        .aqs-message-text {
            background: #f8f9fa;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
            color: #212529;
            word-wrap: break-word;
        }
        
        .aqs-message-own .aqs-message-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .aqs-chat-input-area {
            padding: 12px;
            background: white;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 8px;
        }
        
        .aqs-chat-input-area textarea {
            flex: 1;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 14px;
            resize: none;
            max-height: 100px;
            font-family: inherit;
        }
        
        .aqs-chat-input-area textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .aqs-chat-input-area button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0 16px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .aqs-chat-input-area button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .aqs-chat-input-area button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Private Chat Styles */
        #aqs-private-chat-panel {
            display: flex;
        }
        
        .aqs-private-users-list-panel {
            width: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .aqs-private-users-list {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }
        
        .aqs-private-user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 8px;
            background: white;
            border: 1px solid #e9ecef;
        }
        
        .aqs-private-user-item:hover {
            background: #f8f9fa;
            border-color: #667eea;
            transform: translateX(-4px);
        }
        
        .aqs-private-user-item img {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .aqs-private-user-info {
            flex: 1;
        }
        
        .aqs-private-user-name {
            display: block;
            font-weight: 600;
            color: #212529;
            margin-bottom: 4px;
        }
        
        .aqs-private-chat-area {
            width: 100%;
            display: none;
            flex-direction: column;
        }
        
        .aqs-private-chat-area.active {
            display: flex;
        }
        
        .aqs-private-chat-header {
            padding: 12px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .aqs-back-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .aqs-back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .aqs-private-chat-user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .aqs-private-chat-user-info img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .aqs-loading {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
        
        .aqs-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin: 0 auto 8px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .aqs-no-users {
            text-align: center;
            padding: 30px 20px;
            color: #6c757d;
            font-size: 14px;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .aqs-chat-widget {
                right: 10px;
                bottom: 10px;
                width: calc(100vw - 20px);
            }
            
            .aqs-chat-users-panel {
                width: 100px;
            }
            
            .aqs-user-name {
                display: none;
            }
        }
        </style>
        <?php
    }
    
    /**
     * AJAX: Send group message
     */
    public function ajax_send_message() {
        check_ajax_referer('aqs_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('يجب تسجيل الدخول', 'advanced-quiz-system')));
        }
        
        $user_id = get_current_user_id();
        
        if (!$this->can_access_chat($user_id)) {
            wp_send_json_error(array('message' => __('يجب التسجيل في كورس', 'advanced-quiz-system')));
        }
        
        $message = isset($_POST['message']) ? trim(sanitize_textarea_field($_POST['message'])) : '';
        
        if (empty($message)) {
            wp_send_json_error(array('message' => __('الرسالة فارغة', 'advanced-quiz-system')));
        }
        
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_chat_messages';
        
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'sender_id' => $user_id,
                'receiver_id' => null,
                'course_id' => $course_id,
                'message' => $message,
                'is_read' => 0,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%d', '%s')
        );
        
        if ($inserted === false) {
            wp_send_json_error(array('message' => __('فشل حفظ الرسالة', 'advanced-quiz-system')));
        }
        
        wp_send_json_success(array('message' => __('تم الإرسال', 'advanced-quiz-system')));
    }
    
    /**
     * AJAX: Get group messages
     */
    public function ajax_get_messages() {
        check_ajax_referer('aqs_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_chat_messages';
        
        $messages = $wpdb->get_results("
            SELECT m.*, u.display_name as sender_name
            FROM $table_name m
            LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
            WHERE m.receiver_id IS NULL
            ORDER BY m.created_at DESC
            LIMIT 50
        ");
        
        $formatted_messages = array();
        
        foreach (array_reverse($messages) as $msg) {
            $formatted_messages[] = array(
                'id' => $msg->id,
                'sender_id' => $msg->sender_id,
                'sender_name' => $msg->sender_name,
                'avatar' => get_avatar_url($msg->sender_id, array('size' => 32)),
                'message' => $msg->message,
                'created_at' => $msg->created_at
            );
        }
        
        wp_send_json_success($formatted_messages);
    }
    
    /**
     * AJAX: Get online users
     */
    public function ajax_get_online_users() {
        check_ajax_referer('aqs_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error();
        }
        
        $users = $this->get_online_users();
        wp_send_json_success($users);
    }
    
    /**
     * AJAX: Send private message
     */
    public function ajax_send_private_message() {
        check_ajax_referer('aqs_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('يجب تسجيل الدخول', 'advanced-quiz-system')));
        }
        
        $user_id = get_current_user_id();
        $receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
        $message = isset($_POST['message']) ? trim(sanitize_textarea_field($_POST['message'])) : '';
        
        if (empty($message) || !$receiver_id) {
            wp_send_json_error(array('message' => __('بيانات غير صحيحة', 'advanced-quiz-system')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_chat_messages';
        
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'sender_id' => $user_id,
                'receiver_id' => $receiver_id,
                'course_id' => 0,
                'message' => $message,
                'is_read' => 0,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%d', '%s')
        );
        
        if ($inserted === false) {
            wp_send_json_error(array('message' => __('فشل إرسال الرسالة', 'advanced-quiz-system')));
        }
        
        wp_send_json_success(array('message' => __('تم الإرسال', 'advanced-quiz-system')));
    }
    
    /**
     * AJAX: Get private messages
     */
    public function ajax_get_private_messages() {
        check_ajax_referer('aqs_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error();
        }
        
        $user_id = get_current_user_id();
        $other_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if (!$other_user_id) {
            wp_send_json_error();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_chat_messages';
        
        $messages = $wpdb->get_results($wpdb->prepare("
            SELECT m.*, u.display_name as sender_name
            FROM $table_name m
            LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
            WHERE (
                (m.sender_id = %d AND m.receiver_id = %d)
                OR
                (m.sender_id = %d AND m.receiver_id = %d)
            )
            ORDER BY m.created_at ASC
            LIMIT 100
        ", $user_id, $other_user_id, $other_user_id, $user_id));
        
        // Mark messages as read
        $wpdb->query($wpdb->prepare("
            UPDATE $table_name 
            SET is_read = 1 
            WHERE sender_id = %d AND receiver_id = %d AND is_read = 0
        ", $other_user_id, $user_id));
        
        $formatted_messages = array();
        
        foreach ($messages as $msg) {
            $formatted_messages[] = array(
                'id' => $msg->id,
                'sender_id' => $msg->sender_id,
                'sender_name' => $msg->sender_name,
                'avatar' => get_avatar_url($msg->sender_id, array('size' => 32)),
                'message' => $msg->message,
                'created_at' => $msg->created_at
            );
        }
        
        wp_send_json_success($formatted_messages);
    }
    
    /**
     * AJAX: Mark messages as read
     */
    public function ajax_mark_messages_read() {
        check_ajax_referer('aqs_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error();
        }
        
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_chat_messages';
        
        $wpdb->query($wpdb->prepare("
            UPDATE $table_name 
            SET is_read = 1 
            WHERE receiver_id = %d AND is_read = 0
        ", $user_id));
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Get unread count
     */
    public function ajax_get_unread_count() {
        check_ajax_referer('aqs_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error();
        }
        
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_chat_messages';
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM $table_name 
            WHERE receiver_id = %d AND is_read = 0
        ", $user_id));
        
        wp_send_json_success(array('count' => intval($count)));
    }
}
