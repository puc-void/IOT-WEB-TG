<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $today = date('Y-m-d');

    // 1. Fetch Today's General Attendance Summary Stats
    $stats = [
        'total_students' => 0,
        'present_today' => 0,
        'late_today' => 0,
        'checked_out_today' => 0,
    ];

    $total_students_stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'");
    $stats['total_students'] = (int)$total_students_stmt->fetchColumn();

    $present_stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = :today");
    $present_stmt->execute(['today' => $today]);
    $stats['present_today'] = (int)$present_stmt->fetchColumn();

    $late_stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = :today AND status = 'Late'");
    $late_stmt->execute(['today' => $today]);
    $stats['late_today'] = (int)$late_stmt->fetchColumn();

    $checked_out_stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = :today AND out_time IS NOT NULL");
    $checked_out_stmt->execute(['today' => $today]);
    $stats['checked_out_today'] = (int)$checked_out_stmt->fetchColumn();

    // 2. Fetch Latest 10 RFID Raw Scans for real-time live feed
    $logs_stmt = $pdo->query("
        SELECT id, card_uid, student_name, scan_type, message, DATE_FORMAT(scanned_at, '%h:%i:%s %p') as scan_time 
        FROM rfid_logs 
        ORDER BY id DESC 
        LIMIT 10
    ");
    $logs = $logs_stmt->fetchAll();

    // 3. Fetch Today's Attendance Table Rows
    $att_rows_stmt = $pdo->prepare("
        SELECT a.id, a.date, a.in_time, a.out_time, a.status,
               s.student_id, s.name as student_name, s.card_uid,
               d.dept_code, d.dept_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN departments d ON s.dept_id = d.id
        WHERE a.date = :today
        ORDER BY a.id DESC
    ");
    $att_rows_stmt->execute(['today' => $today]);
    $today_records = $att_rows_stmt->fetchAll();

    // Format times nicely
    foreach ($today_records as &$rec) {
        $rec['in_time_formatted'] = date('h:i:s A', strtotime($rec['in_time']));
        $rec['out_time_formatted'] = $rec['out_time'] ? date('h:i:s A', strtotime($rec['out_time'])) : 'N/A';
    }

    echo json_encode([
        'status' => 'success',
        'stats' => $stats,
        'recent_logs' => $logs,
        'today_attendance' => $today_records
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
