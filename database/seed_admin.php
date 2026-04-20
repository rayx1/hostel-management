<?php

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('Run from CLI only.');
}

$db = Database::getInstance();

$email = 'admin@hostel.local';
$phone = '9000000000';
$password = password_hash('Admin@123', PASSWORD_DEFAULT);

$stmt = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $email]);
if ($stmt->fetch()) {
    echo "Admin user already exists.\n";
    exit;
}

$db->prepare('INSERT INTO users (name, email, phone, password, role, status) VALUES (:name, :email, :phone, :password, :role, :status)')
   ->execute([
       ':name' => 'Super Admin',
       ':email' => $email,
       ':phone' => $phone,
       ':password' => $password,
       ':role' => 'super_admin',
       ':status' => 'active',
   ]);

echo "Created admin user: admin@hostel.local / Admin@123\n";

