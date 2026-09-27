<?php
$page_title = "Student Directory & RFID Registration";
$page_subtitle = "Register Students & Capture RFID Card UIDs Live from ESP32";
require_once 'includes/header.php';
require_once 'includes/telegram_helper.php';

$msg = '';
$msg_type = '';

// Handle Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $student_id = sanitize($_POST['student_id'] ?? '');
        $card_uid = strtoupper(sanitize($_POST['card_uid'] ?? ''));
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $dept_id = (int)($_POST['dept_id'] ?? 0);
        $gender = sanitize($_POST['gender'] ?? 'Male');

        if (!empty($student_id) && !empty($card_uid) && !empty($name) && $dept_id > 0) {
            // Check for existing duplicate Student ID or Card UID before insert
            $dup_chk = $pdo->prepare("SELECT name, student_id, card_uid FROM students WHERE student_id = :sid OR card_uid = :uid LIMIT 1");
            $dup_chk->execute(['sid' => $student_id, 'uid' => $card_uid]);
            $existing_dup = $dup_chk->fetch();

            if ($existing_dup) {
                if ($existing_dup['card_uid'] === $card_uid) {
                    $msg = "Registration Error: Card UID [$card_uid] is ALREADY assigned to student '{$existing_dup['name']}'!";
                } else {
                    $msg = "Registration Error: Student ID [$student_id] is ALREADY in use by '{$existing_dup['name']}'!";
                }
                $msg_type = "error";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO students (student_id, card_uid, name, email, phone, dept_id, gender) 
                        VALUES (:sid, :uid, :name, :email, :phone, :dept_id, :gender)
                    ");
                    $stmt->execute([
                        'sid' => $student_id,
                        'uid' => $card_uid,
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'dept_id' => $dept_id,
                        'gender' => $gender
                    ]);

                    // Clear last scanned card buffer and switch back to ATTENDANCE mode
                    $pdo->query("UPDATE system_mode SET device_mode = 'ATTENDANCE', last_scanned_uid = NULL WHERE id = 1");

                    // Trigger Telegram Notification for New Student Registration
                    $dept_stmt = $pdo->prepare("SELECT dept_code, dept_name FROM departments WHERE id = :id LIMIT 1");
                    $dept_stmt->execute(['id' => $dept_id]);
                    $dept_info = $dept_stmt->fetch();

                    sendTelegramStudentRegistered([
                        'name' => $name,
                        'student_id' => $student_id,
                        'card_uid' => $card_uid,
                        'dept_name' => ($dept_info ? $dept_info['dept_code'] . ' - ' . $dept_info['dept_name'] : 'N/A'),
                        'gender' => $gender,
                        'phone' => $phone,
                        'email' => $email
                    ], $pdo);

                    $msg = "Student '$name' registered successfully with Card UID [$card_uid]! System switched to Attendance Mode.";
                    $msg_type = "success";
                } catch (PDOException $e) {
                    $msg = "Registration Error: Database entry constraint error!";
                    $msg_type = "error";
                }
            }
        } else {
            $msg = "Please fill in all mandatory fields.";
            $msg_type = "error";
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $student_id = sanitize($_POST['student_id'] ?? '');
        $card_uid = strtoupper(sanitize($_POST['card_uid'] ?? ''));
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $dept_id = (int)($_POST['dept_id'] ?? 0);
        $gender = sanitize($_POST['gender'] ?? 'Male');
        $status = sanitize($_POST['status'] ?? 'Active');

        if ($id > 0 && !empty($student_id) && !empty($card_uid) && !empty($name) && $dept_id > 0) {
            // Check duplicates against OTHER students
            $dup_chk = $pdo->prepare("SELECT name, student_id, card_uid FROM students WHERE (student_id = :sid OR card_uid = :uid) AND id != :id LIMIT 1");
            $dup_chk->execute(['sid' => $student_id, 'uid' => $card_uid, 'id' => $id]);
            $existing_dup = $dup_chk->fetch();

            if ($existing_dup) {
                if ($existing_dup['card_uid'] === $card_uid) {
                    $msg = "Update Error: Card UID [$card_uid] belongs to another student '{$existing_dup['name']}'!";
                } else {
                    $msg = "Update Error: Student ID [$student_id] belongs to another student '{$existing_dup['name']}'!";
                }
                $msg_type = "error";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE students 
                        SET student_id = :sid, card_uid = :uid, name = :name, email = :email, 
                            phone = :phone, dept_id = :dept_id, gender = :gender, status = :status
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'sid' => $student_id,
                        'uid' => $card_uid,
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'dept_id' => $dept_id,
                        'gender' => $gender,
                        'status' => $status,
                        'id' => $id
                    ]);
                    $msg = "Student details updated successfully!";
                    $msg_type = "success";
                } catch (PDOException $e) {
                    $msg = "Update Error: " . $e->getMessage();
                    $msg_type = "error";
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM students WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $msg = "Student record deleted successfully!";
                $msg_type = "success";
            } catch (PDOException $e) {
                $msg = "Delete Error: " . $e->getMessage();
                $msg_type = "error";
            }
        }
    }
}

// Fetch Departments list for dropdowns & filter
$depts_stmt = $pdo->query("SELECT * FROM departments ORDER BY dept_code ASC");
$all_departments = $depts_stmt->fetchAll();

// Fetch Students with Department Names
$query = "
    SELECT s.*, d.dept_code, d.dept_name 
    FROM students s
    JOIN departments d ON s.dept_id = d.id
    ORDER BY s.id DESC
";
$students = $pdo->query($query)->fetchAll();
?>

<!-- Banner: Active Mode Status & Controls -->
<div class="bg-slate-900 border border-slate-800 p-4 rounded-3xl shadow-lg flex flex-col md:flex-row items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <div id="status-indicator-icon" class="w-10 h-10 rounded-2xl bg-amber-600/20 text-amber-400 border border-amber-500/30 flex items-center justify-center text-xl">
            <i class="fa-solid fa-id-card-clip"></i>
        </div>
        <div>
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                RFID Mode Status: <span id="current-mode-label" class="text-amber-400 font-mono">ATTENDANCE</span>
            </h3>
            <p class="text-xs text-slate-400">Click "Register Student" to capture RFID Card UID live from ESP32.</p>
        </div>
    </div>

    <!-- Live Scanned RFID Card Detected Alert Banner -->
    <div id="live-scanned-card-banner" class="hidden bg-emerald-500/10 border border-emerald-500/30 px-4 py-2 rounded-2xl flex items-center gap-3 text-xs font-semibold text-emerald-400 animate-pulse">
        <i class="fa-solid fa-wifi text-base"></i>
        <span>Live Scanned Card UID: <strong id="detected-uid-text" class="font-mono text-sm underline">--</strong></span>
        <button onclick="useDetectedUID()" class="ml-2 px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition shadow-sm">
            Use This UID
        </button>
    </div>

    <div class="flex items-center gap-2">
        <button onclick="startRegistrationMode()" class="py-2.5 px-5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-2xl shadow-lg shadow-indigo-600/30 transition flex items-center justify-center gap-2 text-sm">
            <i class="fa-solid fa-user-plus"></i>
            <span>Register Student (Scan RFID)</span>
        </button>
    </div>
</div>

<!-- Alert Messages -->
<?php if (!empty($msg)): ?>
    <div class="p-4 rounded-2xl border text-sm flex items-center justify-between <?= ($msg_type === 'success') ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-red-500/10 border-red-500/30 text-red-400' ?>">
        <div class="flex items-center gap-2">
            <i class="fa-solid <?= ($msg_type === 'success') ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
            <span><?= htmlspecialchars($msg) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="hover:opacity-70">&times;</button>
    </div>
<?php endif; ?>

<!-- Search & Department Filters -->
<div class="flex flex-col sm:flex-row items-center gap-3 bg-slate-900 border border-slate-800 p-4 rounded-3xl">
    <div class="relative w-full sm:w-72">
        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
            <i class="fa-solid fa-magnifying-glass"></i>
        </span>
        <input type="text" id="search-student-input" onkeyup="filterStudents()" placeholder="Search Name, ID, or Card UID..."
            class="w-full pl-10 pr-4 py-2.5 bg-slate-950 border border-slate-800 rounded-2xl focus:outline-none focus:border-indigo-500 text-white text-sm">
    </div>

    <select id="filter-dept-select" onchange="filterStudents()" class="w-full sm:w-48 px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-2xl focus:outline-none focus:border-indigo-500 text-white text-sm">
        <option value="">All Departments</option>
        <?php foreach ($all_departments as $d): ?>
            <option value="<?= htmlspecialchars($d['dept_code']) ?>"><?= htmlspecialchars($d['dept_code']) ?> - <?= htmlspecialchars($d['dept_name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Students Table -->
<div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="students-table">
            <thead>
                <tr class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    <th class="py-4 px-6">Student Info</th>
                    <th class="py-4 px-6">Department</th>
                    <th class="py-4 px-6">RFID Card UID</th>
                    <th class="py-4 px-6">Contact</th>
                    <th class="py-4 px-6 text-center">Status</th>
                    <th class="py-4 px-6 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 text-sm">
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-500">No students registered yet. Click "Register Student" to add.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $st): ?>
                        <tr class="hover:bg-slate-800/40 transition student-row" data-dept="<?= htmlspecialchars($st['dept_code']) ?>">
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 text-indigo-400 flex items-center justify-center font-bold text-sm">
                                        <?= strtoupper(substr($st['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-white student-name"><?= htmlspecialchars($st['name']) ?></div>
                                        <div class="text-xs font-mono text-indigo-400 student-id"><?= htmlspecialchars($st['student_id']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <span class="bg-slate-950 border border-slate-800 px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-300">
                                    <?= htmlspecialchars($st['dept_code']) ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 font-mono text-xs">
                                <span class="bg-emerald-950/60 border border-emerald-500/30 text-emerald-400 px-2.5 py-1 rounded-lg font-bold tracking-wider card-uid">
                                    <i class="fa-solid fa-id-card text-[10px] mr-1"></i><?= htmlspecialchars($st['card_uid']) ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-400">
                                <div><i class="fa-regular fa-envelope text-slate-500 mr-1"></i><?= htmlspecialchars($st['email'] ?? 'N/A') ?></div>
                                <div class="mt-0.5"><i class="fa-solid fa-phone text-slate-500 mr-1"></i><?= htmlspecialchars($st['phone'] ?? 'N/A') ?></div>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <?php if ($st['status'] === 'Active'): ?>
                                    <span class="inline-flex items-center gap-1.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 px-2.5 py-0.5 rounded-full text-xs font-semibold">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 bg-red-500/10 text-red-400 border border-red-500/30 px-2.5 py-0.5 rounded-full text-xs font-semibold">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span> Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-6 text-right space-x-2">
                                <button onclick="openEditStudentModal(<?= htmlspecialchars(json_encode($st)) ?>)"
                                    class="p-2 bg-slate-800 hover:bg-indigo-600 text-slate-300 hover:text-white rounded-xl transition" title="Edit">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button onclick="openDeleteStudentModal(<?= $st['id'] ?>, '<?= htmlspecialchars($st['name']) ?>')"
                                    class="p-2 bg-slate-800 hover:bg-red-600 text-slate-300 hover:text-white rounded-xl transition" title="Delete">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Register Student -->
<div id="add-student-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <div>
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-user-plus text-indigo-400"></i> Register New Student
                </h3>
                <p class="text-xs text-amber-400 mt-1 flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                    <span>Place RFID Card on ESP32 Reader now...</span>
                </p>
            </div>
            <button onclick="closeAddStudentModal()" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <!-- Real-Time Duplicate Warning Alert Inside Modal -->
        <div id="modal-uid-warning" class="hidden p-3 rounded-2xl border text-xs font-semibold"></div>

        <form action="students.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Student ID *</label>
                    <input type="text" name="student_id" placeholder="e.g. CSE-2024-001" required
                        class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">RFID Card UID *</label>
                    <div class="relative">
                        <input type="text" id="card_uid_field" name="card_uid" oninput="checkDuplicateUID(this.value)" placeholder="Swipe Card on ESP32..." required
                            class="w-full pl-4 pr-10 py-2.5 bg-slate-950 border border-amber-500/50 rounded-xl focus:outline-none focus:border-amber-500 text-amber-300 font-mono uppercase text-sm font-bold">
                        <span class="absolute right-3 top-3 text-amber-400">
                            <i class="fa-solid fa-wifi animate-pulse"></i>
                        </span>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Full Name *</label>
                <input type="text" name="name" placeholder="Student's full name" required
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Department *</label>
                    <select name="dept_id" required class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm">
                        <option value="">Select Department</option>
                        <?php foreach ($all_departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['dept_code']) ?> - <?= htmlspecialchars($d['dept_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Gender</label>
                    <select name="gender" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Email Address</label>
                    <input type="email" name="email" placeholder="student@university.edu"
                        class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Phone Number</label>
                    <input type="text" name="phone" placeholder="017xxxxxxxx"
                        class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm">
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-3">
                <button type="button" onclick="closeAddStudentModal()" class="px-4 py-2.5 bg-slate-800 text-slate-300 rounded-xl text-sm font-semibold">Cancel</button>
                <button type="submit" id="submit-register-btn" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold shadow-lg shadow-indigo-600/30">Save Student</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Student -->
<div id="edit-student-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-indigo-400"></i> Edit Student Details
            </h3>
            <button onclick="closeEditStudentModal()" class="text-slate-400 hover:text-white">&times;</button>
        </div>
        <form action="students.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit-id" name="id">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Student ID *</label>
                    <input type="text" id="edit-student-id" name="student_id" required
                        class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">RFID Card UID *</label>
                    <input type="text" id="edit-card-uid" name="card_uid" required
                        class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white font-mono uppercase text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Full Name *</label>
                <input type="text" id="edit-name" name="name" required
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Department *</label>
                    <select id="edit-dept-id" name="dept_id" required class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm">
                        <?php foreach ($all_departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['dept_code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Gender</label>
                    <select id="edit-gender" name="gender" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Status</label>
                    <select id="edit-status" name="status" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Email</label>
                    <input type="email" id="edit-email" name="email"
                        class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Phone</label>
                    <input type="text" id="edit-phone" name="phone"
                        class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm">
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-3">
                <button type="button" onclick="closeEditStudentModal()" class="px-4 py-2.5 bg-slate-800 text-slate-300 rounded-xl text-sm font-semibold">Cancel</button>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold shadow-lg shadow-indigo-600/30">Update Student</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div id="delete-student-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-sm w-full p-6 shadow-2xl space-y-4 text-center">
        <div class="w-14 h-14 bg-red-500/10 border border-red-500/30 text-red-400 rounded-full flex items-center justify-center mx-auto text-2xl">
            <i class="fa-solid fa-user-xmark"></i>
        </div>
        <h3 class="text-lg font-bold text-white">Delete Student?</h3>
        <p class="text-xs text-slate-400" id="delete-student-target">Are you sure you want to delete this student?</p>

        <form action="students.php" method="POST" class="pt-2 flex items-center justify-center gap-3">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" id="delete-student-id" name="id">
            <button type="button" onclick="closeDeleteStudentModal()" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-sm font-semibold">Cancel</button>
            <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-500 text-white rounded-xl text-sm font-semibold shadow-lg shadow-red-600/30">Delete</button>
        </form>
    </div>
</div>

<script>
let lastDetectedUID = '';

function startRegistrationMode() {
    fetch('api/mode_control.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'set_mode', mode: 'REGISTRATION' })
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('current-mode-label').innerText = 'REGISTRATION';
        openAddStudentModal();
    });
}

function pollSystemMode() {
    fetch('api/mode_control.php?action=get_mode')
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                const mode = res.data.device_mode;
                const uid = res.data.last_scanned_uid;
                
                document.getElementById('current-mode-label').innerText = mode;

                if (uid && uid !== lastDetectedUID) {
                    lastDetectedUID = uid;
                    document.getElementById('detected-uid-text').innerText = uid;
                    document.getElementById('live-scanned-card-banner').classList.remove('hidden');

                    const uidField = document.getElementById('card_uid_field');
                    if (uidField) {
                        uidField.value = uid;
                        checkDuplicateUID(uid);
                    }
                }
            }
        });
}

function checkDuplicateUID(uid) {
    const warningEl = document.getElementById('modal-uid-warning');
    const submitBtn = document.getElementById('submit-register-btn');
    if (!uid || uid.trim() === '') {
        warningEl.classList.add('hidden');
        submitBtn.disabled = false;
        return;
    }

    fetch('api/check_duplicate_uid.php?card_uid=' + encodeURIComponent(uid.trim()))
        .then(res => res.json())
        .then(res => {
            if (res.exists) {
                warningEl.className = 'p-3 rounded-2xl border text-xs font-semibold bg-red-500/10 border-red-500/30 text-red-400 block';
                warningEl.innerHTML = `<i class="fa-solid fa-triangle-exclamation mr-1"></i> Warning: Card UID [${uid}] is ALREADY assigned to student "<strong>${res.student_name}</strong>" (${res.dept_code})! Please use a new card.`;
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                warningEl.className = 'p-3 rounded-2xl border text-xs font-semibold bg-emerald-500/10 border-emerald-500/30 text-emerald-400 block';
                warningEl.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> Card UID [${uid}] is Free & Ready for Registration!`;
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });
}

function useDetectedUID() {
    if (lastDetectedUID) {
        document.getElementById('card_uid_field').value = lastDetectedUID;
        checkDuplicateUID(lastDetectedUID);
        openAddStudentModal();
    }
}

setInterval(pollSystemMode, 1000);

function filterStudents() {
    const query = document.getElementById('search-student-input').value.toLowerCase();
    const selectedDept = document.getElementById('filter-dept-select').value;
    const rows = document.querySelectorAll('#students-table tbody .student-row');

    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        const rowDept = row.getAttribute('data-dept');
        const matchesQuery = text.includes(query);
        const matchesDept = (selectedDept === '' || rowDept === selectedDept);
        row.style.display = (matchesQuery && matchesDept) ? '' : 'none';
    });
}

function openAddStudentModal() { document.getElementById('add-student-modal').classList.remove('hidden'); }
function closeAddStudentModal() { 
    document.getElementById('add-student-modal').classList.add('hidden'); 
    fetch('api/mode_control.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'set_mode', mode: 'ATTENDANCE' })
    });
}

function openEditStudentModal(st) {
    document.getElementById('edit-id').value = st.id;
    document.getElementById('edit-student-id').value = st.student_id;
    document.getElementById('edit-card-uid').value = st.card_uid;
    document.getElementById('edit-name').value = st.name;
    document.getElementById('edit-email').value = st.email || '';
    document.getElementById('edit-phone').value = st.phone || '';
    document.getElementById('edit-dept-id').value = st.dept_id;
    document.getElementById('edit-gender').value = st.gender;
    document.getElementById('edit-status').value = st.status;
    document.getElementById('edit-student-modal').classList.remove('hidden');
}
function closeEditStudentModal() { document.getElementById('edit-student-modal').classList.add('hidden'); }

function openDeleteStudentModal(id, name) {
    document.getElementById('delete-student-id').value = id;
    document.getElementById('delete-student-target').innerText = `Are you sure you want to delete student "${name}"?`;
    document.getElementById('delete-student-modal').classList.remove('hidden');
}
function closeDeleteStudentModal() { document.getElementById('delete-student-modal').classList.add('hidden'); }
</script>

<?php require_once 'includes/footer.php'; ?>
