<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::logout();
flashSet('success', 'You have been signed out successfully.');
redirect(BASE_URL . '/modules/auth/login.php');
