<?php
/*
Plugin Name: Simple Quiz
Description: A simple quiz plugin for beginners with improved design.
Version: 1.3
Author: Arif
*/

function simple_quiz_activate()
{
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

function simple_quiz_uninstall()
{
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}quiz_questions");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}quiz_attempts");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}quizzes");
}
register_uninstall_hook(__FILE__, 'simple_quiz_uninstall');

//Admin Menu
add_action('admin_menu', function () {
    add_menu_page("Simple Quiz", "Simple Quiz", "manage_options", "simple-quiz", "quiz_admin_page");
});

function quiz_admin_page()
{
    global $wpdb;
    $quiz_table = $wpdb->prefix . "quizzes";
    $question_table = $wpdb->prefix . "quiz_questions";

    // Add new quiz
    if (isset($_POST['new_quiz']) && !empty($_POST['quiz_title'])) {
        $wpdb->insert($quiz_table, [
            'title' => sanitize_text_field($_POST['quiz_title'])
        ]);
        // Redirect to avoid resubmission and refresh quizzes list
        echo "<script>window.location = '" . admin_url('admin.php?page=simple-quiz') . "';</script>";
        exit;
    }

    // Add new question
    if (isset($_POST['new_question']) && !empty($_POST['question']) && !empty($_POST['quiz_id'])) {
        $result = $wpdb->insert($question_table, [
            'quiz_id' => intval($_POST['quiz_id']),
            'question' => sanitize_text_field($_POST['question']),
            'option1' => sanitize_text_field($_POST['option1']),
            'option2' => sanitize_text_field($_POST['option2']),
            'option3' => sanitize_text_field($_POST['option3']),
            'option4' => sanitize_text_field($_POST['option4']),
            'correct_option' => intval($_POST['correct_option'])
        ]);
        if ($result === false) {
            echo "<div class='error notice'><p>Failed to add question: " . esc_html($wpdb->last_error) . "</p></div>";
        } else {
            echo "<script>window.location = '" . admin_url('admin.php?page=simple-quiz') . "';</script>";
            exit;
        }
    }

    // Delete question
    if (isset($_GET['delete'])) {
        $wpdb->delete($question_table, ['id' => intval($_GET['delete'])]);
        echo "<div class='updated notice'><p>Question deleted!</p></div>";
    }

    // Get quizzes
    $quizzes = $wpdb->get_results("SELECT * FROM $quiz_table");

    echo "<div class='wrap'>
<h2>Manage Quizzes</h2>
<form method='post' style='background:#fff;padding:20px;border:1px solid #ddd;max-width:500px;'>
<h3>Add New Quiz</h3>
<input class='widefat' name='quiz_title' placeholder='Quiz Title' required><br><br>
<button class='button button-primary' name='new_quiz'>Add Quiz</button>
</form><hr>";

    // Add question form
    if ($quizzes) {
        echo "<h3>Add Question to Quiz</h3>
<form method='post' style='background:#fff;padding:20px;border:1px solid #ddd;max-width:500px;'>
<select name='quiz_id' required>
<option value=''>Select Quiz</option>";
        foreach ($quizzes as $quiz) {
            echo "<option value='{$quiz->id}'>{$quiz->title}</option>";
        }
        echo "</select><br><br>
<input class='widefat' name='question' placeholder='Question' required><br><br>
<input class='widefat' name='option1' placeholder='Option 1' required><br><br>
<input class='widefat' name='option2' placeholder='Option 2' required><br><br>
<input class='widefat' name='option3' placeholder='Option 3' required><br><br>
<input class='widefat' name='option4' placeholder='Option 4' required><br><br>
Correct (1-4): <input name='correct_option' type='number' min='1' max='4' required><br><br>
<button class='button button-primary' name='new_question'>Add Question</button>
</form><hr>";
    }

    // List quizzes and questions
    foreach ($quizzes as $quiz) {
        echo "<h3>Quiz: {$quiz->title}</h3>";
        $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $question_table WHERE quiz_id=%d", $quiz->id));
        if ($questions) {
            echo "<table class='widefat striped'>
<thead><tr><th>ID</th><th>Question</th><th>Correct</th><th>Action</th></tr></thead><tbody>";
            foreach ($questions as $q) {
                echo "<tr>
<td>{$q->id}</td>
<td>{$q->question}</td>
<td>Option {$q->correct_option}</td>
<td><a class='button button-secondary' href='?page=simple-quiz&delete={$q->id}'>Delete</a></td>
</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p>No questions for this quiz.</p>";
        }
    }
    echo "</div>";
}

//Frontend Shortcode
add_shortcode('simple_quiz', function ($atts) {
    global $wpdb;
    $atts = shortcode_atts(['id' => 0], $atts);
    $quiz_id = intval($atts['id']);
    if (!$quiz_id)
        return "<p>No quiz selected.</p>";

    $quiz_table = $wpdb->prefix . "quizzes";
    $question_table = $wpdb->prefix . "quiz_questions";

    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM $quiz_table WHERE id=%d", $quiz_id));
    if (!$quiz)
        return "<p>Quiz not found.</p>";

    $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $question_table WHERE quiz_id=%d", $quiz_id));
    if (!$questions)
        return "<p>No questions in this quiz.</p>";

    ob_start();
    ?>
    <style>
        .simple-quiz-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 2px solid #0073aa;
            border-radius: 10px;
            background: #f9f9f9;
        }

        .simple-quiz-container h3 {
            text-align: center;
            color: #0073aa;
        }

        .simple-quiz-question {
            margin-bottom: 15px;
            padding: 10px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .simple-quiz-question b {
            display: block;
            margin-bottom: 8px;
        }

        .simple-quiz-title {
            font-size: 1.3em;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
            text-align: center;
        }

        .simple-quiz-container button {
            background: #0073aa;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .simple-quiz-container button:hover {
            background: #005f8d;
        }
    </style>
    <div class="simple-quiz-container">
        <div class='simple-quiz-title'><?php echo esc_html($quiz->title); ?></div>
        <?php
        if (isset($_POST['submit_quiz'])) {
            $score = 0;
            $i = 0;
            foreach ($questions as $q) {
                $i++;
                if (isset($_POST["q{$q->id}"]) && $_POST["q{$q->id}"] == $q->correct_option) {
                    $score++;
                }
            }
            $user_id = get_current_user_id();
            $wpdb->insert($wpdb->prefix . "quiz_attempts", [
                'user_id' => $user_id,
                'quiz_id' => $quiz_id,
                'score' => $score
            ]);
            echo "<h3>Your Score: $score / $i</h3>";
        } else {
            echo "<form method='post'>";
            foreach ($questions as $q) {
                echo "<div class='simple-quiz-question'>
<b>{$q->question}</b>";
                for ($i = 1; $i <= 4; $i++) {
                    $opt = $q->{"option$i"};
                    echo "<label><input type='radio' name='q{$q->id}' value='$i'> $opt</label><br>";
                }
                echo "</div>";
            }
            echo "<div style='text-align:center;'><button name='submit_quiz'>Submit Quiz</button></div></form>";
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
});