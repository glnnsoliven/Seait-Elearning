<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireRole('teacher');
$pageTitle = 'My Profile';
$teacher   = getTeacherProfile(Auth::id());
$tid       = (int)$teacher['id'];
$errors    = [];

// Fetch all active departments for the selector
$departments = Database::fetchAll("SELECT id, name FROM departments WHERE is_active=1 ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = postStr('action');

    // ── Update Profile ────────────────────────────────────────
    if ($action === 'update_profile') {
        $firstName  = postStr('first_name');
        $lastName   = postStr('last_name');
        $employeeId = postStr('employee_id');
        $spec       = postStr('specialization');
        $bio        = postStr('bio');
        $deptId     = postInt('department_id') ?: null;

        if (empty($firstName)) $errors[] = 'First name is required.';
        if (empty($lastName))  $errors[] = 'Last name is required.';

        if (empty($errors)) {
            Database::update('users', [
                'first_name' => $firstName,
                'last_name'  => $lastName,
            ], 'id=?', [Auth::id()]);

            Database::update('teachers', [
                'department_id'  => $deptId,
                'employee_id'    => $employeeId ?: null,
                'specialization' => $spec ?: null,
                'bio'            => $bio ?: null,
            ], 'id=?', [$tid]);

            // Handle avatar upload
            if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                try {
                    // Delete old avatar
                    if (!empty($teacher['avatar'])) {
                        $oldFile = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $teacher['avatar']);
                        if (file_exists($oldFile)) @unlink($oldFile);
                    }
                    $path = handleUpload($_FILES['avatar'], 'teachers', ALLOWED_AVATAR_TYPES);
                    Database::update('users', ['avatar' => $path], 'id=?', [Auth::id()]);
                    $_SESSION['user_avatar'] = $path;
                } catch (RuntimeException $ex) {
                    $errors[] = $ex->getMessage();
                }
            }

            if (empty($errors)) {
                $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                AuditLog::write(Auth::id(), 'update_profile', 'teachers', 'Teacher profile updated');
                flashSet('success', 'Profile updated successfully!');
                redirect($_SERVER['PHP_SELF']);
            }
        }
    }

    // ── Change Password ───────────────────────────────────────
    if ($action === 'change_password') {
        $cur  = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password']      ?? '';
        $conf = $_POST['confirm_password']  ?? '';
        $user = Database::fetchOne("SELECT password FROM users WHERE id=?", [Auth::id()]);

        if (!password_verify($cur, $user['password'])) $errors[] = 'Current password is incorrect.';
        if (strlen($new) < 8)                          $errors[] = 'New password must be at least 8 characters.';
        if ($new !== $conf)                            $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            Database::update('users', [
                'password' => password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])
            ], 'id=?', [Auth::id()]);
            flashSet('success', 'Password changed successfully!');
            redirect($_SERVER['PHP_SELF']);
        }
    }
}

// Re-fetch after possible update
$teacher = getTeacherProfile(Auth::id());
$avatar  = $teacher['avatar']
    ? fileUrl($teacher['avatar'])
    : 'https://ui-avatars.com/api/?name=' . urlencode($teacher['first_name'] . ' ' . $teacher['last_name']) . '&background=e67e22&color=fff&size=160';

// Stats
$totalStudents = Database::fetchOne(
    "SELECT COUNT(DISTINCT e.student_id) c FROM enrollments e
     JOIN courses c ON c.id=e.course_id WHERE c.teacher_id=? AND e.status='active'", [$tid])['c'];
$totalCourses  = Database::fetchOne(
    "SELECT COUNT(*) c FROM courses WHERE teacher_id=? AND is_active=1", [$tid])['c'];
$totalQuizzes  = Database::fetchOne(
    "SELECT COUNT(*) c FROM quizzes WHERE teacher_id=? AND is_published=1", [$tid])['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include __DIR__ . '/../../includes/components/head.php'; ?></head>
<body class="bg-gray-50 min-h-screen">
<?php include __DIR__ . '/../../includes/components/sidebar_teacher.php'; ?>
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

    <!-- ── Left: Avatar + Stats ─────────────────────────────── -->
    <div class="space-y-4">
        <!-- Avatar card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
            <div class="relative inline-block mb-4">
                <img src="<?= e($avatar) ?>" alt="Avatar"
                     class="w-28 h-28 rounded-full object-cover mx-auto border-4"
                     style="border-color:#e67e22"
                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($teacher['first_name'].' '.$teacher['last_name']) ?>&background=e67e22&color=fff&size=160'">
                <label for="avatarInput"
                       class="absolute bottom-0 right-0 w-8 h-8 bg-orange-500 hover:bg-orange-600 text-white rounded-full flex items-center justify-center cursor-pointer shadow-md transition"
                       title="Change photo">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </label>
            </div>

            <h3 class="text-lg font-bold text-gray-800"><?= e($teacher['first_name'] . ' ' . $teacher['last_name']) ?></h3>
            <p class="text-sm text-gray-500"><?= e($teacher['email']) ?></p>
            <?php if ($teacher['employee_id']): ?>
            <p class="text-xs font-mono text-orange-600 mt-1"><?= e($teacher['employee_id']) ?></p>
            <?php endif; ?>
            <?php if ($teacher['department_id']): ?>
            <?php $dept = Database::fetchOne("SELECT name FROM departments WHERE id=?", [$teacher['department_id']]); ?>
            <div class="mt-2 inline-flex items-center gap-1.5 px-3 py-1 bg-orange-50 border border-orange-100 rounded-full">
                <svg class="w-3 h-3 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/>
                </svg>
                <span class="text-xs font-medium text-orange-700"><?= e($dept['name'] ?? '') ?></span>
            </div>
            <?php endif; ?>
            <?php if ($teacher['specialization']): ?>
            <p class="text-xs text-gray-500 mt-2 bg-gray-50 rounded-xl px-3 py-2"><?= e($teacher['specialization']) ?></p>
            <?php endif; ?>
        </div>

        <!-- Stats -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">My Stats</p>
            <div class="space-y-3">
                <?php foreach ([
                    [$totalStudents, 'Students',  '#e67e22', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                    [$totalCourses,  'Subjects',  '#2563eb', 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13'],
                    [$totalQuizzes,  'Quizzes',   '#10b981', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
                ] as [$val, $lbl, $hex, $icon]): ?>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                         style="background:<?= $hex ?>20">
                        <svg class="w-4 h-4" style="color:<?= $hex ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-lg font-extrabold text-gray-800 leading-none"><?= $val ?></p>
                        <p class="text-xs text-gray-500"><?= $lbl ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── Right: Forms ─────────────────────────────────────── -->
    <div class="lg:col-span-2 space-y-5">

        <!-- Update Profile Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100" style="background:linear-gradient(135deg,#e67e22,#d35400)">
                <h3 class="font-bold text-white">Update Profile</h3>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="update_profile">
                <!-- Hidden file input triggered by avatar camera icon -->
                <input type="file" id="avatarInput" name="avatar" accept="image/*" class="hidden"
                       onchange="this.form.submit()">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name *</label>
                        <input type="text" name="first_name" value="<?= e($teacher['first_name']) ?>" required
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name *</label>
                        <input type="text" name="last_name" value="<?= e($teacher['last_name']) ?>" required
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Employee ID</label>
                        <input type="text" name="employee_id" value="<?= e($teacher['employee_id'] ?? '') ?>"
                               placeholder="EMP-001"
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-orange-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Department</label>
                        <select name="department_id"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 bg-white">
                            <option value="">— Select Department —</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"
                                <?= ($teacher['department_id'] ?? '') == $dept['id'] ? 'selected' : '' ?>>
                                <?= e($dept['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Departments are managed by the administrator.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Specialization</label>
                    <input type="text" name="specialization" value="<?= e($teacher['specialization'] ?? '') ?>"
                           placeholder="e.g. Networking, Database Systems, Web Development"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Bio</label>
                    <textarea name="bio" rows="3"
                              placeholder="Brief description about yourself…"
                              class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"><?= e($teacher['bio'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Profile Photo</label>
                    <label class="flex items-center gap-3 px-4 py-3 rounded-xl border border-dashed border-gray-300 cursor-pointer hover:border-orange-400 hover:bg-orange-50 transition">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <span id="photoLabel" class="text-sm text-gray-500">Click to choose a photo</span>
                        <input type="file" name="avatar" accept="image/*" class="hidden"
                               onchange="document.getElementById('photoLabel').textContent = this.files[0]?.name || 'Click to choose a photo'">
                    </label>
                </div>

                <button type="submit"
                        class="w-full py-3 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-xl text-sm transition shadow-sm">
                    Save Changes
                </button>
            </form>
        </div>

        <!-- Change Password Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-gray-800">Change Password</h3>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="change_password">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Current Password</label>
                    <input type="password" name="current_password" required
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                           placeholder="••••••••">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">New Password</label>
                        <input type="password" name="new_password" required minlength="8"
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                               placeholder="Min. 8 characters">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm New Password</label>
                        <input type="password" name="confirm_password" required
                               class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400"
                               placeholder="••••••••">
                    </div>
                </div>

                <button type="submit"
                        class="w-full py-3 bg-gray-800 hover:bg-gray-900 text-white font-semibold rounded-xl text-sm transition">
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
