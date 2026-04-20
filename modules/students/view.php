<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireRole(['super_admin', 'class_coordinator', 'warden']);
$db = Database::getInstance();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM students WHERE id=:id LIMIT 1');
$stmt->execute([':id'=>$id]);
$student = $stmt->fetch();
if (!$student) {
    flash('error', 'Student not found');
    redirect('/modules/students/index.php');
}

$allotment = $db->prepare("SELECT a.*, h.name AS hostel_name, r.room_number, b.bed_number
FROM allotments a
JOIN hostels h ON h.id=a.hostel_id
JOIN beds b ON b.id=a.bed_id
JOIN rooms r ON r.id=b.room_id
WHERE a.student_id=:sid AND a.status='active' LIMIT 1");
$allotment->execute([':sid'=>$id]);
$currentAllotment = $allotment->fetch();

$leaveHistory = $db->prepare('SELECT * FROM leave_requests WHERE student_id=:sid ORDER BY id DESC');
$leaveHistory->execute([':sid'=>$id]);
$leaves = $leaveHistory->fetchAll();

$complaintHistory = $db->prepare('SELECT * FROM complaints WHERE student_id=:sid ORDER BY id DESC');
$complaintHistory->execute([':sid'=>$id]);
$complaints = $complaintHistory->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/page_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Student Profile</h3>
    <a class="btn btn-secondary" href="<?= BASE_URL ?>/modules/students/index.php">Back</a>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <?php if (!empty($student['photo'])): ?>
                <img src="<?= BASE_URL . '/' . e($student['photo']) ?>" class="img-fluid rounded mb-2" alt="photo">
            <?php endif; ?>
            <h5><?= e($student['name']) ?></h5>
            <p class="mb-1">Roll: <?= e($student['roll_number']) ?></p>
            <p class="mb-1">Phone: <?= e($student['phone']) ?></p>
            <p class="mb-1">Email: <?= e($student['email']) ?></p>
            <p class="mb-1">Parent: <?= e($student['parent_name']) ?> (<?= e($student['parent_phone']) ?>)</p>
            <p class="mb-0">Status: <span class="badge bg-info"><?= e($student['status']) ?></span></p>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-header">Current Bed Allotment</div><div class="card-body">
            <?php if ($currentAllotment): ?>
                <strong><?= e($currentAllotment['hostel_name']) ?> / Room <?= e($currentAllotment['room_number']) ?> / Bed <?= e((string)$currentAllotment['bed_number']) ?></strong>
            <?php else: ?>
                <span class="text-muted">No active allotment</span>
            <?php endif; ?>
        </div></div>
        <div class="card mb-3"><div class="card-header">Leave History</div><div class="card-body">
            <table class="table table-sm"><thead><tr><th>From</th><th>To</th><th>Type</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($leaves as $l): ?><tr><td><?= e($l['from_date']) ?></td><td><?= e($l['to_date']) ?></td><td><?= e($l['leave_type']) ?></td><td><?= e($l['final_status']) ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div></div>
        <div class="card"><div class="card-header">Complaint History</div><div class="card-body">
            <table class="table table-sm"><thead><tr><th>Category</th><th>Subject</th><th>Priority</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($complaints as $c): ?><tr><td><?= e($c['category']) ?></td><td><?= e($c['subject']) ?></td><td><?= e($c['priority']) ?></td><td><?= e($c['status']) ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div></div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/page_end.php'; ?>

