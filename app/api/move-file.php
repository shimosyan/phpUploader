<?php

// ファイル移動API
header('Content-Type: application/json; charset=utf-8');

// configをインクルード
include_once('../../config/config.php');
$config = new config();
$ret = $config->index();
if (!is_null($ret)) {
    if (is_array($ret)) {
        extract($ret);
    }
}

// フォルダ機能が無効な場合はエラーを返す
if (!isset($folders_enabled) || !$folders_enabled) {
    http_response_code(403);
    echo json_encode(['error' => 'フォルダ機能が無効です'], JSON_UNESCAPED_UNICODE);
    exit;
}

// POSTメソッドのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'メソッドが許可されていません'], JSON_UNESCAPED_UNICODE);
    exit;
}

// データベースの作成・オープン
try {
    $db = new PDO('sqlite:' . $db_directory . '/uploader.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベース接続エラー'], JSON_UNESCAPED_UNICODE);
    exit;
}

// JSONデータを取得
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => '不正なJSONデータです'], JSON_UNESCAPED_UNICODE);
    exit;
}

$fileId = isset($data['file_id']) ? (int)$data['file_id'] : 0;
$folderId = isset($data['folder_id']) && $data['folder_id'] !== '' ? (int)$data['folder_id'] : null;

// ファイルIDの検証
if ($fileId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => '有効なファイルIDが必要です'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ファイルの存在確認
$stmt = $db->prepare("SELECT id, origin_file_name, folder_id FROM uploaded WHERE id = ?");
$stmt->execute([$fileId]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    echo json_encode(['error' => 'ファイルが見つかりません'], JSON_UNESCAPED_UNICODE);
    exit;
}

// フォルダIDの存在確認（NULLでない場合）
if ($folderId !== null) {
    $stmt = $db->prepare("SELECT id FROM folders WHERE id = ?");
    $stmt->execute([$folderId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => '移動先フォルダが見つかりません'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ファイルの移動（folder_idを更新）
try {
    $stmt = $db->prepare("UPDATE uploaded SET folder_id = ? WHERE id = ?");
    $stmt->execute([$folderId, $fileId]);
    
    $targetFolder = $folderId ? "フォルダID: $folderId" : "ルートフォルダ";
    
    echo json_encode([
        'message' => 'ファイルを移動しました',
        'file_id' => $fileId,
        'file_name' => $file['origin_file_name'],
        'old_folder_id' => $file['folder_id'],
        'new_folder_id' => $folderId,
        'target_folder' => $targetFolder
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'ファイル移動に失敗しました: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

?>