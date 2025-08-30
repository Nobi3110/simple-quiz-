<?php
/*
Plugin Name: Simple Quiz
Description: A simple quiz plugin for beginners with improved design.
Version: 1.0
Author: Arif
*/

function simple_quiz_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Quizzes table
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}quizzes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL
    ) $charset_collate");

    // Questions table
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}quiz_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quiz_id INT NOT NULL,
        question TEXT,
        option1 TEXT, option2 TEXT, option3 TEXT, option4 TEXT,
        correct_option TINYINT,
        INDEX (quiz_id)
    ) $charset_collate");

    // Attempts table
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}quiz_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT,
        quiz_id INT,
        score INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate");
}
register_activation_hook(__FILE__, 'simple_quiz_activate');

function simple_quiz_uninstall() {
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}quiz_questions");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}quiz_attempts");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}quizzes");
}
register_uninstall_hook(__FILE__, 'simple_quiz_uninstall');


//Admin Menu
add_action('admin_menu', function(){
    add_menu_page("Simple Quiz", "Simple Quiz", "manage_options", "simple-quiz", "quiz_admin_page");
});

function quiz_admin_page(){
    echo "<div class='wrap'><h2>Simple Quiz Admin</h2><p>Manage your quizzes here.</p></div>";
}


function quiz_admin_page(){
    global $wpdb;
    $quiz_table = $wpdb->prefix."quizzes";

    // Add new quiz
    if(isset($_POST['new_quiz']) && !empty($_POST['quiz_title'])){
        $wpdb->insert($quiz_table, [
            'title'=>sanitize_text_field($_POST['quiz_title'])
        ]);
        echo "<div class='updated notice'><p>New quiz added!</p></div>";
    }

    // Get quizzes
    $quizzes = $wpdb->get_results("SELECT * FROM $quiz_table");

    echo "<div class='wrap'>
        <h2>Manage Quizzes</h2>
        <form method='post'>
            <input name='quiz_title' placeholder='Quiz Title' required>
            <button class='button button-primary' name='new_quiz'>Add Quiz</button>
        </form><hr>";

    if($quizzes){
        echo "<h3>Existing Quizzes</h3><ul>";
        foreach($quizzes as $quiz){
            echo "<li>{$quiz->title}</li>";
        }
        echo "</ul>";
    }
    echo "</div>";
}


// Add new question
    $question_table = $wpdb->prefix."quiz_questions";
    if(isset($_POST['new_question']) && !empty($_POST['question']) && !empty($_POST['quiz_id'])){
        $wpdb->insert($question_table, [
            'quiz_id'=>intval($_POST['quiz_id']),
            'question'=>sanitize_text_field($_POST['question']),
            'option1'=>sanitize_text_field($_POST['option1']),
            'option2'=>sanitize_text_field($_POST['option2']),
            'option3'=>sanitize_text_field($_POST['option3']),
            'option4'=>sanitize_text_field($_POST['option4']),
            'correct_option'=>intval($_POST['correct_option'])
        ]);
        echo "<div class='updated notice'><p>New question added!</p></div>";
    }

    // Show add question form
    if($quizzes){
        echo "<h3>Add Question</h3>
        <form method='post'>
            <select name='quiz_id' required>";
        foreach($quizzes as $quiz){
            echo "<option value='{$quiz->id}'>{$quiz->title}</option>";
        }
        echo "</select><br>
            <input name='question' placeholder='Question'><br>
            <input name='option1' placeholder='Option 1'><br>
            <input name='option2' placeholder='Option 2'><br>
            <input name='option3' placeholder='Option 3'><br>
            <input name='option4' placeholder='Option 4'><br>
            Correct (1-4): <input name='correct_option' type='number' min='1' max='4'><br>
            <button class='button button-primary' name='new_question'>Add Question</button>
        </form><hr>";
    }


    //FRONTEND shortcode 
add_shortcode('simple_quiz', function($atts){
    global $wpdb;
    $atts = shortcode_atts(['id' => 0], $atts);
    $quiz_id = intval($atts['id']);
    if(!$quiz_id) return "<p>No quiz selected.</p>";

    $quiz_table = $wpdb->prefix."quizzes";
    $question_table = $wpdb->prefix."quiz_questions";

    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quiz_table WHERE id=%d", $quiz_id));
    if(!$quiz) return "<p>Quiz not found.</p>";

    $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $question_table WHERE quiz_id=%d", $quiz_id));
    if(!$questions) return "<p>No questions in this quiz.</p>";

    ob_start();
    ?>
    <div class="simple-quiz-container">
        <h3><?php echo esc_html($quiz->title); ?></h3>
        <form method="post">
        <?php foreach($questions as $q): ?>
            <div class="simple-quiz-question">
                <b><?php echo esc_html($q->question); ?></b><br>
                <?php for($i=1;$i<=4;$i++): ?>
                    <label>
                        <input type="radio" name="q<?php echo $q->id; ?>" value="<?php echo $i; ?>">
                        <?php echo esc_html($q->{"option$i"}); ?>
                    </label><br>
                <?php endfor; ?>
            </div>
        <?php endforeach; ?>
        <button name="submit_quiz">Submit Quiz</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
});

    if(isset($_POST['submit_quiz'])){
        $score = 0; $i=0;
        foreach($questions as $q){
            $i++;
            if(isset($_POST["q{$q->id}"]) && $_POST["q{$q->id}"] == $q->correct_option){
                $score++;
            }
        }
        $user_id = get_current_user_id();
        $wpdb->insert($wpdb->prefix."quiz_attempts", [
            'user_id'=>$user_id,
            'quiz_id'=>$quiz_id,
            'score'=>$score
        ]);
        echo "<h3>Your Score: $score / $i</h3>";
    }
