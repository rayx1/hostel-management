<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$user = Auth::user();

$hostelFilter = '';
$params = [];
if ($user['role'] === 'warden' && $user['hostel_id']) {
    $hostelFilter = ' WHERE h.id = :hostel_id ';
    $params[':hostel_id'] = $user['hostel_id'];
}

$occupancySql = "
SELECT h.id, h.name, h.type,
       COUNT(b.id) AS total_beds,
       SUM(CASE WHEN b.status = 'occupied' THEN 1 ELSE 0 END) AS occupied_beds
FROM hostels h
LEFT JOIN floors f ON f.hostel_id = h.id
LEFT JOIN rooms r ON r.floor_id = f.id
LEFT JOIN beds b ON b.room_id = r.id
$hostelFilter
GROUP BY h.id, h.name, h.type
";
$occStmt = $db->prepare($occupancySql);
$occStmt->execute($params);
$occupancy = $occStmt->fetchAll();

$ccPending = (int) $db->query("SELECT COUNT(*) FROM leave_requests WHERE cc_status='pending' AND final_status='pending'")->fetchColumn();
$wardenPending = (int) $db->query("SELECT COUNT(*) FROM leave_requests WHERE final_status='cc_approved' AND warden_status='pending'")->fetchColumn();
$openComplaints = (int) $db->query("SELECT COUNT(*) FROM complaints WHERE status IN ('open','in_progress')")->fetchColumn();
$visitorsInside = (int) $db->query("SELECT COUNT(*) FROM visitors WHERE status='checked_in'")->fetchColumn();

$recentAllotments = $db->query("SELECT a.id, s.name AS student_name, s.roll_number, h.name AS hostel_name, r.room_number, b.bed_number, a.allotment_date
FROM allotments a
JOIN students s ON s.id = a.student_id
JOIN hostels h ON h.id = a.hostel_id
JOIN beds b ON b.id = a.bed_id
JOIN rooms r ON r.id = b.room_id
ORDER BY a.id DESC LIMIT 8")->fetchAll();

$notices = $db->query("SELECT n.*, h.name AS hostel_name FROM notices n LEFT JOIN hostels h ON h.id=n.hostel_id WHERE n.expiry_date IS NULL OR n.expiry_date >= CURDATE() ORDER BY n.id DESC LIMIT 8")->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/page_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Dashboard</h3>
</div>
<div class="row g-3 mb-3">
    <?php foreach ($occupancy as $o):
        $percent = ((int) $o['total_beds']) > 0 ? round(((int) $o['occupied_beds'] / (int) $o['total_beds']) * 100, 1) : 0;
        $cls = $o['type'] === 'girls' ? 'card-pink' : 'card-blue';
    ?>
    <div class="col-md-6">
        <div class="card <?= e($cls) ?>">
            <div class="card-body">
                <h5><?= e($o['name']) ?></h5>
                <p class="mb-1">Occupied: <?= e((string) $o['occupied_beds']) ?> / <?= e((string) $o['total_beds']) ?></p>
                <strong><?= e((string) $percent) ?>%</strong>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card"><div class="card-body"><h6>CC Pending Leave</h6><h3><?= e((string) $ccPending) ?></h3></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><h6>Warden Pending Leave</h6><h3><?= e((string) $wardenPending) ?></h3></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><h6>Open Complaints</h6><h3><?= e((string) $openComplaints) ?></h3></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><h6>Visitors Inside</h6><h3><?= e((string) $visitorsInside) ?></h3></div></div></div>
</div>
<div class="card mb-3">
    <div class="card-header">Occupancy Overview</div>
    <div class="card-body">
        <canvas id="occupancyChart" height="80"></canvas>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Recent Allotments</div>
            <div class="card-body">
                <table class="table table-sm datatable">
                    <thead><tr><th>Student</th><th>Roll</th><th>Hostel</th><th>Room/Bed</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentAllotments as $r): ?>
                        <tr>
                            <td><?= e($r['student_name']) ?></td>
                            <td><?= e($r['roll_number']) ?></td>
                            <td><?= e($r['hostel_name']) ?></td>
                            <td><?= e($r['room_number']) ?>/<?= e((string) $r['bed_number']) ?></td>
                            <td><?= e($r['allotment_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Notices</div>
            <div class="card-body">
                <?php if (!$notices): ?>
                    <p class="text-muted mb-0">No active notices.</p>
                <?php endif; ?>
                <?php foreach ($notices as $n): ?>
                    <div class="border rounded p-2 mb-2">
                        <strong><?= e($n['title']) ?></strong>
                        <div class="small text-muted"><?= e($n['hostel_name'] ?? 'All Hostels') ?> | Target: <?= e($n['target']) ?></div>
                        <div><?= nl2br(e($n['content'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<script>
const occupancyLabels = <?= json_encode(array_map(static fn($x) => $x['name'], $occupancy)) ?>;
const occupancyData = <?= json_encode(array_map(static fn($x) => (int) $x['occupied_beds'], $occupancy)) ?>;
const occupancyTotal = <?= json_encode(array_map(static fn($x) => (int) $x['total_beds'], $occupancy)) ?>;
new Chart(document.getElementById('occupancyChart'), {
    type: 'bar',
    data: {
        labels: occupancyLabels,
        datasets: [
            { label: 'Occupied Beds', data: occupancyData, backgroundColor: '#1a6fb5' },
            { label: 'Total Beds', data: occupancyTotal, backgroundColor: '#b5196e' }
        ]
    },
    options: { responsive: true }
});
</script>
<?php require_once dirname(__DIR__, 2) . '/includes/page_end.php'; ?>

