<?php
require_once __DIR__ . '/../../core/bootstrap.php';

// Redirect if already logged in
if (Auth::check()) {
    redirect(BASE_URL . '/modules/' . Auth::role() . '/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $email    = postStr('email');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if (empty($email) || empty($password)) {
        $errors[] = 'Email and password are required.';
    } else {
        $result = Auth::attempt($email, $password);
        if ($result === true) {
            if ($remember) {
                Auth::setRememberToken(Auth::id());
            }
            redirect(BASE_URL . '/modules/' . Auth::role() . '/dashboard.php');
        } else {
            $errors[] = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <?php $pageTitle = 'Sign In'; include __DIR__ . '/../../includes/components/head.php'; ?>
</head>
<body class="h-full flex items-center justify-center p-4"
      style="background-image: url('<?= BASE_URL ?>/public/assets/images/seait-building.jpg');
             background-size: cover;
             background-position: center 30%;
             background-repeat: no-repeat;
             background-attachment: fixed;
             min-height: 100vh;">

<!-- Warm dark overlay matching the building's orange/brick tones -->
<div class="fixed inset-0" style="background: linear-gradient(135deg, rgba(180,60,20,0.55) 0%, rgba(0,0,0,0.60) 60%, rgba(20,20,40,0.70) 100%); z-index:0"></div>

<div class="w-full max-w-md relative" style="z-index:1">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">

        <!-- Header — white with logo centered -->
        <div class="px-8 pt-8 pb-6 text-center border-b border-gray-100">
            <div class="flex justify-center mb-3">
                <img src="<?= BASE_URL ?>/public/assets/images/seait-logo.png"
                     alt="SEAIT Logo"
                     class="w-24 h-24 rounded-full object-cover"
                     style="background:white;">
            </div>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-widest">SEAIT</h1>
            <p class="text-xs font-semibold mt-0.5" style="color:#e67e22">South East Asian Institute of Technology</p>
            <p class="text-gray-500 text-sm mt-1.5">Sign in to your E-Learning account</p>
        </div>

        <!-- Form -->
        <div class="px-8 py-7">

            <?php if (!empty($errors)): ?>
            <div class="mb-5 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
                <?php foreach ($errors as $err): ?>
                <p>• <?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">

                <!-- Email -->
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email Address</label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="<?= e($_POST['email'] ?? '') ?>"
                           placeholder="you@seait.edu.ph"
                           required
                           autocomplete="email"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:border-transparent transition"
                           style="--tw-ring-color:#e67e22" onfocus="this.style.borderColor='#e67e22';this.style.boxShadow='0 0 0 3px rgba(230,126,34,0.15)'" onblur="this.style.borderColor='';this.style.boxShadow=''">
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <a href="<?= BASE_URL ?>/modules/auth/forgot_password.php"
                           class="text-xs font-medium hover:underline" style="color:#e67e22">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <input type="password"
                               id="password"
                               name="password"
                               placeholder="••••••••"
                               required
                               autocomplete="current-password"
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none transition pr-10"
                               onfocus="this.style.borderColor='#e67e22';this.style.boxShadow='0 0 0 3px rgba(230,126,34,0.15)'" onblur="this.style.borderColor='';this.style.boxShadow=''">
                        <button type="button"
                                onclick="togglePassword()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                aria-label="Toggle password visibility">
                            <svg id="eye-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center mb-6">
                    <input type="checkbox" id="remember" name="remember"
                           class="w-4 h-4 rounded border-gray-300"
                           style="accent-color:#e67e22">
                    <label for="remember" class="ml-2 text-sm text-gray-600">Remember me for <?= REMEMBER_DAYS ?> days</label>
                </div>

                <!-- Submit -->
                <button type="submit"
                        class="w-full py-3 text-white font-bold rounded-xl transition text-sm shadow-md hover:opacity-90 focus:outline-none"
                        style="background:#e67e22;box-shadow:0 4px 14px rgba(230,126,34,0.35)">
                    Sign In
                </button>
            </form>

            <!-- Register link -->
            <p class="text-center text-sm text-gray-500 mt-5">
                Don't have an account?
                <a href="<?= BASE_URL ?>/modules/auth/register.php"
                   class="font-semibold hover:underline" style="color:#e67e22">Register here</a>
            </p>
        </div>
    </div>

    <p class="text-center text-xs mt-4" style="color:rgba(255,255,255,0.7)">
        &copy; <?= date('Y') ?> South East Asian Institute of Technology &bull; Est. 2006
    </p>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>

