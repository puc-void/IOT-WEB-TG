<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'set_mode') {
    $mode = strtoupper(trim($_REQUEST['mode'] ?? 'ATTENDANCE'));
    if (in_array($mode, ['ATTENDANCE', 'REGISTRATION'])) {
        $stmt = $pdo->prepare("UPDATE system_mode SET device_mode = :mode, last_scanned_uid = NULL WHERE id = 1");
        $stmt->execute(['mode' => $mode]);
        echo json_encode(['status' => 'success', 'mode' => $mode, 'message' => "System switched to $mode Mode"]);
        exit();
    }
} elseif ($action === 'generate_token') {
    $new_token = bin2hex(random_bytes(8)); // Generates 16-char hex token
    $stmt = $pdo->prepare("UPDATE system_mode SET device_token = :token WHERE id = 1");
    $stmt->execute(['token' => $new_token]);
    echo json_encode(['status' => 'success', 'device_token' => $new_token, 'message' => 'New Device Token generated successfully!']);
    exit();
} elseif ($action === 'set_token') {
    $custom_token = trim($_REQUEST['device_token'] ?? '');
    if (!empty($custom_token)) {
        $stmt = $pdo->prepare("UPDATE system_mode SET device_token = :token WHERE id = 1");
        $stmt->execute(['token' => $custom_token]);
        echo json_encode(['status' => 'success', 'device_token' => $custom_token, 'message' => 'Device Token updated!']);
        exit();
    }
} elseif ($action === 'get_mode') {
    $stmt = $pdo->query("SELECT * FROM system_mode WHERE id = 1 LIMIT 1");
    $data = $stmt->fetch();
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit();
} elseif ($action === 'clear_scanned') {
    $stmt = $pdo->query("UPDATE system_mode SET last_scanned_uid = NULL WHERE id = 1");
    echo json_encode(['status' => 'success']);
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>
