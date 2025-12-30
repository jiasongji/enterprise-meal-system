<?php
require '../init.php';
Auth::checkLogin();
$uid = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getJsonInput();
    $name = trim($input['name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $pass = $input['password'] ?? '';
    
    if (!$name || !$phone) Response::error('姓名和手机号不能为空');
    
    // 检查手机号重复
    $exist = db()->fetch("SELECT id FROM users WHERE phone = ? AND id != ?", [$phone, $uid]);
    if ($exist) Response::error('手机号已存在');
    
    $sql = "UPDATE users SET name = ?, phone = ? WHERE id = ?";
    $params = [$name, $phone, $uid];
    
    if ($pass) {
        $sql = "UPDATE users SET name = ?, phone = ?, password = ? WHERE id = ?";
        $params = [$name, $phone, password_hash($pass, PASSWORD_DEFAULT), $uid];
    }
    
    db()->execute($sql, $params);
    Response::success([], '资料已更新');
}