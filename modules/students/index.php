<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireRole(['super_admin', 'class_coordinator', 'warden']);
$db = Database::getInstance();

$students = $db->query('SELECT * FROM students ORDER BY id DESC')->fetchAll();
require_once dirname(__DIR__, 2) . '/includes/page_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Students</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal">Add Student</button>
</div>
<div class="card">
    <div class="card-body">
        <table class="table table-bordered datatable">
            <thead>
            <tr>
                <th>Name</th><th>Roll</th><th>Course</th><th>Year</th><th>Phone</th><th>Status</th><th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><?= e($s['name']) ?></td>
                    <td><?= e($s['roll_number']) ?></td>
                    <td><?= e($s['course']) ?> / <?= e($s['branch']) ?></td>
                    <td><?= e((string)$s['year']) ?></td>
                    <td><?= e($s['phone']) ?></td>
                    <td><span class="badge bg-<?= $s['status']==='active'?'success':'warning' ?>"><?= e($s['status']) ?></span></td>
                    <td class="d-flex gap-2">
                        <a class="btn btn-sm btn-outline-info" href="<?= BASE_URL ?>/modules/students/view.php?id=<?= (int) $s['id'] ?>">View</a>
                        <button class="btn btn-sm btn-outline-primary edit-student" data-student='<?= e(json_encode($s)) ?>'>Edit</button>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/modules/students/status.php?id=<?= (int)$s['id'] ?>">Toggle</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="studentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Student Form</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="studentForm" method="post" enctype="multipart/form-data" action="<?= BASE_URL ?>/modules/students/save.php">
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
            <input type="hidden" name="id" id="id">
            <div class="row g-2">
                <div class="col-md-6"><label class="form-label">Name*</label><input name="name" id="name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Roll Number*</label><input name="roll_number" id="roll_number" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Gender*</label><select name="gender" id="gender" class="form-select" required><option value="male">Male</option><option value="female">Female</option></select></div>
                <div class="col-md-4"><label class="form-label">Course*</label><input name="course" id="course" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Branch*</label><input name="branch" id="branch" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Year*</label><input type="number" name="year" id="year" class="form-control" min="1" max="6" required></div>
                <div class="col-md-4"><label class="form-label">Phone*</label><input name="phone" id="phone" class="form-control" pattern="[6-9][0-9]{9}" required></div>
                <div class="col-md-4"><label class="form-label">Email*</label><input type="email" name="email" id="email" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Parent Name*</label><input name="parent_name" id="parent_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Parent Phone*</label><input name="parent_phone" id="parent_phone" class="form-control" pattern="[6-9][0-9]{9}" required></div>
                <div class="col-md-6"><label class="form-label">Parent Email</label><input type="email" name="parent_email" id="parent_email" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Joining Date</label><input type="date" name="joining_date" id="joining_date" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">ID Proof Type</label><input name="id_proof_type" id="id_proof_type" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">ID Proof Number</label><input name="id_proof_number" id="id_proof_number" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Photo</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
                <div class="col-md-6"><label class="form-label">ID Proof File</label><input type="file" name="id_proof_file" class="form-control" accept="image/*,.pdf"></div>
                <div class="col-12"><label class="form-label">Address*</label><textarea name="address" id="address" class="form-control" required></textarea></div>
            </div>
            <div id="studentErrors" class="mt-2 text-danger small"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Student</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
$(function(){
    $('.edit-student').on('click', function(){
        const s = JSON.parse($(this).attr('data-student'));
        Object.keys(s).forEach(k=>{ if($('#'+k).length){ $('#'+k).val(s[k]); }});
        $('#studentModal').modal('show');
    });
    $('#studentForm').on('submit', function(e){
        e.preventDefault();
        const fd = new FormData(this);
        $('#studentErrors').html('');
        $.ajax({
            url: $(this).attr('action'), method: 'POST', data: fd, processData:false, contentType:false,
            success: function(r){
                if(r.success){ window.location.reload(); return; }
                if(r.errors){
                    const html = Object.values(r.errors).map(x=>`<div>${x}</div>`).join('');
                    $('#studentErrors').html(html);
                } else { $('#studentErrors').text(r.message || 'Failed'); }
            },
            error: function(xhr){ $('#studentErrors').text(xhr.responseJSON?.message || 'Validation failed'); }
        });
    });
});
</script>
<?php require_once dirname(__DIR__, 2) . '/includes/page_end.php'; ?>

