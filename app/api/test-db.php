<?php

// デバッグ用データベーステストファイル
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// エラー表示を有効にしてデバッグ
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    echo json_encode([
        'step' => 1,
        'message' => 'PHP execution started',
        'cwd' => getcwd(),
        'timestamp' => date('c')
    ]) . "\n";
    
    // 設定ファイル読み込みテスト
    if (file_exists('../../config/config.php')) {
        echo json_encode(['step' => 2, 'message' => 'config.php exists']) . "\n";
        require_once '../../config/config.php';
        $configObj = new config();
        $config = $configObj->index();
        echo json_encode(['step' => 3, 'message' => 'config loaded', 'api_enabled' => $config['api_enabled']]) . "\n";
    } else {
        echo json_encode(['step' => 2, 'error' => 'config.php not found']) . "\n";
        exit;
    }
    
    // データベース接続テスト
    $db_directory = '../../db';
    $dsn = 'sqlite:' . $db_directory . '/uploader.db';
    
    echo json_encode(['step' => 4, 'message' => 'Testing DB connection', 'dsn' => $dsn]) . "\n";
    
    if (file_exists($db_directory . '/uploader.db')) {
        echo json_encode(['step' => 5, 'message' => 'DB file exists']) . "\n";
        
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        echo json_encode(['step' => 6, 'message' => 'DB connection successful']) . "\n";
        
        // テーブル存在確認
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tables = $stmt->fetchAll();
        
        echo json_encode(['step' => 7, 'message' => 'Tables found', 'tables' => $tables]) . "\n";
        
        // uploaded テーブルのデータ確認
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM uploaded");
        $count = $stmt->fetch();
        
        echo json_encode(['step' => 8, 'message' => 'Files count', 'count' => $count]) . "\n";
        
    } else {
        echo json_encode(['step' => 5, 'error' => 'DB file not found', 'path' => $db_directory . '/uploader.db']) . "\n";
    }
    
    echo json_encode(['step' => 'final', 'message' => 'All tests completed successfully']) . "\n";
    
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]) . "\n";
}

?>