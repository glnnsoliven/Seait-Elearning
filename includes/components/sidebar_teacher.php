<?php
/**
 * Teacher Sidebar Navigation — SEAIT Design
 */
$base    = BASE_URL . '/modules/teacher';
$current = $_SERVER['PHP_SELF'] ?? '';

$nav = [
    ['href' => $base . '/dashboard.php',       'label' => 'Dashboard',      'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    ['href' => $base . '/courses.php',          'label' => 'My Subjects',    'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
    ['href' => $base . '/sections.php',         'label' => 'Blocks',         'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
    ['href' => $base . '/materials.php',        'label' => 'Materials',      'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
    ['href' => $base . '/quizzes.php',          'label' => 'Quizzes',        'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
    ['href' => $base . '/ai_quiz_generator.php','label' => 'AI Generator',   'icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'],
    ['href' => $base . '/students.php',         'label' => 'Students',       'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
    ['href' => $base . '/misconceptions.php',   'label' => 'Misconceptions', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
    ['href' => $base . '/analytics.php',        'label' => 'Analytics',      'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
    ['href' => $base . '/announcements.php',    'label' => 'Announcements',  'icon' => 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z'],
    ['href' => $base . '/reports.php',          'label' => 'Reports',        'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    ['href' => $base . '/profile.php',          'label' => 'My Profile',     'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
];
?>
<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden"></div>

<aside id="sidebar"
       class="fixed top-0 left-0 h-full w-64 bg-white border-r border-gray-200 z-50
              transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out
              flex flex-col sidebar-scroll overflow-y-auto shadow-lg">

    <!-- Logo / School Header -->
    <div class="flex flex-col items-center px-5 py-5 border-b border-gray-100 bg-white">
        <img src="<?= BASE_URL ?>/public/assets/images/seait-logo.png"
             alt="SEAIT Logo"
             class="w-20 h-20 rounded-full object-cover mb-2"
             style="background:white;">
        <div class="text-center">
            <p class="font-extrabold text-gray-900 text-sm leading-tight tracking-wide">SEAIT</p>
            <p class="text-orange-500 text-xs font-semibold leading-tight">South East Asian Institute</p>
            <p class="text-orange-500 text-xs font-semibold leading-tight">of Technology</p>
        </div>
        <div class="mt-2 px-3 py-0.5 bg-orange-500 rounded-full">
            <p class="text-white text-xs font-bold tracking-wide">Teacher Portal</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-3 space-y-0.5">
        <?php foreach ($nav as $item): ?>
        <?php $isActive = str_contains($current, basename($item['href'])); ?>
        <a href="<?= $item['href'] ?>"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all
                  <?= $isActive
                      ? 'bg-orange-500 text-white shadow-sm shadow-orange-200'
                      : 'text-gray-600 hover:bg-orange-50 hover:text-orange-600' ?>">
            <svg class="w-4.5 h-4.5 flex-shrink-0 <?= $isActive ? 'text-white' : 'text-gray-400' ?>"
                 style="width:1.1rem;height:1.1rem"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['icon'] ?>"/>
            </svg>
            <?= e($item['label']) ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Footer -->
    <div class="px-5 py-4 border-t border-gray-100">
        <p class="text-gray-400 text-xs text-center">SEAIT &copy; <?= date('Y') ?> &bull; Est. 2006</p>
    </div>
</aside>
