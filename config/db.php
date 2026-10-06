<?php
/**
 * Database Configuration
 * 
 * XAMPP defaults: host=localhost, user=root, pass=(empty)
 * cPanel: use the database credentials from your hosting control panel.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_marketplace');
define('DB_USER', 'root');
define('DB_PASS', '123456');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log("[DB ERROR] " . $e->getMessage());
    die("Database connection failed. Please contact the administrator.");
}
