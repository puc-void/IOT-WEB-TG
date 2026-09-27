<?php
// includes/telegram_helper.php - Telegram Bot Notification Engine

/**
 * Helper to fetch a setting value from DB
 */
function getTelegramSetting($key, $default = '', $pdo = null) {
    if (!$pdo) {
        global $pdo;
    }
    if (!$pdo) {
        return $default;
    }

    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1");
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();
        return ($row && isset($row['setting_value'])) ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Send raw HTML formatted Telegram message
 * @param string $message
 * @param PDO|null $pdo
 * @return array ['success' => bool, 'response' => string]
 */
function sendTelegramNotification($message, $pdo = null) {
    if (!$pdo) {
        global $pdo;
    }

    $bot_token = trim(getTelegramSetting('telegram_bot_token', '', $pdo));
    $chat_id = trim(getTelegramSetting('telegram_chat_id', '', $pdo));
    $enabled = getTelegramSetting('telegram_enabled', '1', $pdo);

    if ($enabled !== '1' && $enabled !== 'true') {
        return ['success' => false, 'error' => 'Telegram notifications disabled'];
    }

    if (empty($bot_token) || empty($chat_id)) {
        return ['success' => false, 'error' => 'Bot Token or Chat ID missing'];
    }

    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($result === false) {
        return ['success' => false, 'error' => "cURL Error: " . $curlErr];
    }

    $json = json_decode($result, true);
    if ($httpCode === 200 && isset($json['ok']) && $json['ok'] === true) {
        return ['success' => true, 'response' => $json];
    } else {
        $errorMsg = $json['description'] ?? 'Telegram API Error (Code ' . $httpCode . ')';
        return ['success' => false, 'error' => $errorMsg];
    }
}

/**
 * Send Student Registration Telegram Notification
 */
function sendTelegramStudentRegistered($studentData, $pdo = null) {
    $name = htmlspecialchars($studentData['name']);
    $student_id = htmlspecialchars($studentData['student_id']);
    $card_uid = htmlspecialchars($studentData['card_uid']);
    $dept_name = htmlspecialchars($studentData['dept_name'] ?? 'N/A');
    $gender = htmlspecialchars($studentData['gender'] ?? 'N/A');
    $phone = htmlspecialchars(!empty($studentData['phone']) ? $studentData['phone'] : 'N/A');
    $email = htmlspecialchars(!empty($studentData['email']) ? $studentData['email'] : 'N/A');
    $time = date('d M Y, h:i:s A');

    $msg = "🎉 <b>NEW STUDENT REGISTERED</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "👤 <b>Name:</b> {$name}\n";
    $msg .= "🆔 <b>Student ID:</b> <code>{$student_id}</code>\n";
    $msg .= "💳 <b>RFID Card UID:</b> <code>{$card_uid}</code>\n";
    $msg .= "🏫 <b>Department:</b> {$dept_name}\n";
    $msg .= "👫 <b>Gender:</b> {$gender}\n";
    $msg .= "📞 <b>Phone:</b> {$phone}\n";
    $msg .= "📧 <b>Email:</b> {$email}\n";
    $msg .= "⏰ <b>Registered At:</b> {$time}\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━";

    return sendTelegramNotification($msg, $pdo);
}

/**
 * Send Attendance Check-In Telegram Notification
 */
function sendTelegramAttendanceIn($attendanceData, $pdo = null) {
    $name = htmlspecialchars($attendanceData['name']);
    $student_id = htmlspecialchars($attendanceData['student_id']);
    $dept = htmlspecialchars($attendanceData['dept_code'] ?? 'N/A');
    $card_uid = htmlspecialchars($attendanceData['card_uid']);
    $in_time = date('h:i:s A', strtotime($attendanceData['in_time']));
    $date = date('d M, Y', strtotime($attendanceData['date']));
    $status = htmlspecialchars($attendanceData['status']);

    $status_emoji = ($status === 'Late') ? '⚠️' : '✅';

    $msg = "📥 <b>STUDENT CHECK-IN</b> {$status_emoji}\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "👤 <b>Name:</b> {$name}\n";
    $msg .= "🆔 <b>Student ID:</b> <code>{$student_id}</code>\n";
    $msg .= "🏫 <b>Department:</b> {$dept}\n";
    $msg .= "💳 <b>Card UID:</b> <code>{$card_uid}</code>\n";
    $msg .= "📅 <b>Date:</b> {$date}\n";
    $msg .= "⏰ <b>In-Time:</b> <b>{$in_time}</b>\n";
    $msg .= "📌 <b>Status:</b> <b>{$status}</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━";

    return sendTelegramNotification($msg, $pdo);
}

/**
 * Send Attendance Check-Out Telegram Notification
 */
function sendTelegramAttendanceOut($attendanceData, $pdo = null) {
    $name = htmlspecialchars($attendanceData['name']);
    $student_id = htmlspecialchars($attendanceData['student_id']);
    $dept = htmlspecialchars($attendanceData['dept_code'] ?? 'N/A');
    $card_uid = htmlspecialchars($attendanceData['card_uid']);
    $in_time = !empty($attendanceData['in_time']) ? date('h:i:s A', strtotime($attendanceData['in_time'])) : 'N/A';
    $out_time = date('h:i:s A', strtotime($attendanceData['out_time']));
    $date = date('d M, Y', strtotime($attendanceData['date']));

    $msg = "📤 <b>STUDENT CHECK-OUT</b> 👋\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "👤 <b>Name:</b> {$name}\n";
    $msg .= "🆔 <b>Student ID:</b> <code>{$student_id}</code>\n";
    $msg .= "🏫 <b>Department:</b> {$dept}\n";
    $msg .= "💳 <b>Card UID:</b> <code>{$card_uid}</code>\n";
    $msg .= "📅 <b>Date:</b> {$date}\n";
    $msg .= "⏱️ <b>In-Time:</b> {$in_time}\n";
    $msg .= "⏰ <b>Out-Time:</b> <b>{$out_time}</b>\n";
    $msg .= "📌 <b>Status:</b> <b>Checked Out</b>\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━";

    return sendTelegramNotification($msg, $pdo);
}
