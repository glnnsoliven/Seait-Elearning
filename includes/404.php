<?php require_once __DIR__ . '/../core/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found — SEAIT</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="text-center">
        <div class="text-9xl font-black text-orange-200">404</div>
        <h1 class="text-3xl font-bold text-gray-800 mt-4">Page Not Found</h1>
        <p class="text-gray-500 mt-2">The page you're looking for doesn't exist.</p>
        <a href="<?= BASE_URL ?>"
           class="mt-6 inline-block px-6 py-3 bg-orange-500 text-white rounded-xl hover:bg-orange-700 transition">
            Go Home
        </a>
    </div>
</body>
</html>
