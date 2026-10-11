<?php
/**
 * Reusable material card partial.
 * Expects $m = learning_materials row, $typeIcons defined in parent.
 */
$icon    = $typeIcons[$m['type']] ?? '📁';
$hasFile = !empty($m['file_path']);
$hasLink = !empty($m['external_url']);
?>
<div class="border border-gray-100 rounded-xl p-4 flex gap-3 hover:bg-gray-50 transition">
    <div class="text-2xl flex-shrink-0"><?= $icon ?></div>
    <div class="flex-1 min-w-0">
        <p class="font-medium text-sm text-gray-800"><?= e($m['title']) ?></p>
        <?php if ($m['content'] && $m['type'] === 'text'): ?>
        <div class="text-sm text-gray-600 mt-2 prose max-w-none">
            <?= nl2br_e(substr($m['content'], 0, 500)) ?>
            <?php if (strlen($m['content']) > 500): ?>
            <a href="<?= BASE_URL ?>/modules/student/learning_material.php?id=<?= $m['id'] ?>" class="text-blue-600 hover:underline text-xs ml-1">Read more</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="flex items-center gap-3 mt-2">
            <?php if ($hasFile): ?>
            <a href="<?= fileUrl($m['file_path']) ?>"
               target="_blank"
               class="text-xs px-3 py-1 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition inline-flex items-center gap-1">
                <?= $m['is_downloadable'] ? '⬇ Download' : '👁 View' ?>
            </a>
            <?php endif; ?>
            <?php if ($hasLink): ?>
            <a href="<?= e($m['external_url']) ?>"
               target="_blank" rel="noopener"
               class="text-xs px-3 py-1 bg-orange-50 text-orange-600 rounded-lg hover:bg-orange-100 transition inline-flex items-center gap-1">
                🔗 Open Link
            </a>
            <?php endif; ?>
            <?php if ($m['type'] !== 'text' && !$hasFile && !$hasLink): ?>
            <a href="<?= BASE_URL ?>/modules/student/learning_material.php?id=<?= $m['id'] ?>"
               class="text-xs px-3 py-1 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition">View</a>
            <?php endif; ?>
            <?php if ($m['file_size']): ?>
            <span class="text-xs text-gray-400"><?= round($m['file_size']/1024) ?> KB</span>
            <?php endif; ?>
        </div>
    </div>
</div>
