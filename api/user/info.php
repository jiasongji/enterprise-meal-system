<?php
// api/user/info.php
require '../init.php';

Auth::checkLogin();
$uid = Auth::id();

// 关联查询：用户基础信息 + 余额 + 等级名称 + 部门名称
$sql = "SELECT u.id, u.name, u.phone, u.role, 
               b.amount as balance, 
               l.name as level_name, 
               d.name as dept_name
        FROM users u
        LEFT JOIN balances b ON u.id = b.user_id
        LEFT JOIN user_levels l ON u.level_id = l.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.id = ?";

$user = db()->fetch($sql, [$uid]);

Response::success($user);