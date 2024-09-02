<?php
require __DIR__ . '/vendor/autoload.php'; // Load Composer's autoload file
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



// Function to handle incoming messages
function processMessage($message)
{
    global $blank_chat_id, $chastity_room_id;

    // Check if the message is a command
    if (isset($message['entities']) && $message['entities'][0]['type'] == 'bot_command') {
        $command = substr($message['text'], 1, $message['entities'][0]['length'] - 1);
        $command = explode('@', $command)[0]; // Remove the bot name if it was included

        // Handle the command
        switch ($command) {
            case 'start':
                sendMessage($message['chat']['id'], "Welcome to the Chastity Bot! Type /help for a list of available commands.");
                break;
            case 'help':
                sendMessage($message['chat']['id'], "Available commands:\n\n" .
                    "/start - Start the bot\n" .
                    "/help - Show this help message\n" .
                    "/code - Get today's code\n" .
                    "/submit - Submit a code\n" .
                    "/status - Check your chastity status\n" .
                    "/reset - Reset your chastity status\n" .
                    "/lock - Lock yourself in chastity\n" .
                    "/unlock - Unlock yourself from chastity\n" .
                    "/rules - Show the rules\n" .
                    "/about - Show information about the bot\n" .
                    "/feedback - Send feedback to the bot creator\n" .
                    "/report - Report a user for breaking the rules\n" .
                    "/admin - Show admin commands (admins only)");
                break;
            case 'code':
                $code = getDailyCode();
                sendMessage($message['chat']['id'], "Today's code is: " . $code);
                break;
            case 'submit':
                sendMessage($message['chat']['id'], "Please send your code in the format /submit <code>");
                break;
            case 'status':
                $status = getUserStatus($message['chat']['id']);
                if ($status) {
                    sendMessage($message['chat']['id'], "Your chastity status is: " . $status);
                } else {
                    sendMessage($message['chat']['id'], "You are not currently locked in chastity.");
                }
                break;
            case 'reset':
                resetUserStatus($message['chat']['id']);
                sendMessage($message['chat']['id'], "Your chastity status has been reset.");
                break;
            case 'lock':
                $status = getUserStatus($message['chat']['id']);
                if ($status) {
                    sendMessage($message['chat']['id'], "You are already locked in chastity.");
                } else {
                    lockUser($message['chat']['id']);
                    sendMessage($message['chat']['id'], "You have been locked in chastity.");
                }
                break;
            case 'unlock':
                $status = getUserStatus($message['chat']['id']);
                if ($status) {
                    unlockUser($message['chat']['id']);
                    sendMessage($message['chat']['id'], "You have been unlocked from chastity.");
                } else {
                    sendMessage($message['chat']['id'], "You are not currently locked in chastity.");
                }
                break;
            case 'rules':
                sendMessage($message['chat']['id'], "The rules are:\n\n" .
                    "1. You must be locked in chastity to participate.\n" .
                    "2. You must submit the daily code every day.\n" .
                    "3. You must not share the daily code with anyone else.\n" .
                    "4. You must not unlock yourself from chastity without permission.\n" .
                    "5. You must not use any loopholes to cheat the system.\n" .
                    "6. You must not harass or bully other users.\n" .
                    "7. You must not send explicit or inappropriate content in the chat.\n" .
                    "8. You must not engage in any illegal activities.\n" .
                    "9. You must follow the instructions of the bot and the admins at all times.\n" .
                    "10. Breaking any of these rules may result in a temporary or permanent ban from the bot.");
                break;
            case 'about':
                sendMessage($message['chat']['id'], "The Chastity Bot was created by [Your Name] as a fun way to explore chastity and self-control. If you have any feedback or suggestions, please use the /feedback command to send a message to the bot creator.");
                break;
            case 'feedback':
                sendMessage($message['chat']['id'], "Please enter your feedback or suggestions:");
                break;
            case 'report':
                sendMessage($message['chat']['id'], "Please enter the username of the user you want to report:");
                break;
            case 'admin':
                sendMessage($message['chat']['id'], "Available admin commands:\n\n" .
                    "/broadcast - Send a message to all users\n" .
                    "/ban - Ban a user from the bot\n" .
                    "/unban - Unban a user from the bot\n" .
                    "/kick - Kick a user from the bot\n" .
                    "/promote - Promote a user to admin\n" .
                    "/demote - Demote an admin\n" .
                    "/setroom - Set the chat room for the bot\n" .
                    "/setblank - Set the blank chat for the bot\n" .
                    "/setcode - Set the daily code\n" .
                    "/resetcode - Reset the daily code\n" .
                    "/resetall - Reset all user statuses\n" .
                    "/stats - Show bot statistics");
                break;
            default:
                sendMessage($message['chat']['id'], "Invalid command. Type /help for a list of available commands.");
                break;
        }
    } else {
        // Handle non-command messages
        $status = getUserStatus($message['chat']['id']);
        if ($status) {
            // Check if the message is the daily code
            if ($message['text'] == $status) {
                // Check if the user has already submitted the code today
                if (hasUserSubmitted($message['chat']['id'])) {
                    sendMessage($message['chat']['id'], "You have already submitted today's code.");
                } else {
                    // Add the code to the database and update the user's status
                    submitCode($message['chat']['id'], $message['text']);
                    updateStatus($message['chat']['id'], null);
                    sendMessage($message['chat']['id'], "Code submitted successfully. You are now unlocked from chastity.");
                }
            } else {
                sendMessage($message['chat']['id'], "Incorrect code. Please try again.");
            }
        } else {
            // Check if the message is a feedback or report
            if ($message['text'] == '/feedback') {
                sendMessage($message['chat']['id'], "Please enter your feedback or suggestions:");
            } elseif ($message['text'] == '/report') {
                sendMessage($message['chat']['id'], "Please enter the username of the user you want to report:");
            } else {
                sendMessage($message['chat']['id'], "Invalid command. Type /help for a list of available commands.");
            }
        }
    }
}

// Function to handle feedback messages
function processFeedback($message) {
    global $blank_chat_id;

    // Send the feedback to the bot creator
    sendMessage($blank_chat_id, "Feedback from user " . $message['chat']['username'] . ":\n\n" . $message['text']);
    sendMessage($message['chat']['id'], "Thank you for your feedback. It has been sent to the bot creator.");
}

// Function to handle report messages
function processReport($message) {
    global $blank_chat_id;

    // Send the report to the bot creator
    sendMessage($blank_chat_id, "Report from user " . $message['chat']['username'] . ":\n\n" . $message['text']);
    sendMessage($message['chat']['id'], "Thank you for your report. It has been sent to the bot creator.");
}
