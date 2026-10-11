<script>
// ── Sidebar toggle (mobile) ──────────────────────────────────
(function () {
    const btn     = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    function openSidebar() {
        sidebar?.classList.remove('-translate-x-full');
        overlay?.classList.remove('hidden');
    }
    function closeSidebar() {
        sidebar?.classList.add('-translate-x-full');
        overlay?.classList.add('hidden');
    }

    btn?.addEventListener('click', openSidebar);
    overlay?.addEventListener('click', closeSidebar);
})();

// ── User menu dropdown ────────────────────────────────────────
(function () {
    const btn      = document.getElementById('user-menu-btn');
    const dropdown = document.getElementById('user-menu-dropdown');

    btn?.addEventListener('click', (e) => {
        e.stopPropagation();
        const isHidden = dropdown?.classList.contains('hidden');
        dropdown?.classList.toggle('hidden', !isHidden);
        btn.setAttribute('aria-expanded', String(isHidden));
    });

    document.addEventListener('click', () => {
        dropdown?.classList.add('hidden');
        btn?.setAttribute('aria-expanded', 'false');
    });
})();

// ── Modal helpers ─────────────────────────────────────────────
function openModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('hidden'); document.body.style.overflow = ''; }
}

// ── CSRF helper (for fetch calls) ────────────────────────────
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

// ── Toast (JS-initiated) ─────────────────────────────────────
function showToast(message, type = 'info') {
    const colors = {
        success: 'bg-green-50 border-green-300 text-green-800',
        error:   'bg-red-50 border-red-300 text-red-800',
        warning: 'bg-yellow-50 border-yellow-300 text-yellow-800',
        info:    'bg-blue-50 border-blue-300 text-blue-800',
    };
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-[100] max-w-sm w-full fade-in px-4 py-3 rounded-xl border shadow-lg flex items-center gap-3 ${colors[type] ?? colors.info}`;
    toast.innerHTML = `<span class="flex-1 text-sm font-medium">${message}</span>
        <button onclick="this.closest('div').remove()" class="text-gray-400 hover:text-gray-600">✕</button>`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.transition = 'opacity .4s'; toast.style.opacity = '0'; setTimeout(() => toast.remove(), 400); }, 5000);
}

// ── Confirm delete helper ─────────────────────────────────────
function confirmDelete(message, formId) {
    if (confirm(message ?? 'Are you sure you want to delete this item?')) {
        document.getElementById(formId)?.submit();
    }
}
</script>
