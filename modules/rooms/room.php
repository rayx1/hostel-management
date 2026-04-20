<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireRole(['super_admin', 'warden']);
$db = Database::getInstance();

$roomId = (int) ($_GET['id'] ?? 0);
$roomStmt = $db->prepare('SELECT r.*, f.floor_label, h.name AS hostel_name FROM rooms r JOIN floors f ON f.id=r.floor_id JOIN hostels h ON h.id=f.hostel_id WHERE r.id=:id');
$roomStmt->execute([':id'=>$roomId]);
$room = $roomStmt->fetch();
if (!$room) { redirect('/modules/rooms/index.php'); }

if (is_post()) {
    Csrf::guard($_POST['csrf_token'] ?? null);
    $db->prepare('UPDATE beds SET status=:status WHERE id=:id AND room_id=:room_id')->execute([':status'=>$_POST['status'],':id'=>(int)$_POST['bed_id'],':room_id'=>$roomId]);
    AuditLogger::log(Auth::id() ?? 0, 'rooms', 'update', 'Bed status changed in room '.$roomId);
    flash('success', 'Bed status updated');
    redirect('/modules/rooms/room.php?id=' . $roomId);
}

$beds = $db->prepare("SELECT b.*, s.name AS student_name, s.roll_number, s.course
FROM beds b
LEFT JOIN allotments a ON a.bed_id=b.id AND a.status='active'
LEFT JOIN students s ON s.id=a.student_id
WHERE b.room_id=:room_id
ORDER BY b.bed_number");
$beds->execute([':room_id'=>$roomId]);
$rows = $beds->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/page_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Room <?= e($room['room_number']) ?> Beds</h3>
    <a class="btn btn-secondary" href="<?= BASE_URL ?>/modules/rooms/index.php">Back</a>
</div>
<div class="card"><div class="card-body">
<table class="table table-bordered">
<thead><tr><th>Bed</th><th>Status</th><th>Occupant</th><th>Roll</th><th>Course</th><th>Action</th></tr></thead>
<tbody>
<?php foreach($rows as $b): ?>
<tr>
    <td><?= e((string)$b['bed_number']) ?></td>
    <td><?= e($b['status']) ?></td>
    <td><?= e($b['student_name']) ?></td>
    <td><?= e($b['roll_number']) ?></td>
    <td><?= e($b['course']) ?></td>
    <td>
        <form method="post" class="d-flex gap-2">
            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="bed_id" value="<?= (int)$b['id'] ?>">
            <select class="form-select form-select-sm" name="status">
                <?php foreach(['vacant','occupied','reserved','maintenance'] as $st): ?>
                    <option value="<?= e($st) ?>" <?= $b['status']===$st?'selected':'' ?>><?= e($st) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-primary">Update</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/page_end.php'; ?>

