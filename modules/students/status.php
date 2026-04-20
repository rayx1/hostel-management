<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireRole(['super_admin', 'class_coordinator', 'warden']);
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('/modules/students/index.php');
}
$db = Database::getInstance();
$st = $db->prepare('SELECT status FROM students WHERE id=:id');
$st->execute([':id'=>$id]);
$current = $st->fetchColumn();
if (!$current) {
    flash('error', 'Student not found');
    redirect('/modules/students/index.php');
}
$next = $current === 'active' ? 'suspended' : 'active';
$db->prepare('UPDATE students SET status=:status WHERE id=:id')->execute([':status'=>$next,':id'=>$id]);
AuditLogger::log(Auth::id() ?? 0, 'students', 'status', 'Changed student status ID '.$id.' to '.$next);
flash('success', 'Student status updated');
redirect('/modules/students/index.php');

