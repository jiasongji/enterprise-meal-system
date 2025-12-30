<?php
// api/user/feedback.php
require '../init.php';
Auth::checkLogin();

$method = $_SERVER['REQUEST_METHOD'];
$uid = Auth::id();
$user = Auth::user();

// POST: 提交或删除
if ($method === 'POST') {
    $input = getJsonInput();
    $action = $input['action'] ?? 'add'; // 默认为添加

    // 1. 提交反馈
    if ($action === 'add') {
        $content = trim($input['content'] ?? '');
        if (!$content) Response::error('内容不能为空');
        
        db()->execute("INSERT INTO feedbacks (user_id, content) VALUES (?, ?)", [$uid, $content]);
        Response::success([], '反馈提交成功');
    }

    // 2. 删除反馈 (仅管理员)
    elseif ($action === 'delete') {
        if ($user['role'] !== 'admin') Response::error('权限不足');
        
        $id = intval($input['id'] ?? 0);
        db()->execute("DELETE FROM feedbacks WHERE id = ?", [$id]);
        Response::success([], '已删除');
    }
}

// GET: 查看反馈列表
if ($method === 'GET') {
    if ($user['role'] === 'admin') {
        // 管理员看所有
        $sql = "SELECT f.*, u.name, u.phone FROM feedbacks f 
                LEFT JOIN users u ON f.user_id = u.id 
                ORDER BY f.created_at DESC LIMIT 50";
        $list = db()->query($sql);
    } else {
        // 普通用户看自己的 (可选)
        $list = [];
    }
    Response::success($list);
}