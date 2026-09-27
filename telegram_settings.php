<?php
$page_title = "Telegram Bot Notifications";
$page_subtitle = "Configure Bot Token & Chat ID for Instant Registration & Attendance Alerts";
require_once 'includes/header.php';
require_once 'includes/telegram_helper.php';

$msg = '';
$msg_type = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $bot_token = trim($_POST['telegram_bot_token'] ?? '');
        $chat_id = trim($_POST['telegram_chat_id'] ?? '');
        $enabled = isset($_POST['telegram_enabled']) ? '1' : '0';

        $settings = [
            'telegram_bot_token' => $bot_token,
            'telegram_chat_id' => $chat_id,
            'telegram_enabled' => $enabled
        ];

        foreach ($settings as $key => $val) {
            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES (:key, :val) 
                ON DUPLICATE KEY UPDATE setting_value = :val2
            ");
            $stmt->execute(['key' => $key, 'val' => $val, 'val2' => $val]);
        }

        $msg = "Telegram Bot settings saved successfully!";
        $msg_type = "success";
    } elseif ($action === 'test_notification') {
        $testMsg = "🤖 <b>TELEGRAM BOT TEST NOTIFICATION</b>\n";
        $testMsg .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $testMsg .= "✅ Your RFID Attendance Management System Telegram Bot is <b>Online & Connected!</b>\n";
        $testMsg .= "⏰ <b>Time:</b> " . date('d M Y, h:i:s A') . "\n";
        $testMsg .= "━━━━━━━━━━━━━━━━━━━━━";

        $res = sendTelegramNotification($testMsg, $pdo);
        if ($res['success']) {
            $msg = "Test notification sent successfully to Telegram!";
            $msg_type = "success";
        } else {
            $msg = "Test Failed: " . ($res['error'] ?? 'Unknown error');
            $msg_type = "error";
        }
    }
}

// Fetch Current Settings
$bot_token = getTelegramSetting('telegram_bot_token', '', $pdo);
$chat_id = getTelegramSetting('telegram_chat_id', '', $pdo);
$enabled = getTelegramSetting('telegram_enabled', '1', $pdo);
?>

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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Telegram Configuration Card -->
    <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-6">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-sky-500/20 text-sky-400 border border-sky-500/30 flex items-center justify-center text-2xl">
                    <i class="fa-brands fa-telegram"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-white">Telegram Bot Setup</h2>
                    <p class="text-xs text-slate-400">Receive instant notifications when students register or scan their cards</p>
                </div>
            </div>
            
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?= ($enabled === '1' && !empty($bot_token) && !empty($chat_id)) ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/10 text-amber-400 border border-amber-500/30' ?>">
                <span class="w-2 h-2 rounded-full <?= ($enabled === '1' && !empty($bot_token) && !empty($chat_id)) ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400' ?>"></span>
                <span><?= ($enabled === '1' && !empty($bot_token) && !empty($chat_id)) ? 'Active & Ready' : 'Setup Required' ?></span>
            </span>
        </div>

        <form action="telegram_settings.php" method="POST" class="space-y-5">
            <input type="hidden" name="action" value="save_settings">

            <!-- Enable / Disable Switch -->
            <div class="flex items-center justify-between p-4 bg-slate-950 border border-slate-800 rounded-2xl">
                <div>
                    <label for="telegram_enabled" class="text-sm font-bold text-white block cursor-pointer">Enable Telegram Notifications</label>
                    <p class="text-xs text-slate-400">Toggle ON to send automated messages to your Telegram Chat/Group</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="telegram_enabled" name="telegram_enabled" value="1" <?= ($enabled === '1') ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-500"></div>
                </label>
            </div>

            <!-- Bot Token -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">
                    Telegram Bot Token *
                </label>
                <div class="relative">
                    <input type="text" name="telegram_bot_token" value="<?= htmlspecialchars($bot_token) ?>" placeholder="e.g. 123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ"
                        class="w-full pl-4 pr-10 py-3 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-sky-500 text-sky-300 font-mono text-sm">
                    <span class="absolute right-3 top-3.5 text-slate-500">
                        <i class="fa-solid fa-key"></i>
                    </span>
                </div>
                <p class="text-[11px] text-slate-500 mt-1">Obtained from <a href="https://t.me/BotFather" target="_blank" class="text-sky-400 underline hover:text-sky-300">@BotFather</a> on Telegram.</p>
            </div>

            <!-- Chat ID -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">
                    Telegram Chat ID / Group ID *
                </label>
                <div class="relative">
                    <input type="text" name="telegram_chat_id" value="<?= htmlspecialchars($chat_id) ?>" placeholder="e.g. 987654321 or -1001234567890"
                        class="w-full pl-4 pr-10 py-3 bg-slate-950 border border-slate-800 rounded-xl focus:outline-none focus:border-sky-500 text-sky-300 font-mono text-sm">
                    <span class="absolute right-3 top-3.5 text-slate-500">
                        <i class="fa-solid fa-paper-plane"></i>
                    </span>
                </div>
                <p class="text-[11px] text-slate-500 mt-1">Personal Chat ID or Group/Channel ID (e.g. from <a href="https://t.me/userinfobot" target="_blank" class="text-sky-400 underline hover:text-sky-300">@userinfobot</a>).</p>
            </div>

            <!-- Submit Button & Test Button -->
            <div class="pt-3 flex flex-wrap items-center justify-between gap-3 border-t border-slate-800">
                <button type="submit" class="px-6 py-2.5 bg-sky-600 hover:bg-sky-500 text-white font-semibold rounded-xl text-sm transition shadow-lg shadow-sky-600/30 flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Save Settings</span>
                </button>
        </form>

        <form action="telegram_settings.php" method="POST" class="inline">
            <input type="hidden" name="action" value="test_notification">
            <button type="submit" <?= (empty($bot_token) || empty($chat_id)) ? 'disabled class="px-5 py-2.5 bg-slate-800 text-slate-500 rounded-xl text-sm font-semibold cursor-not-allowed opacity-50"' : 'class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-sm font-semibold transition shadow-lg shadow-emerald-600/20 flex items-center gap-2"' ?>>
                <i class="fa-solid fa-paper-plane"></i>
                <span>Send Test Message</span>
            </button>
        </form>
            </div>
    </div>

    <!-- Quick Setup Guide Card -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <h3 class="text-base font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
            <i class="fa-solid fa-circle-info text-sky-400"></i> How to Setup Bot
        </h3>

        <ol class="text-xs text-slate-300 space-y-3 list-decimal list-inside leading-relaxed">
            <li class="pl-1">Open Telegram and search for <b>@BotFather</b>.</li>
            <li class="pl-1">Send <code>/newbot</code> command and follow instructions to get your <b>Bot Token</b>.</li>
            <li class="pl-1">Start a conversation with your bot (or add it to a Telegram group/channel).</li>
            <li class="pl-1">Search for <b>@userinfobot</b> on Telegram to get your personal <b>Chat ID</b> (or group chat ID).</li>
            <li class="pl-1">Paste your <b>Bot Token</b> and <b>Chat ID</b> in the fields on the left and click <b>Save Settings</b>.</li>
            <li class="pl-1">Click <b>Send Test Message</b> to verify instant connectivity!</li>
        </ol>

        <div class="mt-4 p-4 rounded-2xl bg-sky-950/40 border border-sky-500/20 text-xs text-sky-300 space-y-2">
            <div class="font-bold flex items-center gap-1.5 text-sky-400">
                <i class="fa-solid fa-bell"></i> Automatic Alerts Sent:
            </div>
            <ul class="space-y-1 list-disc list-inside text-[11px] text-slate-300">
                <li><strong>New Student Registration:</strong> Full Info (Name, Student ID, RFID Card UID, Dept, Phone, Email).</li>
                <li><strong>Attendance Check-In:</strong> Full Info (Name, Student ID, Dept, Card UID, Date, In-Time, Status).</li>
                <li><strong>Attendance Check-Out:</strong> Full Info (Name, Student ID, Dept, Card UID, Date, In-Time, Out-Time, Status).</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
