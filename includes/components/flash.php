<?php
/**
 * Flash message toast display component
 * Include once near the top of any page body.
 */
$flashTypes = [
    'success' => ['bg' => 'bg-green-50 border-green-300', 'text' => 'text-green-800', 'icon' => '✓', 'icon_bg' => 'bg-green-100 text-green-600'],
    'error'   => ['bg' => 'bg-red-50 border-red-300',   'text' => 'text-red-800',   'icon' => '✕', 'icon_bg' => 'bg-red-100 text-red-600'],
    'warning' => ['bg' => 'bg-yellow-50 border-yellow-300', 'text' => 'text-yellow-800', 'icon' => '!', 'icon_bg' => 'bg-yellow-100 text-yellow-600'],
    'info'    => ['bg' => 'bg-blue-50 border-blue-300',  'text' => 'text-blue-800',  'icon' => 'i', 'icon_bg' => 'bg-blue-100 text-blue-600'],
];

foreach ($flashTypes as $type => $styles):
    $messages = flashGet($type);
    foreach ($messages as $msg):
?>
<div class="flash-toast fixed top-4 right-4 z-[100] max-w-sm w-full fade-in"
     role="alert"
     aria-live="polite">
    <div class="flex items-start gap-3 px-4 py-3 rounded-xl border <?= $styles['bg'] ?> shadow-lg">
        <span class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-sm font-bold <?= $styles['icon_bg'] ?>">
            <?= $styles['icon'] ?>
        </span>
        <p class="text-sm font-medium <?= $styles['text'] ?> flex-1"><?= e($msg) ?></p>
        <button onclick="this.closest('.flash-toast').remove()"
                class="flex-shrink-0 text-gray-400 hover:text-gray-600 ml-2"
                aria-label="Dismiss">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>
    </div>
</div>
<?php
    endforeach;
endforeach;
?>
<script>
// Auto-dismiss flash toasts after 5 seconds
document.querySelectorAll('.flash-toast').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity 0.4s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
    }, 5000);
});
</script>
