<?php
/**
 * Core: Mock Test Frontend Portal & Grading Engine
 * Architecture: AJAX Evaluator, Access Control, URL-based Routing
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. FRONTEND SHORTCODE: [cppm_mock_test]
// ==========================================
add_shortcode( 'cppm_mock_test', 'cppm_render_mock_test' );
function cppm_render_mock_test( $atts ) {
    
    // 1. DYNAMIC ROUTING: Check the URL first (e.g., ?test_id=124)
    $test_id = isset( $_GET['test_id'] ) ? intval( $_GET['test_id'] ) : 0;

    // 2. FALLBACK: Check shortcode attribute if URL is empty
    if ( ! $test_id ) {
        $atts = shortcode_atts( array( 'id' => '' ), $atts );
        $test_id = intval( $atts['id'] );
    }

    if ( empty( $test_id ) ) {
        return '<div style="padding:40px; text-align:center; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;"><h3 style="color:#0f172a; margin-top:0;">Exam Not Found</h3><p>Please provide a valid test ID in the URL to begin.</p></div>';
    }

    $current_user = wp_get_current_user();

    // Access Gates
    if ( ! is_user_logged_in() ) {
        return '<div style="padding:40px; text-align:center; background:#fff3f3; border-radius:12px; border:1px solid #fecaca;"><h3 style="color:#dc2626; margin-top:0;">Authentication Required</h3><p>Please log in to take this mock test.</p></div>';
    }

    // Check Membership Ledger
    if ( function_exists('cppm_get_test_balance') ) {
        $attempts_left = cppm_get_test_balance( $current_user->ID, $test_id );
        
        // Admins bypass
        if ( ! current_user_can('manage_options') && $attempts_left === 0 ) {
            return '<div style="padding:40px; text-align:center; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;"><h3 style="color:#0f172a; margin-top:0;">Access Denied</h3><p>You do not have any attempts remaining for this test. Please purchase a test pass from the store.</p></div>';
        }
    }

    // Fetch Questions
    $question_posts = get_posts( array(
        'post_type'   => 'cppm_mock_question',
        'meta_key'    => '_cppm_parent_test_id',
        'meta_value'  => $test_id,
        'numberposts' => -1,
        'orderby'     => 'ID',
        'order'       => 'ASC'
    ) );

    if ( empty($question_posts) ) return '<div style="padding:40px; text-align:center; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;"><h3 style="color:#0f172a; margin-top:0;">Under Construction</h3><p>No questions have been configured for this test yet.</p></div>';

    $duration = get_post_meta( $test_id, '_cppm_duration', true ) ?: 60;
    
    // Prepare Secure JSON
    $js_questions = array();
    foreach ( $question_posts as $q ) {
        $options = json_decode( wp_unslash( get_post_meta( $q->ID, '_cppm_q_options', true ) ), true );
        $js_questions[] = array(
            'id'      => $q->ID,
            'text'    => wpautop( get_post_meta( $q->ID, '_cppm_q_text', true ) ),
            'options' => is_array($options) ? $options : array(),
            'abc'     => get_post_meta( $q->ID, '_cppm_q_abc', true )
        );
    }

    // Enqueue Assets
    $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
    
    wp_enqueue_script( 'abcjs-basic', 'https://cdnjs.cloudflare.com/ajax/libs/abcjs/6.0.0/abcjs-basic-min.js', array(), '6.0.0', true );
    wp_enqueue_style( 'cppm-mock-css', $plugin_url . 'assets/css/mock-test.css', array(), '1.0.0' );
    wp_enqueue_script( 'cppm-mock-js', $plugin_url . 'assets/js/mock-test.js', array('abcjs-basic'), '1.0.0', true );

    wp_localize_script( 'cppm-mock-js', 'cppmExamData', array(
        'testId'    => $test_id,
        'duration'  => intval($duration),
        'questions' => $js_questions,
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'cppm_exam_nonce' )
    ));

    // Render Shell
    ob_start();
    ?>
    <div class="cppm-exam-wrapper" id="cppm-exam-workspace">
        <div class="cppm-exam-header">
            <h1 class="cppm-exam-title"><?php echo get_the_title($test_id); ?></h1>
            <div class="cppm-exam-timer">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <span id="cppm-timer-display"><?php echo $duration; ?>:00</span>
            </div>
        </div>
        
        <div class="cppm-exam-body">
            <div class="cppm-exam-main">
                <div class="cppm-question-header">
                    <span id="cppm-q-num">Question 1</span>
                    <button class="cppm-btn cppm-btn-clear" id="btn-clear">Clear Response</button>
                </div>
                
                <div class="cppm-question-content">
                    <div id="cppm-q-text"></div>
                    <div id="cppm-q-abc" class="cppm-abc-container" style="display:none;"></div>
                    <div id="cppm-q-options" style="margin-top:20px;"></div>
                </div>

                <div class="cppm-exam-actions">
                    <button class="cppm-btn cppm-btn-review" id="btn-review-next">Mark for Review & Next</button>
                    <button class="cppm-btn cppm-btn-save" id="btn-save-next">Save & Next</button>
                </div>
            </div>

            <div class="cppm-exam-sidebar">
                <div class="cppm-palette-grid" id="cppm-palette-grid">
                    </div>
                <div class="cppm-palette-legend">
                    <div class="cppm-legend-item"><div class="cppm-legend-dot" style="background:#10b981;"></div> Answered</div>
                    <div class="cppm-legend-item"><div class="cppm-legend-dot" style="background:#ef4444;"></div> Not Answered</div>
                    <div class="cppm-legend-item"><div class="cppm-legend-dot" style="background:#8b5cf6;"></div> Marked for Review</div>
                    <div class="cppm-legend-item"><div class="cppm-legend-dot" style="background:#fff; border:1px solid #cbd5e1;"></div> Not Visited</div>
                </div>
                <button class="cppm-btn cppm-btn-submit" id="btn-submit" style="margin: 15px; width: calc(100% - 30px);">Submit Exam</button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ==========================================
// 2. AJAX HANDLER: SECURE GRADING ENGINE
// ==========================================
add_action( 'wp_ajax_cppm_submit_mock_test', 'cppm_ajax_submit_mock_test' );
function cppm_ajax_submit_mock_test() {
    check_ajax_referer( 'cppm_exam_nonce', 'security' );

    $user_id = get_current_user_id();
    $test_id = intval( $_POST['test_id'] );
    $time_taken = intval( $_POST['time_taken'] );
    $answers_json = stripslashes( $_POST['answers'] );
    $user_answers = json_decode( $answers_json, true );

    if ( ! $user_id || ! $test_id ) wp_send_json_error("Authentication Error.");

    // Fetch Grading Rules
    $positive = floatval( get_post_meta( $test_id, '_cppm_positive_marks', true ) ?: 2 );
    $negative = floatval( get_post_meta( $test_id, '_cppm_negative_marks', true ) ?: 0.5 );

    $question_posts = get_posts( array(
        'post_type'   => 'cppm_mock_question',
        'meta_key'    => '_cppm_parent_test_id',
        'meta_value'  => $test_id,
        'numberposts' => -1,
        'orderby'     => 'ID',
        'order'       => 'ASC'
    ) );

    $total_q = count( $question_posts );
    $correct_count = 0;
    $wrong_count = 0;
    $unattempted = 0;
    $score = 0;

    $explanations_html = '';

    // Grading Loop
    foreach ( $question_posts as $idx => $q ) {
        $correct_idx = intval( get_post_meta( $q->ID, '_cppm_q_correct', true ) );
        $options = json_decode( wp_unslash( get_post_meta( $q->ID, '_cppm_q_options', true ) ), true );
        $explanation = get_post_meta( $q->ID, '_cppm_q_explanation', true );
        $abc_note = get_post_meta( $q->ID, '_cppm_q_abc', true );
        
        $user_chose = isset( $user_answers[$q->ID] ) ? intval( $user_answers[$q->ID] ) : -1;

        $q_title = "Q" . ($idx + 1) . ". " . wp_strip_all_tags( get_post_meta( $q->ID, '_cppm_q_text', true ) );
        
        $explanations_html .= '<div class="cppm-explanation-box">';
        $explanations_html .= '<h4>' . esc_html($q_title) . '</h4>';

        if ( !empty($abc_note) ) {
            $explanations_html .= '<div id="cppm-exp-abc-'.$q->ID.'" class="cppm-exp-abc" data-abc="'.esc_attr($abc_note).'" style="border:1px dashed #cbd5e1; padding:10px; margin-bottom:15px; border-radius:6px; background:#f8fafc;"></div>';
        }

        if ( $user_chose === -1 ) {
            $unattempted++;
            $explanations_html .= '<p style="color:#64748b; font-weight:bold;">Status: Not Attempted</p>';
        } else if ( $user_chose === $correct_idx ) {
            $correct_count++;
            $score += $positive;
            $explanations_html .= '<p class="cppm-exp-correct">✓ Correct (+'.$positive.' marks)</p>';
        } else {
            $wrong_count++;
            $score -= $negative;
            $explanations_html .= '<p class="cppm-exp-wrong">✗ Wrong (-'.$negative.' marks)</p>';
            $user_ans_text = isset($options[$user_chose]) ? $options[$user_chose] : 'Unknown';
            $explanations_html .= '<p>Your Answer: <strike>' . esc_html($user_ans_text) . '</strike></p>';
        }

        $correct_ans_text = isset($options[$correct_idx]) ? $options[$correct_idx] : 'Unknown';
        $explanations_html .= '<p style="color:#10b981; font-weight:bold;">Correct Answer: ' . esc_html($correct_ans_text) . '</p>';

        if ( !empty($explanation) ) {
            $explanations_html .= '<div class="cppm-exp-text"><strong>Explanation:</strong><br>' . wpautop($explanation) . '</div>';
        }

        $explanations_html .= '</div>';
    }

    $max_score = $total_q * $positive;
    $accuracy = ($correct_count + $wrong_count > 0) ? round(($correct_count / ($correct_count + $wrong_count)) * 100) : 0;
    $mins = floor($time_taken / 60);
    $secs = $time_taken % 60;

    // Deduct Attempt from Ledger
    if ( function_exists('cppm_deduct_test_attempt') ) {
        cppm_deduct_test_attempt( $user_id, $test_id );
    }

    // Build Scorecard HTML
    ob_start();
    ?>
    <div class="cppm-scorecard">
        <h2 style="font-size:28px; color:#0f172a; margin-bottom:10px;">Test Submitted Successfully</h2>
        <p style="color:#64748b; margin-bottom:30px;">Here is your performance report.</p>

        <div class="cppm-score-circle">
            <?php echo $score; ?>
        </div>
        <p style="font-weight:bold; color:#1e293b;">Out of <?php echo $max_score; ?> marks</p>

        <div class="cppm-score-stats">
            <div class="cppm-stat-box"><h4>Correct</h4><p style="color:#10b981;"><?php echo $correct_count; ?></p></div>
            <div class="cppm-stat-box"><h4>Wrong</h4><p style="color:#ef4444;"><?php echo $wrong_count; ?></p></div>
            <div class="cppm-stat-box"><h4>Accuracy</h4><p style="color:#2874f0;"><?php echo $accuracy; ?>%</p></div>
            <div class="cppm-stat-box"><h4>Time</h4><p style="color:#8b5cf6;"><?php echo $mins . 'm ' . $secs . 's'; ?></p></div>
        </div>

        <h3 style="text-align:left; border-bottom:2px solid #e2e8f0; padding-bottom:10px; margin-top:40px;">Detailed Explanations</h3>
        <?php echo $explanations_html; ?>
        
        <a href="<?php echo esc_url( home_url('/my-account/') ); ?>" class="cppm-btn cppm-btn-save" style="text-decoration:none; display:inline-block; margin-top:20px;">Back to Dashboard</a>
    </div>
    <?php
    $scorecard = ob_get_clean();

    wp_send_json_success( $scorecard );
}