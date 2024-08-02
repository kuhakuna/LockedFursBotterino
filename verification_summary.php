<?php
require __DIR__ . '/vendor/autoload.php';
include 'scripts/database.php'; // Include the database script
include 'scripts/daily_code.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$blank_chat_id = $_ENV['BLANK_CHAT_ID'];
$bot_token = $_ENV['BOT_TOKEN'];
$chastity_room_id = "-1001313741176";
$website = 'https://api.telegram.org/bot' . $bot_token;

function getUserFullInfo($chat_id) {
    global $website;
    $url = $website . "/getChat?chat_id=" . $chat_id;
    $response = file_get_contents($url);

    // Decode the JSON response
    $result = json_decode($response, true);

    // Check if the request was successful
    if ($result['ok']) {
        return $result['result'];
    } else {
        return null;
    }
}

function sendMessage($chat_id, $message, $reply_markup = null) {
    global $website;
    $url = $website . "/sendMessage?chat_id=" . $chat_id . "&text=" . urlencode($message);
    if ($reply_markup) {
        $url .= "&reply_markup=" . urlencode(json_encode($reply_markup));
    }
    file_get_contents($url);
}

function printSummary() {
    $daily_code = getDailyCode();
    $currentDate = new DateTime();
    $totalDays = $currentDate->format('t');
    $currentDay = $currentDate->format('j');

    $summaryText = "Day $currentDay of $totalDays\n\nCode: $daily_code\n\n";

    $topEntries = getTopEntries();
    $summaryText .= "Top 10 Verifiers of the Month:\n$topEntries";
    return $summaryText;
}

// Function to get top entries from Verification_Requests table
function getTopEntries() {
    $pdo = connectToDatabase();

    $query = "
        SELECT user_id, COUNT(*) as verification_count
        FROM Verification_Requests
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
        GROUP BY user_id
        ORDER BY verification_count DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $output = "";
    foreach ($results as $index => $row) {
        $user = getUserInfo($row['user_id']);
        $user_id = $user['id'];
        $user_first_name = $user['first_name'];
        $user_last_name = $user['last_name'];
        $user_name = $user['username'];
        $output .= ($index + 1) . ". User: " . $user_first_name . "(@" . $user_name . ") - Verifications: " . $row['verification_count'] . "\n";
    }
    
    return $output;
}

$TopVerifiers = printSummary();
sendMessage($chastity_room_id, $TopVerifiers);
?>
