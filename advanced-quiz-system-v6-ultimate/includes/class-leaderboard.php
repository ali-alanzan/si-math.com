<?php
/**
 * Leaderboard Class - ENHANCED VERSION v4.0
 * Weekly + Monthly + All-Time Rankings per Course
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQS_Leaderboard {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (get_option('aqs_leaderboard_enabled', '1') == '1') {
            // Save score after quiz attempt
            add_action('tutor_quiz/attempt/ended', array($this, 'save_quiz_score'), 10, 1);
            
            // Auto-display leaderboard
            if (get_option('aqs_leaderboard_auto_display', '1') == '1') {
                add_action('tutor_course/single/after/inner-wrap', array($this, 'display_course_leaderboard'));
            }
            
            // Shortcode
            add_shortcode('aqs_leaderboard', array($this, 'leaderboard_shortcode'));
            
            // AJAX handlers
            add_action('wp_ajax_aqs_get_leaderboard', array($this, 'ajax_get_leaderboard'));
            add_action('wp_ajax_nopriv_aqs_get_leaderboard', array($this, 'ajax_get_leaderboard'));
        }
    }
    
    /**
     * Save quiz score to leaderboard
     */
    public function save_quiz_score($attempt_id) {
        $attempt = tutor_utils()->get_attempt($attempt_id);
        
        if (!$attempt) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_leaderboard';
        
        $user_id = $attempt->user_id;
        $quiz_id = $attempt->quiz_id;
        
        // Get course ID from quiz
        $course_id = tutor_utils()->get_course_id_by_quiz($quiz_id);
        
        if (!$course_id) {
            return;
        }
        
        $total_marks = $attempt->total_marks;
        $earned_marks = $attempt->earned_marks;
        
        if ($total_marks > 0) {
            $score_percentage = ($earned_marks / $total_marks) * 100;
            
            // Insert score
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'course_id' => $course_id,
                    'quiz_id' => $quiz_id,
                    'score' => $score_percentage,
                    'date_recorded' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%f', '%s')
            );
        }
    }
    
    /**
     * Get leaderboard data
     */
    public function get_leaderboard_data($course_id, $period = 'all', $limit = 5) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_leaderboard';
        
        $where = array($wpdb->prepare('course_id = %d', $course_id));
        
        switch ($period) {
            case 'week':
                $where[] = 'date_recorded >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $where[] = 'date_recorded >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Get top performers
        $top_users = $wpdb->get_results($wpdb->prepare("
            SELECT 
                user_id,
                AVG(score) as avg_score,
                MAX(score) as max_score,
                COUNT(*) as attempts,
                MAX(date_recorded) as last_attempt
            FROM $table_name
            WHERE $where_clause
            GROUP BY user_id
            ORDER BY avg_score DESC
            LIMIT %d
        ", $limit));
        
        $leaderboard = array();
        $rank = 1;
        
        foreach ($top_users as $user_data) {
            $user = get_userdata($user_data->user_id);
            
            if (!$user) {
                continue;
            }
            
            $leaderboard[] = array(
                'rank' => $rank,
                'user_id' => $user_data->user_id,
                'name' => $user->display_name,
                'avatar' => get_avatar_url($user_data->user_id, array('size' => 60)),
                'avg_score' => round($user_data->avg_score, 2),
                'max_score' => round($user_data->max_score, 2),
                'attempts' => $user_data->attempts,
                'last_attempt' => human_time_diff(strtotime($user_data->last_attempt), current_time('timestamp')) . ' ' . __('مضت', 'advanced-quiz-system')
            );
            
            $rank++;
        }
        
        return $leaderboard;
    }
    
    /**
     * Get user's rank in course
     */
    public function get_user_rank($user_id, $course_id, $period = 'all') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aqs_leaderboard';
        
        $where = array($wpdb->prepare('course_id = %d', $course_id));
        
        switch ($period) {
            case 'week':
                $where[] = 'date_recorded >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $where[] = 'date_recorded >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Get all users ranked by average score
        $all_users = $wpdb->get_results("
            SELECT 
                user_id,
                AVG(score) as avg_score
            FROM $table_name
            WHERE $where_clause
            GROUP BY user_id
            ORDER BY avg_score DESC
        ");
        
        $rank = 1;
        $user_data = null;
        
        foreach ($all_users as $u) {
            if ($u->user_id == $user_id) {
                $user_data = array(
                    'rank' => $rank,
                    'avg_score' => round($u->avg_score, 2),
                    'total_users' => count($all_users)
                );
                break;
            }
            $rank++;
        }
        
        return $user_data;
    }
    
    /**
     * Display leaderboard on course page
     */
    public function display_course_leaderboard() {
        global $post;
        
        $course_id = $post->ID;
        $current_user_id = get_current_user_id();
        
        // Check if user is enrolled
        if (!tutor_utils()->is_enrolled($course_id, $current_user_id)) {
            return;
        }
        
        $limit = get_option('aqs_leaderboard_limit', 5);
        
        // Get data for all periods
        $weekly_enabled = get_option('aqs_leaderboard_weekly_enabled', '1') == '1';
        $monthly_enabled = get_option('aqs_leaderboard_monthly_enabled', '1') == '1';
        
        ?>
        <div class="aqs-leaderboard-section">
            <h2 class="aqs-section-title">
                🏆 <?php _e('لوحة المتصدرين', 'advanced-quiz-system'); ?>
            </h2>
            
            <div class="aqs-leaderboard-tabs">
                <?php if ($weekly_enabled): ?>
                <button class="aqs-tab-btn active" data-period="week">
                    📅 <?php _e('هذا الأسبوع', 'advanced-quiz-system'); ?>
                </button>
                <?php endif; ?>
                
                <?php if ($monthly_enabled): ?>
                <button class="aqs-tab-btn <?php echo !$weekly_enabled ? 'active' : ''; ?>" data-period="month">
                    📆 <?php _e('هذا الشهر', 'advanced-quiz-system'); ?>
                </button>
                <?php endif; ?>
                
                <button class="aqs-tab-btn <?php echo (!$weekly_enabled && !$monthly_enabled) ? 'active' : ''; ?>" data-period="all">
                    🌟 <?php _e('الأفضل على الإطلاق', 'advanced-quiz-system'); ?>
                </button>
            </div>
            
            <?php
            // Display user's personal rank card
            $default_period = $weekly_enabled ? 'week' : ($monthly_enabled ? 'month' : 'all');
            $user_rank = $this->get_user_rank($current_user_id, $course_id, $default_period);
            
            if ($user_rank):
            ?>
            <div class="aqs-user-rank-card">
                <div class="aqs-rank-badge">
                    <span class="aqs-rank-number">#<?php echo $user_rank['rank']; ?></span>
                </div>
                <div class="aqs-rank-info">
                    <h3><?php _e('ترتيبك الحالي', 'advanced-quiz-system'); ?></h3>
                    <p class="aqs-rank-details">
                        <?php printf(__('أنت في المركز %d من أصل %d طالب', 'advanced-quiz-system'), $user_rank['rank'], $user_rank['total_users']); ?>
                    </p>
                    <div class="aqs-rank-score">
                        <span><?php _e('متوسط درجاتك:', 'advanced-quiz-system'); ?></span>
                        <strong><?php echo $user_rank['avg_score']; ?>%</strong>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div id="aqs-leaderboard-container" class="aqs-leaderboard-container">
                <div class="aqs-loading">
                    <div class="aqs-spinner"></div>
                    <?php _e('جاري التحميل...', 'advanced-quiz-system'); ?>
                </div>
            </div>
        </div>
        
        <style>
        .aqs-leaderboard-section {
            margin: 40px 0;
            padding: 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .aqs-section-title {
            color: white;
            text-align: center;
            font-size: 32px;
            margin-bottom: 30px;
        }
        
        .aqs-leaderboard-tabs {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .aqs-tab-btn {
            padding: 12px 30px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .aqs-tab-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }
        
        .aqs-tab-btn.active {
            background: white;
            color: #667eea;
            border-color: white;
        }
        
        .aqs-user-rank-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .aqs-rank-badge {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(255,215,0,0.4);
        }
        
        .aqs-rank-number {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }
        
        .aqs-rank-info h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 20px;
        }
        
        .aqs-rank-details {
            color: #666;
            margin: 0 0 10px 0;
        }
        
        .aqs-rank-score {
            color: #667eea;
            font-size: 16px;
        }
        
        .aqs-rank-score strong {
            font-size: 24px;
            margin-right: 10px;
        }
        
        .aqs-leaderboard-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            min-height: 300px;
        }
        
        .aqs-loading {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .aqs-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .aqs-leaderboard-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 2px solid #f0f0f0;
            transition: all 0.3s ease;
        }
        
        .aqs-leaderboard-item:last-child {
            border-bottom: none;
        }
        
        .aqs-leaderboard-item:hover {
            background: #f9f9f9;
            transform: translateX(5px);
        }
        
        .aqs-rank {
            font-size: 24px;
            font-weight: bold;
            width: 50px;
            text-align: center;
        }
        
        .aqs-rank-1 {
            color: #ffd700;
        }
        
        .aqs-rank-2 {
            color: #c0c0c0;
        }
        
        .aqs-rank-3 {
            color: #cd7f32;
        }
        
        .aqs-user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: 0 20px;
            border: 3px solid #667eea;
        }
        
        .aqs-user-details {
            flex: 1;
        }
        
        .aqs-user-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            display: block;
            margin-bottom: 5px;
        }
        
        .aqs-user-stats {
            font-size: 13px;
            color: #999;
        }
        
        .aqs-score-display {
            text-align: left;
            padding-left: 20px;
        }
        
        .aqs-avg-score {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            display: block;
        }
        
        .aqs-max-score {
            font-size: 12px;
            color: #999;
        }
        
        .aqs-medal {
            font-size: 32px;
            margin-right: 10px;
        }
        
        @media (max-width: 768px) {
            .aqs-leaderboard-section {
                padding: 20px 15px;
            }
            
            .aqs-user-rank-card {
                flex-direction: column;
                text-align: center;
            }
            
            .aqs-leaderboard-item {
                flex-wrap: wrap;
                justify-content: center;
                text-align: center;
            }
            
            .aqs-rank {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .aqs-score-display {
                width: 100%;
                text-align: center;
                padding: 0;
                margin-top: 10px;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var courseId = <?php echo $course_id; ?>;
            var currentPeriod = '<?php echo $default_period; ?>';
            
            // Load initial leaderboard
            loadLeaderboard(currentPeriod);
            
            // Tab switching
            $('.aqs-tab-btn').on('click', function() {
                $('.aqs-tab-btn').removeClass('active');
                $(this).addClass('active');
                
                var period = $(this).data('period');
                currentPeriod = period;
                loadLeaderboard(period);
            });
            
            function loadLeaderboard(period) {
                var $container = $('#aqs-leaderboard-container');
                
                $container.html('<div class="aqs-loading"><div class="aqs-spinner"></div><p><?php _e('جاري التحميل...', 'advanced-quiz-system'); ?></p></div>');
                
                $.ajax({
                    url: aqsData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'aqs_get_leaderboard',
                        nonce: aqsData.nonce,
                        course_id: courseId,
                        period: period,
                        limit: <?php echo $limit; ?>
                    },
                    success: function(response) {
                        if (response.success && response.data.leaderboard) {
                            displayLeaderboard(response.data.leaderboard);
                        } else {
                            $container.html('<div class="aqs-empty-state"><p><?php _e('لا توجد بيانات لعرضها', 'advanced-quiz-system'); ?></p></div>');
                        }
                    },
                    error: function() {
                        $container.html('<div class="aqs-empty-state"><p><?php _e('حدث خطأ أثناء التحميل', 'advanced-quiz-system'); ?></p></div>');
                    }
                });
            }
            
            function displayLeaderboard(leaderboard) {
                var html = '';
                var medals = ['🥇', '🥈', '🥉'];
                
                leaderboard.forEach(function(user) {
                    var rankClass = 'aqs-rank-' + user.rank;
                    var medal = user.rank <= 3 ? '<span class="aqs-medal">' + medals[user.rank - 1] + '</span>' : '';
                    
                    html += '<div class="aqs-leaderboard-item">';
                    html += medal;
                    html += '<div class="aqs-rank ' + rankClass + '">#' + user.rank + '</div>';
                    html += '<img src="' + user.avatar + '" class="aqs-user-avatar" alt="' + user.name + '">';
                    html += '<div class="aqs-user-details">';
                    html += '<span class="aqs-user-name">' + user.name + '</span>';
                    html += '<span class="aqs-user-stats">' + user.attempts + ' <?php _e('محاولة', 'advanced-quiz-system'); ?> • <?php _e('آخر محاولة:', 'advanced-quiz-system'); ?> ' + user.last_attempt + '</span>';
                    html += '</div>';
                    html += '<div class="aqs-score-display">';
                    html += '<span class="aqs-avg-score">' + user.avg_score + '%</span>';
                    html += '<span class="aqs-max-score"><?php _e('أعلى درجة:', 'advanced-quiz-system'); ?> ' + user.max_score + '%</span>';
                    html += '</div>';
                    html += '</div>';
                });
                
                $('#aqs-leaderboard-container').html(html);
            }
        });
        </script>
        <?php
    }
    
    /**
     * Leaderboard shortcode
     */
    public function leaderboard_shortcode($atts) {
        $atts = shortcode_atts(array(
            'course_id' => 0,
            'period' => 'all',
            'limit' => 5
        ), $atts);
        
        $course_id = intval($atts['course_id']);
        
        if ($course_id <= 0) {
            return '<p>' . __('يجب تحديد معرف الكورس', 'advanced-quiz-system') . '</p>';
        }
        
        ob_start();
        // Similar to display_course_leaderboard but for shortcode
        echo '<div class="aqs-leaderboard-shortcode">[Leaderboard for course ' . $course_id . ']</div>';
        return ob_get_clean();
    }
    
    /**
     * AJAX: Get leaderboard
     */
    public function ajax_get_leaderboard() {
        check_ajax_referer('aqs_nonce', 'nonce');
        
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'all';
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 5;
        
        if ($course_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid course ID'));
            return;
        }
        
        $leaderboard = $this->get_leaderboard_data($course_id, $period, $limit);
        
        wp_send_json_success(array('leaderboard' => $leaderboard));
    }
}
