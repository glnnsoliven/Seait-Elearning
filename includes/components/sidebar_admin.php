<?php
/**
 * Admin Sidebar Navigation — SEAIT Design
 */
$adminBase = BASE_URL . '/modules/admin';
$current   = $_SERVER['PHP_SELF'] ?? '';

$nav = [
    ['href' => $adminBase . '/dashboard.php',    'label' => 'Dashboard',     'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    ['href' => $adminBase . '/users.php',         'label' => 'Users',         'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
    ['href' => $adminBase . '/students.php',      'label' => 'Students',      'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
    ['href' => $adminBase . '/teachers.php',      'label' => 'Teachers',      'icon' => 'M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['href' => $adminBase . '/departments.php',   'label' => 'Departments',   'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
    ['href' => $adminBase . '/courses.php',       'label' => 'Subjects',      'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
    ['href' => $adminBase . '/analytics.php',     'label' => 'Analytics',     'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
    ['href' => $adminBase . '/reports.php',       'label' => 'Reports',        'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    ['href' => $adminBase . '/announcements.php', 'label' => 'Announcements',  'icon' => 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z'],
    ['href' => $adminBase . '/notifications.php', 'label' => 'Notifications',  'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
    ['href' => $adminBase . '/settings.php',      'label' => 'Settings',      'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
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
            <p class="text-white text-xs font-bold tracking-wide">Admin Portal</p>
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
            <svg style="width:1.1rem;height:1.1rem"
                 class="flex-shrink-0 <?= $isActive ? 'text-white' : 'text-gray-400' ?>"
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
