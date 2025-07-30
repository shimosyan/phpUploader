<?php

/**
 * RESTful API認証システム
 * APIキー/トークンベースの認証機能
 */

class ApiAuth
{
    private $config;
    private $apiKey = null;
    private $permissions = array();

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * APIキーを使用した認証を実行
     * @return bool 認証成功かどうか
     */
    public function authenticate()
    {
        // API機能が無効の場合は認証失敗
        if (!$this->config['api_enabled']) {
            $this->sendError(503, 'API_DISABLED', 'RESTful API機能が無効です');
            return false;
        }

        // AuthorizationヘッダーまたはクエリパラメータからAPIキーを取得
        $apiKey = $this->extractApiKey();
        if (!$apiKey) {
            $this->sendError(401, 'API_KEY_MISSING', 'APIキーが必要です');
            return false;
        }

        // APIキーの検証
        if (!$this->validateApiKey($apiKey)) {
            $this->sendError(401, 'API_KEY_INVALID', '無効なAPIキーです');
            return false;
        }

        // レート制限チェック
        if (!$this->checkRateLimit($apiKey)) {
            $this->sendError(429, 'RATE_LIMIT_EXCEEDED', 'リクエスト制限を超過しました');
            return false;
        }

        $this->apiKey = $apiKey;
        return true;
    }

    /**
     * 指定された権限があるかチェック
     */
    public function hasPermission($permission)
    {
        return in_array($permission, $this->permissions);
    }

    /**
     * 現在のAPIキーを取得
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * APIキーを抽出（Authorizationヘッダーまたはクエリパラメータから）
     */
    private function extractApiKey()
    {
        // Authorization: Bearer <api_key>
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            if (preg_match('/Bearer\s+(.+)/', $headers['Authorization'], $matches)) {
                return $matches[1];
            }
        }

        // クエリパラメータ ?api_key=<api_key>
        if (isset($_GET['api_key'])) {
            return $_GET['api_key'];
        }

        // POSTパラメータ
        if (isset($_POST['api_key'])) {
            return $_POST['api_key'];
        }

        return null;
    }

    /**
     * APIキーの検証
     */
    private function validateApiKey($apiKey) {
        if (!isset($this->config['api_keys'][$apiKey])) {
            return false;
        }

        $keyConfig = $this->config['api_keys'][$apiKey];
        
        // 有効期限チェック
        if (isset($keyConfig['expires']) && $keyConfig['expires']) {
            $expiryTime = strtotime($keyConfig['expires']);
            if (time() > $expiryTime) {
                return false;
            }
        }

        // 権限を設定
        $this->permissions = isset($keyConfig['permissions']) ? $keyConfig['permissions'] : array();
        
        return true;
    }

    /**
     * レート制限チェック
     */
    private function checkRateLimit($apiKey) {
        $rateLimit = $this->config['api_rate_limit'];
        
        // レート制限が0の場合は無制限
        if ($rateLimit <= 0) {
            return true;
        }

        // レート制限の実装（簡易版：1時間ごとのリセット）
        $rateLimitFile = dirname(__FILE__) . '/data/rate_limits/' . md5($apiKey) . '.json';
        $rateLimitDir = dirname($rateLimitFile);
        
        if (!is_dir($rateLimitDir)) {
            mkdir($rateLimitDir, 0755, true);
        }

        $currentHour = date('Y-m-d-H');
        $rateLimitData = array();
        
        if (file_exists($rateLimitFile)) {
            $rateLimitData = json_decode(file_get_contents($rateLimitFile), true) ?: array();
        }

        // 古いデータをクリーンアップ
        $rateLimitData = array_filter($rateLimitData, function($hour) use ($currentHour) {
            return $hour >= date('Y-m-d-H', strtotime('-1 hour'));
        }, ARRAY_FILTER_USE_KEY);

        // 現在の時間のリクエスト数をチェック
        $currentRequests = isset($rateLimitData[$currentHour]) ? $rateLimitData[$currentHour] : 0;
        
        if ($currentRequests >= $rateLimit) {
            return false;
        }

        // リクエスト数を増加
        $rateLimitData[$currentHour] = $currentRequests + 1;
        file_put_contents($rateLimitFile, json_encode($rateLimitData));

        return true;
    }

    /**
     * エラーレスポンスを送信
     */
    private function sendError($statusCode, $errorCode, $message) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'success' => false,
            'error' => array(
                'code' => $errorCode,
                'message' => $message
            ),
            'timestamp' => date('c')
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

