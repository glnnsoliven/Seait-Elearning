<?php
require_once __DIR__ . '/../../core/bootstrap.php';

if (Auth::check()) {
    redirect(BASE_URL . '/modules/' . Auth::role() . '/dashboard.php');
}

// Check if open registration is allowed
$settings = Database::fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'allow_register'");
if (($settings['setting_value'] ?? '0') !== '1') {
    flashSet('error', 'Self-registration is currently disabled. Please contact your administrator.');
    redirect(BASE_URL . '/modules/auth/login.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $firstName  = postStr('first_name');
    $middleName = postStr('middle_name');
    $lastName   = postStr('last_name');
    $studentNum = postStr('student_id');
    $email      = postStr('email');
    $username   = postStr('username');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['password_confirm'] ?? '';
    $role       = 'student';
    $deptId     = 1; // Always BSIT

    // Validation
    if (empty($firstName))              $errors[] = 'First name is required.';
    if (empty($lastName))               $errors[] = 'Last name is required.';
    if (empty($studentNum))             $errors[] = 'Student ID number is required.';
    if (!preg_match('/^\d{4}-\d{4}$/', $studentNum)) {
        $errors[] = 'Student ID must follow the format YYYY-NNNN (e.g. 2025-0001).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($username) < 3)          $errors[] = 'Username must be at least 3 characters.';
    if (strlen($password) < 8)          $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)         $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        if (Database::fetchOne("SELECT id FROM users WHERE email = ?", [$email])) {
            $errors[] = 'This email address is already registered.';
        }
        if (Database::fetchOne("SELECT id FROM users WHERE username = ?", [$username])) {
            $errors[] = 'This username is already taken.';
        }
        if (Database::fetchOne("SELECT id FROM students WHERE student_id = ?", [$studentNum])) {
            $errors[] = 'This Student ID is already registered.';
        }
    }

    if (empty($errors)) {
        Database::beginTransaction();
        try {
            $hash   = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $userId = Database::insert('users', [
                'username'   => $username,
                'email'      => $email,
                'password'   => $hash,
                'role'       => $role,
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'is_active'  => 1,
            ]);

            Database::insert('students', [
                'user_id'       => $userId,
                'department_id' => $deptId,
                'student_id'    => $studentNum,
            ]);

            Database::commit();
            AuditLog::write($userId, 'register', 'auth', 'New student registered: ' . $email);

            flashSet('success', 'Account created! You can now sign in.');
            redirect(BASE_URL . '/modules/auth/login.php');

        } catch (Throwable $e) {
            Database::rollback();
            $errors[] = 'Registration failed. Please try again.';
            if (APP_DEBUG) $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <?php $pageTitle = 'Register'; include __DIR__ . '/../../includes/components/head.php'; ?>
</head>
<body class="min-h-full flex items-center justify-center p-4"
      style="background-image: url('<?= BASE_URL ?>/public/assets/images/seait-building.jpg');
             background-size: cover;
             background-position: center 30%;
             background-repeat: no-repeat;
             background-attachment: fixed;
             min-height: 100vh;">

<div class="fixed inset-0" style="background: linear-gradient(135deg, rgba(180,60,20,0.55) 0%, rgba(0,0,0,0.60) 60%, rgba(20,20,40,0.70) 100%); z-index:0"></div>

<div class="w-full max-w-lg my-8 relative" style="z-index:1">
    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">

        <div class="bg-gradient-to-r from-orange-500 to-blue-600 px-8 py-6 text-center">
            <h1 class="text-2xl font-bold text-white">Create Account</h1>
            <p class="text-orange-200 text-sm mt-1">SEAIT E-Learning · BSIT Program</p>
        </div>

        <div class="px-8 py-8">

            <?php if (!empty($errors)): ?>
            <div class="mb-5 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
                <?php foreach ($errors as $err): ?>
                <p>• <?= e($err) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">

                <!-- Student ID -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Student ID Number *</label>
                    <input type="text" name="student_id" value="<?= e($_POST['student_id'] ?? '') ?>"
                           placeholder="e.g. 2025-0001"
                           maxlength="9"
                           oninput="formatStudentId(this)"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent" required>
                    <p class="text-xs text-gray-400 mt-1">Format: YYYY-NNNN (e.g. 2025-0001)</p>
                </div>

                <!-- Full Name -->
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name *</label>
                        <input type="text" name="first_name" value="<?= e($_POST['first_name'] ?? '') ?>"
                               placeholder="Juan"
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Middle Name</label>
                        <input type="text" name="middle_name" value="<?= e($_POST['middle_name'] ?? '') ?>"
                               placeholder="Santos"
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name *</label>
                        <input type="text" name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>"
                               placeholder="Dela Cruz"
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent" required>
                    </div>
                </div>

                <!-- Program (read-only) -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Program</label>
                    <div class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-600">
                        BSIT — Bachelor of Science in Information Technology
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address *</label>
                    <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"
                           placeholder="you@seait.edu.ph"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent" required>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Username *</label>
                    <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>"
                           placeholder="jdelacruz"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent" required>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Password *</label>
                        <input type="password" name="password" placeholder="••••••••"
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent" required>
                        <p class="text-xs text-gray-400 mt-1">Min. 8 characters</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password *</label>
                        <input type="password" name="password_confirm" placeholder="••••••••"
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-transparent" required>
                    </div>
                </div>

                <button type="submit"
                        class="w-full py-3 bg-gradient-to-r from-orange-500 to-blue-600 text-white font-semibold rounded-xl hover:from-orange-600 hover:to-blue-700 transition shadow-md shadow-indigo-200">
                    Create Account
                </button>
            </form>

            <p class="text-center text-sm text-gray-500 mt-6">
                Already have an account?
                <a href="<?= BASE_URL ?>/modules/auth/login.php" class="text-orange-600 font-medium hover:underline">Sign in</a>
            </p>
        </div>
    </div>
</div>
<script>
function formatStudentId(input) {
    // Auto-insert dash after 4 digits
    let val = input.value.replace(/[^0-9]/g, '');
    if (val.length > 4) {
        val = val.substring(0, 4) + '-' + val.substring(4, 8);
    }
    input.value = val;
}
</script>
</body>
</html>
