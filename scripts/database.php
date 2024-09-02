<?php
require __DIR__ . '/../vendor/autoload.php';
// scripts/database.php
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

function connectToDatabase(): PDO {
    $HOST_NAME = $_ENV['DB_HOST'];
    $DATABASE = $_ENV['DB_NAME'];
    $dsn = "mysql:host={$HOST_NAME};dbname={$DATABASE};";

    try {
        $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        throw new RuntimeException('Database connection error: ' . $e->getMessage());
    }
}

// Example function to add a point to the user's record
function addUserPoint($user_id) {
    $pdo = connectToDatabase(); // Assuming connectToDatabase() returns a PDO instance

    $stmt = $pdo->prepare("UPDATE User SET verification_points = verification_points + 1 WHERE telegram_id = ?");
    $stmt->execute([$user_id]);
}

// Function to get user information
function getUserInfo($user_id) {
    $pdo = connectToDatabase(); // Assuming connectToDatabase() returns a PDO instance

    $stmt = $pdo->prepare("SELECT * FROM User WHERE telegram_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function getUserPoints($user_id) {
    $pdo = connectToDatabase(); // Assuming connectToDatabase() returns a PDO instance

    $stmt = $pdo->prepare("SELECT verification_points FROM User WHERE telegram_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();

    return $result ? $result['verification_points'] : 0;
}

// Function to register a new user
function registerUser($user_id, $username): string
{
    $pdo = connectToDatabase(); // Assuming connectToDatabase() returns a PDO instance

    // Check if the user already exists
    if (getUserFullInfo($user_id)) {
        return "Already registered!";
    }

    // Register the user
    $stmt = $pdo->prepare("INSERT INTO User (telegram_id, username, verification_points) VALUES (?, ?, 0)");
    $stmt->execute([$user_id, $username]);
    return "Registration successful!";
}

function addUser($userId, $userInfo, $lastVerified, $points) {
    $pdo = connectToDatabase();
    $stmt = $pdo->prepare("INSERT INTO User (user_id, telegram_id, verification_points) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $userInfo, $lastVerified, $points]);
}

function addVerificationRequest($user_id, $chat_id, $photo_url, $caption, $code) {
    $pdo = connectToDatabase();
    $stmt = $pdo->prepare("INSERT INTO Verification_Requests (user_id, chat_id, photo, caption, code) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $chat_id, $photo_url, $caption, $code]);
}
?>