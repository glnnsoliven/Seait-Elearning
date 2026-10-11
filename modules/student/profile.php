<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('student');
$student = getStudentProfile(Auth::id());
$sid     = (int)$student['id'];
$pageTitle='My Profile';
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action    = postStr('action');

    if ($action === 'update_profile') {
        $fn    = postStr('first_name');
        $ln    = postStr('last_name');
        $phone = postStr('phone');
        $addr  = postStr('address');
        $birth = postStr('birth_date');

        if (empty($fn) || empty($ln)) $errors[] = 'Name is required.';

        if (empty($errors)) {
            Database::update('users',['first_name'=>$fn,'last_name'=>$ln],'id=?',[Auth::id()]);
            Database::update('students',['phone'=>$phone,'address'=>$addr,'birth_date'=>$birth?:null],'id=?',[$sid]);

            // Handle avatar upload
            if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error']===UPLOAD_ERR_OK) {
                try {
                    // Save to students/ subfolder
                    $path = handleUpload($_FILES['avatar'], 'students', ALLOWED_AVATAR_TYPES);
                    // Delete old avatar file if it exists
                    if (!empty($student['avatar'])) {
                        $oldFile = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $student['avatar']);
                        if (file_exists($oldFile)) @unlink($oldFile);
                    }
                    Database::update('users', ['avatar' => $path], 'id=?', [Auth::id()]);
                    $_SESSION['user_avatar'] = $path;
                } catch (RuntimeException $ex) {
                    $errors[] = $ex->getMessage();
                }
            }
            if (empty($errors)) {
                $_SESSION['user_name'] = $fn.' '.$ln;
                AuditLog::write(Auth::id(),'update_profile','profile','Profile updated');
                flashSet('success','Profile updated!');
                redirect($_SERVER['PHP_SELF']);
            }
        }
    } elseif ($action === 'change_password') {
        $cur  = $_POST['current_password']  ?? '';
        $new  = $_POST['new_password']       ?? '';
        $conf = $_POST['confirm_password']   ?? '';
        $user = Database::fetchOne("SELECT password FROM users WHERE id=?",[Auth::id()]);

        if (!password_verify($cur, $user['password']))  $errors[] = 'Current password is incorrect.';
        if (strlen($new) < 8)                           $errors[] = 'New password must be at least 8 characters.';
        if ($new !== $conf)                             $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            Database::update('users',['password'=>password_hash($new,PASSWORD_BCRYPT,['cost'=>BCRYPT_COST])],'id=?',[Auth::id()]);
            flashSet('success','Password changed!');
            redirect($_SERVER['PHP_SELF']);
        }
    }
}

// Re-fetch after possible update
$student = getStudentProfile(Auth::id());
$avatar  = $student['avatar'] ? fileUrl($student['avatar']) : 'https://ui-avatars.com/api/?name='.urlencode($student['first_name'].' '.$student['last_name']).'&background=e67e22&color=fff&size=160';

// Stats
$quizCount  = Database::fetchOne("SELECT COUNT(*) c FROM quiz_attempts WHERE student_id=? AND status='completed'",[$sid])['c'];
$avgScore   = Database::fetchOne("SELECT ROUND(AVG(percentage),1) v FROM quiz_attempts WHERE student_id=? AND status='completed'",[$sid])['v'] ?? 0;
$masteryAvg = Database::fetchOne("SELECT ROUND(AVG(mastery_pct),1) v FROM student_competencies WHERE student_id=?",[$sid])['v'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/../../includes/components/head.php'; ?></head>
<body class="bg-gray-50 min-h-screen">
<?php include __DIR__ . '/../../includes/components/sidebar_student.php'; ?>
<div class="md:ml-64 flex flex-col min-h-screen">
<?php include __DIR__ . '/../../includes/components/topbar.php'; ?>
<main class="flex-1 p-4 md:p-6 fade-in">
<?php include __DIR__ . '/../../includes/components/flash.php'; ?>

<?php if (!empty($errors)): ?>
<div class="mb-5 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
    <?php foreach ($errors as $e): ?><p>• <?= e($e) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Profile Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
        <div class="relative inline-block mb-4">
            <img src="<?= e($avatar) ?>" class="w-24 h-24 rounded-2xl mx-auto object-cover ring-4 ring-blue-100" alt="">
        </div>
        <h3 class="font-bold text-gray-800 text-xl"><?= e($student['first_name'].' '.$student['last_name']) ?></h3>
        <p class="text-sm text-gray-500"><?= e($student['email']) ?></p>
        <p class="text-xs text-blue-600 mt-1"><?= e($student['student_id'] ?? 'No student ID') ?></p>

        <div class="grid grid-cols-3 gap-2 mt-4">
            <?php foreach ([[$quizCount,'Quizzes'],[$avgScore.'%','Avg Score'],[$masteryAvg.'%','Mastery']] as [$v,$l]): ?>
            <div class="bg-gray-50 rounded-xl py-2">
                <p class="text-base font-bold text-blue-600"><?= $v ?></p>
                <p class="text-xs text-gray-500"><?= $l ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Edit Forms -->
    <div class="lg:col-span-2 space-y-5">
        <!-- Profile Update -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Update Profile</h3>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="update_profile">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                        <input type="text" name="first_name" value="<?= e($student['first_name']) ?>" required
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                        <input type="text" name="last_name" value="<?= e($student['last_name']) ?>" required
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" value="<?= e($student['phone'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Birth Date</label>
                    <input type="date" name="birth_date" value="<?= e($student['birth_date'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea name="address" rows="2"
                              class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"><?= e($student['address'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Profile Photo</label>
                    <input type="file" name="avatar" accept="image/*"
                           class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition">
                    Save Changes
                </button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Change Password</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="change_password">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                    <input type="password" name="current_password" required
                           class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <input type="password" name="new_password" required minlength="8"
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New</label>
                        <input type="password" name="confirm_password" required
                               class="w-full px-3 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <button type="submit" class="px-6 py-2.5 bg-gray-800 text-white text-sm font-medium rounded-xl hover:bg-gray-900 transition">
                    Change Password
                </button>
            </form>
        </div>
    </div>
</div>

</main>
</div>
<?php include __DIR__ . '/../../includes/components/sidebar_js.php'; ?>
</body>
</html>
