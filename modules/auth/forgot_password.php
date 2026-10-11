<?php
require_once __DIR__ . '/../../core/bootstrap.php';

if (Auth::check()) {
    redirect(BASE_URL . '/modules/' . Auth::role() . '/dashboard.php');
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $email = postStr('email');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $token = Auth::createPasswordResetToken($email);
        // In a real app, send an email with the reset link
        // For now, show the link directly in dev mode
        $success = true;
        $resetLink = BASE_URL . '/modules/auth/reset_password.php?token=' . ($token ?? 'invalid');
        AuditLog::write(null, 'password_reset_request', 'auth', "Reset requested for: $email");
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <?php $pageTitle = 'Forgot Password'; include __DIR__ . '/../../includes/components/head.php'; ?>
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
            <h1 class="text-2xl font-bold text-white">Forgot Password</h1>
            <p class="text-orange-200 text-sm mt-1">We'll send you a reset link</p>
        </div>

        <div class="px-8 py-8">

            <?php if ($success): ?>
            <div class="bg-green-50 border border-green-300 rounded-xl px-4 py-4 mb-6">
                <p class="text-green-700 text-sm font-medium">
                    If an account exists for that email, a reset link has been sent.
                </p>
                <?php if (APP_DEBUG && isset($resetLink)): ?>
                <p class="text-xs text-gray-500 mt-2 break-all">
                    <strong>Dev mode:</strong> <a href="<?= e($resetLink) ?>" class="text-orange-600 underline"><?= e($resetLink) ?></a>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="mb-5 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
                <?php foreach ($errors as $err): ?><p>• <?= e($err) ?></p><?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" action="" novalidate>
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">

                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address</label>
                    <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"
                           placeholder="you@seait.edu.ph"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent"
                           required autofocus>
                </div>

                <button type="submit"
                        class="w-full py-3 bg-gradient-to-r from-orange-500 to-blue-600 text-white font-semibold rounded-xl hover:from-orange-600 hover:to-blue-700 transition shadow-md shadow-indigo-200">
                    Send Reset Link
                </button>
            </form>
            <?php endif; ?>

            <p class="text-center text-sm text-gray-500 mt-6">
                <a href="<?= BASE_URL ?>/modules/auth/login.php" class="text-orange-600 font-medium hover:underline">
                    ← Back to sign in
                </a>
            </p>
        </div>
    </div>
</div>
</body>
</html>
