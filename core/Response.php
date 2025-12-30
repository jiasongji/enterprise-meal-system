<?php
// core/Response.php

class Response {
    /**
     * 输出 JSON 响应并终止脚本
     * @param int $code 业务状态码 (0:成功, >0: 错误)
     * @param string $msg 提示信息
     * @param mixed $data 返回数据
     */
    public static function json($code, $msg, $data = []) {
        // 清除缓冲区，防止之前有 echo 输出干扰 JSON
        if (ob_get_length()) ob_clean();
        
        header('Content-Type: application/json; charset=utf-8');
        
        // 允许跨域 (方便本地 H5 调试)
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        echo json_encode([
            'code' => $code,
            'msg'  => $msg,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
        
        exit; // 强制结束
    }

    // 快捷成功响应
    public static function success($data = [], $msg = 'success') {
        self::json(0, $msg, $data);
    }

    // 快捷错误响应
    public static function error($msg = 'error', $code = 1) {
        self::json($code, $msg);
    }
}