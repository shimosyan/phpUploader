<?php
/**
 * 複数ファイルの一括移動API
 */

// CORS対応
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// OPTIONSリクエストに対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 設定読み込み
include_once('../../config/config.php');

$config = new config();
$ret = $config->index();
if (!is_null($ret) && is_array($ret)) {
    extract($ret);
}

// フォルダ機能が無効な場合はエラー
if (!isset($folders_enabled) || !$folders_enabled) {
    http_response_code(400);
    echo json_encode(['error' => 'フォルダ機能が無効です'], JSON_UNESCAPED_UNICODE);
    exit;
}

// JSONデータを取得
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['file_ids']) || !is_array($input['file_ids'])) {
    http_response_code(400);
    echo json_encode(['error' => '無効なリクエストデータです'], JSON_UNESCAPED_UNICODE);
    exit;
}

$fileIds = array_map('intval', $input['file_ids']);
$folderId = isset($input['folder_id']) ? $input['folder_id'] : null;

if (empty($fileIds)) {
    http_response_code(400);
    echo json_encode(['error' => '移動するファイルが指定されていません'], JSON_UNESCAPED_UNICODE);
    exit;
}

// フォルダIDが指定されている場合、存在確認
if ($folderId !== null) {
    $folderId = intval($folderId);
    if ($folderId <= 0) {
        $folderId = null; // 無効な値はnullに変換
    }
}

try {
    // データベース接続
    $db = new PDO('sqlite:' . $db_directory . '/uploader.db');
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // フォルダが指定されている場合、存在確認
    if ($folderId !== null) {
        $stmt = $db->prepare("SELECT id FROM folders WHERE id = ?");
        $stmt->execute([$folderId]);
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => '指定されたフォルダが存在しません'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // 指定されたファイルの存在確認
    $placeholders = str_repeat('?,', count($fileIds) - 1) . '?';
    $stmt = $db->prepare("SELECT id FROM uploaded WHERE id IN ($placeholders)");
    $stmt->execute($fileIds);
    $existingFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $notFoundFiles = array_diff($fileIds, $existingFiles);
    if (!empty($notFoundFiles)) {
        http_response_code(400);
        echo json_encode([
            'error' => '一部のファイルが見つかりません',
            'not_found_ids' => $notFoundFiles
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 一括移動実行
    $stmt = $db->prepare("UPDATE uploaded SET folder_id = ? WHERE id IN ($placeholders)");
    $params = array_merge([$folderId], $fileIds);
    $stmt->execute($params);
    
    $affectedRows = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => "{$affectedRows}個のファイルを移動しました",
        'moved_count' => $affectedRows,
        'target_folder_id' => $folderId
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log('Bulk move error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'ファイルの移動に失敗しました'], JSON_UNESCAPED_UNICODE);
}
?>