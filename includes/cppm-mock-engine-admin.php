<?php
/**
 * Core: Mock Test Database Architecture & Admin Importer
 * Architecture: Custom Post Types & JSON Bulk Importer
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. REGISTER CUSTOM POST TYPES
// ==========================================
add_action( 'init', 'cppm_register_mock_test_cpts' );
function cppm_register_mock_test_cpts() {
    
    // The Main Test Container
    register_post_type( 'cppm_mock_test', array(
        'labels'      => array(
            'name'          => 'Mock Tests',
            'singular_name' => 'Mock Test',
            'add_new_item'  => 'Add New Mock Test',
            'edit_item'     => 'Edit Mock Test',
        ),
        'public'      => true,
        'has_archive' => true,
        'menu_icon'   => 'dashicons-welcome-write-blog',
        'supports'    => array( 'title', 'editor', 'thumbnail' ), // Editor used for Instructions
        'rewrite'     => array( 'slug' => 'mock-test' ),
    ) );

    // The Hidden Question Database
    register_post_type( 'cppm_mock_question', array(
        'labels'      => array( 'name' => 'Questions', 'singular_name' => 'Question' ),
        'public'      => false, // Hidden from frontend URLs
        'show_ui'     => true,
        'show_in_menu'=> 'edit.php?post_type=cppm_mock_test', // Put under Mock Tests menu
        'supports'    => array( 'title' ),
    ) );
}

// ==========================================
// 2. ADMIN UI: TEST SETTINGS & JSON IMPORTER
// ==========================================
add_action( 'add_meta_boxes', 'cppm_mock_test_meta_boxes' );
function cppm_mock_test_meta_boxes() {
    add_meta_box( 'cppm_test_settings', 'Exam Rules & Scoring', 'cppm_render_test_settings', 'cppm_mock_test', 'normal', 'high' );
    add_meta_box( 'cppm_test_importer', 'Bulk Question Importer (JSON)', 'cppm_render_json_importer', 'cppm_mock_test', 'normal', 'high' );
}

function cppm_render_test_settings( $post ) {
    $duration = get_post_meta( $post->ID, '_cppm_duration', true ) ?: '60';
    $positive = get_post_meta( $post->ID, '_cppm_positive_marks', true ) ?: '2';
    $negative = get_post_meta( $post->ID, '_cppm_negative_marks', true ) ?: '0.5';
    
    wp_nonce_field( 'cppm_save_mock_test', 'cppm_mock_test_nonce' );
    ?>
    <div style="display:flex; gap:20px; padding: 10px 0;">
        <div>
            <label style="font-weight:bold; display:block;">Duration (Minutes)</label>
            <input type="number" name="cppm_duration" value="<?php echo esc_attr( $duration ); ?>" style="width:100%;" />
        </div>
        <div>
            <label style="font-weight:bold; display:block;">Marks per Correct Answer (+)</label>
            <input type="number" step="0.1" name="cppm_positive_marks" value="<?php echo esc_attr( $positive ); ?>" style="width:100%;" />
        </div>
        <div>
            <label style="font-weight:bold; display:block;">Negative Marking (-)</label>
            <input type="number" step="0.1" name="cppm_negative_marks" value="<?php echo esc_attr( $negative ); ?>" style="width:100%;" />
        </div>
    </div>
    <?php
}

function cppm_render_json_importer( $post ) {
    // Count existing questions
    $questions = get_posts( array(
        'post_type'   => 'cppm_mock_question',
        'meta_key'    => '_cppm_parent_test_id',
        'meta_value'  => $post->ID,
        'numberposts' => -1,
        'fields'      => 'ids'
    ) );
    $count = count($questions);
    ?>
    <div style="padding: 10px 0;">
        <p style="color:#10b981; font-weight:bold; font-size:16px;">Total Questions Loaded: <?php echo $count; ?></p>
        <p style="color:#64748b;">Paste a JSON array of questions here and hit "Update" to instantly add them to this test. <em>Note: This will ADD to existing questions, not overwrite them.</em></p>
        <textarea name="cppm_json_import" style="width:100%; height:200px; font-family:monospace; background:#1e293b; color:#10b981; padding:15px; border-radius:8px;" placeholder='[ { "question": "...", "options": ["A","B","C","D"], "correct_index": 0, "explanation": "...", "abc_notation": "" } ]'></textarea>
        
        <?php if ( $count > 0 ) : ?>
        <label style="display:block; margin-top:15px; color:#ef4444; font-weight:bold;">
            <input type="checkbox" name="cppm_delete_all_questions" value="yes"> DELETE ALL EXISTING QUESTIONS FOR THIS TEST
        </label>
        <?php endif; ?>
    </div>
    <?php
}

// ==========================================
// 3. PROCESS SAVING & BULK IMPORTING
// ==========================================
add_action( 'save_post_cppm_mock_test', 'cppm_save_mock_test_data' );
function cppm_save_mock_test_data( $post_id ) {
    
    // Security verification
    if ( ! isset( $_POST['cppm_mock_test_nonce'] ) || ! wp_verify_nonce( $_POST['cppm_mock_test_nonce'], 'cppm_save_mock_test' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    // Save Basic Settings
    if ( isset( $_POST['cppm_duration'] ) ) update_post_meta( $post_id, '_cppm_duration', intval( $_POST['cppm_duration'] ) );
    if ( isset( $_POST['cppm_positive_marks'] ) ) update_post_meta( $post_id, '_cppm_positive_marks', floatval( $_POST['cppm_positive_marks'] ) );
    if ( isset( $_POST['cppm_negative_marks'] ) ) update_post_meta( $post_id, '_cppm_negative_marks', floatval( $_POST['cppm_negative_marks'] ) );

    // Handle "Delete All" Checkbox
    if ( isset( $_POST['cppm_delete_all_questions'] ) && $_POST['cppm_delete_all_questions'] === 'yes' ) {
        $old_questions = get_posts( array( 'post_type' => 'cppm_mock_question', 'meta_key' => '_cppm_parent_test_id', 'meta_value' => $post_id, 'numberposts' => -1, 'fields' => 'ids' ) );
        foreach( $old_questions as $q_id ) { wp_delete_post( $q_id, true ); }
    }

    // Handle Bulk JSON Import
    if ( ! empty( $_POST['cppm_json_import'] ) ) {
        $json_string = stripslashes( $_POST['cppm_json_import'] );
        $questions_array = json_decode( $json_string, true );

        if ( is_array( $questions_array ) ) {
            foreach ( $questions_array as $index => $q_data ) {
                
                if ( empty( $q_data['question'] ) || ! isset( $q_data['options'] ) ) continue;

                // Create the question post
                $new_q_id = wp_insert_post( array(
                    'post_title'  => 'Q: ' . wp_trim_words( sanitize_text_field( $q_data['question'] ), 8, '...' ),
                    'post_type'   => 'cppm_mock_question',
                    'post_status' => 'publish'
                ) );

                if ( $new_q_id ) {
                    // Link to the parent test
                    update_post_meta( $new_q_id, '_cppm_parent_test_id', $post_id );
                    
                    // Save Question Data
                    update_post_meta( $new_q_id, '_cppm_q_text', sanitize_textarea_field( $q_data['question'] ) );
                    update_post_meta( $new_q_id, '_cppm_q_options', wp_slash( json_encode( $q_data['options'] ) ) );
                    update_post_meta( $new_q_id, '_cppm_q_correct', intval( $q_data['correct_index'] ) );
                    
                    if ( isset( $q_data['explanation'] ) ) {
                        update_post_meta( $new_q_id, '_cppm_q_explanation', sanitize_textarea_field( $q_data['explanation'] ) );
                    }
                    if ( isset( $q_data['abc_notation'] ) ) {
                        update_post_meta( $new_q_id, '_cppm_q_abc', sanitize_textarea_field( $q_data['abc_notation'] ) );
                    }
                }
            }
        }
    }
}