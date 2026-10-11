<?php
require_once __DIR__ . '/core/bootstrap.php';

if (Auth::check()) {
    redirect(BASE_URL . '/modules/' . Auth::role() . '/dashboard.php');
}

redirect(BASE_URL . '/modules/auth/login.php');
