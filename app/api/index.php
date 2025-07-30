<?php

/**
 * RESTful APIメインエントリーポイント
 * すべてのAPIリクエストをここで受け取り、ルーターに転送
 */

// エラー表示設定
ini_set('display_errors', 0);
error_reporting(E_ALL);

// CORS設定
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// OPTIONSリクエスト（プリフライト）への対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 設定ファイル読み込み
try {
    if (!file_exists('../../config/config.php')) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'success' => false,
            'error' => array(
                'code' => 'CONFIG_NOT_FOUND',
                'message' => '設定ファイルが見つかりません。config.php.exampleをconfig.phpとしてコピーし、適切に設定してください。'
            ),
            'timestamp' => date('c')
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    require_once '../../config/config.php';
    $configObj = new config();
    $config = $configObj->index();
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'success' => false,
        'error' => array(
            'code' => 'CONFIG_ERROR',
            'message' => '設定ファイルの読み込みに失敗しました'
        ),
        'timestamp' => date('c')
    ), JSON_UNESCAPED_UNICODE);
    error_log('Config error: ' . $e->getMessage());
    exit;
}

// API機能の有効性チェック
if (!isset($config['api_enabled']) || !$config['api_enabled']) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'success' => false,
        'error' => array(
            'code' => 'API_DISABLED',
            'message' => 'RESTful API機能が無効です'
        ),
        'timestamp' => date('c')
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

// ルーター読み込みとリクエスト処理
try {
    require_once 'router.php';
    
    $router = new ApiRouter($config);
    $router->handleRequest();
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'success' => false,
        'error' => array(
            'code' => 'INTERNAL_ERROR',
            'message' => 'サーバー内部エラーが発生しました'
        ),
        'timestamp' => date('c')
    ), JSON_UNESCAPED_UNICODE);
    error_log('API error: ' . $e->getMessage());
    exit;
}

?>