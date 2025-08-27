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