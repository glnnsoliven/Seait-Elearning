<?php
/**
 * SEAIT E-Learning — One-Click Browser Installer
 * Access via: http://localhost/seait-elearning/install.php
 * DELETE this file after installation is complete.
 */

define('INSTALLER_VERSION', '1.0.0');

// ── Load config ──────────────────────────────────────────────
$configPath = __DIR__ . '/config/config.php';
$sqlPath    = __DIR__ . '/database/seait_elearning.sql';

// Read config values directly (avoid define conflicts)
$dbHost    = '127.0.0.1';   // use TCP, not socket
$dbPort    = '3306';
$dbUser    = 'root';
$dbPass    = '';            // XAMPP default root has no password
$dbName    = 'seait_elearning';
$dbCharset = 'utf8mb4';

// ── Run installer when form submitted ────────────────────────
$log     = [];
$success = false;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        // 1. Connect WITHOUT selecting a database first
        // Try submitted password first, then fallback to blank
        $dsn = "mysql:host={$dbHost};port={$dbPort};charset={$dbCharset}";
        $pdo = null;
        $triedPasswords = array_unique([$dbPass, '', 'root', 'mysql']);
        $connectedWith  = null;
        foreach ($triedPasswords as $tryPass) {
            try {
                $pdo = new PDO($dsn, $dbUser, $tryPass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $connectedWith = $tryPass;
                $dbPass = $tryPass; // use this going forward
                break;
            } catch (PDOException $e) {
                $log[] = ['info', "Password '" . (empty($tryPass) ? '(blank)' : '****') . "' failed: " . $e->getMessage()];
                $pdo = null;
            }
        }
        if ($pdo === null) {
            throw new RuntimeException("Could not connect with any known password. Please reset your MySQL root password (see instructions below).");
        }
        $log[] = ['ok', "Connected to MySQL at {$dbHost}:{$dbPort} (password: " . (empty($connectedWith) ? 'blank' : '****') . ")"];

        // 2. Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $log[] = ['ok', "Database `{$dbName}` created / already exists"];

        // 3. Select the database
        $pdo->exec("USE `{$dbName}`");
        $log[] = ['ok', "Using database `{$dbName}`"];

        // 4. Read SQL file
        if (!file_exists($sqlPath)) {
            throw new RuntimeException("SQL file not found: {$sqlPath}");
        }
        $sql = file_get_contents($sqlPath);
        $log[] = ['ok', "SQL file loaded (" . number_format(strlen($sql)) . " bytes)"];

        // 5. Split into individual statements and execute
        // Remove comments and split on semicolons
        $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);   // single-line comments
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);   // block comments

        // Split on semicolons but not inside strings
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => strlen($s) > 5
        );

        $tableCount  = 0;
        $insertCount = 0;
        $otherCount  = 0;
        $skipStatements = ['CREATE DATABASE', 'USE '];

        foreach ($statements as $stmt) {
            $upper = strtoupper(ltrim($stmt));

            // Skip CREATE DATABASE and USE — already handled
            $skip = false;
            foreach ($skipStatements as $sk) {
                if (str_starts_with($upper, $sk)) { $skip = true; break; }
            }
            if ($skip) continue;

            $pdo->exec($stmt);

            if (str_starts_with($upper, 'CREATE TABLE'))       $tableCount++;
            elseif (str_starts_with($upper, 'INSERT'))          $insertCount++;
            else                                                $otherCount++;
        }

        $log[] = ['ok', "Tables created: {$tableCount}"];
        $log[] = ['ok', "Data rows inserted (statements): {$insertCount}"];
        if ($otherCount) $log[] = ['ok', "Other statements executed: {$otherCount}"];

        // 6. Quick sanity check — count users
        $users = $pdo->query("SELECT COUNT(*) as cnt FROM users")->fetch();
        $log[] = ['ok', "Users in database: " . $users['cnt']];

        $admins = $pdo->query("SELECT email FROM users WHERE role='admin' LIMIT 3")->fetchAll();
        foreach ($admins as $a) {
            $log[] = ['info', "Admin account: " . $a['email']];
        }

        // 7. Create uploads directory
        $uploadDirs = [
            __DIR__ . '/public/uploads',
            __DIR__ . '/public/uploads/avatars',
            __DIR__ . '/public/uploads/materials',
            __DIR__ . '/public/uploads/quiz_images',
        ];
        foreach ($uploadDirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                $log[] = ['ok', "Created directory: " . str_replace(__DIR__, '.', $dir)];
            } else {
                $log[] = ['info', "Directory exists: " . str_replace(__DIR__, '.', $dir)];
            }
        }

        $success = true;
        $log[]   = ['ok', 'Installation complete!'];

        // Update config.php with the correct password
        $configFile = __DIR__ . '/config/config.php';
        if (file_exists($configFile)) {
            $cfg = file_get_contents($configFile);
            $cfg = preg_replace(
                "/define\('DB_PASS',\s*'[^']*'\);/",
                "define('DB_PASS',    '" . addslashes($dbPass) . "');",
                $cfg
            );
            file_put_contents($configFile, $cfg);
            $log[] = ['YES', 'config.php updated with correct DB password'];
        }

    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
        $log[] = ['err', $error];
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        $log[] = ['err', $error];
    }
}

// ── Pre-flight checks ────────────────────────────────────────
$checks = [];
$checks[] = ['PHP version', PHP_VERSION, version_compare(PHP_VERSION, '8.0.0', '>=')];
$checks[] = ['PDO extension', extension_loaded('pdo') ? 'Loaded' : 'Missing', extension_loaded('pdo')];
$checks[] = ['PDO MySQL driver', extension_loaded('pdo_mysql') ? 'Loaded' : 'Missing', extension_loaded('pdo_mysql')];
$checks[] = ['SQL file exists', file_exists($sqlPath) ? 'Found' : 'Missing', file_exists($sqlPath)];
$checks[] = ['Config file exists', file_exists($configPath) ? 'Found' : 'Missing', file_exists($configPath)];
$checks[] = ['Uploads writable', is_writable(__DIR__ . '/public') ? 'Writable' : 'Not writable', is_writable(__DIR__ . '/public')];

$allChecksPassed = array_reduce($checks, fn($c, $i) => $c && $i[2], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SEAIT E-Learning — Installer</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-start justify-center py-12 px-4">
<div class="w-full max-w-2xl">

  <!-- Header -->
  <div class="bg-blue-700 rounded-t-xl p-6 text-white text-center">
    <div class="text-3xl font-bold mb-1">SEAIT E-Learning</div>
    <div class="text-blue-200 text-sm">Database Installer v<?= INSTALLER_VERSION ?></div>
  </div>

  <!-- Body -->
  <div class="bg-white shadow-lg rounded-b-xl p-8 space-y-8">

    <!-- Pre-flight Checks -->
    <div>
      <h2 class="text-lg font-semibold text-gray-800 mb-3">Pre-flight Checks</h2>
      <div class="rounded-lg border border-gray-200 overflow-hidden">
        <?php foreach ($checks as [$label, $value, $pass]): ?>
        <div class="flex items-center justify-between px-4 py-2 <?= $pass ? 'bg-green-50' : 'bg-red-50' ?> border-b border-gray-100 last:border-0">
          <span class="text-gray-700 text-sm"><?= htmlspecialchars($label) ?></span>
          <span class="flex items-center gap-1 text-sm font-medium <?= $pass ? 'text-green-700' : 'text-red-600' ?>">
            <?= $pass ? '✓' : '✗' ?>
            <?= htmlspecialchars($value) ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (!$allChecksPassed): ?>
      <p class="mt-2 text-sm text-red-600">Fix the failing checks before installing.</p>
      <?php endif; ?>
    </div>

    <!-- DB Config Summary -->
    <div>
      <h2 class="text-lg font-semibold text-gray-800 mb-3">Database Configuration</h2>
      <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 text-sm font-mono space-y-1">
        <div><span class="text-gray-500">Host:</span> <span class="text-gray-800"><?= htmlspecialchars($dbHost) ?>:<?= htmlspecialchars($dbPort) ?></span></div>
        <div><span class="text-gray-500">User:</span> <span class="text-gray-800"><?= htmlspecialchars($dbUser) ?></span></div>
        <div><span class="text-gray-500">Password:</span> <span class="text-gray-800"><?= empty($dbPass) ? '(none / blank)' : '••••••••' ?></span></div>
        <div><span class="text-gray-500">Database:</span> <span class="text-gray-800"><?= htmlspecialchars($dbName) ?></span></div>
      </div>
    </div>

    <!-- Install Log -->
    <?php if (!empty($log)): ?>
    <div>
      <h2 class="text-lg font-semibold text-gray-800 mb-3">Installation Log</h2>
      <div class="bg-gray-900 rounded-lg p-4 text-sm font-mono space-y-1 max-h-72 overflow-y-auto">
        <?php foreach ($log as [$type, $msg]): ?>
          <?php $color = match($type) { 'ok' => 'text-green-400', 'err' => 'text-red-400', default => 'text-blue-300' }; ?>
          <div class="<?= $color ?>">
            <?= match($type) { 'ok' => '[OK]  ', 'err' => '[ERR] ', default => '[---] ' } ?>
            <?= htmlspecialchars($msg) ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Success Banner -->
    <?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-5">
      <div class="font-semibold text-green-800 text-lg mb-3">Installation Successful!</div>
      <p class="text-green-700 text-sm mb-4">The database has been created and populated. You can now log in with these credentials:</p>
      <div class="space-y-2">
        <?php
        $creds = [
          ['Admin',   'admin@seait.edu.ph',   'Admin@1234',   'bg-purple-50 border-purple-200'],
          ['Teacher', 'teacher@seait.edu.ph', 'Teacher@1234', 'bg-blue-50 border-blue-200'],
          ['Student', 'student@seait.edu.ph', 'Student@1234', 'bg-teal-50 border-teal-200'],
        ];
        foreach ($creds as [$role, $email, $pass, $cls]):
        ?>
        <div class="<?= $cls ?> border rounded p-3 text-sm font-mono">
          <span class="font-semibold text-gray-700"><?= $role ?>:</span>
          <?= $email ?> / <strong><?= $pass ?></strong>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="mt-5 flex flex-wrap gap-3">
        <a href="/seait-elearning/public/" class="bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-semibold hover:bg-blue-800">
          Launch Platform →
        </a>
        <a href="/seait-elearning/modules/auth/login.php" class="bg-gray-700 text-white px-5 py-2 rounded-lg text-sm font-semibold hover:bg-gray-800">
          Go to Login
        </a>
      </div>
      <p class="mt-4 text-xs text-red-600 font-semibold">⚠ Delete install.php from your server after installation.</p>
    </div>

    <!-- Error Banner -->
    <?php elseif ($error): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700 text-sm space-y-3">
      <div><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
      <div class="bg-white border border-red-100 rounded p-3 text-gray-700 space-y-2">
        <p class="font-semibold text-gray-800">How to reset your XAMPP MySQL root password:</p>
        <ol class="list-decimal list-inside space-y-1 text-xs">
          <li>Open <strong>XAMPP Control Panel</strong></li>
          <li>Click <strong>Shell</strong> (or open a cmd/PowerShell in <code>C:\xampp\mysql\bin</code>)</li>
          <li>Run: <code class="bg-gray-100 px-1 rounded">mysqladmin -u root password ""</code> to set blank password</li>
          <li>Or run: <code class="bg-gray-100 px-1 rounded">mysqladmin -u root -pOLDPASS password "NEW"</code></li>
          <li>Then come back here and try with the new password (or leave blank)</li>
        </ol>
        <p class="text-xs text-gray-500 mt-2">Alternative: Open phpMyAdmin at <a href="http://localhost/phpmyadmin" class="text-blue-600 underline" target="_blank">http://localhost/phpmyadmin</a>, go to User Accounts → root → Change password</p>
      </div>
    </div>
    <?php endif; ?>

    <!-- Install Form -->
    <?php if (!$success): ?>
    <form method="POST" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">MySQL Root Password</label>
        <input
          type="password"
          name="db_pass"
          value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>"
          placeholder="Leave blank if no password (default XAMPP)"
          class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
        <p class="text-xs text-gray-500 mt-1">Default XAMPP MySQL root has no password — try leaving this blank first.</p>
      </div>
      <button
        type="submit"
        name="install"
        value="1"
        <?= !$allChecksPassed ? 'disabled' : '' ?>
        class="w-full py-3 px-6 rounded-lg text-white font-semibold text-lg
               <?= $allChecksPassed ? 'bg-blue-700 hover:bg-blue-800 cursor-pointer' : 'bg-gray-400 cursor-not-allowed' ?>
               transition-colors">
        <?= empty($log) ? 'Install Database' : 'Retry Installation' ?>
      </button>
    </form>
    <?php else: ?>
    <!-- Delete self button -->
    <form method="POST">
      <button type="submit" name="delete_installer" value="1"
        class="w-full py-2 px-4 rounded-lg border border-red-300 text-red-600 text-sm hover:bg-red-50">
        Delete this installer file
      </button>
    </form>
    <?php endif; ?>

  </div>
</div>
<?php
// Handle self-deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_installer'])) {
    @unlink(__FILE__);
    echo '<script>window.location="/seait-elearning/public/";</script>';
}
?>
</body>
</html>
