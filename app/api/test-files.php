<?php

// ファイルエンドポイント専用デバッグ
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    echo json_encode(['step' => 1, 'message' => 'Files endpoint debug started']) . "\n";
    
    // 設定読み込み
    require_once '../../config/config.php';
    $configObj = new config();
    $config = $configObj->index();
    
    echo json_encode(['step' => 2, 'message' => 'Config loaded']) . "\n";
    
    // データベース接続
    $db_directory = '../../db';
    $dsn = 'sqlite:' . $db_directory . '/uploader.db';
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    echo json_encode(['step' => 3, 'message' => 'DB connected']) . "\n";
    
    // ファイル一覧取得クエリをテスト
    $page = 1;
    $limit = 20;
    $folder = null;
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT id, origin_file_name as original_name, origin_file_name as filename, 
                   comment, dl_key as password_dl, del_key as password_del, 
                   size as file_size, 'application/octet-stream' as mime_type, 
                   input_date as upload_date, count as download_count, folder_id 
            FROM uploaded WHERE 1=1";
    $params = array();
    
    echo json_encode(['step' => 4, 'message' => 'SQL prepared', 'sql' => $sql]) . "\n";
    
    $sql .= " ORDER BY input_date DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    echo json_encode(['step' => 5, 'message' => 'Executing query', 'params' => $params]) . "\n";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['step' => 6, 'message' => 'Files fetched', 'count' => count($files)]) . "\n";
    
    // 総件数取得
    $countSql = "SELECT COUNT(*) FROM uploaded WHERE 1=1";
    $countParams = array();
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetchColumn();
    
    echo json_encode(['step' => 7, 'message' => 'Total count', 'total' => $total]) . "\n";
    
    // 最終レスポンス構築
    $response = array(
        'success' => true,
        'data' => array(
            'files' => $files,
            'pagination' => array(
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            )
        ),
        'timestamp' => date('c')
    );
    
    echo json_encode(['step' => 8, 'message' => 'Response built', 'files_sample' => array_slice($files, 0, 2)]) . "\n";
    echo json_encode(['step' => 'final', 'message' => 'SUCCESS', 'response' => $response]) . "\n";
    
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]) . "\n";
}

?>