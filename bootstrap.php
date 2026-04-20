<?php

require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/core/helpers.php';
require_once BASE_PATH . '/core/Csrf.php';
require_once BASE_PATH . '/core/Auth.php';
require_once BASE_PATH . '/core/Validator.php';
require_once BASE_PATH . '/core/FileUpload.php';
require_once BASE_PATH . '/core/AuditLogger.php';

if (file_exists(BASE_PATH . '/vendor/fpdf/fpdf.php')) {
    require_once BASE_PATH . '/vendor/fpdf/fpdf.php';
}

