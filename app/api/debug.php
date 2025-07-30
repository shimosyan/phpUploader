<?php

// デバッグ用簡易APIエンドポイント
header('Content-Type: application/json; charset=utf-8');

// CORS設定
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// APIキー認証テスト
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

if (!$authHeader) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'NO_AUTH_HEADER',
            'message' => 'Authorization header missing'
        ],
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'headers' => $headers,
            'get' => $_GET,
            'post' => $_POST
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// APIキー抽出
$apiKey = null;
if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
    $apiKey = $matches[1];
}

if (!$apiKey) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INVALID_AUTH_FORMAT',
            'message' => 'Invalid Authorization format'
        ],
        'debug' => [
            'auth_header' => $authHeader
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 設定ファイル読み込み
try {
    require_once '../../config/config.php';
    $configObj = new config();
    $config = $configObj->index();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'CONFIG_ERROR',
            'message' => 'Config loading failed: ' . $e->getMessage()
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// APIキー検証
$isValidKey = isset($config['api_keys'][$apiKey]);

echo json_encode([
    'success' => $isValidKey,
    'data' => [
        'api_key_received' => $apiKey,
        'api_key_valid' => $isValidKey,
        'configured_keys' => array_keys($config['api_keys']),
        'api_enabled' => $config['api_enabled'],
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'timestamp' => date('c')
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>