<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireRole(['super_admin', 'warden']);
$db = Database::getInstance();

if (is_post()) {
    Csrf::guard($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        $dupStmt = $db->prepare('SELECT id FROM staff WHERE (phone=:phone OR email=:email) ' . ($id ? 'AND id != :id' : '') . ' LIMIT 1');
        $params = [':phone'=>$phone, ':email'=>$email];
        if ($id) { $params[':id']=$id; }
        $dupStmt->execute($params);
        $dup = $dupStmt->fetch();

        if (Validator::duplicateInSystem('phone', $phone)) {
            flash('error', 'Mobile number already exists in the system');
            redirect('/modules/staff/index.php');
        }
        if (Validator::duplicateInSystem('email', $email)) {
            flash('error', 'Email address already registered');
            redirect('/modules/staff/index.php');
        }

        if ($dup) {
            flash('error', 'Staff phone/email already exists');
            redirect('/modules/staff/index.php');
        }

        if ($id) {
            $db->prepare('UPDATE staff SET name=:name, role=:role, hostel_id=:hostel_id, phone=:phone, email=:email, shift=:shift, joining_date=:joining_date, status=:status WHERE id=:id')
               ->execute([':name'=>trim($_POST['name']),':role'=>$_POST['role'],':hostel_id'=>(int)$_POST['hostel_id'],':phone'=>$phone,':email'=>$email,':shift'=>$_POST['shift'],':joining_date'=>$_POST['joining_date'] ?: null,':status'=>$_POST['status'],':id'=>$id]);
            AuditLogger::log(Auth::id() ?? 0, 'staff', 'update', 'Updated staff '.$id);
            flash('success', 'Staff updated');
        } else {
            $db->prepare('INSERT INTO staff (name, role, hostel_id, phone, email, shift, joining_date, status) VALUES (:name,:role,:hostel_id,:phone,:email,:shift,:joining_date,:status)')
               ->execute([':name'=>trim($_POST['name']),':role'=>$_POST['role'],':hostel_id'=>(int)$_POST['hostel_id'],':phone'=>$phone,':email'=>$email,':shift'=>$_POST['shift'],':joining_date'=>$_POST['joining_date'] ?: null,':status'=>$_POST['status']]);
            AuditLogger::log(Auth::id() ?? 0, 'staff', 'create', 'Created staff '.$db->lastInsertId());
            flash('success', 'Staff added');
        }
    }

    if ($action === 'toggle') {
        $id = (int) $_POST['id'];
        $db->prepare('UPDATE staff SET status=CASE WHEN status="active" THEN "inactive" ELSE "active" END WHERE id=:id')->execute([':id'=>$id]);
        AuditLogger::log(Auth::id() ?? 0, 'staff', 'status', 'Toggled staff '.$id);
        flash('success', 'Staff status changed');
    }

    redirect('/modules/staff/index.php');
}

$hostels = $db->query('SELECT id, name FROM hostels ORDER BY name')->fetchAll();
$staffRows = $db->query('SELECT st.*, h.name AS hostel_name FROM staff st LEFT JOIN hostels h ON h.id=st.hostel_id ORDER BY st.id DESC')->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/page_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Staff Management</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#staffModal">Add Staff</button>
</div>
<div class="card"><div class="card-body">
<table class="table table-bordered datatable"><thead><tr><th>Name</th><th>Role</th><th>Hostel</th><th>Phone</th><th>Email</th><th>Shift</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach($staffRows as $s): ?>
<tr>
<td><?= e($s['name']) ?></td><td><?= e($s['role']) ?></td><td><?= e($s['hostel_name']) ?></td><td><?= e($s['phone']) ?></td><td><?= e($s['email']) ?></td><td><?= e($s['shift']) ?></td><td><?= e($s['status']) ?></td>
<td>
    <button class="btn btn-sm btn-outline-primary edit-staff" data-staff='<?= e(json_encode($s)) ?>'>Edit</button>
    <form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-outline-secondary">Toggle</button></form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
<div class="modal fade" id="staffModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5>Staff Form</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="id">
<label class="form-label">Name</label><input class="form-control" name="name" id="name" required>
<label class="form-label mt-2">Role</label><select class="form-select" name="role" id="role"><option>warden</option><option>caretaker</option><option>security</option><option>cleaner</option><option>admin</option></select>
<label class="form-label mt-2">Hostel</label><select class="form-select" name="hostel_id" id="hostel_id"><?php foreach($hostels as $h): ?><option value="<?= (int)$h['id'] ?>"><?= e($h['name']) ?></option><?php endforeach; ?></select>
<div class="row g-2 mt-1"><div class="col"><label class="form-label">Phone</label><input class="form-control" name="phone" id="phone" pattern="[6-9][0-9]{9}" required></div><div class="col"><label class="form-label">Email</label><input class="form-control" type="email" name="email" id="email" required></div></div>
<div class="row g-2 mt-1"><div class="col"><label class="form-label">Shift</label><select class="form-select" name="shift" id="shift"><option>morning</option><option>evening</option><option>night</option><option>full_day</option></select></div><div class="col"><label class="form-label">Joining</label><input type="date" class="form-control" name="joining_date" id="joining_date"></div></div>
<label class="form-label mt-2">Status</label><select class="form-select" name="status" id="status"><option>active</option><option>inactive</option></select>
</div><div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div></div>
<script>
$(function(){
    $('.edit-staff').on('click', function(){
        const s = JSON.parse($(this).attr('data-staff'));
        Object.keys(s).forEach(k=>{ if($('#'+k).length){ $('#'+k).val(s[k]); } });
        $('#staffModal').modal('show');
    });
});
</script>
<?php require_once dirname(__DIR__, 2) . '/includes/page_end.php'; ?>

