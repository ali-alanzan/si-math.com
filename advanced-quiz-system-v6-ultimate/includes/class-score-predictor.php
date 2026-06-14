<?php
/**
 * Score Predictor Class
 * توقع الدرجات بناءً على الأداء السابق
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQS_Score_Predictor {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Shortcode
        add_shortcode('aqs_score_predictor', array($this, 'predictor_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_aqs_predict_score', array($this, 'ajax_predict_score'));
        
        // Add to Tutor dashboard
        add_filter('tutor_dashboard/nav_items', array($this, 'add_predictor_nav'), 25);
        
        // Handle custom page
        add_action('template_redirect', array($this, 'handle_predictor_page'));
    }
    
    /**
     * Get user's quiz history
     */
    public function get_user_quiz_history($user_id, $course_id = 0, $limit = 10) {
        global $wpdb;
        
        $where = array($wpdb->prepare('user_id = %d', $user_id));
        
        if ($course_id > 0) {
            $where[] = $wpdb->prepare('quiz_id IN (SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type = "tutor_quiz" AND post_parent = %d)', $course_id);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $attempts = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                attempt_id,
                quiz_id,
                total_marks,
                earned_marks,
                attempt_status,
                attempt_started_at,
                attempt_ended_at
            FROM {$wpdb->prefix}tutor_quiz_attempts
            WHERE $where_clause 
            AND attempt_status = 'attempt_ended'
            ORDER BY attempt_id DESC
            LIMIT %d",
            $limit
        ));
        
        $history = array();
        
        foreach ($attempts as $attempt) {
            if ($attempt->total_marks > 0) {
                $percentage = ($attempt->earned_marks / $attempt->total_marks) * 100;
                
                $history[] = array(
                    'attempt_id' => $attempt->attempt_id,
                    'quiz_id' => $attempt->quiz_id,
                    'quiz_title' => get_the_title($attempt->quiz_id),
                    'score' => round($percentage, 2),
                    'earned_marks' => $attempt->earned_marks,
                    'total_marks' => $attempt->total_marks,
                    'date' => $attempt->attempt_ended_at,
                    'time_taken' => $this->calculate_time_diff($attempt->attempt_started_at, $attempt->attempt_ended_at)
                );
            }
        }
        
        return $history;
    }
    
    /**
     * Calculate time difference
     */
    private function calculate_time_diff($start, $end) {
        $start_time = strtotime($start);
        $end_time = strtotime($end);
        $diff = $end_time - $start_time;
        
        $hours = floor($diff / 3600);
        $minutes = floor(($diff % 3600) / 60);
        $seconds = $diff % 60;
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            return sprintf('%02d:%02d', $minutes, $seconds);
        }
    }
    
    /**
     * Predict score using linear regression
     */
    public function predict_score($user_id, $course_id = 0) {
        $history = $this->get_user_quiz_history($user_id, $course_id, 20);
        
        if (empty($history) || count($history) < 3) {
            return array(
                'status' => 'insufficient_data',
                'message' => __('تحتاج إلى 3 امتحانات على الأقل للتوقع', 'advanced-quiz-system'),
                'attempts_count' => count($history)
            );
        }
        
        // Extract scores
        $scores = array_map(function($item) {
            return $item['score'];
        }, $history);
        
        // Reverse to get chronological order
        $scores = array_reverse($scores);
        
        // Calculate statistics
        $avg_score = array_sum($scores) / count($scores);
        $max_score = max($scores);
        $min_score = min($scores);
        
        // Calculate trend (linear regression)
        $n = count($scores);
        $x_sum = 0;
        $y_sum = 0;
        $xy_sum = 0;
        $x_squared_sum = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1; // Attempt number
            $y = $scores[$i]; // Score
            
            $x_sum += $x;
            $y_sum += $y;
            $xy_sum += ($x * $y);
            $x_squared_sum += ($x * $x);
        }
        
        // Calculate slope (m) and intercept (b) for y = mx + b
        $slope = ($n * $xy_sum - $x_sum * $y_sum) / ($n * $x_squared_sum - $x_sum * $x_sum);
        $intercept = ($y_sum - $slope * $x_sum) / $n;
        
        // Predict next score
        $next_x = $n + 1;
        $predicted_score = $slope * $next_x + $intercept;
        
        // Ensure prediction is within valid range
        $predicted_score = max(0, min(100, $predicted_score));
        
        // Calculate confidence level based on score variance
        $variance = 0;
        foreach ($scores as $score) {
            $variance += pow($score - $avg_score, 2);
        }
        $variance = $variance / $n;
        $std_deviation = sqrt($variance);
        
        // Lower standard deviation = higher confidence
        $confidence = max(0, min(100, 100 - ($std_deviation * 2)));
        
        // Determine trend
        $trend = 'stable';
        if ($slope > 2) {
            $trend = 'improving';
        } elseif ($slope < -2) {
            $trend = 'declining';
        }
        
        // Calculate improvement rate
        $improvement_rate = $slope;
        
        // Get recent performance (last 5 attempts)
        $recent_scores = array_slice($scores, -5);
        $recent_avg = array_sum($recent_scores) / count($recent_scores);
        
        return array(
            'status' => 'success',
            'prediction' => round($predicted_score, 2),
            'confidence' => round($confidence, 2),
            'trend' => $trend,
            'improvement_rate' => round($improvement_rate, 2),
            'statistics' => array(
                'average' => round($avg_score, 2),
                'max' => round($max_score, 2),
                'min' => round($min_score, 2),
                'recent_average' => round($recent_avg, 2),
                'std_deviation' => round($std_deviation, 2),
                'attempts_count' => $n
            ),
            'history' => $history,
            'recommendation' => $this->get_recommendation($predicted_score, $trend, $avg_score)
        );
    }
    
    /**
     * Get personalized recommendation
     */
    private function get_recommendation($predicted_score, $trend, $avg_score) {
        $recommendations = array();
        
        if ($trend === 'improving') {
            $recommendations[] = '🎉 ' . __('أنت تتحسن باستمرار! استمر في الأداء الجيد', 'advanced-quiz-system');
        } elseif ($trend === 'declining') {
            $recommendations[] = '⚠️ ' . __('لاحظنا انخفاض في الأداء. راجع الأخطاء السابقة', 'advanced-quiz-system');
        } else {
            $recommendations[] = '📊 ' . __('أداء مستقر. حاول تحدي نفسك أكثر', 'advanced-quiz-system');
        }
        
        if ($predicted_score >= 90) {
            $recommendations[] = '⭐ ' . __('مستوى ممتاز! أنت مستعد للامتحان النهائي', 'advanced-quiz-system');
        } elseif ($predicted_score >= 75) {
            $recommendations[] = '👍 ' . __('مستوى جيد. ركز على النقاط الضعيفة', 'advanced-quiz-system');
        } elseif ($predicted_score >= 60) {
            $recommendations[] = '📚 ' . __('تحتاج لمزيد من المراجعة والممارسة', 'advanced-quiz-system');
        } else {
            $recommendations[] = '💪 ' . __('راجع المواد وحل المزيد من الأمثلة', 'advanced-quiz-system');
        }
        
        if ($avg_score < $predicted_score) {
            $recommendations[] = '📈 ' . __('التوقعات تشير لتحسن قادم!', 'advanced-quiz-system');
        }
        
        return $recommendations;
    }
    
    /**
     * Add predictor nav to dashboard
     */
    public function add_predictor_nav($items) {
        $items['score-predictor'] = array(
            'title' => __('توقع الدرجات', 'advanced-quiz-system'),
            'icon' => 'tutor-icon-chart-line',
            'url' => tutor_utils()->get_tutor_dashboard_url('score-predictor')
        );
        
        return $items;
    }
    
    /**
     * Handle predictor page
     */
    public function handle_predictor_page() {
        if (get_query_var('tutor_dashboard_page') === 'score-predictor') {
            include AQS_PLUGIN_DIR . 'templates/score-predictor-page.php';
            exit;
        }
    }
    
    /**
     * Shortcode
     */
    public function predictor_shortcode($atts) {
        $atts = shortcode_atts(array(
            'course_id' => 0
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<p>' . __('يجب تسجيل الدخول لعرض التوقعات', 'advanced-quiz-system') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $prediction = $this->predict_score($user_id, $atts['course_id']);
        
        ob_start();
        include AQS_PLUGIN_DIR . 'templates/score-predictor.php';
        return ob_get_clean();
    }
    
    /**
     * AJAX: Predict score
     */
    public function ajax_predict_score() {
        check_ajax_referer('aqs_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('غير مصرح', 'advanced-quiz-system')));
        }
        
        $user_id = get_current_user_id();
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        
        $prediction = $this->predict_score($user_id, $course_id);
        
        wp_send_json_success($prediction);
    }
}
