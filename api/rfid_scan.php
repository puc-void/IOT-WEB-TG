<?php
// API Wrapper for web terminal and backward compatibility
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';

$card_uid = $_REQUEST['card_uid'] ?? '';
$card_uid = trim(strtoupper($card_uid));

if (empty($card_uid)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing card_uid']);
    exit();
}

// Forward to getdata logic internally & parse output
ob_start();
$_REQUEST['card_uid'] = $card_uid;
require __DIR__ . '/../getdata.php';
$output = ob_get_clean();

// Interpret text response into friendly JSON structure for web UI
if (str_starts_with($output, 'login')) {
    $name = substr($output, 5);
    echo json_encode([
        'status' => 'success',
        'type' => 'IN',
        'student_name' => $name,
        'message' => "Welcome $name! In-Time recorded"
    ]);
} elseif (str_starts_with($output, 'logout')) {
    $name = substr($output, 6);
    echo json_encode([
        'status' => 'success',
        'type' => 'OUT',
        'student_name' => $name,
        'message' => "Goodbye $name! Out-Time recorded"
    ]);
} elseif ($output === 'succesful') {
    echo json_encode([
        'status' => 'info',
        'type' => 'NEW_CARD',
        'card_uid' => $card_uid,
        'message' => 'New / Unregistered Card Scanned'
    ]);
} elseif ($output === 'available') {
    echo json_encode([
        'status' => 'info',
        'type' => 'CARD_SCANNED',
        'card_uid' => $card_uid,
        'message' => 'Card Scanned for Registration'
    ]);
} elseif ($output === 'Already Out') {
    echo json_encode([
        'status' => 'warning',
        'type' => 'LIMIT_EXCEEDED',
        'message' => 'Limit Reached: Student already checked out today!'
    ]);
} else {
    echo json_encode([
        'status' => 'response',
        'raw' => $output,
        'message' => $output
    ]);
}
?>
