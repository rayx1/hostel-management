<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireRole(['super_admin', 'warden', 'class_coordinator']);
$db = Database::getInstance();

$type = $_GET['type'] ?? 'occupancy';
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$data = [];
$columns = [];

if ($type === 'occupancy') {
    $columns = ['Hostel', 'Floor', 'Room', 'Total Beds', 'Occupied'];
    $data = $db->query("SELECT h.name AS Hostel, f.floor_label AS Floor, r.room_number AS Room, COUNT(b.id) AS `Total Beds`, SUM(CASE WHEN b.status='occupied' THEN 1 ELSE 0 END) AS Occupied
FROM rooms r
JOIN floors f ON f.id=r.floor_id
JOIN hostels h ON h.id=f.hostel_id
LEFT JOIN beds b ON b.room_id=r.id
GROUP BY h.name, f.floor_label, r.room_number
ORDER BY h.name, f.floor_number, r.room_number")->fetchAll();
} elseif ($type === 'student_strength') {
    $columns = ['Course', 'Branch', 'Year', 'Students'];
    $data = $db->query('SELECT course AS Course, branch AS Branch, year AS Year, COUNT(*) AS Students FROM students GROUP BY course, branch, year ORDER BY course, branch, year')->fetchAll();
} elseif ($type === 'leave') {
    $columns = ['Student', 'From', 'To', 'Type', 'Final Status', 'Created'];
    $stmt = $db->prepare("SELECT s.name AS Student, lr.from_date AS `From`, lr.to_date AS `To`, lr.leave_type AS Type, lr.final_status AS `Final Status`, lr.created_at AS Created
FROM leave_requests lr
JOIN students s ON s.id=lr.student_id
WHERE DATE(lr.created_at) BETWEEN :from AND :to
ORDER BY lr.id DESC");
    $stmt->execute([':from'=>$from,':to'=>$to]);
    $data = $stmt->fetchAll();
} elseif ($type === 'complaints') {
    $columns = ['Student', 'Category', 'Priority', 'Status', 'Created'];
    $stmt = $db->prepare("SELECT s.name AS Student, c.category AS Category, c.priority AS Priority, c.status AS Status, c.created_at AS Created
FROM complaints c LEFT JOIN students s ON s.id=c.student_id
WHERE DATE(c.created_at) BETWEEN :from AND :to
ORDER BY c.id DESC");
    $stmt->execute([':from'=>$from,':to'=>$to]);
    $data = $stmt->fetchAll();
} elseif ($type === 'visitors') {
    $columns = ['Student', 'Visitor', 'Relation', 'Status', 'Check In', 'Check Out'];
    $stmt = $db->prepare("SELECT s.name AS Student, v.visitor_name AS Visitor, v.relation AS Relation, v.status AS Status, v.check_in AS `Check In`, v.check_out AS `Check Out`
FROM visitors v LEFT JOIN students s ON s.id=v.student_id
WHERE DATE(v.check_in) BETWEEN :from AND :to
ORDER BY v.id DESC");
    $stmt->execute([':from'=>$from,':to'=>$to]);
    $data = $stmt->fetchAll();
}

require_once dirname(__DIR__, 2) . '/includes/page_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Reports</h3>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" target="_blank" href="<?= BASE_URL ?>/modules/reports/export.php?type=<?= e($type) ?>&format=csv&from=<?= e($from) ?>&to=<?= e($to) ?>">Export CSV</a>
        <a class="btn btn-outline-primary" target="_blank" href="<?= BASE_URL ?>/modules/reports/export.php?type=<?= e($type) ?>&format=pdf&from=<?= e($from) ?>&to=<?= e($to) ?>">Export PDF</a>
    </div>
</div>
<form class="card mb-3"><div class="card-body row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">Report Type</label>
        <select class="form-select" name="type">
            <option value="occupancy" <?= $type==='occupancy'?'selected':'' ?>>Occupancy</option>
            <option value="student_strength" <?= $type==='student_strength'?'selected':'' ?>>Student Strength</option>
            <option value="leave" <?= $type==='leave'?'selected':'' ?>>Leave</option>
            <option value="complaints" <?= $type==='complaints'?'selected':'' ?>>Complaints</option>
            <option value="visitors" <?= $type==='visitors'?'selected':'' ?>>Visitors</option>
        </select>
    </div>
    <div class="col-md-3"><label class="form-label">From</label><input type="date" class="form-control" name="from" value="<?= e($from) ?>"></div>
    <div class="col-md-3"><label class="form-label">To</label><input type="date" class="form-control" name="to" value="<?= e($to) ?>"></div>
    <div class="col-md-2"><button class="btn btn-primary">Generate</button></div>
</div></form>
<div class="card"><div class="card-body">
<table class="table table-bordered datatable"><thead><tr><?php foreach($columns as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach($data as $row): ?><tr><?php foreach($columns as $c): ?><td><?= e((string)($row[$c] ?? '')) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</tbody></table>
</div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/page_end.php'; ?>

