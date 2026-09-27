<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $filter_date = $_GET['date'] ?? '';
    $filter_dept = $_GET['dept_id'] ?? '';
    $filter_status = $_GET['status'] ?? '';
    $search_query = trim($_GET['search'] ?? '');

    $where = [];
    $params = [];

    if (!empty($filter_date)) {
        $where[] = "a.date = :date";
        $params['date'] = $filter_date;
    }
    if (!empty($filter_dept)) {
        $where[] = "s.dept_id = :dept_id";
        $params['dept_id'] = $filter_dept;
    }
    if (!empty($filter_status)) {
        $where[] = "a.status = :status";
        $params['status'] = $filter_status;
    }
    if (!empty($search_query)) {
        $where[] = "(s.name LIKE :search OR s.student_id LIKE :search OR s.card_uid LIKE :search)";
        $params['search'] = "%$search_query%";
    }

    $where_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

    $query = "
        SELECT a.*, s.student_id, s.name as student_name, s.card_uid, d.dept_code, d.dept_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN departments d ON s.dept_id = d.id
        $where_sql
        ORDER BY a.date DESC, a.in_time DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    foreach ($logs as &$log) {
        $log['date_formatted'] = date('d M Y', strtotime($log['date']));
        $log['in_time_formatted'] = date('h:i:s A', strtotime($log['in_time']));
        $log['out_time_formatted'] = $log['out_time'] ? date('h:i:s A', strtotime($log['out_time'])) : 'N/A';
    }

    echo json_encode([
        'status' => 'success',
        'logs' => $logs
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
