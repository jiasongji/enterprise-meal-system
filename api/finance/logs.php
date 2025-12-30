<?php
// api/finance/logs.php
require '../init.php';

Auth::checkRole(['admin', 'finance']);

$kw = $_GET['keyword'] ?? ''; // 搜索用户名
$sql = "SELECT l.id, l.amount, l.created_at, 
               u.name as user_name, u.phone, 
               op.name as operator_name
        FROM recharge_logs l
        JOIN users u ON l.user_id = u.id
        JOIN users op ON l.operator_id = op.id
        WHERE 1=1";

$params = [];
if ($kw) {
    $sql .= " AND (u.name LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$kw%";
    $params[] = "%$kw%";
}

$sql .= " ORDER BY l.created_at DESC LIMIT 50";

$list = db()->query($sql, $params);
Response::success($list);