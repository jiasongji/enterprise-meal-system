<?php
// api/finance/recharge.php
require '../init.php';

// 允许 管理员 或 财务 角色操作
Auth::checkRole(['admin', 'finance']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method Not Allowed', 405);
}

$input = getJsonInput();
$targetUid = intval($input['user_id'] ?? 0);
$amountYuan = floatval($input['amount'] ?? 0); // 前端传“元”

if ($targetUid <= 0 || $amountYuan <= 0) {
    Response::error('参数错误：金额必须大于0');
}

// 转换为“分”
$amountFen = intval(round($amountYuan * 100));
$operatorId = Auth::id();

try {
    $pdo = db()->getPdo();
    $pdo->beginTransaction();

    // 1. 检查用户是否存在
    $user = db()->fetch("SELECT id, name FROM users WHERE id = ?", [$targetUid]);
    if (!$user) {
        throw new Exception("用户不存在");
    }

    // 2. 增加余额 (使用 atomic update 防止并发覆盖)
    $stmt = $pdo->prepare("UPDATE balances SET amount = amount + ?, updated_at = datetime('now','localtime') WHERE user_id = ?");
    $stmt->execute([$amountFen, $targetUid]);

    if ($stmt->rowCount() === 0) {
        // 如果 balance 表没记录（理论上注册时已创建，但这步是防御性编程）
        $pdo->prepare("INSERT INTO balances (user_id, amount) VALUES (?, ?)")
            ->execute([$targetUid, $amountFen]);
    }

    // 3. 写入充值日志
    $sqlLog = "INSERT INTO recharge_logs (user_id, operator_id, amount) VALUES (?, ?, ?)";
    $pdo->prepare($sqlLog)->execute([$targetUid, $operatorId, $amountFen]);

    $pdo->commit();
    Response::success(['new_amount' => $amountFen], "充值成功：{$user['name']} +{$amountYuan}元");

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Response::error("充值失败: " . $e->getMessage());
}