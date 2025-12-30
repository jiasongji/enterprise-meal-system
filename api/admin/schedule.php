<?php
require '../init.php';
Auth::checkRole('admin');

$method = $_SERVER['REQUEST_METHOD'];

// GET: 获取排餐
if ($method === 'GET') {
    $start = $_GET['start'] ?? date('Y-m-d');
    $end = $_GET['end'] ?? date('Y-m-d', strtotime('+30 days'));
    $list = db()->query("SELECT * FROM meal_schedules WHERE date BETWEEN ? AND ? ORDER BY date ASC", [$start, $end]);
    Response::success($list);
}

// POST: 批量排餐
if ($method === 'POST') {
    $input = getJsonInput();
    $action = $input['action'] ?? '';

    if ($action === 'add') {
        $startDate = $input['start_date'];
        $endDate = $input['end_date'] ?? $startDate;
        $menu = trim($input['menu'] ?? '工作餐');
        
        if (!$startDate) Response::error('日期不能为空');

        $current = strtotime($startDate);
        $end = strtotime($endDate);
        
        $pdo = db()->getPdo();
        $pdo->beginTransaction();
        try {
            while ($current <= $end) {
                $dateStr = date('Y-m-d', $current);
                // 修复：使用 REPLACE INTO 替代 ON CONFLICT，兼容旧版 SQLite
                $stmt = $pdo->prepare("REPLACE INTO meal_schedules (date, menu_text) VALUES (?, ?)");
                $stmt->execute([$dateStr, $menu]);
                $current = strtotime('+1 day', $current);
            }
            $pdo->commit();
            Response::success([], '排餐成功');
        } catch (Exception $e) {
            $pdo->rollBack();
            Response::error('排餐失败:'.$e->getMessage());
        }
    } 
    elseif ($action === 'delete') {
        $date = $input['date'];
        db()->execute("DELETE FROM meal_schedules WHERE date = ?", [$date]);
        Response::success([], '已删除排餐');
    }
}