<?php

// エラーを画面に表示(1を0にすると画面上にはエラーは出ない)
ini_set('display_errors', 0);

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
$origin_dlkey = $fileData['dl_key'];

// 制限情報を更新（もし新しい制限が設定されている場合）
if ($max_downloads !== null || $expires_at !== null) {
    $updateStmt = $db->prepare("UPDATE uploaded SET max_downloads = :max_downloads, expires_at = :expires_at WHERE id = :id");
    $updateStmt->bindValue(':max_downloads', $max_downloads);
    $updateStmt->bindValue(':expires_at', $expires_at);
    $updateStmt->bindValue(':id', $id);
    $updateStmt->execute();
}

// 共有用のトークンを生成（DLキーなしで直接ダウンロード可能にする）
if (PHP_MAJOR_VERSION == '5' and PHP_MINOR_VERSION == '3') {
    $share_key = bin2hex(openssl_encrypt($origin_dlkey, 'aes-256-ecb', $key, true));
} else {
    $share_key = bin2hex(openssl_encrypt($origin_dlkey, 'aes-256-ecb', $key, OPENSSL_RAW_DATA));
}

// 現在のプロトコルとホストを取得してベースURLを構築
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
             || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_path = dirname(dirname($_SERVER['SCRIPT_NAME'])); // /app/api から2階層上へ
if ($base_path === '/' || $base_path === '\\') {
    $base_path = '';
}

$share_url = $protocol . $host . $base_path . '/download.php?id=' . $id . '&key=' . $share_key;

// JSON形式で出力する
echo json_encode(array(
    'status' => 'ok',
    'id' => $id,
    'filename' => $filename,
    'comment' => $comment,
    'share_url' => $share_url,
    'share_url_with_comment' => $comment . "\n" . $share_url
));