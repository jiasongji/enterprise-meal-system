<?php
// api/user/login.php
require '../init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method Not Allowed', 405);
}

$input = getJsonInput();
$phone = $input['phone'] ?? '';
$pass  = $input['password'] ?? '';

// 1. 查询用户
$user = db()->fetch("SELECT * FROM users WHERE phone = ?", [$phone]);

if (!$user || !password_verify($pass, $user['password'])) {
    Response::error('账号或密码错误');
}

// 2. 检查状态
if ($user['status'] == 0) {
    Response::error('账号待审核，请联系管理员');
}
if ($user['status'] == 2) {
    Response::error('账号已禁用');
}

// 3. 登录成功，写入 Session
Auth::login($user);

// 4. 返回用户信息（不含密码）
unset($user['password']);
Response::success($user, '登录成功');