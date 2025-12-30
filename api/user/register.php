<?php
// api/user/register.php
require '../init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method Not Allowed', 405);
}

$input = getJsonInput();
$phone = trim($input['phone'] ?? '');
$pass  = $input['password'] ?? '';
$name  = trim($input['name'] ?? '');
// 接收 level_id (身份: 1=内部, 2=外部)，不再接收部门
$levelId = intval($input['level_id'] ?? 1); 

// 1. 基础校验
if (!$phone || !$pass || !$name) {
    Response::error('请填写完整信息');
}

// 2. 检查手机号唯一性
$exists = db()->fetch("SELECT id FROM users WHERE phone = ?", [$phone]);
if ($exists) {
    Response::error('该手机号已注册');
}

// 3. 密码加密
$hash = password_hash($pass, PASSWORD_DEFAULT);

// 4. 插入数据库 
// 注意：department_id 默认设为 1，status 默认 0 (待审核)
try {
    db()->getPdo()->beginTransaction();

    $sql = "INSERT INTO users (phone, password, name, department_id, level_id, status, role) VALUES (?, ?, ?, 1, ?, 0, 'user')";
    db()->execute($sql, [$phone, $hash, $name, $levelId]);
    $uid = db()->lastInsertId();

    // 初始化余额
    db()->execute("INSERT INTO balances (user_id, amount) VALUES (?, 0)", [$uid]);

    db()->getPdo()->commit();
    Response::success([], '注册成功，请等待管理员审核');

} catch (Exception $e) {
    db()->getPdo()->rollBack();
    Response::error('注册失败: ' . $e->getMessage());
}