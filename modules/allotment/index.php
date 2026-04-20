<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireRole(['super_admin', 'warden']);
$db = Database::getInstance();
$user = Auth::user();

if (is_post()) {
    Csrf::guard($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'allot') {
        $studentId = (int) $_POST['student_id'];
        $bedId = (int) $_POST['bed_id'];
        $hostelId = (int) $_POST['hostel_id'];

        $student = $db->prepare('SELECT id, gender FROM students WHERE id=:id');
        $student->execute([':id'=>$studentId]);
        $s = $student->fetch();

        $hostel = $db->prepare('SELECT id, type FROM hostels WHERE id=:id');
        $hostel->execute([':id'=>$hostelId]);
        $h = $hostel->fetch();

        $active = $db->prepare("SELECT id FROM allotments WHERE student_id=:sid AND status='active' LIMIT 1");
        $active->execute([':sid'=>$studentId]);

        if (!$s || !$h) {
            flash('error', 'Invalid student/hostel');
        } elseif (($s['gender'] === 'male' && $h['type'] !== 'boys') || ($s['gender'] === 'female' && $h['type'] !== 'girls')) {
            flash('error', 'Gender lock violation: Student cannot be allotted to this hostel');
        } elseif ($active->fetch()) {
            flash('error', 'Student already has one active allotment');
        } else {
            $db->beginTransaction();
            try {
                $bedStmt = $db->prepare('SELECT status FROM beds WHERE id=:id FOR UPDATE');
                $bedStmt->execute([':id'=>$bedId]);
                $bedStatus = $bedStmt->fetchColumn();
                if ($bedStatus !== 'vacant') {
                    throw new RuntimeException('Selected bed is not vacant');
                }
                $db->prepare('INSERT INTO allotments (student_id, bed_id, hostel_id, allotment_date, status, remarks, allotted_by) VALUES (:student_id,:bed_id,:hostel_id,CURDATE(),"active",:remarks,:allotted_by)')
                   ->execute([':student_id'=>$studentId,':bed_id'=>$bedId,':hostel_id'=>$hostelId,':remarks'=>trim($_POST['remarks'] ?? ''),':allotted_by'=>Auth::id()]);
                $db->prepare('UPDATE beds SET status="occupied" WHERE id=:id')->execute([':id'=>$bedId]);
                $db->commit();
                AuditLogger::log(Auth::id() ?? 0, 'allotment', 'create', 'Allotted student '.$studentId.' to bed '.$bedId);
                flash('success', 'Bed allotted successfully');
            } catch (Throwable $e) {
                $db->rollBack();
                flash('error', $e->getMessage());
            }
        }
    }

    if ($action === 'vacate') {
        $allotmentId = (int) $_POST['allotment_id'];
        $reason = trim($_POST['reason'] ?? '');
        $db->beginTransaction();
        try {
            $st = $db->prepare("SELECT bed_id FROM allotments WHERE id=:id AND status='active' FOR UPDATE");
            $st->execute([':id'=>$allotmentId]);
            $row = $st->fetch();
            if (!$row) { throw new RuntimeException('Active allotment not found'); }
            $db->prepare('UPDATE allotments SET status="vacated", vacate_date=CURDATE(), remarks=CONCAT(IFNULL(remarks,"")," | Vacate: ", :reason) WHERE id=:id')
               ->execute([':reason'=>$reason,':id'=>$allotmentId]);
            $db->prepare('UPDATE beds SET status="vacant" WHERE id=:bed')->execute([':bed'=>$row['bed_id']]);
            $db->commit();
            AuditLogger::log(Auth::id() ?? 0, 'allotment', 'update', 'Vacated allotment '.$allotmentId);
            flash('success', 'Bed vacated');
        } catch (Throwable $e) {
            $db->rollBack();
            flash('error', $e->getMessage());
        }
    }

    if ($action === 'transfer') {
        $allotmentId = (int) $_POST['allotment_id'];
        $newBedId = (int) $_POST['new_bed_id'];
        $db->beginTransaction();
        try {
            $old = $db->prepare("SELECT student_id, hostel_id, bed_id FROM allotments WHERE id=:id AND status='active' FOR UPDATE");
            $old->execute([':id'=>$allotmentId]);
            $a = $old->fetch();
            if (!$a) { throw new RuntimeException('Allotment not found'); }

            $newBed = $db->prepare('SELECT status FROM beds WHERE id=:id FOR UPDATE');
            $newBed->execute([':id'=>$newBedId]);
            if ($newBed->fetchColumn() !== 'vacant') { throw new RuntimeException('New bed not vacant'); }

            $db->prepare('UPDATE allotments SET status="transferred", vacate_date=CURDATE(), remarks=CONCAT(IFNULL(remarks,"")," | Transferred") WHERE id=:id')->execute([':id'=>$allotmentId]);
            $db->prepare('UPDATE beds SET status="vacant" WHERE id=:id')->execute([':id'=>$a['bed_id']]);

            $db->prepare('INSERT INTO allotments (student_id, bed_id, hostel_id, allotment_date, status, remarks, allotted_by) VALUES (:student,:bed,:hostel,CURDATE(),"active","Transferred from previous bed",:by)')
               ->execute([':student'=>$a['student_id'],':bed'=>$newBedId,':hostel'=>$a['hostel_id'],':by'=>Auth::id()]);
            $db->prepare('UPDATE beds SET status="occupied" WHERE id=:id')->execute([':id'=>$newBedId]);

            $db->commit();
            AuditLogger::log(Auth::id() ?? 0, 'allotment', 'update', 'Transferred allotment '.$allotmentId.' to bed '.$newBedId);
            flash('success', 'Transfer completed');
        } catch (Throwable $e) {
            $db->rollBack();
            flash('error', $e->getMessage());
        }
    }

    redirect('/modules/allotment/index.php');
}

$students = $db->query("SELECT id, name, roll_number, gender FROM students WHERE status='active' ORDER BY name")->fetchAll();
$hostels = $db->query('SELECT * FROM hostels ORDER BY name')->fetchAll();
$availableBeds = $db->query("SELECT b.id, h.name AS hostel_name, f.floor_label, r.room_number, b.bed_number
FROM beds b
JOIN rooms r ON r.id=b.room_id
JOIN floors f ON f.id=r.floor_id
JOIN hostels h ON h.id=f.hostel_id
WHERE b.status='vacant'
ORDER BY h.name, r.room_number, b.bed_number")->fetchAll();

$activeAllotments = $db->query("SELECT a.id, a.student_id, s.name AS student_name, s.roll_number, h.name AS hostel_name, r.room_number, b.bed_number, a.allotment_date
FROM allotments a
JOIN students s ON s.id=a.student_id
JOIN hostels h ON h.id=a.hostel_id
JOIN beds b ON b.id=a.bed_id
JOIN rooms r ON r.id=b.room_id
WHERE a.status='active' ORDER BY a.id DESC")->fetchAll();

$history = $db->query("SELECT a.id, s.name AS student_name, s.roll_number, a.status, a.allotment_date, a.vacate_date, a.remarks
FROM allotments a
JOIN students s ON s.id=a.student_id
ORDER BY a.id DESC LIMIT 100")->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/page_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Allotment Management</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#allotModal">Allot Bed</button>
</div>
<div class="card mb-3"><div class="card-header">Active Allotments</div><div class="card-body">
<table class="table table-bordered datatable"><thead><tr><th>Student</th><th>Roll</th><th>Hostel</th><th>Room/Bed</th><th>Date</th><th>Actions</th></tr></thead><tbody>
<?php foreach($activeAllotments as $a): ?>
<tr>
    <td><?= e($a['student_name']) ?></td><td><?= e($a['roll_number']) ?></td><td><?= e($a['hostel_name']) ?></td><td><?= e($a['room_number']) ?>/<?= e((string)$a['bed_number']) ?></td><td><?= e($a['allotment_date']) ?></td>
    <td class="d-flex gap-1">
        <form method="post" class="d-flex gap-1"><input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="vacate"><input type="hidden" name="allotment_id" value="<?= (int)$a['id'] ?>"><input name="reason" class="form-control form-control-sm" placeholder="Vacate reason" required><button class="btn btn-sm btn-warning">Vacate</button></form>
        <button class="btn btn-sm btn-info transfer-btn" data-id="<?= (int)$a['id'] ?>">Transfer</button>
        <a class="btn btn-sm btn-secondary" target="_blank" href="<?= BASE_URL ?>/modules/allotment/letter.php?id=<?= (int)$a['id'] ?>">Print Letter</a>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>

<div class="card"><div class="card-header">Allotment History</div><div class="card-body">
<table class="table table-sm datatable"><thead><tr><th>Student</th><th>Roll</th><th>Status</th><th>Allotted</th><th>Vacated</th><th>Remarks</th></tr></thead><tbody>
<?php foreach($history as $h): ?><tr><td><?= e($h['student_name']) ?></td><td><?= e($h['roll_number']) ?></td><td><?= e($h['status']) ?></td><td><?= e($h['allotment_date']) ?></td><td><?= e($h['vacate_date']) ?></td><td><?= e($h['remarks']) ?></td></tr><?php endforeach; ?>
</tbody></table>
</div></div>

<div class="modal fade" id="allotModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5>Allot Bed</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="allot">
<label class="form-label">Student</label><select class="form-select select2" name="student_id" required><?php foreach($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['roll_number']) ?> - <?= e($s['gender']) ?>)</option><?php endforeach; ?></select>
<label class="form-label mt-2">Hostel</label><select class="form-select" name="hostel_id" required><?php foreach($hostels as $h): ?><option value="<?= (int)$h['id'] ?>"><?= e($h['name']) ?> (<?= e($h['type']) ?>)</option><?php endforeach; ?></select>
<label class="form-label mt-2">Available Bed</label><select class="form-select select2" name="bed_id" required><?php foreach($availableBeds as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['hostel_name']) ?> - <?= e($b['floor_label']) ?> / <?= e($b['room_number']) ?> / Bed <?= e((string)$b['bed_number']) ?></option><?php endforeach; ?></select>
<label class="form-label mt-2">Remarks</label><textarea name="remarks" class="form-control"></textarea>
</div><div class="modal-footer"><button class="btn btn-primary">Allot</button></div></form></div></div></div>

<div class="modal fade" id="transferModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5>Transfer Bed</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="transfer"><input type="hidden" name="allotment_id" id="transfer_allotment_id">
<label class="form-label">New Bed</label><select class="form-select select2" name="new_bed_id" required><?php foreach($availableBeds as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['hostel_name']) ?> - <?= e($b['floor_label']) ?> / <?= e($b['room_number']) ?> / Bed <?= e((string)$b['bed_number']) ?></option><?php endforeach; ?></select>
</div><div class="modal-footer"><button class="btn btn-primary">Transfer</button></div></form></div></div></div>
<script>
$(function(){
    $('.transfer-btn').on('click', function(){
        $('#transfer_allotment_id').val($(this).data('id'));
        $('#transferModal').modal('show');
    });
});
</script>
<?php require_once dirname(__DIR__, 2) . '/includes/page_end.php'; ?>

