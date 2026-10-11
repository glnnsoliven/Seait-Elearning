<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('admin');
$pageTitle = 'System Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    if ($action === 'save_settings') {
        $keys = ['site_name','site_email','allow_register','ai_quiz_enabled','smtp_host','smtp_port','smtp_user','maintenance_mode','max_upload_mb'];
        foreach ($keys as $k) {
            $val = postStr($k);
            Database::update('system_settings', ['setting_value' => $val], 'setting_key=?', [$k]);
        }
        AuditLog::write(Auth::id(), 'update_settings', 'settings', 'System settings updated');
        flashSet('success', 'Settings saved successfully.');
    }
    redirect($_SERVER['PHP_SELF']);
}

$settings = [];
$rows = Database::fetchAll("SELECT setting_key, setting_value FROM system_settings");
foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];

// Audit logs for display
$page     = getInt('page', 1);
$total    = Database::fetchOne("SELECT COUNT(*) c FROM audit_logs")['c'];
$pag      = paginate($total, $page, 15);
$auditLogs = Database::fetchAll(
    "SELECT al.*, CONCAT(u.first_name,' ',u.last_name) AS user_name
     FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id
     ORDER BY al.created_at DESC LIMIT {$pag['per_page']} OFFSET {$pag['offset']}"
);
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/../../includes/components/head.php'; ?></head>
<body class="bg-gray-50 min-h-screen">
<?php include __DIR__ . '/../../includes/components/sidebar_admin.php'; ?>
<div class="md:ml-64 flex flex-col min-h-screen">
<?php include __DIR__ . '/../../includes/components/topbar.php'; ?>
<main class="flex-1 p-4 md:p-6 fade-in">
<?php include __DIR__ . '/../../includes/components/flash.php'; ?>

<div class="mb-6">
    <h2 class="text-xl font-bold text-gray-800">System Settings</h2>
</div>

<!-- Settings Form -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
    <form method="POST">
        <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="save_settings">

        <h3 class="font-semibold text-gray-800 mb-4 pb-3 border-b border-gray-100">General</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Site Name</label>
                <input type="text" name="site_name" value="<?= e($settings['site_name'] ?? '') ?>"
                       class="w-full px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">System Email</label>
                <input type="email" name="site_email" value="<?= e($settings['site_email'] ?? '') ?>"
                       class="w-full px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Upload (MB)</label>
                <input type="number" name="max_upload_mb" value="<?= e($settings['max_upload_mb'] ?? '50') ?>" min="1" max="500"
                       class="w-full px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
        </div>

        <h3 class="font-semibold text-gray-800 mb-4 pb-3 border-b border-gray-100">Feature Flags</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <?php foreach ([
                ['key'=>'allow_register',   'label'=>'Open Registration'],
                ['key'=>'ai_quiz_enabled',  'label'=>'AI Quiz Generator'],
                ['key'=>'maintenance_mode', 'label'=>'Maintenance Mode'],
            ] as $flag): ?>
            <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50">
                <input type="hidden" name="<?= $flag['key'] ?>" value="0">
                <input type="checkbox" name="<?= $flag['key'] ?>" value="1"
                       <?= ($settings[$flag['key']] ?? '0') === '1' ? 'checked' : '' ?>
                       class="w-4 h-4 rounded border-gray-300 text-orange-600 focus:ring-orange-400">
                <span class="text-sm font-medium text-gray-700"><?= $flag['label'] ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <h3 class="font-semibold text-gray-800 mb-4 pb-3 border-b border-gray-100">Email (SMTP)</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">SMTP Host</label>
                <input type="text" name="smtp_host" value="<?= e($settings['smtp_host'] ?? '') ?>"
                       class="w-full px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">SMTP Port</label>
                <input type="text" name="smtp_port" value="<?= e($settings['smtp_port'] ?? '587') ?>"
                       class="w-full px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">SMTP Username</label>
                <input type="text" name="smtp_user" value="<?= e($settings['smtp_user'] ?? '') ?>"
                       class="w-full px-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
            </div>
        </div>

        <button type="submit" class="px-6 py-2.5 bg-orange-500 text-white font-medium text-sm rounded-xl hover:bg-orange-700 transition">
            Save Settings
        </button>
    </form>
</div>

<!-- Audit Log -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100" id="audit">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Audit Log</h3>
        <p class="text-xs text-gray-500 mt-0.5"><?= $total ?> total entries</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">User</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Action</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Module</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden lg:table-cell">Description</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
            <?php foreach ($auditLogs as $log): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium text-gray-800"><?= e($log['user_name'] ?? 'System') ?></td>
                <td class="px-5 py-3"><span class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded text-xs"><?= e($log['action']) ?></span></td>
                <td class="px-5 py-3 text-gray-600 hidden md:table-cell"><?= e($log['module']) ?></td>
                <td class="px-5 py-3 text-gray-500 text-xs hidden lg:table-cell max-w-xs truncate"><?= e($log['description']) ?></td>
                <td class="px-5 py-3 text-gray-500 text-xs"><?= timeAgo($log['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['total_pages'] > 1): ?>
    <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100">
        <p class="text-xs text-gray-500">Showing <?= $pag['offset']+1 ?>–<?= min($pag['offset']+$pag['per_page'],$total) ?> of <?= $total ?></p>
        <div class="flex gap-1">
            <?php for ($p=1;$p<=$pag['total_pages'];$p++): ?>
            <a href="?page=<?=$p?>#audit" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm <?= $p===$pag['current']?'bg-orange-500 text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?=$p?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
