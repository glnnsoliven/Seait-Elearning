<?php
/**
 * HTML <head> component
 * Variables expected (set before including):
 *   $pageTitle  — used in <title>
 *   $extraHead  — optional extra <link> or <meta> tags
 */
$pageTitle = $pageTitle ?? 'SEAIT E-Learning';
$siteTitle = APP_NAME;
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(Auth::csrfToken()) ?>">
<title><?= e($pageTitle) ?> — <?= e($siteTitle) ?></title>

<!-- Tailwind CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                brand: {
                    50:  '#eef2ff',
                    100: '#e0e7ff',
                    500: '#e67e22',
                    600: '#e67e22',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#312e81',
                }
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', 'sans-serif'],
            }
        }
    }
}
</script>

<!-- Google Fonts: Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- PDF.js — client-side PDF text extraction -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc =
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }
</script>

<!-- Custom base styles (minimal, Tailwind-compatible) -->
<style>
    body { font-family: 'Inter', system-ui, sans-serif; }
    .sidebar-scroll::-webkit-scrollbar { width: 4px; }
    .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
    .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 2px; }
    .fade-in { animation: fadeIn .25s ease-in-out; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }
    .progress-ring__circle { transition: stroke-dashoffset 0.6s ease; transform-origin: 50% 50%; transform: rotate(-90deg); }
    [x-cloak] { display: none !important; }
</style>

<?= $extraHead ?? '' ?>
