<?php
/**
 * Database Connection
 * Provides $mysqli global for all database operations
 */
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'clinic_diabetes';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    die('فشل الاتصال بقاعدة البيانات - Database connection failed: ' . $mysqli->connect_error);
}

// Set UTF-8 for Arabic support
$mysqli->set_charset('utf8mb4');
$mysqli->query("SET NAMES utf8mb4");
$mysqli->query("SET character_set_client = 'utf8mb4'");
$mysqli->query("SET character_set_results = 'utf8mb4'");
?>
