<?php
$page_title = "Web RFID Scan Terminal";
$page_subtitle = "Simulate & Test ESP32 RFID Card Scans in Real-Time";
require_once 'includes/header.php';

// Fetch registered students
$students_stmt = $pdo->query("
    SELECT s.id, s.name, s.student_id, s.card_uid, d.dept_code 
    FROM students s
    JOIN departments d ON s.dept_id = d.id
    ORDER BY s.id ASC
");
$test_students = $students_stmt->fetchAll();

// Fetch active device token
$active_token = $pdo->query("SELECT device_token FROM system_mode WHERE id = 1 LIMIT 1")->fetchColumn() ?: '19453d39b4edd4e6';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Scanner Hardware Simulation Card -->
    <div class="lg:col-span-1 bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-emerald-400 animate-ping"></span>
                    <h2 class="text-lg font-bold text-white">Virtual RFID Scanner</h2>
                </div>
                <span class="text-xs bg-slate-800 text-slate-400 px-2.5 py-1 rounded-lg border border-slate-700">RC522 Mode</span>
            </div>

            <!-- Visual Scanner Terminal Box -->
            <div id="scan-terminal-box" class="bg-slate-950 border-2 border-indigo-500/40 rounded-2xl p-6 text-center transition-all duration-300 relative overflow-hidden group">
                <div class="w-20 h-20 mx-auto rounded-full bg-indigo-600/10 border border-indigo-500/30 flex items-center justify-center text-indigo-400 text-3xl mb-4 group-hover:scale-110 transition-transform">
                    <i class="fa-solid fa-wifi text-4xl"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-300">Ready for RFID Card</h3>
                <p class="text-xs text-slate-500 mt-1">Select a card below or type UID to simulate HTTP GET request.</p>

                <!-- Scan Status Banner -->
                <div id="scan-feedback" class="hidden mt-4 p-3 rounded-xl text-xs font-semibold"></div>
            </div>

            <!-- Device Token & Manual Card UID Input -->
            <form id="rfid-scan-form" class="mt-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Active Device Token</label>
                    <input type="text" id="device_token_input" value="<?= htmlspecialchars($active_token) ?>" readonly
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-amber-400 font-mono text-xs font-bold tracking-wider">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Enter / Scan Card UID</label>
                    <div class="relative">
                        <input type="text" id="card_uid_input" placeholder="e.g. A3B4C5D6" required autofocus
                            class="w-full pl-4 pr-12 py-3 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-indigo-500 text-white font-mono uppercase tracking-widest text-sm">
                        <button type="submit" class="absolute right-2 top-2 bottom-2 px-3 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg transition flex items-center gap-1">
                            <span>Scan</span>
                            <i class="fa-solid fa-bolt text-xs"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Quick Demo Students -->
        <div class="mt-6 pt-6 border-t border-slate-800">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 flex items-center justify-between">
                <span>Preset Registered Cards</span>
                <span class="text-[10px] text-indigo-400">Click to scan</span>
            </h4>
            <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                <?php foreach ($test_students as $st): ?>
                    <button type="button" onclick="simulateCardScan('<?= htmlspecialchars($st['card_uid']) ?>')"
                        class="w-full flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 hover:bg-indigo-600/20 border border-slate-800 hover:border-indigo-500/40 transition text-left group">
                        <div>
                            <div class="text-xs font-semibold text-white group-hover:text-indigo-300"><?= htmlspecialchars($st['name']) ?></div>
                            <div class="text-[10px] text-slate-500"><?= htmlspecialchars($st['dept_code']) ?> • ID: <?= htmlspecialchars($st['student_id']) ?></div>
                        </div>
                        <span class="text-[11px] font-mono font-bold bg-slate-900 group-hover:bg-indigo-600 group-hover:text-white px-2 py-1 rounded text-slate-400 transition">
                            <?= htmlspecialchars($st['card_uid']) ?>
                        </span>
                    </button>
                <?php endforeach; ?>

                <button type="button" onclick="simulateCardScan('99999999')"
                    class="w-full flex items-center justify-between p-2.5 rounded-xl bg-red-950/20 hover:bg-red-900/30 border border-red-900/40 transition text-left group">
                    <div>
                        <div class="text-xs font-semibold text-red-400">Test Unregistered Card</div>
                        <div class="text-[10px] text-red-500/70">Invalid UID simulation</div>
                    </div>
                    <span class="text-[11px] font-mono font-bold bg-red-900/50 text-red-300 px-2 py-1 rounded">
                        99999999
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Terminal Live Logs Feed -->
    <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-terminal text-indigo-400"></i> API Live Response Feed
                </h2>
                <button onclick="clearTerminalLogs()" class="text-xs text-slate-400 hover:text-white bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-700 transition">
                    <i class="fa-solid fa-eraser mr-1"></i> Clear Feed
                </button>
            </div>
            
            <div id="terminal-log-output" class="bg-slate-950 border border-slate-800 rounded-2xl p-4 h-96 overflow-y-auto font-mono text-xs space-y-3">
                <div class="text-slate-500 italic">[System] Terminal ready. Waiting for RFID scan request...</div>
            </div>
        </div>

        <div class="mt-4 pt-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-500">
            <div>API Target: <code class="text-indigo-400">GET /getdata.php</code></div>
            <div>Response Speed: <span id="response-speed" class="text-emerald-400 font-mono">-- ms</span></div>
        </div>
    </div>
</div>

<script>
document.getElementById('rfid-scan-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const uid = document.getElementById('card_uid_input').value.trim();
    if (uid) {
        simulateCardScan(uid);
    }
});

function simulateCardScan(uid) {
    document.getElementById('card_uid_input').value = uid;
    const token = document.getElementById('device_token_input').value;
    const startTime = performance.now();
    const output = document.getElementById('terminal-log-output');
    const feedback = document.getElementById('scan-feedback');
    const box = document.getElementById('scan-terminal-box');

    const timestamp = new Date().toLocaleTimeString();
    const reqLog = document.createElement('div');
    reqLog.className = 'text-indigo-400 border-l-2 border-indigo-500 pl-2 py-1 bg-indigo-950/30 rounded-r';
    reqLog.innerHTML = `<span class="text-slate-500">[${timestamp}]</span> <strong>GET /getdata.php</strong> &rarr; card_uid="${uid}" & device_token="${token}"`;
    output.appendChild(reqLog);
    output.scrollTop = output.scrollHeight;

    fetch(`getdata.php?card_uid=${encodeURIComponent(uid)}&device_token=${encodeURIComponent(token)}`)
    .then(res => res.text())
    .then(dataText => {
        const endTime = performance.now();
        document.getElementById('response-speed').innerText = Math.round(endTime - startTime) + ' ms';

        const resLog = document.createElement('div');
        resLog.className = 'pl-2 py-1 rounded text-xs space-y-1';

        feedback.classList.remove('hidden', 'bg-emerald-500/20', 'text-emerald-400', 'bg-blue-500/20', 'text-blue-400', 'bg-red-500/20', 'text-red-400', 'bg-amber-500/20', 'text-amber-400');
        box.classList.remove('border-emerald-500', 'border-blue-500', 'border-red-500', 'border-amber-500');

        if (dataText.startsWith('login')) {
            const name = dataText.substring(5);
            resLog.className += ' text-emerald-300 bg-emerald-950/40 border-l-2 border-emerald-500';
            feedback.className += ' bg-emerald-500/20 text-emerald-400 border border-emerald-500/30';
            box.classList.add('border-emerald-500');
            feedback.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i> WELCOME: ${name} (In-Time Recorded)`;
        } else if (dataText.startsWith('logout')) {
            const name = dataText.substring(6);
            resLog.className += ' text-blue-300 bg-blue-950/40 border-l-2 border-blue-500';
            feedback.className += ' bg-blue-500/20 text-blue-400 border border-blue-500/30';
            box.classList.add('border-blue-500');
            feedback.innerHTML = `<i class="fa-solid fa-right-from-bracket mr-1"></i> GOOD BYE: ${name} (Out-Time Recorded)`;
        } else if (dataText === 'succesful') {
            resLog.className += ' text-amber-300 bg-amber-950/40 border-l-2 border-amber-500';
            feedback.className += ' bg-amber-500/20 text-amber-400 border border-amber-500/30';
            box.classList.add('border-amber-500');
            feedback.innerHTML = `<i class="fa-solid fa-id-card mr-1"></i> NEW CARD SCANNED`;
        } else if (dataText === 'available') {
            resLog.className += ' text-indigo-300 bg-indigo-950/40 border-l-2 border-indigo-500';
            feedback.className += ' bg-indigo-500/20 text-indigo-400 border border-indigo-500/30';
            box.classList.add('border-indigo-500');
            feedback.innerHTML = `<i class="fa-solid fa-id-card-clip mr-1"></i> FREE CARD SCANNED`;
        } else {
            resLog.className += ' text-red-300 bg-red-950/40 border-l-2 border-red-500';
            feedback.className += ' bg-red-500/20 text-red-400 border border-red-500/30';
            box.classList.add('border-red-500');
            feedback.innerHTML = `<i class="fa-solid fa-circle-xmark mr-1"></i> SERVER RESPONSE: ${dataText}`;
        }

        resLog.innerHTML = `<span class="text-slate-500">[${timestamp}]</span> Raw Output: <strong class="font-mono text-amber-300 font-bold">${dataText}</strong>`;
        output.appendChild(resLog);
        output.scrollTop = output.scrollHeight;
    })
    .catch(err => {
        const errLog = document.createElement('div');
        errLog.className = 'text-red-400 bg-red-950/30 p-2 rounded';
        errLog.innerText = '[Error] Failed to connect: ' + err.message;
        output.appendChild(errLog);
    });
}

function clearTerminalLogs() {
    document.getElementById('terminal-log-output').innerHTML = '<div class="text-slate-500 italic">[System] Feed cleared.</div>';
}
</script>

<?php require_once 'includes/footer.php'; ?>
