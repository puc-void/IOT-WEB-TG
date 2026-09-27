<?php
$page_title = "Attendance Logs & Reports";
$page_subtitle = "Complete Real-Time Filterable Logs, Validation Badges & CSV Export";
require_once 'includes/header.php';

// Filter Variables
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_dept = $_GET['dept_id'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search_query = trim($_GET['search'] ?? '');

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
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

    $where_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

    $export_stmt = $pdo->prepare("
        SELECT a.date, s.student_id, s.name as student_name, d.dept_code, s.card_uid,
               a.in_time, a.out_time, a.status
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN departments d ON s.dept_id = d.id
        $where_sql
        ORDER BY a.date DESC, a.in_time ASC
    ");
    $export_stmt->execute($params);
    $records = $export_stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance_report_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Student ID', 'Student Name', 'Department', 'Card UID', 'In Time', 'Out Time', 'Status']);

    foreach ($records as $row) {
        fputcsv($output, [
            $row['date'],
            $row['student_id'],
            $row['student_name'],
            $row['dept_code'],
            $row['card_uid'],
            date('h:i:s A', strtotime($row['in_time'])),
            $row['out_time'] ? date('h:i:s A', strtotime($row['out_time'])) : 'N/A',
            $row['status']
        ]);
    }
    fclose($output);
    exit();
}

// Fetch Departments for filter
$depts = $pdo->query("SELECT * FROM departments ORDER BY dept_code ASC")->fetchAll();
?>

<!-- Filter & Export Controls Bar -->
<div class="bg-slate-900 border border-slate-800 p-5 rounded-3xl shadow-xl space-y-4">
    <form id="attendance-filter-form" method="GET" action="attendance.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <!-- Date Selector -->
        <div>
            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Filter Date</label>
            <input type="date" id="filter-date" name="date" value="<?= htmlspecialchars($filter_date) ?>"
                class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-xs">
        </div>

        <!-- Dept Selector -->
        <div>
            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Department</label>
            <select id="filter-dept" name="dept_id" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-xs">
                <option value="">All Departments</option>
                <?php foreach ($depts as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= ($filter_dept == $d['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['dept_code']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Status Selector -->
        <div>
            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Status</label>
            <select id="filter-status" name="status" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-xs">
                <option value="">All Statuses</option>
                <option value="On Time" <?= ($filter_status === 'On Time') ? 'selected' : '' ?>>On Time</option>
                <option value="Late" <?= ($filter_status === 'Late') ? 'selected' : '' ?>>Late</option>
                <option value="Checked Out" <?= ($filter_status === 'Checked Out') ? 'selected' : '' ?>>Checked Out</option>
            </select>
        </div>

        <!-- Search Text -->
        <div>
            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1">Search</label>
            <input type="text" id="filter-search" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Name, ID, UID..."
                class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-xs">
        </div>

        <!-- Filter Submit & Reset -->
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 py-2 px-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl text-xs transition flex items-center justify-center gap-1.5 shadow-md shadow-indigo-600/20">
                <i class="fa-solid fa-filter"></i> Apply
            </button>
            <a href="attendance.php" class="py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold rounded-xl text-xs transition" title="Reset Filters">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
        </div>
    </form>

    <!-- Table Action Header: Live Status & Export -->
    <div class="pt-3 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-2 text-slate-400">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
            <span>Real-time Live Logs (Showing <strong id="record-count-text" class="text-white">0</strong> records)</span>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold rounded-xl transition flex items-center gap-1.5">
                <i class="fa-solid fa-print text-indigo-400"></i> Print / PDF
            </button>

            <a id="export-csv-link" href="attendance.php?export=csv&date=<?= urlencode($filter_date) ?>&dept_id=<?= urlencode($filter_dept) ?>&status=<?= urlencode($filter_status) ?>"
                class="py-2 px-4 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl transition flex items-center gap-1.5 shadow-lg shadow-emerald-600/20">
                <i class="fa-solid fa-file-csv"></i> Export to CSV
            </a>
        </div>
    </div>
</div>

<!-- Attendance Logs Data Table -->
<div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl print:border-none print:shadow-none">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    <th class="py-4 px-6">Date</th>
                    <th class="py-4 px-6">Student Info</th>
                    <th class="py-4 px-6">Department</th>
                    <th class="py-4 px-6">Card UID</th>
                    <th class="py-4 px-6">In Time</th>
                    <th class="py-4 px-6">Out Time</th>
                    <th class="py-4 px-6 text-center">Status</th>
                </tr>
            </thead>
            <tbody id="attendance-table-body" class="divide-y divide-slate-800/60 text-sm">
                <tr>
                    <td colspan="7" class="py-12 text-center text-slate-500">
                        <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 block text-indigo-400"></i>
                        Loading attendance logs...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Real-Time Auto Refresh Script -->
<script>
function fetchLiveAttendance() {
    const dateVal = document.getElementById('filter-date').value;
    const deptVal = document.getElementById('filter-dept').value;
    const statusVal = document.getElementById('filter-status').value;
    const searchVal = document.getElementById('filter-search').value.trim();

    const params = new URLSearchParams({
        action: 'fetch_filtered_logs',
        date: dateVal,
        dept_id: deptVal,
        status: statusVal,
        search: searchVal
    });

    fetch('api/get_attendance_api.php?' + params.toString())
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('record-count-text').innerText = data.logs.length;
                const tbody = document.getElementById('attendance-table-body');

                if (data.logs.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500">
                                <i class="fa-solid fa-calendar-xmark text-3xl mb-2 block text-slate-600"></i>
                                No attendance records match the selected filters.
                            </td>
                        </tr>`;
                } else {
                    let rowsHtml = '';
                    data.logs.forEach(log => {
                        let statusBadge = '';
                        if (log.status === 'On Time') {
                            statusBadge = `<span class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-3 py-1 rounded-full text-xs font-semibold inline-flex items-center gap-1"><i class="fa-solid fa-check text-[10px]"></i> On Time</span>`;
                        } else if (log.status === 'Late') {
                            statusBadge = `<span class="bg-amber-500/10 border border-amber-500/30 text-amber-400 px-3 py-1 rounded-full text-xs font-semibold inline-flex items-center gap-1"><i class="fa-solid fa-clock-rotate-left text-[10px]"></i> Late</span>`;
                        } else {
                            statusBadge = `<span class="bg-blue-500/10 border border-blue-500/30 text-blue-400 px-3 py-1 rounded-full text-xs font-semibold inline-flex items-center gap-1"><i class="fa-solid fa-right-from-bracket text-[10px]"></i> Checked Out</span>`;
                        }

                        let outTimeHtml = log.out_time_formatted !== 'N/A'
                            ? `<span class="text-blue-400 font-semibold"><i class="fa-solid fa-arrow-right-from-bracket text-[10px] mr-1"></i> ${log.out_time_formatted}</span>`
                            : `<span class="text-slate-500 italic">-- : --</span>`;

                        rowsHtml += `
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="py-4 px-6 font-mono text-xs text-slate-400">${log.date_formatted}</td>
                                <td class="py-4 px-6">
                                    <div class="font-bold text-white">${escapeHtml(log.student_name)}</div>
                                    <div class="text-xs font-mono text-indigo-400">${escapeHtml(log.student_id)}</div>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="bg-slate-950 border border-slate-800 px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-300">${escapeHtml(log.dept_code)}</span>
                                </td>
                                <td class="py-4 px-6 font-mono text-xs text-slate-400">${escapeHtml(log.card_uid)}</td>
                                <td class="py-4 px-6 font-mono text-xs font-semibold text-emerald-400">
                                    <i class="fa-solid fa-arrow-right-to-bracket text-[10px] mr-1"></i> ${log.in_time_formatted}
                                </td>
                                <td class="py-4 px-6 font-mono text-xs">${outTimeHtml}</td>
                                <td class="py-4 px-6 text-center">${statusBadge}</td>
                            </tr>`;
                    });
                    tbody.innerHTML = rowsHtml;
                }
            }
        });
}

function escapeHtml(str) {
    return str ? str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;") : '';
}

// Auto poll every 2 seconds for real-time live update
setInterval(fetchLiveAttendance, 2000);
fetchLiveAttendance();
</script>

<?php require_once 'includes/footer.php'; ?>
