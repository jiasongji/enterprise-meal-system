<?php
// core/Auth.php

class Auth {
    
    // 启动 Session
    public static function init() {
        if (session_status() == PHP_SESSION_NONE) {
            // 设置 Session 有效期 (如 24 小时)
            ini_set('session.gc_maxlifetime', 86400);
            session_start();
        }
    }

    /**
     * 用户登录，写入 Session
     * @param array $user 用户数据库记录
     */
    public static function login($user) {
        // 移除敏感信息
        unset($user['password']); 
        $_SESSION['user'] = $user;
    }

    /**
     * 用户登出
     */
    public static function logout() {
        unset($_SESSION['user']);
        session_destroy();
    }

    /**
     * 获取当前登录用户信息
     * @return array|null
     */
    public static function user() {
        return isset($_SESSION['user']) ? $_SESSION['user'] : null;
    }

    /**
     * 检查是否登录，未登录直接返回 401
     */
    public static function checkLogin() {
        if (!self::user()) {
            Response::json(401, '未登录或会话已过期', null);
        }
    }

    /**
     * 检查是否为指定角色，权限不足返回 403
     * @param string|array $roles 允许的角色，如 'admin' 或 ['admin', 'finance']
     */
    public static function checkRole($roles) {
        self::checkLogin();
        
        $user = self::user();
        $currentRole = $user['role']; // user, admin, finance

        if (is_string($roles)) {
            $roles = [$roles];
        }

        if (!in_array($currentRole, $roles)) {
            Response::json(403, '权限不足', null);
        }
    }
    
    /**
     * 获取当前用户 ID
     */
    public static function id() {
        $u = self::user();
        return $u ? $u['id'] : 0;
    }
}