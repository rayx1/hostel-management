<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireLogin();
$db = Database::getInstance();
$user = Auth::user();
$role = $user['role'];

if (is_post()) {
    Csrf::guard($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'apply' && Auth::hasRole(['super_admin', 'class_coordinator', 'staff'])) {
        $studentId = (int) $_POST['student_id'];
        $from = $_POST['from_date'] ?? '';
        $to = $_POST['to_date'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        $leaveType = $_POST['leave_type'] ?? 'other';
        $destination = trim($_POST['destination'] ?? '');
        $contact = trim($_POST['contact_during_leave'] ?? '');

        $pending = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE student_id=:sid AND final_status='pending'");
        $pending->execute([':sid'=>$studentId]);
        if ((int)$pending->fetchColumn() > 0) {
            flash('error', 'Student already has a pending leave request');
            redirect('/modules/leave/index.php');
        }

        $student = $db->prepare('SELECT branch FROM students WHERE id=:id');
        $student->execute([':id'=>$studentId]);
        $branch = $student->fetchColumn();
        $cc = null;
        if ($branch) {
            $ccStmt = $db->prepare("SELECT id FROM users WHERE role='class_coordinator' AND department=:department AND status='active' LIMIT 1");
            $ccStmt->execute([':department'=>$branch]);
            $cc = $ccStmt->fetchColumn();
        }

        $db->prepare('INSERT INTO leave_requests (student_id, from_date, to_date, reason, leave_type, destination, contact_during_leave, cc_id, final_status) VALUES (:student_id,:from_date,:to_date,:reason,:leave_type,:destination,:contact,:cc_id,"pending")')
           ->execute([
               ':student_id'=>$studentId, ':from_date'=>$from, ':to_date'=>$to, ':reason'=>$reason,
               ':leave_type'=>$leaveType, ':destination'=>$destination, ':contact'=>$contact, ':cc_id'=>$cc ?: null,
           ]);
        $leaveId = (int)$db->lastInsertId();
        AuditLogger::log(Auth::id() ?? 0, 'leave', 'create', 'Created leave request ID '.$leaveId);
        flash('success', 'Leave request submitted');
        redirect('/modules/leave/index.php');
    }

    if ($action === 'cc_action' && $role === 'class_coordinator') {
        $leaveId = (int) $_POST['leave_id'];
        $decision = $_POST['decision'];
        $remarks = trim($_POST['remarks'] ?? '');

        $stmt = $db->prepare("SELECT lr.id FROM leave_requests lr JOIN students s ON s.id=lr.student_id WHERE lr.id=:id AND lr.cc_status='pending' AND lr.final_status='pending' AND s.branch=:dept");
        $stmt->execute([':id'=>$leaveId, ':dept'=>$user['department']]);
        if ($stmt->fetch()) {
            $final = $decision === 'approved' ? 'cc_approved' : 'cc_rejected';
            $db->prepare('UPDATE leave_requests SET cc_status=:status, cc_remarks=:remarks, cc_action_at=NOW(), final_status=:final WHERE id=:id')
               ->execute([':status'=>$decision, ':remarks'=>$remarks, ':final'=>$final, ':id'=>$leaveId]);
            $db->prepare('INSERT INTO leave_approval_log (leave_id, approver_id, approver_role, action, remarks) VALUES (:leave_id,:approver_id,"class_coordinator",:action,:remarks)')
               ->execute([':leave_id'=>$leaveId, ':approver_id'=>$user['id'], ':action'=>$decision, ':remarks'=>$remarks]);
            AuditLogger::log(Auth::id() ?? 0, 'leave', 'update', 'CC '.$decision.' leave '.$leaveId);
            flash('success', 'Leave updated');
        }
        redirect('/modules/leave/index.php');
    }

    if ($action === 'warden_action' && $role === 'warden') {
        $leaveId = (int) $_POST['leave_id'];
        $decision = $_POST['decision'];
        $remarks = trim($_POST['remarks'] ?? '');

        $stmt = $db->prepare("SELECT lr.id FROM leave_requests lr
            JOIN allotments a ON a.student_id=lr.student_id AND a.status='active'
            WHERE lr.id=:id AND lr.final_status='cc_approved' AND lr.cc_status='approved' AND lr.warden_status='pending' AND a.hostel_id=:hostel_id");
        $stmt->execute([':id'=>$leaveId, ':hostel_id'=>$user['hostel_id']]);
        if ($stmt->fetch()) {
            $final = $decision === 'approved' ? 'warden_approved' : 'warden_rejected';
            $db->prepare('UPDATE leave_requests SET warden_id=:wid, warden_status=:status, warden_remarks=:remarks, warden_action_at=NOW(), final_status=:final WHERE id=:id')
               ->execute([':wid'=>$user['id'], ':status'=>$decision, ':remarks'=>$remarks, ':final'=>$final, ':id'=>$leaveId]);
            $db->prepare('INSERT INTO leave_approval_log (leave_id, approver_id, approver_role, action, remarks) VALUES (:leave_id,:approver_id,"warden",:action,:remarks)')
               ->execute([':leave_id'=>$leaveId, ':approver_id'=>$user['id'], ':action'=>$decision, ':remarks'=>$remarks]);
            AuditLogger::log(Auth::id() ?? 0, 'leave', 'update', 'Warden '.$decision.' leave '.$leaveId);
            flash('success', 'Leave updated');
        }
        redirect('/modules/leave/index.php');
    }

    if ($action === 'cancel') {
        $leaveId = (int) $_POST['leave_id'];
        $stmt = $db->prepare("SELECT id FROM leave_requests WHERE id=:id AND final_status='pending' AND cc_status='pending'");
        $stmt->execute([':id'=>$leaveId]);
        if ($stmt->fetch()) {
            $db->prepare('DELETE FROM leave_requests WHERE id=:id')->execute([':id'=>$leaveId]);
            AuditLogger::log(Auth::id() ?? 0, 'leave', 'delete', 'Cancelled leave request '.$leaveId);
            flash('success', 'Leave request cancelled');
        }
        redirect('/modules/leave/index.php');
    }
}

$students = $db->query("SELECT id, name, roll_number, branch FROM students WHERE status='active' ORDER BY name")->fetchAll();

if ($role === 'class_coordinator') {
    $listStmt = $db->prepare("SELECT lr.*, s.name AS student_name, s.roll_number, s.branch
        FROM leave_requests lr
        JOIN students s ON s.id=lr.student_id
        WHERE s.branch=:dept AND lr.final_status='pending' AND lr.cc_status='pending'
        ORDER BY lr.id DESC");
    $listStmt->execute([':dept'=>$user['department']]);
    $myPending = $listStmt->fetchAll();
} elseif ($role === 'warden') {
    $listStmt = $db->prepare("SELECT lr.*, s.name AS student_name, s.roll_number, s.branch
        FROM leave_requests lr
        JOIN students s ON s.id=lr.student_id
        JOIN allotments a ON a.student_id=s.id AND a.status='active'
        WHERE lr.final_status='cc_approved' AND lr.cc_status='approved' AND lr.warden_status='pending' AND a.hostel_id=:hostel
        ORDER BY lr.id DESC");
    $listStmt->execute([':hostel'=>$user['hostel_id']]);
    $myPending = $listStmt->fetchAll();
} else {
    $myPending = [];
}

$allLeaves = $db->query("SELECT lr.*, s.name AS student_name, s.roll_number
FROM leave_requests lr JOIN students s ON s.id=lr.student_id ORDER BY lr.id DESC LIMIT 200")->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/page_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Leave Management</h3>
    <?php if (Auth::hasRole(['super_admin', 'class_coordinator', 'staff'])): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#leaveModal">Submit Leave</button>
    <?php endif; ?>
</div>
<div class="row g-3 mb-3">
    <?php if ($role === 'class_coordinator'): ?><div class="col-md-4"><div class="card"><div class="card-body"><h6>Leaves Pending Your Approval</h6><h3><?= e((string)count($myPending)) ?></h3></div></div></div><?php endif; ?>
    <?php if ($role === 'warden'): ?><div class="col-md-4"><div class="card"><div class="card-body"><h6>Leaves Pending Your Approval</h6><h3><?= e((string)count($myPending)) ?></h3></div></div></div><?php endif; ?>
</div>
<?php if (($role === 'class_coordinator' || $role === 'warden') && $myPending): ?>
<div class="card mb-3"><div class="card-header">Action Queue</div><div class="card-body">
<table class="table table-bordered"><thead><tr><th>Student</th><th>Dates</th><th>Type</th><th>Reason</th><th>Action</th></tr></thead><tbody>
<?php foreach($myPending as $p): ?>
<tr>
    <td><?= e($p['student_name']) ?> (<?= e($p['roll_number']) ?>)</td>
    <td><?= e($p['from_date']) ?> to <?= e($p['to_date']) ?></td>
    <td><?= e($p['leave_type']) ?></td>
    <td><?= e($p['reason']) ?></td>
    <td>
        <form method="post" class="d-flex gap-1 align-items-center">
            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="<?= $role==='class_coordinator'?'cc_action':'warden_action' ?>">
            <input type="hidden" name="leave_id" value="<?= (int)$p['id'] ?>">
            <input name="remarks" class="form-control form-control-sm" placeholder="Remarks" required>
            <button class="btn btn-sm btn-success" name="decision" value="approved">Approve</button>
            <button class="btn btn-sm btn-danger" name="decision" value="rejected">Reject</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Leave Requests</div>
    <div class="card-body">
        <table class="table table-sm datatable"><thead><tr><th>Student</th><th>From</th><th>To</th><th>Type</th><th>Final Status</th><th>Timeline</th><th>Cancel</th></tr></thead><tbody>
        <?php foreach($allLeaves as $l): ?>
            <tr>
                <td><?= e($l['student_name']) ?></td>
                <td><?= e($l['from_date']) ?></td>
                <td><?= e($l['to_date']) ?></td>
                <td><?= e($l['leave_type']) ?></td>
                <td><span class="badge bg-secondary"><?= e($l['final_status']) ?></span></td>
                <td>
                    <div class="timeline-step done">Submitted</div>
                    <div class="timeline-step <?= $l['cc_status']==='approved'?'done':($l['cc_status']==='rejected'?'rejected':'') ?>">CC: <?= e($l['cc_status']) ?></div>
                    <div class="timeline-step <?= $l['warden_status']==='approved'?'done':($l['warden_status']==='rejected'?'rejected':'') ?>">Warden: <?= e($l['warden_status']) ?></div>
                </td>
                <td>
                    <?php if ($l['final_status']==='pending' && $l['cc_status']==='pending'): ?>
                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="leave_id" value="<?= (int)$l['id'] ?>"><button class="btn btn-sm btn-outline-danger">Cancel</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
</div>

<div class="modal fade" id="leaveModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5>Submit Leave</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="apply">
<label class="form-label">Student</label><select class="form-select select2" name="student_id" required><?php foreach($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['roll_number']) ?> / <?= e($s['branch']) ?>)</option><?php endforeach; ?></select>
<label class="form-label mt-2">Leave Type</label><select class="form-select" name="leave_type"><option value="home">home</option><option value="medical">medical</option><option value="emergency">emergency</option><option value="event">event</option><option value="other">other</option></select>
<div class="row g-2 mt-1"><div class="col"><label class="form-label">From</label><input type="date" class="form-control" name="from_date" required></div><div class="col"><label class="form-label">To</label><input type="date" class="form-control" name="to_date" required></div></div>
<label class="form-label mt-2">Destination</label><input class="form-control" name="destination" required>
<label class="form-label mt-2">Contact During Leave</label><input class="form-control" name="contact_during_leave" pattern="[6-9][0-9]{9}" required>
<label class="form-label mt-2">Reason</label><textarea class="form-control" name="reason" required></textarea>
</div><div class="modal-footer"><button class="btn btn-primary">Submit</button></div></form></div></div></div>

<?php require_once dirname(__DIR__, 2) . '/includes/page_end.php'; ?>

