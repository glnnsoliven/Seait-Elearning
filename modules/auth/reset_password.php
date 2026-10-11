<?php
require_once __DIR__ . '/../../core/bootstrap.php';

$token  = getStr('token');
$record = $token ? Auth::validateResetToken($token) : null;
$errors = [];
$done   = false;

if (!$record && !$done) {
    // Invalid or expired token
    flashSet('error', 'This reset link is invalid or has expired. Please request a new one.');
    redirect(BASE_URL . '/modules/auth/forgot_password.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        if (Auth::resetPassword($token, $password)) {
            $done = true;
            AuditLog::write(null, 'password_reset', 'auth', 'Password reset for: ' . $record['email']);
        } else {
            $errors[] = 'Reset failed. Please request a new link.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <?php $pageTitle = 'Reset Password'; include __DIR__ . '/../../includes/components/head.php'; ?>
</head>
<body class="h-full flex items-center justify-center p-4"
      style="background-image: url('<?= BASE_URL ?>/public/assets/images/seait-building.jpg');
             background-size: cover;
             background-position: center 30%;
             background-repeat: no-repeat;
             background-attachment: fixed;
             min-height: 100vh;">

<div class="fixed inset-0" style="background: linear-gradient(135deg, rgba(180,60,20,0.55) 0%, rgba(0,0,0,0.60) 60%, rgba(20,20,40,0.70) 100%); z-index:0"></div>

<div class="w-full max-w-md relative" style="z-index:1">
    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">

        <div class="bg-gradient-to-r from-orange-500 to-blue-600 px-8 py-6 text-center">
            <h1 class="text-2xl font-bold text-white">Reset Password</h1>
            <p class="text-orange-200 text-sm mt-1">Create your new password</p>
        </div>

        <div class="px-8 py-8">

            <?php if ($done): ?>
            <div class="text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <p class="text-gray-700 font-medium mb-4">Password reset successfully!</p>
                <a href="<?= BASE_URL ?>/modules/auth/login.php"
                   class="inline-block px-6 py-3 bg-orange-500 text-white rounded-xl hover:bg-orange-700 transition font-medium">
                    Sign In
                </a>
            </div>
            <?php else: ?>

            <?php if (!empty($errors)): ?>
            <div class="mb-5 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
                <?php foreach ($errors as $err): ?><p>• <?= e($err) ?></p><?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">New Password</label>
                    <input type="password" name="password" placeholder="••••••••"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                           required autofocus>
                    <p class="text-xs text-gray-400 mt-1">Min. 8 characters</p>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
                    <input type="password" name="password_confirm" placeholder="••••••••"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                           required>
                </div>

                <button type="submit"
                        class="w-full py-3 bg-gradient-to-r from-orange-500 to-blue-600 text-white font-semibold rounded-xl hover:from-orange-600 hover:to-blue-700 transition shadow-md shadow-indigo-200">
                    Reset Password
                </button>
            </form>

            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
