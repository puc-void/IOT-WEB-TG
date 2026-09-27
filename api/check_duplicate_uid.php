<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

$card_uid = trim(strtoupper($_GET['card_uid'] ?? ''));

if (empty($card_uid)) {
    echo json_encode(['exists' => false]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT s.name, s.student_id, d.dept_code 
        FROM students s
        LEFT JOIN departments d ON s.dept_id = d.id
        WHERE s.card_uid = :uid
        LIMIT 1
    ");
    $stmt->execute(['uid' => $card_uid]);
    $student = $stmt->fetch();

    if ($student) {
        echo json_encode([
            'exists' => true,
            'student_name' => $student['name'],
            'student_id' => $student['student_id'],
            'dept_code' => $student['dept_code']
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
?>
