<?php
// getdata.php - Main Endpoint for ESP32 Arduino RFID Attendance & Registration
header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/telegram_helper.php';

$card_uid = $_REQUEST['card_uid'] ?? '';
$card_uid = trim(strtoupper($card_uid));
$device_token = trim($_REQUEST['device_token'] ?? '');

if (empty($card_uid)) {
    echo "No Card UID";
    exit();
}

try {
    // 1. Fetch System Mode & Valid Device Token
    $mode_stmt = $pdo->query("SELECT * FROM system_mode WHERE id = 1 LIMIT 1");
    $sys_mode = $mode_stmt->fetch();
    $current_mode = $sys_mode['device_mode'] ?? 'ATTENDANCE';
    $valid_device_token = $sys_mode['device_token'] ?? '';

    // 2. Security Check: Validate Device Token
    if (!empty($valid_device_token) && $device_token !== $valid_device_token) {
        $log_stmt = $pdo->prepare("
            INSERT INTO rfid_logs (card_uid, student_name, scan_type, message) 
            VALUES (:uid, 'Unauthorized Device', 'INVALID', 'Invalid Device Token Attempt')
        ");
        $log_stmt->execute(['uid' => $card_uid]);

        echo "Invalid Token";
        exit();
    }

    // 3. REGISTRATION MODE WORKFLOW
    if ($current_mode === 'REGISTRATION') {
        // Save scanned card UID into system_mode table so website registration modal auto-captures it!
        $update_mode = $pdo->prepare("
            UPDATE system_mode 
            SET last_scanned_uid = :uid, last_scanned_at = NOW() 
            WHERE id = 1
        ");
        $update_mode->execute(['uid' => $card_uid]);

        // Check if this card is already assigned to a student
        $chk_stmt = $pdo->prepare("SELECT name FROM students WHERE card_uid = :uid LIMIT 1");
        $chk_stmt->execute(['uid' => $card_uid]);
        $existing_student = $chk_stmt->fetch();

        // Log scan event
        $log_stmt = $pdo->prepare("
            INSERT INTO rfid_logs (card_uid, student_name, scan_type, message) 
            VALUES (:uid, :name, 'REGISTRATION', 'Card Scanned for Registration')
        ");
        $log_stmt->execute([
            'uid' => $card_uid,
            'name' => $existing_student ? $existing_student['name'] : 'New Card'
        ]);

        if ($existing_student) {
            echo "available";
        } else {
            echo "succesful";
        }
        exit();
    }

    // 4. ATTENDANCE MODE WORKFLOW
    $stmt = $pdo->prepare("
        SELECT s.*, d.dept_code, d.dept_name 
        FROM students s
        LEFT JOIN departments d ON s.dept_id = d.id
        WHERE s.card_uid = :card_uid AND s.status = 'Active'
        LIMIT 1
    ");
    $stmt->execute(['card_uid' => $card_uid]);
    $student = $stmt->fetch();

    // UNREGISTERED CARD REJECTION IN ATTENDANCE MODE
    if (!$student) {
        $log_stmt = $pdo->prepare("
            INSERT INTO rfid_logs (card_uid, student_name, scan_type, message) 
            VALUES (:uid, 'Unregistered Card', 'INVALID', 'Attendance Denied: Unregistered Card')
        ");
        $log_stmt->execute(['uid' => $card_uid]);

        // Returns explicit "Not Registered" response to ESP32
        echo "Not Registered";
        exit();
    }

    $today = date('Y-m-d');
    $current_time = date('H:i:s');
    $student_id_fk = $student['id'];
    $student_name = $student['name'];

    // Fetch late time threshold
    $setting_stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'late_time_threshold' LIMIT 1");
    $setting = $setting_stmt->fetch();
    $late_threshold = $setting['setting_value'] ?? '09:30:00';

    // Check existing attendance today
    $att_stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = :sid AND date = :today LIMIT 1");
    $att_stmt->execute(['sid' => $student_id_fk, 'today' => $today]);
    $existing = $att_stmt->fetch();

    if (!$existing) {
        // --- IN TIME (Check-In) ---
        $att_status = ($current_time > $late_threshold) ? 'Late' : 'On Time';

        $insert_stmt = $pdo->prepare("
            INSERT INTO attendance (student_id, date, in_time, status)
            VALUES (:sid, :today, :in_time, :status)
        ");
        $insert_stmt->execute([
            'sid' => $student_id_fk,
            'today' => $today,
            'in_time' => $current_time,
            'status' => $att_status
        ]);

        $log_stmt = $pdo->prepare("
            INSERT INTO rfid_logs (card_uid, student_name, scan_type, message) 
            VALUES (:uid, :name, 'IN', :msg)
        ");
        $log_stmt->execute([
            'uid' => $card_uid,
            'name' => $student_name,
            'msg' => "Check-In Recorded ($att_status)"
        ]);

        // Trigger Telegram Bot Notification for Check-In
        sendTelegramAttendanceIn([
            'name' => $student_name,
            'student_id' => $student['student_id'],
            'dept_code' => $student['dept_code'],
            'card_uid' => $card_uid,
            'date' => $today,
            'in_time' => $current_time,
            'status' => $att_status
        ], $pdo);

        echo "login" . $student_name;
        exit();

    } else if (empty($existing['out_time'])) {
        // --- OUT TIME (Check-Out) ---
        $update_stmt = $pdo->prepare("
            UPDATE attendance 
            SET out_time = :out_time, status = 'Checked Out'
            WHERE id = :att_id
        ");
        $update_stmt->execute([
            'out_time' => $current_time,
            'att_id' => $existing['id']
        ]);

        $log_stmt = $pdo->prepare("
            INSERT INTO rfid_logs (card_uid, student_name, scan_type, message) 
            VALUES (:uid, :name, 'OUT', 'Check-Out Recorded')
        ");
        $log_stmt->execute([
            'uid' => $card_uid,
            'name' => $student_name
        ]);

        // Trigger Telegram Bot Notification for Check-Out
        sendTelegramAttendanceOut([
            'name' => $student_name,
            'student_id' => $student['student_id'],
            'dept_code' => $student['dept_code'],
            'card_uid' => $card_uid,
            'date' => $today,
            'in_time' => $existing['in_time'],
            'out_time' => $current_time,
            'status' => 'Checked Out'
        ], $pdo);

        echo "logout" . $student_name;
        exit();

    } else {
        // --- ALREADY CHECKED IN & OUT TODAY ---
        $log_stmt = $pdo->prepare("
            INSERT INTO rfid_logs (card_uid, student_name, scan_type, message) 
            VALUES (:uid, :name, 'INVALID', 'Day Limit: Already checked out today')
        ");
        $log_stmt->execute([
            'uid' => $card_uid,
            'name' => $student_name
        ]);

        echo "Already Out";
        exit();
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
