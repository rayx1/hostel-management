<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireRole(['super_admin', 'warden']);
$db = Database::getInstance();

if (is_post()) {
    Csrf::guard($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? '';
    if ($action === 'add_floor') {
        $db->prepare('INSERT INTO floors (hostel_id, floor_number, floor_label) VALUES (:hostel_id,:floor_number,:floor_label)')
           ->execute([':hostel_id'=>(int)$_POST['hostel_id'],':floor_number'=>(int)$_POST['floor_number'],':floor_label'=>trim($_POST['floor_label'])]);
        AuditLogger::log(Auth::id() ?? 0, 'rooms', 'create', 'Added floor');
    } elseif ($action === 'add_room') {
        $db->prepare('INSERT INTO rooms (floor_id, room_number, room_type, capacity, amenities, status) VALUES (:floor_id,:room_number,:room_type,:capacity,:amenities,:status)')
           ->execute([':floor_id'=>(int)$_POST['floor_id'],':room_number'=>trim($_POST['room_number']),':room_type'=>$_POST['room_type'],':capacity'=>(int)$_POST['capacity'],':amenities'=>trim($_POST['amenities']),':status'=>$_POST['status']]);
        $roomId = (int)$db->lastInsertId();
        for ($i=1; $i<=(int)$_POST['capacity']; $i++) {
            $db->prepare('INSERT INTO beds (room_id, bed_number, status) VALUES (:room_id,:bed_number,"vacant")')->execute([':room_id'=>$roomId,':bed_number'=>$i]);
        }
        AuditLogger::log(Auth::id() ?? 0, 'rooms', 'create', 'Added room '.$roomId);
    } elseif ($action === 'set_bed_status') {
        $db->prepare('UPDATE beds SET status=:status WHERE id=:id')->execute([':status'=>$_POST['status'],':id'=>(int)$_POST['bed_id']]);
        AuditLogger::log(Auth::id() ?? 0, 'rooms', 'update', 'Changed bed status ID '.(int)$_POST['bed_id']);
    } elseif ($action === 'set_room_status') {
        $db->prepare('UPDATE rooms SET status=:status WHERE id=:id')->execute([':status'=>$_POST['status'],':id'=>(int)$_POST['room_id']]);
        AuditLogger::log(Auth::id() ?? 0, 'rooms', 'update', 'Changed room status ID '.(int)$_POST['room_id']);
    }
    flash('success', 'Room module updated');
    redirect('/modules/rooms/index.php');
}

$hostels = $db->query('SELECT * FROM hostels ORDER BY id')->fetchAll();
$floors = $db->query('SELECT f.*, h.name AS hostel_name FROM floors f JOIN hostels h ON h.id=f.hostel_id ORDER BY f.hostel_id, f.floor_number')->fetchAll();

$roomGridSql = "SELECT r.id, r.room_number, r.status, f.floor_label, f.floor_number, h.name AS hostel_name,
SUM(CASE WHEN b.status='occupied' THEN 1 ELSE 0 END) AS occupied,
SUM(CASE WHEN b.status='vacant' THEN 1 ELSE 0 END) AS vacant,
COUNT(b.id) AS total
FROM rooms r
JOIN floors f ON f.id=r.floor_id
JOIN hostels h ON h.id=f.hostel_id
LEFT JOIN beds b ON b.room_id=r.id
GROUP BY r.id, r.room_number, r.status, f.floor_label, f.floor_number, h.name
ORDER BY h.id, f.floor_number, r.room_number";
$rooms = $db->query($roomGridSql)->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/page_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Rooms & Beds</h3>
    <div class="d-flex gap-2 no-print">
        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#floorModal">Add Floor</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roomModal">Add Room</button>
    </div>
</div>
<div class="row g-2 mb-3">
    <?php foreach ($rooms as $r):
        $tile='room-vacant';
        if ($r['status']==='maintenance') { $tile='room-maintenance'; }
        elseif ((int)$r['occupied']===0) { $tile='room-vacant'; }
        elseif ((int)$r['occupied']<(int)$r['total']) { $tile='room-partial'; }
        else { $tile='room-full'; }
    ?>
    <div class="col-md-3">
        <div class="room-tile <?= e($tile) ?>">
            <div class="d-flex justify-content-between">
                <strong><?= e($r['room_number']) ?></strong>
                <span><?= e((string)$r['occupied']) ?>/<?= e((string)$r['total']) ?></span>
            </div>
            <div class="small"><?= e($r['hostel_name']) ?> | <?= e($r['floor_label']) ?></div>
            <div class="mt-2 d-flex gap-1 no-print">
                <a href="<?= BASE_URL ?>/modules/rooms/room.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-light">Beds</a>
                <form method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="set_room_status"><input type="hidden" name="room_id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="status" value="<?= $r['status']==='maintenance'?'active':'maintenance' ?>">
                    <button class="btn btn-sm btn-dark" type="submit"><?= $r['status']==='maintenance'?'Activate':'Maintenance' ?></button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="floorModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5>Add Floor</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="add_floor">
<label class="form-label">Hostel</label><select class="form-select" name="hostel_id" required><?php foreach($hostels as $h): ?><option value="<?= (int)$h['id'] ?>"><?= e($h['name']) ?></option><?php endforeach; ?></select>
<label class="form-label mt-2">Floor Number</label><input class="form-control" type="number" name="floor_number" required>
<label class="form-label mt-2">Label</label><input class="form-control" name="floor_label" required>
</div><div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div></div>

<div class="modal fade" id="roomModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5>Add Room</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
<input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="add_room">
<label class="form-label">Floor</label><select class="form-select" name="floor_id" required><?php foreach($floors as $f): ?><option value="<?= (int)$f['id'] ?>"><?= e($f['hostel_name']) ?> - <?= e($f['floor_label']) ?></option><?php endforeach; ?></select>
<label class="form-label mt-2">Room Number</label><input class="form-control" name="room_number" required>
<label class="form-label mt-2">Room Type</label><select class="form-select" name="room_type"><option>single</option><option>double</option><option>triple</option><option>quad</option></select>
<label class="form-label mt-2">Capacity</label><input class="form-control" type="number" min="1" max="10" name="capacity" required>
<label class="form-label mt-2">Amenities</label><textarea class="form-control" name="amenities"></textarea>
<label class="form-label mt-2">Status</label><select class="form-select" name="status"><option value="active">active</option><option value="maintenance">maintenance</option><option value="closed">closed</option></select>
</div><div class="modal-footer"><button class="btn btn-primary">Save</button></div></form></div></div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/page_end.php'; ?>

