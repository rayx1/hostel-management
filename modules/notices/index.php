<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireRole(['super_admin', 'warden']);
$db = Database::getInstance();

if (is_post()) {
    Csrf::guard($_POST['csrf_token'] ?? null);
    $db->prepare('INSERT INTO notices (hostel_id, title, content, target, posted_by, expiry_date) VALUES (:hostel_id,:title,:content,:target,:posted_by,:expiry_date)')
       ->execute([':hostel_id'=>$_POST['hostel_id'] !== '' ? (int)$_POST['hostel_id'] : null, ':title'=>trim($_POST['title']), ':content'=>trim($_POST['content']), ':target'=>$_POST['target'], ':posted_by'=>Auth::id(), ':expiry_date'=>$_POST['expiry_date'] ?: null]);
    AuditLogger::log(Auth::id() ?? 0, 'notices', 'create', 'Notice posted');
    flash('success', 'Notice posted');
    redirect('/modules/notices/index.php');
}

$hostels = $db->query('SELECT id, name FROM hostels ORDER BY name')->fetchAll();
$notices = $db->query('SELECT n.*, h.name AS hostel_name, u.name AS posted_by_name FROM notices n LEFT JOIN hostels h ON h.id=n.hostel_id LEFT JOIN users u ON u.id=n.posted_by ORDER BY n.id DESC')->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/page_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Notices</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#noticeModal">Post Notice</button>
</div>
<div class="card"><div class="card-body">
<table class="table table-bordered datatable"><thead><tr><th>Title</th><th>Hostel</th><th>Target</th><th>Expiry</th><th>Status</th><th>Posted By</th></tr></thead><tbody>
<?php foreach($notices as $n): $active = empty($n['expiry_date']) || $n['expiry_date'] >= date('Y-m-d'); ?>
<tr>
<td><strong><?= e($n['title']) ?></strong><div class="small text-muted"><?= e($n['content']) ?></div></td>
<td><?= e($n['hostel_name'] ?: 'All') ?></td>
<td><?= e($n['target']) ?></td>
<td><?= e($n['expiry_date']) ?></td>
<td><span class="badge bg-<?= $active?'success':'secondary' ?>"><?= $active?'Active':'Expired' ?></span></td>
<td><?= e($n['posted_by_name']) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
<div class="modal fade" id="noticeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5>Post Notice</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
<label class="form-label">Hostel (Optional)</label><select class="form-select" name="hostel_id"><option value="">All</option><?php foreach($hostels as $h): ?><option value="<?= (int)$h['id'] ?>"><?= e($h['name']) ?></option><?php endforeach; ?></select>
<label class="form-label mt-2">Title</label><input class="form-control" name="title" required>
<label class="form-label mt-2">Content</label><textarea class="form-control" name="content" required></textarea>
<label class="form-label mt-2">Target</label><select class="form-select" name="target"><option>all</option><option>boys</option><option>girls</option></select>
<label class="form-label mt-2">Expiry Date</label><input type="date" class="form-control" name="expiry_date">
</div><div class="modal-footer"><button class="btn btn-primary">Post</button></div></form></div></div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/page_end.php'; ?>

