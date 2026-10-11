<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::requireLogin();
jsonResponse(['count' => unreadNotifCount(Auth::id())]);
