<?php
require __DIR__ . '/../vendor/autoload.php'; // Load Composer's autoload file
include '../config/db.php'; // Include PDO connection setup
include '../scripts/daily_code.php'; // Include other necessary scripts

// Load environment variables from the .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../config');
$dotenv->load();

$blank_chat_id = $_ENV['BLANK_CHAT_ID'];
$bot_token = $_ENV['BOT_TOKEN'];
$chastity_room_id = $_ENV['CHASTITY_ROOM_ID']; // Ensure all environment variables are set correctly
$website = 'https://api.telegram.org/bot' . $bot_token;

// Function to retrieve user information
function getUserFullInfo($chat_id) {
    global $website;
    $url = $website . "/getChat?chat_id=" . $chat_id;

    $response = file_get_contents($url);
    $result = json_decode($response, true);

    // Return user info if the request was successful
    if ($result['ok']) {
        return $result['result'];
    } else {
        return null;
    }
}

// Function to send a message to a Telegram chat
function sendMessage($chat_id, $message, $reply_markup = null) {
    global $website;
    $url = $website . "/sendMessage?chat_id=" . $chat_id . "&text=" . urlencode($message);
    if ($reply_markup) {
        $url .= "&reply_markup=" . urlencode(json_encode($reply_markup));
    }
    file_get_contents($url); // Consider switching to curl for better error handling
}
?>