<?php
// api/user/password.php
require '../init.php';

Auth::checkLogin();
$uid = Auth::id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method Not Allowed', 405);
}

$input = getJsonInput();
$oldPass = $input['old_password'] ?? '';
$newPass = $input['new_password'] ?? '';

if (!$oldPass || !$newPass) {
    Response::error('请输入原密码和新密码');
}

if (strlen($newPass) < 6) {
    Response::error('新密码至少需要6位');
}

// 1. 验证原密码
$user = db()->fetch("SELECT password FROM users WHERE id = ?", [$uid]);
if (!password_verify($oldPass, $user['password'])) {
    Response::error('原密码错误');
}

// 2. 更新新密码
$newHash = password_hash($newPass, PASSWORD_DEFAULT);
db()->execute("UPDATE users SET password = ? WHERE id = ?", [$newHash, $uid]);

Response::success([], '密码修改成功，请重新登录');