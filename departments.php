<?php
$page_title = "Department Management";
$page_subtitle = "Manage Academic & Administrative Departments";
require_once 'includes/header.php';

$msg = '';
$msg_type = '';

// Handle Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $code = strtoupper(sanitize($_POST['dept_code'] ?? ''));
        $name = sanitize($_POST['dept_name'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');

        if (!empty($code) && !empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO departments (dept_code, dept_name, description) VALUES (:code, :name, :desc)");
                $stmt->execute(['code' => $code, 'name' => $name, 'desc' => $desc]);
                $msg = "Department '$name' ($code) added successfully!";
                $msg_type = "success";
            } catch (PDOException $e) {
                $msg = "Error: Department Code '$code' already exists or DB failure.";
                $msg_type = "error";
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['dept_id'] ?? 0);
        $code = strtoupper(sanitize($_POST['dept_code'] ?? ''));
        $name = sanitize($_POST['dept_name'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');

        if ($id > 0 && !empty($code) && !empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE departments SET dept_code = :code, dept_name = :name, description = :desc WHERE id = :id");
                $stmt->execute(['code' => $code, 'name' => $name, 'desc' => $desc, 'id' => $id]);
                $msg = "Department updated successfully!";
                $msg_type = "success";
            } catch (PDOException $e) {
                $msg = "Error updating department: " . $e->getMessage();
                $msg_type = "error";
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['dept_id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM departments WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $msg = "Department deleted successfully!";
                $msg_type = "success";
            } catch (PDOException $e) {
                $msg = "Cannot delete department as students are assigned to it!";
                $msg_type = "error";
            }
        }
    }
}

// Fetch all departments with student count
$depts_stmt = $pdo->query("
    SELECT d.*, COUNT(s.id) as student_count
    FROM departments d
    LEFT JOIN students s ON d.id = s.dept_id
    GROUP BY d.id
    ORDER BY d.id DESC
");
$departments = $depts_stmt->fetchAll();
?>

<!-- Action Bar & Search -->
<div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-4 rounded-3xl shadow-lg">
    <!-- Search Bar -->
    <div class="relative flex-1 max-w-md">
        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
            <i class="fa-solid fa-magnifying-glass"></i>
        </span>
        <input type="text" id="search-dept-input" onkeyup="filterDepartments()" placeholder="Search department name or code..."
            class="w-full pl-10 pr-4 py-2.5 bg-slate-950 border border-slate-800 rounded-2xl focus:outline-none focus:border-indigo-500 text-white text-sm">
    </div>

    <!-- Add Dept Button -->
    <button onclick="openAddModal()" class="py-2.5 px-5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-2xl shadow-lg shadow-indigo-600/30 transition flex items-center justify-center gap-2 text-sm">
        <i class="fa-solid fa-plus"></i>
        <span>Add Department</span>
    </button>
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

<!-- Departments Data Table -->
<div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="dept-table">
            <thead>
                <tr class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    <th class="py-4 px-6">ID</th>
                    <th class="py-4 px-6">Dept Code</th>
                    <th class="py-4 px-6">Department Name</th>
                    <th class="py-4 px-6">Description</th>
                    <th class="py-4 px-6 text-center">Total Students</th>
                    <th class="py-4 px-6 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 text-sm">
                <?php if (empty($departments)): ?>
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-500">No departments found. Click "Add Department" to create one.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($departments as $dept): ?>
                        <tr class="hover:bg-slate-800/40 transition dept-row">
                            <td class="py-4 px-6 font-mono text-xs text-slate-500">#<?= $dept['id'] ?></td>
                            <td class="py-4 px-6 font-bold text-indigo-400">
                                <span class="bg-indigo-950/60 border border-indigo-500/30 px-2.5 py-1 rounded-lg">
                                    <?= htmlspecialchars($dept['dept_code']) ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 font-semibold text-white dept-name"><?= htmlspecialchars($dept['dept_name']) ?></td>
                            <td class="py-4 px-6 text-slate-400 text-xs"><?= htmlspecialchars($dept['description'] ?? 'N/A') ?></td>
                            <td class="py-4 px-6 text-center">
                                <span class="bg-slate-950 border border-slate-800 px-3 py-1 rounded-full text-xs font-semibold text-slate-300">
                                    <i class="fa-solid fa-users text-indigo-400 mr-1"></i> <?= $dept['student_count'] ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 text-right space-x-2">
                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($dept)) ?>)"
                                    class="p-2 bg-slate-800 hover:bg-indigo-600 text-slate-300 hover:text-white rounded-xl transition" title="Edit">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button onclick="openDeleteModal(<?= $dept['id'] ?>, '<?= htmlspecialchars($dept['dept_name']) ?>')"
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

<!-- Modal: Add Department -->
<div id="add-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-building-circle-check text-indigo-400"></i> Add New Department
            </h3>
            <button onclick="closeAddModal()" class="text-slate-400 hover:text-white">&times;</button>
        </div>
        <form action="departments.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Department Code</label>
                <input type="text" name="dept_code" placeholder="e.g. CSE" required
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white uppercase text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Department Name</label>
                <input type="text" name="dept_name" placeholder="e.g. Computer Science & Engineering" required
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Description (Optional)</label>
                <textarea name="description" rows="3" placeholder="Brief department notes..."
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm"></textarea>
            </div>
            <div class="pt-3 flex items-center justify-end gap-3">
                <button type="button" onclick="closeAddModal()" class="px-4 py-2.5 bg-slate-800 text-slate-300 rounded-xl text-sm font-semibold">Cancel</button>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold shadow-lg shadow-indigo-600/30">Save Department</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Department -->
<div id="edit-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-indigo-400"></i> Edit Department
            </h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-white">&times;</button>
        </div>
        <form action="departments.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit-dept-id" name="dept_id">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Department Code</label>
                <input type="text" id="edit-dept-code" name="dept_code" required
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white uppercase text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Department Name</label>
                <input type="text" id="edit-dept-name" name="dept_name" required
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Description</label>
                <textarea id="edit-dept-desc" name="description" rows="3"
                    class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white text-sm"></textarea>
            </div>
            <div class="pt-3 flex items-center justify-end gap-3">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2.5 bg-slate-800 text-slate-300 rounded-xl text-sm font-semibold">Cancel</button>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold shadow-lg shadow-indigo-600/30">Update Department</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div id="delete-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-sm w-full p-6 shadow-2xl space-y-4 text-center">
        <div class="w-14 h-14 bg-red-500/10 border border-red-500/30 text-red-400 rounded-full flex items-center justify-center mx-auto text-2xl">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3 class="text-lg font-bold text-white">Delete Department?</h3>
        <p class="text-xs text-slate-400" id="delete-target-name">Are you sure you want to delete this department?</p>

        <form action="departments.php" method="POST" class="pt-2 flex items-center justify-center gap-3">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" id="delete-dept-id" name="dept_id">
            <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-sm font-semibold">Cancel</button>
            <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-500 text-white rounded-xl text-sm font-semibold shadow-lg shadow-red-600/30">Delete</button>
        </form>
    </div>
</div>

<script>
function filterDepartments() {
    const query = document.getElementById('search-dept-input').value.toLowerCase();
    const rows = document.querySelectorAll('#dept-table tbody .dept-row');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
}

function openAddModal() { document.getElementById('add-modal').classList.remove('hidden'); }
function closeAddModal() { document.getElementById('add-modal').classList.add('hidden'); }

function openEditModal(dept) {
    document.getElementById('edit-dept-id').value = dept.id;
    document.getElementById('edit-dept-code').value = dept.dept_code;
    document.getElementById('edit-dept-name').value = dept.dept_name;
    document.getElementById('edit-dept-desc').value = dept.description || '';
    document.getElementById('edit-modal').classList.remove('hidden');
}
function closeEditModal() { document.getElementById('edit-modal').classList.add('hidden'); }

function openDeleteModal(id, name) {
    document.getElementById('delete-dept-id').value = id;
    document.getElementById('delete-target-name').innerText = `Are you sure you want to delete department "${name}"?`;
    document.getElementById('delete-modal').classList.remove('hidden');
}
function closeDeleteModal() { document.getElementById('delete-modal').classList.add('hidden'); }
</script>

<?php require_once 'includes/footer.php'; ?>
