<?php
// db.php
require __DIR__ . '/vendor/autoload.php'; // Make sure you require the Composer autoload file

// Load environment variables from the .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Use environment variables to set up the PDO connection
try {
$db = new PDO(
"mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']}",
$_ENV['DB_USER'],
$_ENV['DB_PASS'],
[
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]
);
} catch (PDOException $e) {
// Handle connection error
die("Database connection failed: " . $e->getMessage());
}