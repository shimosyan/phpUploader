<?php

// エラーを画面に表示(1を0にすると画面上にはエラーは出ない)
ini_set('display_errors', 1);

$id = $_POST['id'];
$max_downloads = isset($_POST['max_downloads']) ? (int)$_POST['max_downloads'] : null;
$expires_days = isset($_POST['expires_days']) ? (int)$_POST['expires_days'] : null;

header('Content-Type: application/json');

if ($id === null) {
    // JSON形式で出力する
    echo json_encode(array('status' => 'ng'));
    exit;
}

// 有効期限を計算（日数から UNIX タイムスタンプに変換）
$expires_at = null;
if ($expires_days && $expires_days > 0) {
    $expires_at = time() + ($expires_days * 24 * 60 * 60);
}

// configをインクルード
include('../../config/config.php');
$config = new config();
$ret = $config->index();
// 配列キーが設定されている配列なら展開
if (!is_null($ret)) {
    if (is_array($ret)) {
        extract($ret);
    }
}

// データベースの作成・オープン
try {
    $db = new PDO('sqlite:../../' . $db_directory . '/uploader.db');
} catch (Exception $e) {
    $response = array('status' => 'sqlerror');
    // JSON形式で出力する
    echo json_encode($response);
    exit;
}

// デフォルトのフェッチモードを連想配列形式に設定
// (毎回PDO::FETCH_ASSOCを指定する必要が無くなる)
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// 選択 (プリペアドステートメント)
$stmt = $db->prepare("SELECT * FROM uploaded WHERE id = :id");
$stmt->bindValue(':id', $id); // ID
$stmt->execute();
$result = $stmt->fetchAll();

if (empty($result)) {
    // JSON形式で出力する
    echo json_encode(array('status' => 'not_found'));
    exit;
}

$fileData = $result[0];
$filename = $fileData['origin_file_name'];
$comment = $fileData['comment'];
$origin_dlkey = $fileData['dl_key_hash'];

// 既存の共有リンクをチェック
$stmt = $db->prepare("SELECT * FROM shared_links WHERE file_id = :file_id");
$stmt->bindValue(':file_id', $id, PDO::PARAM_INT);
$stmt->execute();
$existing_link = $stmt->fetch();

$current_time = time();

if ($existing_link) {
    // 既存のリンクを更新
    $share_token = $existing_link['share_token'];
    
    if ($max_downloads !== null || $expires_at !== null) {
        $updateStmt = $db->prepare("UPDATE shared_links SET max_downloads = :max_downloads, expires_at = :expires_at, updated_at = :updated_at WHERE file_id = :file_id");
        $updateStmt->bindValue(':max_downloads', $max_downloads, PDO::PARAM_INT);
        $updateStmt->bindValue(':expires_at', $expires_at, PDO::PARAM_INT);
        $updateStmt->bindValue(':updated_at', $current_time, PDO::PARAM_INT);
        $updateStmt->bindValue(':file_id', $id, PDO::PARAM_INT);
        $updateStmt->execute();
    }
} else {
    // 新しい共有リンクを作成
    $share_token = bin2hex(random_bytes(32));
    
    $insertStmt = $db->prepare("INSERT INTO shared_links (file_id, share_token, max_downloads, current_downloads, expires_at, created_at, updated_at) VALUES (:file_id, :share_token, :max_downloads, 0, :expires_at, :created_at, :updated_at)");
    $insertStmt->bindValue(':file_id', $id, PDO::PARAM_INT);
    $insertStmt->bindValue(':share_token', $share_token, PDO::PARAM_STR);
    $insertStmt->bindValue(':max_downloads', $max_downloads, PDO::PARAM_INT);
    $insertStmt->bindValue(':expires_at', $expires_at, PDO::PARAM_INT);
    $insertStmt->bindValue(':created_at', $current_time, PDO::PARAM_INT);
    $insertStmt->bindValue(':updated_at', $current_time, PDO::PARAM_INT);
    
    if (!$insertStmt->execute()) {
        echo json_encode(array('status' => 'sqlerror'));
        exit;
    }
}

// 現在のプロトコルとホストを取得してベースURLを構築
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
             || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_path = dirname(dirname($_SERVER['SCRIPT_NAME'])); // /app/api から2階層上へ
if ($base_path === '/' || $base_path === '\\') {
    $base_path = '';
}

$share_url = $protocol . $host . $base_path . '/download.php?share=' . $share_token;

// JSON形式で出力する
echo json_encode(array(
    'status' => 'ok',
    'id' => $id,
    'filename' => $filename,
    'comment' => $comment,
    'share_url' => $share_url,
    'share_url_with_comment' => $comment . "\n" . $share_url,
    'max_downloads' => $max_downloads,
    'expires_at' => $expires_at
));