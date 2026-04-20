<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$user = Auth::user();

if (is_post()) {
    Csrf::guard($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $db->prepare('INSERT INTO complaints (student_id, hostel_id, category, subject, description, priority, status) VALUES (:student_id,:hostel_id,:category,:subject,:description,:priority,"open")')
           ->execute([
               ':student_id'=>(int)$_POST['student_id'], ':hostel_id'=>(int)$_POST['hostel_id'], ':category'=>$_POST['category'],
               ':subject'=>trim($_POST['subject']), ':description'=>trim($_POST['description']), ':priority'=>$_POST['priority']
           ]);
        AuditLogger::log(Auth::id() ?? 0, 'complaints', 'create', 'Complaint created');
        flash('success', 'Complaint submitted');
    }

    if ($action === 'assign' && Auth::hasRole(['super_admin', 'warden'])) {
        $db->prepare('UPDATE complaints SET assigned_to=:staff_id, status="in_progress" WHERE id=:id')->execute([':staff_id'=>(int)$_POST['staff_id'],':id'=>(int)$_POST['complaint_id']]);
        AuditLogger::log(Auth::id() ?? 0, 'complaints', 'update', 'Assigned complaint '.(int)$_POST['complaint_id']);
        flash('success', 'Complaint assigned');
    }

    if ($action === 'status') {
        $id = (int) $_POST['complaint_id'];
        $status = $_POST['status'];
        $note = trim($_POST['resolution_note'] ?? '');
        if ($status === 'closed' && $note === '') {
            flash('error', 'Resolution note required before closing');
        } else {
            $db->prepare('UPDATE complaints SET status=:status, resolution_note=:note, resolved_date=CASE WHEN :status IN ("resolved","closed") THEN CURDATE() ELSE resolved_date END WHERE id=:id')
               ->execute([':status'=>$status,':note'=>$note ?: null,':id'=>$id]);
            AuditLogger::log(Auth::id() ?? 0, 'complaints', 'update', 'Complaint status update '.$id.' -> '.$status);
            flash('success', 'Complaint status updated');
        }
    }

    redirect('/modules/complaints/index.php');
}

$students = $db->query("SELECT id, name, roll_number FROM students WHERE status='active' ORDER BY name")->fetchAll();
$hostels = $db->query('SELECT id, name FROM hostels ORDER BY name')->fetchAll();
$staff = $db->query("SELECT id, name FROM staff WHERE status='active' ORDER BY name")->fetchAll();
$complaints = $db->query("SELECT c.*, s.name AS student_name, st.name AS staff_name, h.name AS hostel_name
FROM complaints c
LEFT JOIN students s ON s.id=c.student_id
LEFT JOIN staff st ON st.id=c.assigned_to
LEFT JOIN hostels h ON h.id=c.hostel_id
ORDER BY c.id DESC")->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/page_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Complaint Management</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#complaintModal">Submit Complaint</button>
</div>
<div class="card">
    <div class="card-body">
        <table class="table table-bordered datatable"><thead><tr><th>Student</th><th>Hostel</th><th>Category</th><th>Subject</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Action</th></tr></thead><tbody>
        <?php foreach($complaints as $c): ?>
            <tr class="<?= $c['priority']==='urgent' ? 'table-danger' : '' ?>">
                <td><?= e($c['student_name']) ?></td>
                <td><?= e($c['hostel_name']) ?></td>
                <td><?= e($c['category']) ?></td>
                <td><?= e($c['subject']) ?></td>
                <td><?= e($c['priority']) ?></td>
                <td><?= e($c['status']) ?></td>
                <td><?= e($c['staff_name']) ?></td>
                <td>
                    <?php if (Auth::hasRole(['super_admin', 'warden'])): ?>
                    <form method="post" class="mb-1 d-flex gap-1">
                        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="assign"><input type="hidden" name="complaint_id" value="<?= (int)$c['id'] ?>">
                        <select name="staff_id" class="form-select form-select-sm"><?php foreach($staff as $st): ?><option value="<?= (int)$st['id'] ?>"><?= e($st['name']) ?></option><?php endforeach; ?></select>
                        <button class="btn btn-sm btn-secondary">Assign</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" class="d-flex gap-1">
                        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="complaint_id" value="<?= (int)$c['id'] ?>">
                        <select name="status" class="form-select form-select-sm"><option>open</option><option>in_progress</option><option>resolved</option><option>closed</option></select>
                        <input name="resolution_note" class="form-control form-control-sm" placeholder="Resolution note">
                        <button class="btn btn-sm btn-primary">Update</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
</div>

<div class="modal fade" id="complaintModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5>Submit Complaint</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="create">
<label class="form-label">Student</label><select class="form-select select2" name="student_id" required><?php foreach($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['roll_number']) ?>)</option><?php endforeach; ?></select>
<label class="form-label mt-2">Hostel</label><select class="form-select" name="hostel_id" required><?php foreach($hostels as $h): ?><option value="<?= (int)$h['id'] ?>"><?= e($h['name']) ?></option><?php endforeach; ?></select>
<label class="form-label mt-2">Category</label><select class="form-select" name="category"><option>maintenance</option><option>hygiene</option><option>security</option><option>electrical</option><option>plumbing</option><option>other</option></select>
<label class="form-label mt-2">Priority</label><select class="form-select" name="priority"><option>low</option><option selected>medium</option><option>high</option><option>urgent</option></select>
<label class="form-label mt-2">Subject</label><input class="form-control" name="subject" required>
<label class="form-label mt-2">Description</label><textarea class="form-control" name="description" required></textarea>
</div><div class="modal-footer"><button class="btn btn-primary">Submit</button></div></form></div></div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/page_end.php'; ?>

