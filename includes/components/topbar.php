<?php
/**
 * Top navigation bar (used in all dashboards)
 * Expects: $pageTitle
 */
$userId       = Auth::id();
$userName     = $_SESSION['user_name']  ?? 'User';
$userRole     = Auth::role();
$userAvatar   = $_SESSION['user_avatar'] ?? null;
$notifCount   = $userId ? unreadNotifCount($userId) : 0;

$avatarSrc = $userAvatar
    ? fileUrl($userAvatar)
    : 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=e67e22&color=fff&size=80';

$notifLink = match ($userRole) {
    'admin'   => BASE_URL . '/modules/admin/notifications.php',
    'teacher' => BASE_URL . '/modules/teacher/notifications.php',
    default   => BASE_URL . '/modules/student/notifications.php',
};
?>
<header class="bg-white border-b border-gray-200 sticky top-0 z-30">
    <div class="flex items-center justify-between h-16 px-4 md:px-6">

        <!-- Left: Sidebar toggle (mobile) + Page title -->
        <div class="flex items-center gap-3">
            <button id="sidebar-toggle"
                    aria-label="Toggle sidebar"
                    class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 md:hidden">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <h1 class="text-base font-semibold text-gray-800 hidden sm:block"><?= e($pageTitle ?? 'Dashboard') ?></h1>
        </div>

        <!-- Right: Notifications + User menu -->
        <div class="flex items-center gap-2 md:gap-4">

            <!-- Notification Bell -->
            <div class="relative">
                <a href="<?= $notifLink ?>"
                   class="relative p-2 rounded-lg text-gray-500 hover:bg-gray-100 flex items-center"
                   aria-label="Notifications">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <?php if ($notifCount > 0): ?>
                    <span id="notif-badge"
                          class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-4 h-4 flex items-center justify-center">
                        <?= $notifCount > 9 ? '9+' : $notifCount ?>
                    </span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- User dropdown -->
            <div class="relative" id="user-menu-wrapper">
                <button id="user-menu-btn"
                        class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-gray-100 transition"
                        aria-expanded="false"
                        aria-haspopup="true">
                    <img src="<?= e($avatarSrc) ?>"
                         alt="<?= e($userName) ?>"
                         class="w-9 h-9 rounded-full object-cover ring-2"
                         style="ring-color:#e67e22"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($userName) ?>&background=e67e22&color=fff&size=80'">
                    <span class="text-sm font-medium text-gray-700 hidden md:block max-w-[120px] truncate">
                        <?= e($userName) ?>
                    </span>
                    <svg class="w-4 h-4 text-gray-400 hidden md:block" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>

                <!-- Dropdown -->
                <div id="user-menu-dropdown"
                     class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50">
                    <div class="px-4 py-2 border-b border-gray-100">
                        <p class="text-sm font-semibold text-gray-800 truncate"><?= e($userName) ?></p>
                        <p class="text-xs text-gray-400 capitalize"><?= e($userRole) ?></p>
                    </div>
                    <?php if ($userRole === 'student'): ?>
                    <a href="<?= BASE_URL ?>/modules/student/profile.php"
                       class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        My Profile
                    </a>
                    <?php elseif ($userRole === 'teacher'): ?>
                    <a href="<?= BASE_URL ?>/modules/teacher/profile.php"
                       class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        My Profile
                    </a>
                    <?php endif; ?>                    <a href="<?= BASE_URL ?>/modules/auth/logout.php"
                       class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Sign Out
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
