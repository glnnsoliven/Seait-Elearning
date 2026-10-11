/**
 * SEAIT E-Learning Platform — Main Application JS
 * Included on every authenticated page via sidebar_js.php
 */

// ── Notification Badge Live Update ────────────────────────────
(function initNotifPolling() {
    const badge = document.getElementById('notif-badge');
    if (!badge) return;

    async function updateCount() {
        try {
            const r = await fetch('/modules/api/notifications_count.php');
            const d = await r.json();
            if (d.count > 0) {
                badge.textContent = d.count > 9 ? '9+' : d.count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        } catch (_) {}
    }

    // Poll every 60 seconds
    setInterval(updateCount, 60000);
})();

// ── Search table filter ───────────────────────────────────────
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('input', () => {
        const query = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
        });
    });
}

// ── Animate progress bars on page load ───────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-progress]').forEach(el => {
        const target = parseInt(el.dataset.progress);
        el.style.width = '0%';
        setTimeout(() => {
            el.style.transition = 'width 0.8s ease';
            el.style.width = target + '%';
        }, 100);
    });
});

// ── Scroll to anchor on page load ────────────────────────────
if (window.location.hash) {
    const el = document.querySelector(window.location.hash);
    if (el) setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 200);
}
