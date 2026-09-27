<?php
require_once '../config/db.php';

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: ../index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();

        // Check password (supports default 'admin123' plain check or password_hash)
        if ($admin) {
            $is_valid = false;
            if (password_verify($password, $admin['password'])) {
                $is_valid = true;
            } elseif ($password === 'admin123' && $admin['username'] === 'admin') {
                $is_valid = true;
            }

            if ($is_valid) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_fullname'] = $admin['fullname'];

                header("Location: ../index.php");
                exit();
            } else {
                $error = "Invalid password! Please try again.";
            }
        } else {
            $error = "User not found!";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - RFID Attendance System</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            900: '#312e81',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-950 font-sans text-slate-100 min-h-screen flex items-center justify-center relative overflow-hidden">
    <!-- Decorative Glowing Background Orbs -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/30 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-purple-600/30 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md p-6 relative z-10">
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 text-indigo-400 mb-4 shadow-lg shadow-indigo-500/10">
                <i class="fa-solid fa-wifi-slash text-3xl"></i>
            </div>
            <h1 class="text-3xl font-bold tracking-tight text-white">ESP32 RFID System</h1>
            <p class="text-slate-400 text-sm mt-1">Smart Attendance Management Portal</p>
        </div>

        <!-- Login Card -->
        <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-3xl p-8 shadow-2xl shadow-black/50">
            <h2 class="text-xl font-semibold text-white mb-6 flex items-center gap-2">
                <i class="fa-solid fa-lock text-indigo-400"></i> Admin Sign In
            </h2>

            <?php if (!empty($error)): ?>
                <div class="mb-6 bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-2xl text-sm flex items-center gap-3">
                    <i class="fa-solid fa-circle-exclamation text-lg"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-5">
                <div>
                    <label for="username" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Username</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                            <i class="fa-solid fa-user"></i>
                        </span>
                        <input type="text" id="username" name="username" value="admin" required
                            class="w-full pl-10 pr-4 py-3 bg-slate-950/70 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-white placeholder-slate-600 transition text-sm">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                            <i class="fa-solid fa-key"></i>
                        </span>
                        <input type="password" id="password" name="password" value="admin123" required
                            class="w-full pl-10 pr-4 py-3 bg-slate-950/70 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-white placeholder-slate-600 transition text-sm">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit"
                        class="w-full py-3.5 px-4 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-semibold rounded-xl shadow-lg shadow-indigo-600/30 hover:shadow-indigo-500/50 transition duration-200 flex items-center justify-center gap-2 group">
                        <span>Sign In to Dashboard</span>
                        <i class="fa-solid fa-arrow-right text-sm group-hover:translate-x-1 transition-transform"></i>
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-6 border-t border-slate-800/80 text-center">
                <p class="text-xs text-slate-500">
                    Default Credentials: <code class="bg-slate-950 text-indigo-400 px-2 py-1 rounded">admin</code> / <code class="bg-slate-950 text-indigo-400 px-2 py-1 rounded">admin123</code>
                </p>
            </div>
        </div>

        <p class="text-center text-xs text-slate-600 mt-8">
            &copy; <?= date('Y') ?> ESP32 RFID Attendance System • PHP & MySQL
        </p>
    </div>
</body>
</html>
