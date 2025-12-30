<?php
// api/admin/settings.php
require '../init.php';
Auth::checkRole('admin'); 

$method = $_SERVER['REQUEST_METHOD'];

// GET: 获取列表 (后台管理用)
if ($method === 'GET') {
    $levels = db()->query("SELECT * FROM user_levels ORDER BY id ASC");
    Response::success(['levels' => $levels]);
}

// POST: 增删改
if ($method === 'POST') {
    $input = getJsonInput();
    $action = $input['action'] ?? '';

    // 1. 修改价格
    if ($action === 'update_price') {
        $id = intval($input['id']);
        $price = intval($input['price']);
        db()->execute("UPDATE user_levels SET price = ? WHERE id = ?", [$price, $id]);
        Response::success([], '价格已更新');
    }
    
    // 2. 新增身份 (New)
    elseif ($action === 'add_level') {
        $name = trim($input['name'] ?? '');
        $price = intval($input['price'] ?? 0);
        
        if (!$name) Response::error('名称不能为空');
        
        db()->execute("INSERT INTO user_levels (name, price) VALUES (?, ?)", [$name, $price]);
        Response::success([], '新身份已添加');
    }

    // 3. 删除身份 (New)
    elseif ($action === 'del_level') {
        $id = intval($input['id']);
        
        // 保护机制：检查该身份下是否还有用户
        $count = db()->fetch("SELECT count(*) as c FROM users WHERE level_id = ?", [$id]);
        if ($count['c'] > 0) {
            Response::error('该身份下仍有用户，无法删除。请先修改这些用户的身份。');
        }
        
        db()->execute("DELETE FROM user_levels WHERE id = ?", [$id]);
        Response::success([], '身份已删除');
    }
}