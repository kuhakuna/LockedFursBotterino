<?php
require '../vendor/autoload.php';
include '../config/db.php'; // Include the PDO connection file
include '../config/.env';
include 'scripts/database.php';
include 'scripts/daily_code.php';

// Load environment variables from the .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../config');
$dotenv->load();

// Load environment variables
$bot_token = $_ENV['BOT_TOKEN'];
$blank_chat_id = $_ENV['BLANK_CHAT_ID'];
$verification_room_id = $_ENV['VERIFIER_CHAT_ID'];
$chastity_room_id = $_ENV['CHASTITY_CHAT_ID'];

$website = 'https://api.telegram.org/bot' . $bot_token;

// Retrieve incoming updates
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $messageText = $update['message']['text'] ?? '';
    $messageId = $update['message']['message_id'];

    if (strpos($messageText, '/code') === 0) {
        // Handle /code command to echo remaining text
        $remainingText = trim(str_replace('/code', '', $messageText));

        if (!empty($remainingText)) {
            sendMessage($chatId, "Echo: " . $remainingText, $messageId, $bot_token);
        } else {
            sendMessage($chatId, 'Please provide text after the /code command.', $messageId, $bot_token);
        }
    }

    if (isset($update['message']['photo'])) {
        // Forward the image to the verification room
        forwardMessage($chastity_room_id, $messageId, $verification_room_id, $bot_token);

        // Send inline keyboard to verification room
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Accept', 'callback_data' => 'accept'],
                    ['text' => 'Deny', 'callback_data' => 'deny'],
                    ['text' => 'Logic Failure', 'callback_data' => 'logic_failure']
                ]
            ]
        ];

        sendMessage($verification_room_id, 'Please review the image:', null, $bot_token, json_encode($keyboard));
    }
}
if (isset($update['callback_query'])) {
    $callbackData = $update['callback_query']['data'];
    $callbackChatId = $update['callback_query']['message']['chat']['id'];
    $callbackMessageId = $update['callback_query']['message']['message_id'];

    switch ($callbackData) {
        case 'accept':
            sendMessage($callbackChatId, 'Image accepted.', $callbackMessageId, $bot_token);
            break;
        case 'deny':
            sendMessage($callbackChatId, 'Image denied.', $callbackMessageId, $bot_token);
            break;
        case 'logic_failure':
            sendMessage($callbackChatId, 'Logic failure: Image contains prohibited content.', $callbackMessageId, $bot_token);
            break;
    }

    answerCallbackQuery($update['callback_query']['id'], $bot_token);
}

// Function to send a message
function sendMessage($chatId, $text, $replyToMessageId = null, $botToken, $replyMarkup = null)
{
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $postData = [
        'chat_id' => $chatId,
        'text' => $text,
        'reply_to_message_id' => $replyToMessageId,
        'reply_markup' => $replyMarkup
    ];

    makeRequest($url, $postData);
}

// Function to forward a message
function forwardMessage($fromChatId, $messageId, $toChatId, $botToken)
{
    $url = "https://api.telegram.org/bot$botToken/forwardMessage";
    $postData = [
        'chat_id' => $toChatId,
        'from_chat_id' => $fromChatId,
        'message_id' => $messageId
    ];

    makeRequest($url, $postData);
}

// Function to handle callback query
function answerCallbackQuery($callbackQueryId, $botToken)
{
    $url = "https://api.telegram.org/bot$botToken/answerCallbackQuery";
    $postData = [
        'callback_query_id' => $callbackQueryId
    ];

    makeRequest($url, $postData);
}

// Generic function to make a request to the Telegram API
function makeRequest($url, $postData)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}
?>