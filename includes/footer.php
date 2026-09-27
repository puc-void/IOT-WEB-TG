        </main>
        <!-- Footer Info -->
        <footer class="mt-auto border-t border-slate-800/80 px-6 py-4 bg-slate-900/40 text-center text-xs text-slate-500">
            <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
                <div>&copy; <?= date('Y') ?> RFID Attendance System. ESP32 + PHP + MySQL.</div>
                <div class="flex items-center gap-4">
                    <span class="text-slate-400">XAMPP Ready</span>
                    <span>•</span>
                    <span class="text-slate-400">RC522 RFID Compatible</span>
                </div>
            </div>
        </footer>
    </div>

    <!-- Live Clock & Common Utility JS -->
    <script>
        function updateClock() {
            const clockEl = document.getElementById('live-clock');
            if (clockEl) {
                const now = new Date();
                clockEl.innerText = now.toLocaleTimeString();
            }
        }
        setInterval(updateClock, 1000);
        updateClock();

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        }
    </script>
</body>
</html>
