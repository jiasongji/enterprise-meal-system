<?php
// api/admin/users.php
require '../init.php';

Auth::checkRole(['admin', 'finance']); 

$method = $_SERVER['REQUEST_METHOD'];

// GET: 获取用户列表
if ($method === 'GET') {
    $status = $_GET['status'] ?? null;
    $kw = $_GET['keyword'] ?? '';
    
    // 增加 u.level_id 到查询字段，以便前端回显
    $sql = "SELECT u.id, u.phone, u.name, u.role, u.status, u.created_at, u.level_id,
                   l.name as level_name, b.amount 
            FROM users u
            LEFT JOIN user_levels l ON u.level_id = l.id
            LEFT JOIN balances b ON u.id = b.user_id
            WHERE 1=1";
    
    $params = [];
    if ($status !== null && $status !== '') {
        $sql .= " AND u.status = ?";
        $params[] = $status;
    }
    if ($kw) {
        $sql .= " AND (u.name LIKE ? OR u.phone LIKE ?)";
        $params[] = "%$kw%";
        $params[] = "%$kw%";
    }
    
    $sql .= " ORDER BY u.created_at DESC LIMIT 50";
    
    $list = db()->query($sql, $params);
    Response::success($list);
}

// POST: 修改操作
if ($method === 'POST') {
    if (Auth::user()['role'] !== 'admin') {
        Response::error('权限不足');
    }

    $input = getJsonInput();
    $uid = $input['id'] ?? 0;
    $action = $input['action'] ?? ''; 

    if (!$uid) Response::error('参数错误');

    // 1. 审核
    if ($action === 'approve') {
        db()->execute("UPDATE users SET status = 1 WHERE id = ?", [$uid]);
        Response::success([], '已审核通过');
    } 
    // 2. 删除
    elseif ($action === 'delete') {
        $bal = db()->fetch("SELECT amount FROM balances WHERE user_id=?", [$uid]);
        if ($bal && $bal['amount'] > 0) Response::error('该用户尚有余额，无法删除');
        
        db()->execute("DELETE FROM balances WHERE user_id=?", [$uid]);
        db()->execute("DELETE FROM meal_orders WHERE user_id=?", [$uid]);
        db()->execute("DELETE FROM recharge_logs WHERE user_id=?", [$uid]);
        db()->execute("DELETE FROM users WHERE id=?", [$uid]);
        
        Response::success([], '用户已删除');
    }
    // 3. 设置角色
    elseif ($action === 'set_role') {
        $role = $input['role'];
        if (!in_array($role, ['user', 'finance', 'admin'])) Response::error('非法角色');
        if ($uid == Auth::id() && $role !== 'admin') Response::error('不能修改自己的管理员权限');
        
        db()->execute("UPDATE users SET role = ? WHERE id = ?", [$role, $uid]);
        Response::success([], '权限已更新');
    }
    // 4. 编辑资料 (含身份修改)
    elseif ($action === 'update_info') {
        $name = trim($input['name']);
        $phone = trim($input['phone']);
        $levelId = intval($input['level_id']); // 新增
        $pass = $input['password'] ?? '';
        
        if (!$name || !$phone) Response::error('姓名手机不能为空');
        
        $exist = db()->fetch("SELECT id FROM users WHERE phone=? AND id!=?", [$phone, $uid]);
        if ($exist) Response::error('手机号已存在');
        
        $sql = "UPDATE users SET name=?, phone=?, level_id=? WHERE id=?";
        $args = [$name, $phone, $levelId, $uid];
        
        if ($pass) {
            $sql = "UPDATE users SET name=?, phone=?, level_id=?, password=? WHERE id=?";
            $args = [$name, $phone, $levelId, password_hash($pass, PASSWORD_DEFAULT), $uid];
        }
        
        db()->execute($sql, $args);
        Response::success([], '资料已保存');
    }
    
    else {
        Response::error('未知操作');
    }
}