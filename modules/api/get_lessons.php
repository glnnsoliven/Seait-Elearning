<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireLogin();

$courseId = getInt('course_id');
if (!$courseId) jsonResponse([]);

// Teachers see all lessons (published + draft); students only see published
$showAll = isset($_GET['all']) && $_GET['all'] === '1' && Auth::hasAny(['teacher','admin']);

if ($showAll) {
    $lessons = Database::fetchAll(
        "SELECT id, title, is_published FROM lessons WHERE course_id=? ORDER BY sort_order, id",
        [$courseId]
    );
} elseif (Auth::is('teacher') || Auth::is('admin')) {
    $lessons = Database::fetchAll(
        "SELECT id, title, is_published FROM lessons WHERE course_id=? ORDER BY sort_order, id",
        [$courseId]
    );
} else {
    $lessons = Database::fetchAll(
        "SELECT id, title, is_published FROM lessons WHERE course_id=? AND is_published=1 ORDER BY sort_order, id",
        [$courseId]
    );
}

jsonResponse($lessons);
