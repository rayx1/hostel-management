<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireRole(['super_admin', 'warden']);

$id = (int) ($_GET['id'] ?? 0);
$db = Database::getInstance();
$stmt = $db->prepare("SELECT a.*, s.name AS student_name, s.roll_number, s.course, s.branch, h.name AS hostel_name, r.room_number, b.bed_number
FROM allotments a
JOIN students s ON s.id=a.student_id
JOIN hostels h ON h.id=a.hostel_id
JOIN beds b ON b.id=a.bed_id
JOIN rooms r ON r.id=b.room_id
WHERE a.id=:id");
$stmt->execute([':id'=>$id]);
$row = $stmt->fetch();
if (!$row) { exit('Allotment not found'); }

if (class_exists('FPDF')) {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Hostel Allotment Letter', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Ln(6);
    foreach ([
        'Student' => $row['student_name'],
        'Roll Number' => $row['roll_number'],
        'Course/Branch' => $row['course'] . ' / ' . $row['branch'],
        'Hostel' => $row['hostel_name'],
        'Room/Bed' => $row['room_number'] . ' / ' . $row['bed_number'],
        'Allotment Date' => $row['allotment_date'],
    ] as $label => $value) {
        $pdf->Cell(60, 8, $label . ':', 0, 0);
        $pdf->Cell(0, 8, (string) $value, 0, 1);
    }
    $pdf->Output('I', 'allotment_letter_' . $id . '.pdf');
    exit;
}
?>
<!doctype html>
<html><head><title>Allotment Letter</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet"></head>
<body onload="window.print()"><div class="container py-4"><h2>Hostel Allotment Letter</h2><hr>
<p><strong>Student:</strong> <?= e($row['student_name']) ?></p>
<p><strong>Roll Number:</strong> <?= e($row['roll_number']) ?></p>
<p><strong>Course/Branch:</strong> <?= e($row['course']) ?> / <?= e($row['branch']) ?></p>
<p><strong>Hostel:</strong> <?= e($row['hostel_name']) ?></p>
<p><strong>Room/Bed:</strong> <?= e($row['room_number']) ?> / <?= e((string)$row['bed_number']) ?></p>
<p><strong>Allotment Date:</strong> <?= e($row['allotment_date']) ?></p>
</div></body></html>

