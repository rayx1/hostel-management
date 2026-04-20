<?php

require_once dirname(__DIR__) . '/includes/header.php';
$flashSuccess = flash('success') ?? '';
$flashError = flash('error') ?? '';
?>
<meta name="flash-success" content="<?= e($flashSuccess) ?>">
<meta name="flash-error" content="<?= e($flashError) ?>">
<div class="layout">
    <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <main class="content-wrap">

