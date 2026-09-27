<?php
require_once __DIR__ . '/../config/db.php';
check_admin_auth();

$current_page = basename($_SERVER['PHP_SELF']);

// Fetch Current Device Mode
$mode_stmt = $pdo->query("SELECT device_mode FROM system_mode WHERE id = 1 LIMIT 1");
$current_mode = $mode_stmt->fetchColumn() ?: 'ATTENDANCE';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'RFID Attendance System' ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            400: '#818cf8',
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
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
    </style>
</head>
<body class="h-full font-sans bg-slate-950 text-slate-100 flex flex-col md:flex-row antialiased">
    <!-- Mobile Sidebar Toggle Overlay -->
    <div id="sidebar-backdrop" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-40 hidden md:hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar Include -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Container -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <!-- Top Navigation Header -->
        <header class="sticky top-0 z-30 bg-slate-900/90 backdrop-blur-md border-b border-slate-800/80 px-4 sm:px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-white p-2 rounded-lg bg-slate-800">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h1 class="text-xl font-bold text-white tracking-tight flex items-center gap-2">
                        <?= $page_title ?? 'Dashboard' ?>
                    </h1>
                    <p class="text-xs text-slate-400 hidden sm:block"><?= $page_subtitle ?? 'Real-time ESP32 RFID Attendance Portal' ?></p>
                </div>
            </div>

            <!-- Topbar Right Actions -->
            <div class="flex items-center gap-3">
                <!-- Mode Controller Badge Button -->
                <div class="flex items-center bg-slate-950 border border-slate-800 rounded-full p-1 text-xs">
                    <button id="btn-mode-attendance" onclick="setSystemMode('ATTENDANCE')"
                        class="px-3 py-1.5 rounded-full font-bold transition flex items-center gap-1.5 <?= ($current_mode === 'ATTENDANCE') ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30' : 'text-slate-400 hover:text-white' ?>">
                        <span class="w-2 h-2 rounded-full <?= ($current_mode === 'ATTENDANCE') ? 'bg-white animate-pulse' : 'bg-slate-600' ?>"></span>
                        <span>Attendance Mode</span>
                    </button>
                    <button id="btn-mode-registration" onclick="setSystemMode('REGISTRATION')"
                        class="px-3 py-1.5 rounded-full font-bold transition flex items-center gap-1.5 <?= ($current_mode === 'REGISTRATION') ? 'bg-amber-600 text-white shadow-md shadow-amber-600/30' : 'text-slate-400 hover:text-white' ?>">
                        <span class="w-2 h-2 rounded-full <?= ($current_mode === 'REGISTRATION') ? 'bg-white animate-ping' : 'bg-slate-600' ?>"></span>
                        <span>Registration Mode</span>
                    </button>
                </div>

                <!-- Clock Display -->
                <div class="hidden lg:flex items-center gap-2 bg-slate-950/60 border border-slate-800 px-3 py-1.5 rounded-xl text-xs text-slate-300 font-mono">
                    <i class="fa-regular fa-clock text-indigo-400"></i>
                    <span id="live-clock">--:--:--</span>
                </div>

                <!-- Admin Profile Dropdown / Logout -->
                <div class="flex items-center gap-3 border-l border-slate-800 pl-4">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-purple-600 flex items-center justify-center text-white font-bold shadow-md shadow-indigo-600/20 text-sm">
                        <?= strtoupper(substr($_SESSION['admin_fullname'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div class="hidden sm:block text-left">
                        <div class="text-xs font-bold text-white"><?= htmlspecialchars($_SESSION['admin_fullname'] ?? 'Admin') ?></div>
                        <div class="text-[10px] text-slate-400">System Admin</div>
                    </div>
                    <a href="auth/logout.php" title="Logout" class="p-2 text-slate-400 hover:text-red-400 hover:bg-slate-800 rounded-xl transition">
                        <i class="fa-solid fa-power-off text-base"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Workspace Body -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto space-y-6">

        <script>
        function setSystemMode(mode) {
            fetch('api/mode_control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'set_mode', mode: mode })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    location.reload();
                }
            });
        }
        </script>
