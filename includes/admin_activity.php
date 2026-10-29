<?php

function record_admin_activity(mysqli $conn, string $action, string $target, string $details): void {
    if (empty($_SESSION['admin_id'])) return;
    $adminId = (int)$_SESSION['admin_id'];
    $stmt = $conn->prepare('INSERT INTO admin_activity (admin_id, action, target, details) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('isss', $adminId, $action, $target, $details);
    $stmt->execute();
}
