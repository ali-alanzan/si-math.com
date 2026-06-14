<?php
/*
Plugin Name: Adaptive Exam Engine for Tutor LMS
Description: Independent adaptive exam system integrated with Tutor LMS assignments.
Version: 1.0
Author: Your Company
*/

if (!defined('ABSPATH')) exit;

/*
|--------------------------------------------------------------------------
| 1️⃣ Register Custom Post Types
|--------------------------------------------------------------------------
*/

add_action('init', function() {

    register_post_type('adaptive_exam', [
        'label' => 'Adaptive Exams',
        'public' => true,
        'supports' => ['title'],
        'menu_icon' => 'dashicons-welcome-learn-more'
    ]);

    register_post_type('adaptive_question', [
        'label' => 'Adaptive Questions',
        'public' => false,
        'show_ui' => true,
        'supports' => ['title'],
        'menu_icon' => 'dashicons-editor-help'
    ]);

});

/*
|--------------------------------------------------------------------------
| 2️⃣ Add Question Fields
|--------------------------------------------------------------------------
*/

add_action('add_meta_boxes', function() {

    add_meta_box(
        'adaptive_question_fields',
        'Question Data',
        function($post){

            $difficulty = get_post_meta($post->ID,'difficulty',true);
            $exam_id    = get_post_meta($post->ID,'exam_id',true);
            $answers    = get_post_meta($post->ID,'answers',true);
            $correct    = get_post_meta($post->ID,'correct_answer',true);

            if(!is_array($answers)) $answers = ['','','',''];

            ?>

            <p>
                <label>Exam ID</label>
                <input type="number" name="exam_id" value="<?php echo esc_attr($exam_id); ?>" style="width:100%;">
            </p>

            <p>
                <label>Difficulty</label>
                <select name="difficulty" style="width:100%;">
                    <option value="easy" <?php selected($difficulty,'easy'); ?>>Easy</option>
                    <option value="medium" <?php selected($difficulty,'medium'); ?>>Medium</option>
                    <option value="hard" <?php selected($difficulty,'hard'); ?>>Hard</option>
                </select>
            </p>

            <hr>

            <?php foreach($answers as $index=>$answer): ?>
                <p>
                    <label>Answer <?php echo $index+1; ?></label>
                    <input type="text" name="answers[]" value="<?php echo esc_attr($answer); ?>" style="width:100%;">
                </p>
            <?php endforeach; ?>

            <p>
                <label>Correct Answer Index (0-3)</label>
                <input type="number" name="correct_answer" value="<?php echo esc_attr($correct); ?>">
            </p>

            <?php
        },
        'adaptive_question'
    );

});

add_action('save_post', function($post_id){

    if(isset($_POST['difficulty']))
        update_post_meta($post_id,'difficulty',sanitize_text_field($_POST['difficulty']));

    if(isset($_POST['exam_id']))
        update_post_meta($post_id,'exam_id',intval($_POST['exam_id']));

    if(isset($_POST['answers']))
        update_post_meta($post_id,'answers',array_map('sanitize_text_field',$_POST['answers']));

    if(isset($_POST['correct_answer']))
        update_post_meta($post_id,'correct_answer',intval($_POST['correct_answer']));

});


// Add the Meta Box to the adaptive_exam post type
add_action('add_meta_boxes', function() {
    add_meta_box('exam_settings', 'Exam Display Settings', 'render_exam_settings_metabox', 'adaptive_exam', 'side');
});

function render_exam_settings_metabox($post) {
    $show_difficulty = get_post_meta($post->ID, '_show_difficulty', true);
    wp_nonce_field('save_exam_settings', 'exam_settings_nonce');
    ?>
    <label>
        <input type="checkbox" name="show_difficulty" value="1" <?php checked($show_difficulty, '1'); ?>>
        Show difficulty level to students
    </label>
    <?php
}

// Save the setting
add_action('save_post', function($post_id) {
    if (!isset($_POST['exam_settings_nonce']) || !wp_verify_nonce($_POST['exam_settings_nonce'], 'save_exam_settings')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    
    $val = isset($_POST['show_difficulty']) ? '1' : '0';
    update_post_meta($post_id, '_show_difficulty', $val);
});











add_shortcode('adaptive_exam', function($atts){
    if(!is_user_logged_in()) return "<div class='exam-notice'>Please login to access the exam.</div>";

    $exam_id = intval($atts['id']);
    $assignment_id = get_the_ID(); 
    $user_id = get_current_user_id();
    $user_exam_key = "adaptive_exam_{$exam_id}_assignment_{$assignment_id}";

    // --- STYLING BLOCK ---
    echo "
    <style>
        .sqt-save-btn-wrapper {
            display: none !important;
        }
        #tutor_assignment_start_btn { display: none; }
        .exam-container { font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 800px; margin: 2rem auto; border: 1px solid #eef2f7; border-radius: 16px; overflow: hidden; background: #ffffff; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .exam-header { background: #f8fafc; padding: 30px; border-bottom: 1px solid #f1f5f9; text-align: center; }
        .exam-header h2 { margin: 0; color: #1e293b; font-size: 24px; letter-spacing: -0.5px; }
        .exam-body { padding: 30px; }
        .history-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 25px; overflow: hidden; }
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th { background: #f8fafc; text-align: left; font-size: 11px; text-transform: uppercase; color: #64748b; padding: 12px 20px; letter-spacing: 1px; }
        .history-table td { padding: 15px 20px; border-top: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-pass { background: #dcfce7; color: #15803d; }
        .status-fail { background: #fee2e2; color: #b91c1c; }
        .btn-next { background: #2563eb; color: #fff; padding: 14px 30px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: block; width: 100%; font-size: 16px; text-align:center; text-decoration:none; }
        .btn-next:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,0.2); }
        
        /* New Button Styles */
        .btn-reset { background: #ef4444; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; transition: 0.3s; margin-top: 15px; }
        .btn-reset:hover { background: #dc2626; }
        .btn-cancel { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 12px 25px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 15px; flex: 1; margin-right: 10px; }
        .btn-cancel:hover { background: #e2e8f0; }
        
        .question-box { background: #fdfdfd; border: 1px solid #f1f5f9; padding: 25px; border-radius: 12px; margin-bottom: 20px; }
        .difficulty-indicator { font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 3px 8px; border-radius: 4px; margin-bottom: 12px; display: inline-block; }
        .flex-buttons { display: flex; align-items: center; justify-content: space-between; margin-top: 25px; }
    </style>";

    // ===== 1. RESET / NAVIGATION LOGIC =====
    
    // FULL RESET: Delete all progress and trials
    if(isset($_POST['full_reset_exam'])){
        delete_user_meta($user_id, "{$user_exam_key}_history");
        delete_user_meta($user_id, "{$user_exam_key}_level");
        delete_user_meta($user_id, "{$user_exam_key}_attempt_count");
        delete_user_meta($user_id, "{$user_exam_key}_shown_questions");
        delete_user_meta($user_id, "{$user_exam_key}_viewing_dashboard");
        echo "<script>window.location.href=window.location.href;</script>";
        exit;
    }

    // CANCEL: Go back to Trials Screen
    if(isset($_POST['cancel_to_trials'])){
        update_user_meta($user_id, "{$user_exam_key}_viewing_dashboard", true);
        echo "<script>window.location.href=window.location.href;</script>";
        exit;
    }

    // START NEXT: Hide dashboard to show questions
    if(isset($_POST['start_next_model'])){
        delete_user_meta($user_id, "{$user_exam_key}_viewing_dashboard");
        echo "<script>window.location.href=window.location.href;</script>";
        exit;
    }

    // ===== 2. SUBMISSION LOGIC (Remains Same) =====
    if(isset($_POST['adaptive_submit'])){
        $user_answers = $_POST['question'] ?? [];
        if(empty($user_answers)) return "<h3>Submission empty.</h3>";

        $correct_count = 0;
        foreach($user_answers as $qid => $ans_idx){
            $correct_idx = get_post_meta($qid, 'correct_answer', true);
            if($ans_idx == $correct_idx) $correct_count++;
        }

        $score = round(($correct_count / count($user_answers)) * 100, 2);
        $attempt_count = intval(get_user_meta($user_id, "{$user_exam_key}_attempt_count", true)) + 1;
        $current_lvl = get_user_meta($user_id, "{$user_exam_key}_level", true) ?: 'medium';
        $new_lvl = ($score >= 70) ? 'hard' : 'easy';
        
        $history = get_user_meta($user_id, "{$user_exam_key}_history", true) ?: [];
        $history[] = [
            'model' => $attempt_count,
            'score' => $score,
            'level' => ($attempt_count == 1) ? 'Diagnostic (Mixed)' : ucfirst($current_lvl),
            'date'  => date('M j, Y, g:i a')
        ];

        update_user_meta($user_id, "{$user_exam_key}_history", $history);
        update_user_meta($user_id, "{$user_exam_key}_level", $new_lvl);
        update_user_meta($user_id, "{$user_exam_key}_attempt_count", $attempt_count);
        update_user_meta($user_id, "{$user_exam_key}_viewing_dashboard", true);

        $shown = get_user_meta($user_id, "{$user_exam_key}_shown_questions", true) ?: [];
        update_user_meta($user_id, "{$user_exam_key}_shown_questions", array_unique(array_merge($shown, array_keys($user_answers))));

        echo "<script>window.location.href=window.location.href;</script>";
        exit;
    }

    // ===== 3. DASHBOARD VIEW (Trials List) =====
    $history = get_user_meta($user_id, "{$user_exam_key}_history", true);
    $viewing_dashboard = get_user_meta($user_id, "{$user_exam_key}_viewing_dashboard", true);

    if($viewing_dashboard || (empty($history) && $viewing_dashboard)){
        $next_model = count($history ?: []) + 1;
        ob_start(); ?>
        <div class="exam-container">
            <div class="exam-header">
                <h2>Exam Progress Track</h2>
                <p style="color: #64748b; margin-top: 5px;"><?php echo !empty($history) ? 'Review your previous trials' : 'Start your first assessment'; ?></p>
            </div>
            <div class="exam-body">
                <?php if(!empty($history)): ?>
                <div class="history-card">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Assessment Phase</th>
                                <th>Difficulty</th>
                                <th>Score</th>
                                <th>Outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history as $h): ?>
                            <tr>
                                <td><strong>Model <?php echo $h['model']; ?></strong></td>
                                <td><?php echo $h['level']; ?></td>
                                <td><?php echo $h['score']; ?>%</td>
                                <td>
                                    <span class="status-badge <?php echo $h['score'] >= 70 ? 'status-pass' : 'status-fail'; ?>">
                                        <?php echo $h['score'] >= 70 ? 'Mastery' : 'Keep Improving'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <form method="post" style="margin-top: 30px; text-align:center;">
                    <button name="start_next_model" class="btn-next">
                        <?php echo empty($history) ? 'Start Diagnostic Exam' : 'Begin Model ' . $next_model; ?> &rarr;
                    </button>
                    
                    <?php if(!empty($history)): ?>
                        <button name="full_reset_exam" class="btn-reset" onclick="return confirm('Are you sure? This will delete all your scores and trials!')">Reset All Progress</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ===== 4. QUESTIONS VIEW =====
    $attempt_count = intval(get_user_meta($user_id, "{$user_exam_key}_attempt_count", true));
    $shown_questions = get_user_meta($user_id, "{$user_exam_key}_shown_questions", true) ?: [];
    $current_difficulty = get_user_meta($user_id, "{$user_exam_key}_level", true) ?: 'medium';
    $total_per_model = 2; 
    $selected_questions = [];

    // Question Fetching Logic
    if($attempt_count == 0){
        $levels = ['easy', 'medium', 'hard'];
        foreach($levels as $lvl){
            $qs = get_posts(['post_type' => 'adaptive_question', 'posts_per_page' => 1, 'orderby' => 'rand', 'post__not_in' => $shown_questions, 'meta_query' => [['key' => 'exam_id', 'value' => $exam_id], ['key' => 'difficulty', 'value' => $lvl]]]);
            $selected_questions = array_merge($selected_questions, $qs);
        }
    } else {
        $selected_questions = get_posts(['post_type' => 'adaptive_question', 'posts_per_page' => $total_per_model, 'orderby' => 'rand', 'post__not_in' => $shown_questions, 'meta_query' => [['key' => 'exam_id', 'value' => $exam_id], ['key' => 'difficulty', 'value' => $current_difficulty]]]);
    }

    if(count($selected_questions) < 1){
        return "<div class='exam-container' style='padding:40px; text-align:center;'>
                    <h3>No more unique questions available.</h3>
                    <form method='post'><button name='cancel_to_trials' class='btn-next'>Back to Progress Track</button></form>
                </div>";
    }

    $show_difficulty_setting = get_post_meta($exam_id, '_show_difficulty', true);

    ob_start(); ?>
    <div class="exam-container">
        <div class="exam-header">
            <h2>Model <?php echo $attempt_count + 1; ?></h2>
            <p style="color: #64748b; margin-top: 5px;">
                <?php echo ($attempt_count == 0) ? 'Initial Diagnostic Assessment' : 'Level: <strong>' . ucfirst($current_difficulty) . '</strong>'; ?>
            </p>
        </div>
        <div class="exam-body">
            <form method="post">
                <?php foreach($selected_questions as $q): 
                    $answers = get_post_meta($q->ID, 'answers', true);
                    $diff = get_post_meta($q->ID, 'difficulty', true);
                    $d_color = ($diff == 'hard') ? '#ef4444' : (($diff == 'medium') ? '#f59e0b' : '#10b981');
                ?>
                <div class="question-box">
                    <?php if($show_difficulty_setting == '1'): ?>
                        <span class="difficulty-indicator" style="background: <?php echo $d_color; ?>15; color: <?php echo $d_color; ?>;">
                            <?php echo $diff; ?>
                        </span>
                    <?php endif; ?>
                    <p style="font-weight: 600; font-size: 17px; margin: 0 0 20px 0; color: #1e293b;"><?php echo esc_html($q->post_title); ?></p>
                    <?php if($answers): foreach($answers as $idx => $ans): ?>
                        <label style="display: block; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 10px; cursor: pointer;">
                            <input type="radio" name="question[<?php echo $q->ID; ?>]" value="<?php echo $idx; ?>" required style="margin-right: 12px;">
                            <?php echo esc_html($ans); ?>
                        </label>
                    <?php endforeach; endif; ?>
                </div>
                <?php endforeach; ?>

                <div class="flex-buttons">
                    <button type="submit" name="cancel_to_trials" class="btn-cancel" formnovalidate>Cancel & Back</button>
                    <button type="submit" name="adaptive_submit" class="btn-next" style="flex:2; margin:0;">Submit Model <?php echo $attempt_count + 1; ?> &rarr;</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

















































































































/*
|--------------------------------------------------------------------------
| Show Difficulty Column in Admin List
|--------------------------------------------------------------------------
*/

add_filter('manage_adaptive_question_posts_columns', function($columns){

    $new_columns = [];

    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;

        if ($key === 'title') {
            $new_columns['difficulty'] = 'Difficulty';
        }
    }

    return $new_columns;
});

add_action('manage_adaptive_question_posts_custom_column', function($column, $post_id){

    if ($column === 'difficulty') {

        $difficulty = get_post_meta($post_id, 'difficulty', true);

        if (!$difficulty) {
            echo '<span style="color:#999;">—</span>';
            return;
        }

        switch ($difficulty) {
            case 'easy':
                $color = '#2ecc71';
                break;

            case 'medium':
                $color = '#f39c12';
                break;

            case 'hard':
                $color = '#e74c3c';
                break;

            default:
                $color = '#999';
        }

        echo '<strong style="color:' . esc_attr($color) . ';">' . esc_html(ucfirst($difficulty)) . '</strong>';
    }

}, 10, 2);

add_filter('manage_edit-adaptive_question_sortable_columns', function($columns){
    $columns['difficulty'] = 'difficulty';
    return $columns;
});

add_action('pre_get_posts', function($query){
    if(!is_admin()) return;
    if($query->get('orderby') === 'difficulty'){
        $query->set('meta_key', 'difficulty');
        $query->set('orderby', 'meta_value');
    }
});






























// Add the column header
add_filter('manage_adaptive_exam_posts_columns', 'set_adaptive_exam_shortcode_column');
function set_adaptive_exam_shortcode_column($columns) {
    // This adds the column to the end. 
    // You can use array_slice if you want it in a specific position.
    $columns['exam_shortcode'] = __('Shortcode', 'textdomain');
    return $columns;
}

// Fill the column with the dynamic shortcode
add_action('manage_adaptive_exam_posts_custom_column' , 'fill_adaptive_exam_shortcode_column', 10, 2);
function fill_adaptive_exam_shortcode_column($column, $post_id) {
    if ($column === 'exam_shortcode') {
        // Create the shortcode string
        $shortcode = '[adaptive_exam id="' . $post_id . '"]';
        
        // Output a readonly input field so users can click and copy it easily
        echo '<input type="text" readonly value="' . esc_attr($shortcode) . '" 
              onclick="this.select();" 
              style="width:100%; background:transparent; border:none; cursor:pointer;" />';
    }
}

add_action('admin_head', 'adaptive_exam_column_width');
function adaptive_exam_column_width() {
    echo '<style>.column-exam_shortcode { width: 200px; }</style>';
}









































add_action('admin_notices', function(){

    if(!is_admin()) return;

    // Only run in Tutor assignment view page
    if(!isset($_GET['view_assignment'])) return;

    global $wpdb;

    // Get assignment submissions (comments used by Tutor LMS)
    $submissions = $wpdb->get_results("
        SELECT comment_post_ID, user_id, comment_content
        FROM {$wpdb->comments}
        WHERE comment_type = 'tutor_assignment'
        ORDER BY comment_ID DESC
        LIMIT 20
    ");

    if(empty($submissions)) return;

    echo '<div class="notice notice-info"><h2>Adaptive Exam Reports</h2>';

    foreach($submissions as $sub){

        $content = $sub->comment_content;

        // Match your format
        if(preg_match('/Exam_(\d+)_Assignment_(\d+)_(\d+)/', $content, $matches)){

            $exam_id       = $matches[1];
            $assignment_id = $matches[2];
            $user_id       = $matches[3];

            $meta_key = "adaptive_exam_{$exam_id}_assignment_{$assignment_id}_attempt";

            $attempts = get_user_meta($user_id, $meta_key);

            $user = get_user_by('id', $user_id);

            echo '<div style="background:#fff;padding:15px;margin-bottom:20px;border:1px solid #ddd;">';

            echo '<h3>User: '.$user->display_name.' (ID: '.$user_id.')</h3>';
            echo '<p><strong>Exam:</strong> '.$exam_id.' | <strong>Assignment:</strong> '.$assignment_id.'</p>';

            if(empty($attempts)){
                echo '<p style="color:red;">No attempts found</p>';
            } else {

                echo '<table style="width:100%;border-collapse:collapse;">';
                echo '<tr>
                        <th style="text-align: left;">Level</th>
                        <th style="text-align: left;">Score</th>
                        <th style="text-align: left;">Date</th>
                      </tr>';

                foreach($attempts as $attempt){

                    echo '<tr>
                            <td>'.$attempt['level'].'</td>
                            <td>'.$attempt['score'].'%</td>
                            <td>'.$attempt['date'].'</td>
                          </tr>';
                }

                echo '</table>';

                // Last result
                $last_result = get_user_meta($user_id,"adaptive_exam_{$exam_id}_assignment_{$assignment_id}_last_result",true);

                echo '<p><strong>Last Score:</strong> '.$last_result.'%</p>';
            }

            echo '</div>';
        }

    }

    echo '</div>';
});


