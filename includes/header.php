<?php

require_once dirname(__DIR__) . '/bootstrap.php';

if (!isset($publicPage) || $publicPage !== true) {
    Auth::requireLogin();
}

$user = Auth::user();
$themeClass = 'theme-default';
if ($user && ($user['role'] === 'warden' || $user['role'] === 'staff')) {
    $themeClass = $user['hostel_id'] === 2 ? 'theme-girls' : 'theme-boys';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="<?= e($themeClass) ?>">

