<?php
$page_title = "Dashboard Overview";
$page_subtitle = "Real-Time ESP32 RFID Attendance Monitoring System";
require_once 'includes/header.php';

$today = date('Y-m-d');

// Initial Data Fetch
$total_students = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
$present_today = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date = '$today'")->fetchColumn();
$late_today = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date = '$today' AND status = 'Late'")->fetchColumn();
$checked_out_today = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date = '$today' AND out_time IS NOT NULL")->fetchColumn();

// Fetch System Settings & Device Token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $late_time = sanitize($_POST['late_time_threshold'] ?? '09:30:00');
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = :val WHERE setting_key = 'late_time_threshold'");
    $stmt->execute(['val' => $late_time]);
    $settings_msg = "Late cut-off threshold updated to " . date('h:i A', strtotime($late_time));
}

$late_setting = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'late_time_threshold'")->fetchColumn() ?: '09:30:00';
$mode_data = $pdo->query("SELECT * FROM system_mode WHERE id = 1 LIMIT 1")->fetch();
$active_token = $mode_data['device_token'] ?? '19453d39b4edd4e6';
?>

<?php if (isset($settings_msg)): ?>
    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= htmlspecialchars($settings_msg) ?></span>
        </div>
        <button onclick="this.parentElement.remove()">&times;</button>
    </div>
<?php endif; ?>

<!-- Stat Widgets Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
    <!-- Stat 1: Total Students -->
    <div class="bg-slate-900 border border-slate-800 p-5 rounded-3xl shadow-xl relative overflow-hidden group">
        <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-indigo-600/10 rounded-full blur-xl group-hover:scale-150 transition-transform"></div>
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Registered</span>
            <div class="w-10 h-10 rounded-2xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
        <div class="mt-4">
            <div id="stat-total-students" class="text-3xl font-extrabold text-white tracking-tight"><?= $total_students ?></div>
            <div class="text-xs text-slate-400 mt-1">Active Card Holders</div>
        </div>
    </div>

    <!-- Stat 2: Present Today -->
    <div class="bg-slate-900 border border-slate-800 p-5 rounded-3xl shadow-xl relative overflow-hidden group">
        <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-emerald-600/10 rounded-full blur-xl group-hover:scale-150 transition-transform"></div>
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Present Today</span>
            <div class="w-10 h-10 rounded-2xl bg-emerald-600/20 text-emerald-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-clipboard-check"></i>
            </div>
        </div>
        <div class="mt-4">
            <div id="stat-present-today" class="text-3xl font-extrabold text-emerald-400 tracking-tight"><?= $present_today ?></div>
            <div class="text-xs text-slate-400 mt-1">Attended <?= date('d M Y') ?></div>
        </div>
    </div>

    <!-- Stat 3: Late Arrivals -->
    <div class="bg-slate-900 border border-slate-800 p-5 rounded-3xl shadow-xl relative overflow-hidden group">
        <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-amber-600/10 rounded-full blur-xl group-hover:scale-150 transition-transform"></div>
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Late Arrivals</span>
            <div class="w-10 h-10 rounded-2xl bg-amber-600/20 text-amber-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
        </div>
        <div class="mt-4">
            <div id="stat-late-today" class="text-3xl font-extrabold text-amber-400 tracking-tight"><?= $late_today ?></div>
            <div class="text-xs text-slate-400 mt-1">After <?= date('h:i A', strtotime($late_setting)) ?></div>
        </div>
    </div>

    <!-- Stat 4: Checked Out -->
    <div class="bg-slate-900 border border-slate-800 p-5 rounded-3xl shadow-xl relative overflow-hidden group">
        <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-blue-600/10 rounded-full blur-xl group-hover:scale-150 transition-transform"></div>
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Checked Out</span>
            <div class="w-10 h-10 rounded-2xl bg-blue-600/20 text-blue-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-right-from-bracket"></i>
            </div>
        </div>
        <div class="mt-4">
            <div id="stat-checked-out-today" class="text-3xl font-extrabold text-blue-400 tracking-tight"><?= $checked_out_today ?></div>
            <div class="text-xs text-slate-400 mt-1">Completed Out-Time</div>
        </div>
    </div>
</div>

<!-- Main Grid Section: Live Feed + ESP32 Device Token Generator -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Today's Attendance Real-Time Feed -->
    <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-bolt text-indigo-400"></i> Today's Live Attendance Feed
                </h2>
                <p class="text-xs text-slate-400">Auto-refreshing live scan stream from ESP32</p>
            </div>
            <span class="text-xs bg-slate-950 border border-slate-800 text-slate-400 px-3 py-1 rounded-full flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> Live Updates
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="live-attendance-table">
                <thead>
                    <tr class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-4">Student</th>
                        <th class="py-3 px-4">Dept</th>
                        <th class="py-3 px-4">In Time</th>
                        <th class="py-3 px-4">Out Time</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody id="live-table-body" class="divide-y divide-slate-800/60 text-sm">
                    <tr>
                        <td colspan="5" class="py-8 text-center text-slate-500">Loading today's attendance logs...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Sidebar Widgets: Token Generator & Settings -->
    <div class="space-y-6">
        <!-- ESP32 Device Token Manager Card -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-key text-amber-400"></i> ESP32 Device Token
                </h3>
                <span class="text-[10px] bg-amber-500/10 text-amber-400 border border-amber-500/30 px-2 py-0.5 rounded font-mono">Security</span>
            </div>
            
            <p class="text-xs text-slate-400">Use this token inside your Arduino <code class="text-indigo-300">.ino</code> code for ESP32 authentication.</p>

            <div class="bg-slate-950 p-3.5 rounded-2xl border border-slate-800 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400 font-semibold">Active Token:</span>
                    <button onclick="copyTokenCode()" class="text-xs text-indigo-400 hover:text-indigo-300 font-bold flex items-center gap-1">
                        <i class="fa-regular fa-copy"></i> Copy Arduino Code
                    </button>
                </div>
                <div class="relative">
                    <input type="text" id="device-token-input" value="<?= htmlspecialchars($active_token) ?>" readonly
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-amber-400 font-mono text-xs font-bold tracking-wider">
                </div>
            </div>

            <!-- Generate New Token Button -->
            <button onclick="generateNewDeviceToken()" class="w-full py-2.5 px-4 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-2xl transition flex items-center justify-center gap-2 text-xs shadow-lg shadow-amber-600/20">
                <i class="fa-solid fa-arrows-rotate"></i> Generate New Device Token
            </button>
        </div>

        <!-- Late Cut-off Setting Form -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider flex items-center gap-2">
                <i class="fa-solid fa-sliders text-indigo-400"></i> Attendance Rules
            </h3>
            <form action="index.php" method="POST" class="space-y-3">
                <input type="hidden" name="save_settings" value="1">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Late Time Threshold</label>
                    <input type="time" name="late_time_threshold" value="<?= htmlspecialchars($late_setting) ?>" required
                        class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono text-xs focus:outline-none focus:border-indigo-500">
                    <p class="text-[10px] text-slate-500 mt-1">Scans after this time are automatically tagged as "Late".</p>
                </div>
                <button type="submit" class="w-full py-2.5 bg-slate-800 hover:bg-indigo-600 text-white font-semibold rounded-xl text-xs transition">
                    Save Threshold
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Scripts for Token Generation & Live Stream -->
<script>
function generateNewDeviceToken() {
    if (confirm("Generating a new Device Token will require updating the device_token variable in your ESP32 Arduino code! Continue?")) {
        fetch('api/mode_control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'generate_token' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('device-token-input').value = data.device_token;
                alert("New Device Token Generated: " + data.device_token + "\n\nCopy this token into your ESP32 code:\nconst char* device_token = \"" + data.device_token + "\";");
            }
        });
    }
}

function copyTokenCode() {
    const token = document.getElementById('device-token-input').value;
    const codeSnippet = `const char* device_token = "${token}";`;
    navigator.clipboard.writeText(codeSnippet).then(() => {
        alert("Copied Arduino Code snippet to clipboard!\n\n" + codeSnippet);
    });
}

function fetchLiveLogs() {
    fetch('api/get_live_logs.php')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('stat-total-students').innerText = data.stats.total_students;
                document.getElementById('stat-present-today').innerText = data.stats.present_today;
                document.getElementById('stat-late-today').innerText = data.stats.late_today;
                document.getElementById('stat-checked-out-today').innerText = data.stats.checked_out_today;

                const tbody = document.getElementById('live-table-body');
                if (data.today_attendance.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="5" class="py-8 text-center text-slate-500">No attendance scans recorded today yet. Use ESP32 or Virtual Terminal to scan a card.</td></tr>`;
                } else {
                    let rowsHtml = '';
                    data.today_attendance.forEach(log => {
                        let statusBadge = '';
                        if (log.status === 'On Time') {
                            statusBadge = `<span class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-2.5 py-0.5 rounded-full text-[11px] font-semibold">On Time</span>`;
                        } else if (log.status === 'Late') {
                            statusBadge = `<span class="bg-amber-500/10 border border-amber-500/30 text-amber-400 px-2.5 py-0.5 rounded-full text-[11px] font-semibold">Late</span>`;
                        } else {
                            statusBadge = `<span class="bg-blue-500/10 border border-blue-500/30 text-blue-400 px-2.5 py-0.5 rounded-full text-[11px] font-semibold">Checked Out</span>`;
                        }

                        rowsHtml += `
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-white">${escapeHtml(log.student_name)}</div>
                                    <div class="text-[11px] font-mono text-indigo-400">${escapeHtml(log.student_id)}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="bg-slate-950 border border-slate-800 px-2 py-0.5 rounded text-xs text-slate-300 font-semibold">${escapeHtml(log.dept_code)}</span>
                                </td>
                                <td class="py-3 px-4 font-mono text-xs font-semibold text-emerald-400">${log.in_time_formatted}</td>
                                <td class="py-3 px-4 font-mono text-xs text-blue-400">${log.out_time_formatted}</td>
                                <td class="py-3 px-4 text-center">${statusBadge}</td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = rowsHtml;
                }
            }
        })
        .catch(err => console.error('Error fetching live logs:', err));
}

function escapeHtml(str) {
    return str ? str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;") : '';
}

setInterval(fetchLiveLogs, 3000);
fetchLiveLogs();
</script>

<?php require_once 'includes/footer.php'; ?>
