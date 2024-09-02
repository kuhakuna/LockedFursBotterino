<?php
function generateDailyCode(): string {
    return '*' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT); // Generate a random 3-digit number prefixed with '*'
}

function getDailyCode(): string {
    $pdo = connectToDatabase();

    // Get current date in UTC-6 timezone
    $timezone = new DateTimeZone('-6');
    try {
        $currentDate = new DateTime('now', $timezone);
    } catch (Exception $e) {
    }
    $today = $currentDate->format('Y-m-d');

    // Check if a code already exists for today
    $stmt = $pdo->prepare("SELECT code FROM daily_codes WHERE date_generated = ?");
    $stmt->execute([$today]);
    $result = $stmt->fetch();

    if ($result) {
        // Return the existing code
        return $result['code'];
    } else {
        // Generate a new code and store it in the database
        $newCode = generateDailyCode();
        $stmt = $pdo->prepare("INSERT INTO daily_codes (code, date_generated) VALUES (?, ?)");
        $stmt->execute([$newCode, $today]);
        return $newCode;
    }
}

// Usage example
$dailyCode = getDailyCode();
echo "Today's code is: " . $dailyCode;