<?php

require_once __DIR__ . '/bootstrap.php';

if (Auth::check()) {
    redirect('/modules/dashboard/index.php');
}

redirect('/auth/login.php');

