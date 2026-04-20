<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireRole(['super_admin', 'class_coordinator', 'warden']);
Csrf::guard($_POST['csrf_token'] ?? null);

$db = Database::getInstance();
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

$data = [
    'name' => trim($_POST['name'] ?? ''),
    'roll_number' => trim($_POST['roll_number'] ?? ''),
    'gender' => trim($_POST['gender'] ?? ''),
    'course' => trim($_POST['course'] ?? ''),
    'branch' => trim($_POST['branch'] ?? ''),
    'year' => (int) ($_POST['year'] ?? 0),
    'phone' => trim($_POST['phone'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
    'parent_name' => trim($_POST['parent_name'] ?? ''),
    'parent_phone' => trim($_POST['parent_phone'] ?? ''),
    'parent_email' => trim($_POST['parent_email'] ?? ''),
    'address' => trim($_POST['address'] ?? ''),
    'id_proof_type' => trim($_POST['id_proof_type'] ?? ''),
    'id_proof_number' => trim($_POST['id_proof_number'] ?? ''),
    'joining_date' => trim($_POST['joining_date'] ?? '') ?: null,
];

$errors = [];
foreach (['name','roll_number','gender','course','branch','year','phone','email','parent_name','parent_phone','address'] as $f) {
    if (empty($data[$f])) { $errors[$f] = ucfirst(str_replace('_', ' ', $f)) . ' is required'; }
}
if ($data['phone'] && !Validator::indianMobile($data['phone'])) {
    $errors['phone'] = 'Phone must be 10-digit Indian mobile number';
}
if ($data['parent_phone'] && !Validator::indianMobile($data['parent_phone'])) {
    $errors['parent_phone'] = 'Parent phone must be 10-digit Indian mobile number';
}
if ($data['email'] && !Validator::email($data['email'])) {
    $errors['email'] = 'Enter valid email address';
}
if ($data['phone'] && Validator::duplicateInSystem('phone', $data['phone'], $id, null)) {
    $stmt = $db->prepare('SELECT id FROM students WHERE phone=:v' . ($id ? ' AND id != :id' : ''));
    $params = [':v' => $data['phone']];
    if ($id) { $params[':id'] = $id; }
    $stmt->execute($params);
    if ($stmt->fetch()) {
        $errors['phone'] = 'This mobile number is already registered';
    } else {
        $errors['phone'] = 'Mobile number already exists in the system';
    }
}
if ($data['email'] && Validator::duplicateInSystem('email', $data['email'], $id, null)) {
    $errors['email'] = 'Email address already registered';
}

foreach (['roll_number','parent_phone','id_proof_number'] as $uq) {
    if (!$data[$uq]) { continue; }
    $sql = "SELECT id FROM students WHERE {$uq}=:v" . ($id ? ' AND id != :id' : '') . ' LIMIT 1';
    $stmt = $db->prepare($sql);
    $params = [':v' => $data[$uq]];
    if ($id) { $params[':id'] = $id; }
    $stmt->execute($params);
    if ($stmt->fetch()) {
        $errors[$uq] = ucfirst(str_replace('_',' ',$uq)) . ' already exists';
    }
}

if ($errors) {
    Response::json(['success' => false, 'errors' => $errors], 422);
}

$photo = null;
$idProofFile = null;
try {
    if (!empty($_FILES['photo']['name'])) {
        $photo = FileUpload::save($_FILES['photo'], 'uploads/students', ['image/jpeg','image/png','image/webp']);
    }
    if (!empty($_FILES['id_proof_file']['name'])) {
        $idProofFile = FileUpload::save($_FILES['id_proof_file'], 'uploads/ids', ['image/jpeg','image/png','application/pdf']);
    }
} catch (Throwable $e) {
    Response::json(['success' => false, 'message' => $e->getMessage()], 422);
}

if ($id) {
    $sql = 'UPDATE students SET name=:name, roll_number=:roll_number, gender=:gender, course=:course, branch=:branch, year=:year, phone=:phone, email=:email, parent_name=:parent_name, parent_phone=:parent_phone, parent_email=:parent_email, address=:address, id_proof_type=:id_proof_type, id_proof_number=:id_proof_number, joining_date=:joining_date';
    if ($photo) { $sql .= ', photo=:photo'; }
    if ($idProofFile) { $sql .= ', id_proof_file=:id_proof_file'; }
    $sql .= ' WHERE id=:id';
    $stmt = $db->prepare($sql);
    $params = [
        ':name'=>$data['name'],':roll_number'=>$data['roll_number'],':gender'=>$data['gender'],':course'=>$data['course'],':branch'=>$data['branch'],':year'=>$data['year'],
        ':phone'=>$data['phone'],':email'=>$data['email'],':parent_name'=>$data['parent_name'],':parent_phone'=>$data['parent_phone'],':parent_email'=>$data['parent_email'] ?: null,
        ':address'=>$data['address'],':id_proof_type'=>$data['id_proof_type'] ?: null,':id_proof_number'=>$data['id_proof_number'] ?: null,':joining_date'=>$data['joining_date'],':id'=>$id,
    ];
    if ($photo) { $params[':photo']=$photo; }
    if ($idProofFile) { $params[':id_proof_file']=$idProofFile; }
    $stmt->execute($params);
    AuditLogger::log(Auth::id() ?? 0, 'students', 'update', 'Updated student ID ' . $id);
    Response::json(['success'=>true,'message'=>'Student updated']);
}

$stmt = $db->prepare('INSERT INTO students (name, roll_number, gender, course, branch, year, phone, email, parent_name, parent_phone, parent_email, address, id_proof_type, id_proof_number, photo, id_proof_file, joining_date)
VALUES (:name,:roll_number,:gender,:course,:branch,:year,:phone,:email,:parent_name,:parent_phone,:parent_email,:address,:id_proof_type,:id_proof_number,:photo,:id_proof_file,:joining_date)');
$stmt->execute([
    ':name'=>$data['name'], ':roll_number'=>$data['roll_number'], ':gender'=>$data['gender'], ':course'=>$data['course'], ':branch'=>$data['branch'], ':year'=>$data['year'],
    ':phone'=>$data['phone'], ':email'=>$data['email'], ':parent_name'=>$data['parent_name'], ':parent_phone'=>$data['parent_phone'], ':parent_email'=>$data['parent_email'] ?: null,
    ':address'=>$data['address'], ':id_proof_type'=>$data['id_proof_type'] ?: null, ':id_proof_number'=>$data['id_proof_number'] ?: null,
    ':photo'=>$photo, ':id_proof_file'=>$idProofFile, ':joining_date'=>$data['joining_date']
]);
$newId = (int)$db->lastInsertId();
AuditLogger::log(Auth::id() ?? 0, 'students', 'create', 'Created student ID ' . $newId);
Response::json(['success'=>true,'message'=>'Student created']);

