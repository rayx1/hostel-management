<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireRole(['super_admin', 'warden', 'staff']);
$db = Database::getInstance();

if (is_post()) {
    Csrf::guard($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';
    if ($action === 'checkin') {
        $db->prepare('INSERT INTO visitors (student_id, visitor_name, visitor_phone, relation, purpose, id_proof, check_in, approved_by, status) VALUES (:student_id,:visitor_name,:visitor_phone,:relation,:purpose,:id_proof,NOW(),:approved_by,"checked_in")')
           ->execute([
               ':student_id'=>(int)$_POST['student_id'], ':visitor_name'=>trim($_POST['visitor_name']), ':visitor_phone'=>trim($_POST['visitor_phone']),
               ':relation'=>trim($_POST['relation']), ':purpose'=>trim($_POST['purpose']), ':id_proof'=>trim($_POST['id_proof']), ':approved_by'=>Auth::id(),
           ]);
        AuditLogger::log(Auth::id() ?? 0, 'visitors', 'create', 'Visitor checked in');
        flash('success', 'Visitor check-in done');
    }
    if ($action === 'checkout') {
        $db->prepare('UPDATE visitors SET check_out=NOW(), status="checked_out" WHERE id=:id AND status="checked_in"')->execute([':id'=>(int)$_POST['visitor_id']]);
        AuditLogger::log(Auth::id() ?? 0, 'visitors', 'update', 'Visitor checked out '.(int)$_POST['visitor_id']);
        flash('success', 'Visitor checked out');
    }
    redirect('/modules/visitors/index.php');
}

$students = $db->query("SELECT id, name, roll_number FROM students WHERE status='active' ORDER BY name")->fetchAll();
$inside = $db->query("SELECT v.*, s.name AS student_name, s.roll_number FROM visitors v LEFT JOIN students s ON s.id=v.student_id WHERE v.status='checked_in' ORDER BY v.check_in DESC")->fetchAll();
$history = $db->query("SELECT v.*, s.name AS student_name, s.roll_number FROM visitors v LEFT JOIN students s ON s.id=v.student_id ORDER BY v.id DESC")->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/page_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Visitor Management</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#visitorModal">Log Visitor</button>
</div>
<div class="card mb-3"><div class="card-header">Visitors Currently Inside</div><div class="card-body">
<table class="table table-bordered datatable"><thead><tr><th>Student</th><th>Visitor</th><th>Relation</th><th>Phone</th><th>Check-In</th><th>Action</th></tr></thead><tbody>
<?php foreach($inside as $v): ?>
<tr>
<td><?= e($v['student_name']) ?> (<?= e($v['roll_number']) ?>)</td>
<td><?= e($v['visitor_name']) ?></td>
<td><?= e($v['relation']) ?></td>
<td><?= e($v['visitor_phone']) ?></td>
<td><?= e($v['check_in']) ?></td>
<td><form method="post"><input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="checkout"><input type="hidden" name="visitor_id" value="<?= (int)$v['id'] ?>"><button class="btn btn-sm btn-warning">Check-Out</button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
<div class="card"><div class="card-header">Visitor History</div><div class="card-body">
<table class="table table-sm datatable"><thead><tr><th>Student</th><th>Visitor</th><th>Purpose</th><th>Status</th><th>In</th><th>Out</th></tr></thead><tbody>
<?php foreach($history as $h): ?><tr><td><?= e($h['student_name']) ?></td><td><?= e($h['visitor_name']) ?></td><td><?= e($h['purpose']) ?></td><td><?= e($h['status']) ?></td><td><?= e($h['check_in']) ?></td><td><?= e($h['check_out']) ?></td></tr><?php endforeach; ?>
</tbody></table>
</div></div>

<div class="modal fade" id="visitorModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5>Visitor Check-In</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="checkin">
<label class="form-label">Student</label><select class="form-select select2" name="student_id" required><?php foreach($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['roll_number']) ?>)</option><?php endforeach; ?></select>
<label class="form-label mt-2">Visitor Name</label><input class="form-control" name="visitor_name" required>
<label class="form-label mt-2">Phone</label><input class="form-control" name="visitor_phone" required>
<label class="form-label mt-2">Relation</label><input class="form-control" name="relation" required>
<label class="form-label mt-2">Purpose</label><textarea class="form-control" name="purpose"></textarea>
<label class="form-label mt-2">ID Proof</label><input class="form-control" name="id_proof">
</div><div class="modal-footer"><button class="btn btn-primary">Check-In</button></div></form></div></div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/page_end.php'; ?>

