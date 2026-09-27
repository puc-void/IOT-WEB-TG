<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside id="sidebar" class="fixed md:static inset-y-0 left-0 z-50 w-64 bg-slate-900 border-r border-slate-800/80 flex flex-col justify-between transition-transform duration-300 transform -translate-x-full md:translate-x-0">
    <div>
        <!-- Brand Header -->
        <div class="h-20 flex items-center px-6 border-b border-slate-800/80 gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white text-xl font-bold shadow-lg shadow-indigo-600/30">
                <i class="fa-solid fa-id-card-clip"></i>
            </div>
            <div>
                <span class="font-extrabold text-lg text-white tracking-tight">RFID Pass</span>
                <span class="block text-[10px] uppercase tracking-widest text-indigo-400 font-semibold">ESP32 Attendance</span>
            </div>
        </div>

        <!-- Navigation Links -->
        <nav class="p-4 space-y-1.5">
            <div class="px-3 text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Main Menu</div>

            <a href="index.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all duration-150 <?= ($current_page == 'index.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-chart-pie w-5 text-center text-base"></i>
                <span>Dashboard</span>
            </a>

            <a href="attendance.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all duration-150 <?= ($current_page == 'attendance.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-clipboard-user w-5 text-center text-base"></i>
                <span>Attendance Logs</span>
            </a>

            <a href="students.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all duration-150 <?= ($current_page == 'students.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-users w-5 text-center text-base"></i>
                <span>Student Directory</span>
            </a>

            <a href="departments.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all duration-150 <?= ($current_page == 'departments.php') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' ?>">
                <i class="fa-solid fa-building-columns w-5 text-center text-base"></i>
                <span>Departments</span>
            </a>

            <div class="pt-4 px-3 text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Bot & Notifications</div>

            <a href="telegram_settings.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all duration-150 <?= ($current_page == 'telegram_settings.php') ? 'bg-sky-600 text-white shadow-lg shadow-sky-600/30' : 'text-sky-400/90 hover:text-sky-300 hover:bg-sky-950/40 border border-sky-500/20' ?>">
                <i class="fa-brands fa-telegram w-5 text-center text-base"></i>
                <span>Telegram Bot</span>
                <span class="ml-auto text-[10px] bg-sky-500/20 text-sky-300 px-2 py-0.5 rounded-md">Bot</span>
            </a>

            <div class="pt-4 px-3 text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">Hardware & Testing</div>

            <a href="rfid_terminal.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl font-medium text-sm transition-all duration-150 <?= ($current_page == 'rfid_terminal.php') ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/30' : 'text-emerald-400/80 hover:text-emerald-300 hover:bg-emerald-950/40 border border-emerald-500/20' ?>">
                <i class="fa-solid fa-[#00F] fa-wave-square w-5 text-center text-base"></i>
                <span>Web Scan Terminal</span>
                <span class="ml-auto text-[10px] bg-emerald-500/20 text-emerald-300 px-2 py-0.5 rounded-md">Live</span>
            </a>
        </nav>
    </div>

    <!-- Sidebar Bottom Card / ESP32 Info -->
    <div class="p-4 border-t border-slate-800/80">
        <div class="p-3.5 rounded-2xl bg-slate-950/80 border border-slate-800 text-xs text-slate-400 space-y-2">
            <div class="flex items-center justify-between text-white font-semibold">
                <span class="flex items-center gap-1.5"><i class="fa-solid fa-microchip text-indigo-400"></i> ESP32 Status</span>
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
            </div>
            <p class="text-[11px] text-slate-400">API Endpoint: <br><code class="text-indigo-300 text-[10px] break-all">api/rfid_scan.php</code></p>
        </div>
    </div>
</aside>
