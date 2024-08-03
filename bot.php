<?php
require __DIR__ . '/vendor/autoload.php';
include 'scripts/database.php';
include 'scripts/daily_code.php'; // Include the daily code script

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$bot_token = $_ENV['BOT_TOKEN'];
$blank_chat_id = $_ENV['BLANK_CHAT_ID'];
$verification_room_id = $_ENV['VERIFIER_CHAT_ID'];
$chastity_room_id = $_ENV['CHASTITY_CHAT_ID'];

$website = 'https://api.telegram.org/bot' . $bot_token;

$update = file_get_contents('php://input');
$update = json_decode($update, TRUE);

function sendMessage($chat_id, $message, $reply_markup = null) {
    global $website;
    $url = $website . "/sendMessage?chat_id=" . $chat_id . "&text=" . urlencode($message);
    if ($reply_markup) {
        $url .= "&reply_markup=" . urlencode(json_encode($reply_markup));
    }
    file_get_contents($url);
}

function sendPhoto($chat_id, $photo, $caption, $reply_markup = null) {
    global $website;
    $url = $website . "/sendPhoto?chat_id=" . $chat_id . "&photo=" . $photo . "&caption=" . urlencode($caption);
    if ($reply_markup) {
        $url .= "&reply_markup=" . urlencode(json_encode($reply_markup));
    }
    file_get_contents($url);
}

function editMessageText($chat_id, $message_id, $text) {
    global $website;
    $url = $website . "/editMessageText?chat_id=" . $chat_id . "&message_id=" . $message_id . "&text=" . urlencode($text);
    file_get_contents($url);
}

function editMessageReplyMarkup($chat_id, $message_id) {
    global $website;
    $url = $website . "/editMessageReplyMarkup?chat_id=" . $chat_id . "&message_id=" . $message_id . "&reply_markup=" . urlencode(json_encode(new stdClass()));
    file_get_contents($url);
}

function answerCallbackQuery($callback_id) {
    global $website;
    $url = $website . "/answerCallbackQuery?callback_query_id=" . $callback_id;
    file_get_contents($url);
}

if (isset($update['message']['new_chat_members'])) {
    $chat_id = $update['message']['chat']['id'];
    $new_members = $update['message']['new_chat_members'];

    foreach ($new_members as $new_member) {
        $first_name = $new_member['first_name'];
        $welcome_message = "Welcome, $first_name!";
        sendMessage($chat_id, $welcome_message);

        // Add new user to the database
        addUser($new_member['id'], json_encode($new_member), date('Y-m-d H:i:s'), 0);
    }
} elseif (isset($update['message']['photo'])) {
    // Handle photo verification
    $chat_id = $update['message']['chat']['id'];
    $user_id = $update['message']['from']['id'];
    $caption = $update['message']['caption'] ?? '';
    $photo = end($update['message']['photo']); // Get the highest resolution photo
    $file_id = $photo['file_id'];
    $file_path = file_get_contents($website . "/getFile?file_id=" . $file_id);
    $file_path = json_decode($file_path, TRUE)['result']['file_path'];
    $photo_url = "https://api.telegram.org/file/bot$bot_token/$file_path";

    // Save verification request
    $code = getDailyCode(); // Get today's verification code
    $verifier_chat_id = $verification_room_id; // The chat ID where verifiers will receive requests
    $verification_message = "User ID: $user_id\nCaption: $caption\nCode: $code\nPhoto: [View Photo]($photo_url)";
    sendVerificationRequest($verifier_chat_id, $verification_message, $photo_url);
    addVerificationRequest($user_id, $chat_id, $photo_url, $caption, $code);

    // "Please hold"
    sendMessage($chat_id, "Your verification request has been submitted. Please wait for the verification.");

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => 'Accept', 'callback_data' => 'accept_' . $user_id],
                ['text' => 'Decline', 'callback_data' => 'decline_' . $user_id],
                ['text' => 'No Code', 'callback_data' => 'nocode_' . $user_id]
            ]
        ]
    ];

    // Send the verification request to the group verifier channel
    sendPhoto($verification_room_id, $file_id, "Photo verification request from user ID: $user_id\nCaption: $caption", $keyboard);
} elseif (isset($update['callback_query'])) {
    // Handle callback queries from inline buttons
    $callback_query = $update['callback_query'];
    $callback_data = $callback_query['data'];
    $callback_id = $callback_query['id'];
    $chat_id = $callback_query['message']['chat']['id'];
    $message_id = $callback_query['message']['message_id'];
    $user_id = explode('_', $callback_data)[1];
    $action = explode('_', $callback_data)[0];

    switch ($action) {
        case 'accept':
            // Accept the photo verification
            addUserPoint($user_id); // Function to add a point to the user's record in the database
            $user_points = getUserPoints($user_id);
            sendMessage($user_id, "Your photo was accepted! You've earned a point. Point total: $user_points");
            break;
        case 'decline':
            // Decline the photo verification
            sendMessage($user_id, "Your photo was declined. Please send an appropriate photo with the correct code.");
            break;
        case 'nocode':
            // No code found in the photo
            $daily_code = getDailyCode(); // Function to get the daily code
            sendMessage($user_id, "Sorry, we couldn't see the code anywhere. The daily code is: $daily_code.");
            break;
    }

    // Update the verification message with the result
    editMessageText($chat_id, $message_id, $callback_query['message']['text'] . "\nStatus: " . ucfirst($action));

    // Remove the inline keyboard
    editMessageReplyMarkup($chat_id, $message_id);
    sendMessage($verification_room_id, "Answered!");

    // Answer the callback query to remove the "loading" state
    answerCallbackQuery($callback_id);
}    elseif (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $message = $update['message']['text'];
    $user_id = $update['message']['from']['id'];

    $message = preg_replace('/@LockedFursBot/', '', $message); // Remove bot mention if present
    switch ($message) {
        case '/start':
            $response = 'Welcome to the bot!';
            break;
        case '/help':
            $response = '/help - Prints this messaage.
            /points - Check how many points you have.
            /verify - Tells you how to verfiy.
            /register - Registers you with the bot. 
            /getcode - Get the current code.
            /id - Prints your User ID and the Group ID(if in a group)';
            break;
        case '/points':
            $user_points = getUserPoints($user_id);
            $response = 'Your current points: ' . $user_points;
            break;
        case '/register':
            $username = $update['message']['from']['username'] ?? 'unknown';
            $response = registerUser($user_id, $username);
            break;
        case '/verify':
            $response = 'Please send a photo with the verification code written somewhere in the image.';
            break;
        case '/getcode':
            $code = getDailyCode(); // Fetch today's verification code
            $response = "Today's verification code is: $code";
            break;
        case '/id':
            $response = "Your ID: $user_id\nGroup ID: $chat_id";
            break;
    }

    sendMessage($chat_id, $response);
}

function sendVerificationRequest($verifier_chat_id, $message, $photo_url) {
    global $website;
    $url = $website . "/sendPhoto?chat_id=" . $verifier_chat_id . "&photo=" . urlencode($photo_url) . "&caption=" . urlencode($message) . "&reply_markup=" . urlencode(json_encode([
            'inline_keyboard' => [
                [
                    ['text' => 'Accept', 'callback_data' => 'accept'],
                    ['text' => 'Decline', 'callback_data' => 'decline'],
                    ['text' => 'No code', 'callback_data' => 'no_code']
                ]
            ]
        ]));
    file_get_contents($url);
}