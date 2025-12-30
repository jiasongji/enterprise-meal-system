<?php
// api/user/order.php
require '../init.php';

Auth::checkLogin();
$uid = Auth::id();
$input = getJsonInput();
$action = $input['action'] ?? '';

// 获取全局截止时间配置
$conf = db()->fetch("SELECT value FROM system_config WHERE key='deadline_lunch'");
$deadlineTime = $conf['value'] ?? '10:30'; 

if ($action === 'book') {
    $date = $input['date'];
    
    // 1. 校验排餐
    $hasSchedule = db()->fetch("SELECT menu_text FROM meal_schedules WHERE date = ?", [$date]);
    if (!$hasSchedule) Response::error('当天未排餐，无法预订');

    // 2. 校验截止时间
    $deadlineTs = strtotime("$date $deadlineTime");
    if (time() > $deadlineTs) {
        Response::error("已超过今日截止时间 ($deadlineTime)，无法报餐");
    }

    $pdo = db()->getPdo();
    try {
        $pdo->beginTransaction();
        
        // 3. 准备价格和余额
        $user = db()->fetch("SELECT level_id FROM users WHERE id = ?", [$uid]);
        $level = db()->fetch("SELECT price FROM user_levels WHERE id = ?", [$user['level_id']]);
        $price = $level['price'];
        
        $bal = db()->fetch("SELECT amount FROM balances WHERE user_id = ?", [$uid]);
        if ($bal['amount'] < $price) throw new Exception("余额不足，请充值");
        
        // 4. 检查是否存在旧订单 (Fix Bug: 23000)
        $existing = db()->fetch("SELECT id, status FROM meal_orders WHERE user_id = ? AND order_date = ?", [$uid, $date]);

        if ($existing) {
            // A. 如果已存在且是已报状态
            if ($existing['status'] === 'ordered') {
                throw new Exception("您已报过当天的餐，请勿重复操作");
            }
            
            // B. 如果已存在但已取消 -> 重新激活 (Update)
            // 扣费
            db()->execute("UPDATE balances SET amount = amount - ? WHERE user_id = ?", [$price, $uid]);
            // 更新订单状态和当前价格
            $sql = "UPDATE meal_orders SET status = 'ordered', price = ?, created_at = datetime('now', 'localtime') WHERE id = ?";
            db()->execute($sql, [$price, $existing['id']]);

        } else {
            // C. 不存在 -> 插入新记录 (Insert)
            // 扣费
            db()->execute("UPDATE balances SET amount = amount - ? WHERE user_id = ?", [$price, $uid]);
            // 插入
            $sql = "INSERT INTO meal_orders (user_id, order_date, meal_type, price, status) VALUES (?, ?, 'lunch', ?, 'ordered')";
            db()->execute($sql, [$uid, $date, $price]);
        }
        
        $pdo->commit();
        Response::success([], '报餐成功');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        Response::error($e->getMessage());
    }
} 

elseif ($action === 'cancel') {
    $date = $input['date'];
    
    // 1. 校验截止时间
    $deadlineTs = strtotime("$date $deadlineTime");
    if (time() > $deadlineTs) {
        Response::error("已截单 ($deadlineTime)，无法取消");
    }

    $pdo = db()->getPdo();
    try {
        $pdo->beginTransaction();
        
        $order = db()->fetch("SELECT id, price FROM meal_orders WHERE user_id=? AND order_date=? AND status='ordered'", [$uid, $date]);
        if (!$order) throw new Exception("未找到有效订单");
        
        // 退费
        db()->execute("UPDATE balances SET amount = amount + ? WHERE user_id = ?", [$order['price'], $uid]);
        // 改状态
        db()->execute("UPDATE meal_orders SET status = 'cancelled' WHERE id = ?", [$order['id']]);
        
        $pdo->commit();
        Response::success([], '已退餐，费用已退回');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        Response::error($e->getMessage());
    }
}

elseif ($action === 'history') {
    $list = db()->query("SELECT * FROM meal_orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 50", [$uid]);
    Response::success($list);
}