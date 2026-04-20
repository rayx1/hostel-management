<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireRole(['super_admin', 'warden', 'class_coordinator']);
$db = Database::getInstance();

$type = $_GET['type'] ?? 'occupancy';
$format = $_GET['format'] ?? 'csv';
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$columns = [];
$data = [];

if ($type === 'occupancy') {
    $columns = ['Hostel', 'Floor', 'Room', 'Total Beds', 'Occupied'];
    $data = $db->query("SELECT h.name AS Hostel, f.floor_label AS Floor, r.room_number AS Room, COUNT(b.id) AS `Total Beds`, SUM(CASE WHEN b.status='occupied' THEN 1 ELSE 0 END) AS Occupied
FROM rooms r JOIN floors f ON f.id=r.floor_id JOIN hostels h ON h.id=f.hostel_id LEFT JOIN beds b ON b.room_id=r.id GROUP BY h.name, f.floor_label, r.room_number")->fetchAll();
} elseif ($type === 'student_strength') {
    $columns = ['Course', 'Branch', 'Year', 'Students'];
    $data = $db->query('SELECT course AS Course, branch AS Branch, year AS Year, COUNT(*) AS Students FROM students GROUP BY course, branch, year')->fetchAll();
} elseif ($type === 'leave') {
    $columns = ['Student', 'From', 'To', 'Type', 'Final Status', 'Created'];
    $st = $db->prepare("SELECT s.name AS Student, lr.from_date AS `From`, lr.to_date AS `To`, lr.leave_type AS Type, lr.final_status AS `Final Status`, lr.created_at AS Created FROM leave_requests lr JOIN students s ON s.id=lr.student_id WHERE DATE(lr.created_at) BETWEEN :from AND :to");
    $st->execute([':from'=>$from,':to'=>$to]);
    $data = $st->fetchAll();
} elseif ($type === 'complaints') {
    $columns = ['Student', 'Category', 'Priority', 'Status', 'Created'];
    $st = $db->prepare("SELECT s.name AS Student, c.category AS Category, c.priority AS Priority, c.status AS Status, c.created_at AS Created FROM complaints c LEFT JOIN students s ON s.id=c.student_id WHERE DATE(c.created_at) BETWEEN :from AND :to");
    $st->execute([':from'=>$from,':to'=>$to]);
    $data = $st->fetchAll();
} else {
    $columns = ['Student', 'Visitor', 'Relation', 'Status', 'Check In', 'Check Out'];
    $st = $db->prepare("SELECT s.name AS Student, v.visitor_name AS Visitor, v.relation AS Relation, v.status AS Status, v.check_in AS `Check In`, v.check_out AS `Check Out` FROM visitors v LEFT JOIN students s ON s.id=v.student_id WHERE DATE(v.check_in) BETWEEN :from AND :to");
    $st->execute([':from'=>$from,':to'=>$to]);
    $data = $st->fetchAll();
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $type . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $columns);
    foreach ($data as $row) {
        $line = [];
        foreach ($columns as $c) { $line[] = $row[$c] ?? ''; }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

if (class_exists('FPDF')) {
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Report: ' . strtoupper($type), 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 9);
    foreach ($columns as $c) { $pdf->Cell(45, 8, $c, 1); }
    $pdf->Ln();
    $pdf->SetFont('Arial', '', 8);
    foreach ($data as $row) {
        foreach ($columns as $c) {
            $pdf->Cell(45, 7, substr((string)($row[$c] ?? ''), 0, 28), 1);
        }
        $pdf->Ln();
    }
    $pdf->Output('I', 'report_' . $type . '.pdf');
    exit;
}
?>
<!doctype html>
<html><head><title>Report</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet"></head>
<body onload="window.print()"><div class="container-fluid py-3"><h3>Report: <?= e(strtoupper($type)) ?></h3><table class="table table-bordered table-sm"><thead><tr><?php foreach($columns as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($data as $row): ?><tr><?php foreach($columns as $c): ?><td><?= e((string)($row[$c] ?? '')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></body></html>

