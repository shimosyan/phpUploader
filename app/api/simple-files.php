<?php

// 簡化されたファイルエンドポイント（認証＋DB処理のみ）
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 認証処理
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

if (!$authHeader || !preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'API_KEY_MISSING', 'message' => 'APIキーが必要です'],
        'timestamp' => date('c')
    ]);
    exit;
}

$apiKey = $matches[1];

// 設定ファイル読み込み
require_once '../../config/config.php';
$configObj = new config();
$config = $configObj->index();

// APIキー検証
if (!isset($config['api_keys'][$apiKey])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'API_KEY_INVALID', 'message' => '無効なAPIキーです'],
        'timestamp' => date('c')
    ]);
    exit;
}

// ファイル一覧取得処理
try {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $folder = (isset($_GET['folder']) && $_GET['folder'] !== '') ? intval($_GET['folder']) : null;
    
    $offset = ($page - 1) * $limit;
    
    // データベース接続
    $db_directory = '../../db';
    $dsn = 'sqlite:' . $db_directory . '/uploader.db';
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // ファイル一覧取得
    $sql = "SELECT id, origin_file_name as original_name, origin_file_name as filename, 
                   comment, dl_key as password_dl, del_key as password_del, 
                   size as file_size, 'application/octet-stream' as mime_type, 
                   input_date as upload_date, count as download_count, folder_id 
            FROM uploaded WHERE 1=1";
    $params = array();
    
    if ($folder !== null) {
        $sql .= " AND folder_id = ?";
        $params[] = $folder;
    }
    
    $sql .= " ORDER BY input_date DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 総件数取得
    $countSql = "SELECT COUNT(*) FROM uploaded WHERE 1=1";
    $countParams = array();
    if ($folder !== null) {
        $countSql .= " AND folder_id = ?";
        $countParams[] = $folder;
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetchColumn();
    
    // 成功レスポンス
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'files' => $files,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ],
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()],
        'timestamp' => date('c')
    ]);
}

?>