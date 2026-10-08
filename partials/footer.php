    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Update Jam Real-time (seperti di mockup)
    function perbaruiJam() {
        const el = document.getElementById('realtimeClock');
        if (el) {
            const now = new Date();
            const jam = String(now.getHours()).padStart(2, '0');
            const menit = String(now.getMinutes()).padStart(2, '0');
            el.textContent = jam + ':' + menit + ' WIB';
        }
    }
    perbaruiJam();
    setInterval(perbaruiJam, 1000);

    // Toggle Sidebar di Layar HP
    const btnToggle = document.getElementById('btnToggleSidebar');
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (btnToggle && sidebar && overlay) {
        btnToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
</script>
