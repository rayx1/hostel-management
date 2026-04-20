<?php

$user = Auth::user();
$role = $user['role'] ?? '';
$menus = [
    'super_admin' => [
        'Dashboard' => '/modules/dashboard/index.php',
        'Students' => '/modules/students/index.php',
        'Rooms & Beds' => '/modules/rooms/index.php',
        'Allotment' => '/modules/allotment/index.php',
        'Leave' => '/modules/leave/index.php',
        'Complaints' => '/modules/complaints/index.php',
        'Visitors' => '/modules/visitors/index.php',
        'Staff' => '/modules/staff/index.php',
        'Notices' => '/modules/notices/index.php',
        'Reports' => '/modules/reports/index.php',
        'Audit Logs' => '/modules/reports/audit.php',
    ],
    'warden' => [
        'Dashboard' => '/modules/dashboard/index.php',
        'Rooms & Beds' => '/modules/rooms/index.php',
        'Allotment' => '/modules/allotment/index.php',
        'Leave' => '/modules/leave/index.php',
        'Complaints' => '/modules/complaints/index.php',
        'Visitors' => '/modules/visitors/index.php',
        'Notices' => '/modules/notices/index.php',
        'Reports' => '/modules/reports/index.php',
    ],
    'class_coordinator' => [
        'Dashboard' => '/modules/dashboard/index.php',
        'Students' => '/modules/students/index.php',
        'Leave' => '/modules/leave/index.php',
        'Complaints' => '/modules/complaints/index.php',
        'Reports' => '/modules/reports/index.php',
    ],
    'staff' => [
        'Dashboard' => '/modules/dashboard/index.php',
        'Complaints' => '/modules/complaints/index.php',
        'Visitors' => '/modules/visitors/index.php',
    ],
];
?>
<nav class="sidebar p-3">
    <h5 class="text-white mb-4">HostelMS</h5>
    <div class="text-white small mb-3">Logged in as <?= e($user['name'] ?? 'Guest') ?></div>
    <ul class="nav flex-column gap-1">
        <?php foreach (($menus[$role] ?? []) as $name => $url): ?>
            <li class="nav-item">
                <a class="nav-link text-white" href="<?= BASE_URL . e($url) ?>"><?= e($name) ?></a>
            </li>
        <?php endforeach; ?>
        <li class="nav-item mt-2">
            <a class="nav-link text-warning" href="<?= BASE_URL ?>/auth/logout.php">Logout</a>
        </li>
    </ul>
</nav>

