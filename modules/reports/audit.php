<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireRole(['super_admin']);
$db = Database::getInstance();

$userId = $_GET['user_id'] ?? '';
$module = $_GET['module'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$sql = 'SELECT al.*, u.name AS user_name FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id WHERE 1=1';
$params = [];
if ($userId !== '') { $sql .= ' AND al.user_id=:user_id'; $params[':user_id']=(int)$userId; }
if ($module !== '') { $sql .= ' AND al.module=:module'; $params[':module']=$module; }
if ($from !== '') { $sql .= ' AND DATE(al.created_at) >= :from'; $params[':from']=$from; }
if ($to !== '') { $sql .= ' AND DATE(al.created_at) <= :to'; $params[':to']=$to; }
$sql .= ' ORDER BY al.id DESC LIMIT 500';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
$users = $db->query('SELECT id, name FROM users ORDER BY name')->fetchAll();
$modules = $db->query('SELECT DISTINCT module FROM audit_logs ORDER BY module')->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/page_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><h3 class="mb-0">Audit Logs</h3></div>
<form class="card mb-3"><div class="card-body row g-2 align-items-end">
<div class="col-md-3"><label class="form-label">User</label><select class="form-select" name="user_id"><option value="">All</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (string)$userId===(string)$u['id']?'selected':'' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label">Module</label><select class="form-select" name="module"><option value="">All</option><?php foreach($modules as $m): ?><option value="<?= e($m['module']) ?>" <?= $module===$m['module']?'selected':'' ?>><?= e($m['module']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><label class="form-label">From</label><input type="date" class="form-control" name="from" value="<?= e($from) ?>"></div>
<div class="col-md-2"><label class="form-label">To</label><input type="date" class="form-control" name="to" value="<?= e($to) ?>"></div>
<div class="col-md-2"><button class="btn btn-primary">Filter</button></div>
</div></form>
<div class="card"><div class="card-body"><table class="table table-sm datatable"><thead><tr><th>User</th><th>Module</th><th>Action</th><th>Description</th><th>IP</th><th>Time</th></tr></thead><tbody>
<?php foreach($logs as $l): ?><tr><td><?= e($l['user_name']) ?></td><td><?= e($l['module']) ?></td><td><?= e($l['action']) ?></td><td><?= e($l['description']) ?></td><td><?= e($l['ip_address']) ?></td><td><?= e($l['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/page_end.php'; ?>

