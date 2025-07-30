<?php
/**
 * Tus.io プロトコル対応アップロードAPI
 * phpUploader - Tus.io Server Implementation
 */

// エラー表示とタイムアウト設定
ini_set('display_errors', 1);
ini_set('max_execution_time', 0);
set_time_limit(0);

// Docker環境用のデバッグログ
error_log("Tus.io Request - Method: " . $_SERVER['REQUEST_METHOD'] . 
          ", URI: " . $_SERVER['REQUEST_URI'] . 
          ", PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'not set') .
          ", QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'not set'));

// CORS対応
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PATCH, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Upload-Offset, Upload-Length, Upload-Metadata, Tus-Resumable, Content-Type');
header('Access-Control-Expose-Headers: Upload-Offset, Upload-Length, Tus-Resumable, Location');

// Tus.ioバージョン
$tus_version = '1.0.0';
header('Tus-Resumable: ' . $tus_version);

//configをインクルード
include(__DIR__ . '/../../config/config.php');
$config = new config();
$ret = $config->index();
if (!is_null($ret) && is_array($ret)) {
    extract($ret);
}

// データベース接続
try {
    $db = new PDO('sqlite:' . __DIR__ . '/../../' . $db_directory . '/uploader.db');
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'OPTIONS':
            handleOptions();
            break;
        case 'POST':
            handleCreate();
            break;
        case 'PATCH':
            handlePatch();
            break;
        case 'HEAD':
            handleHead();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed', 'method' => $method]);
            break;
    }
} catch (Throwable $e) {
    error_log("Tus.io Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'method' => $method
        ]
    ]);
}

/**
 * OPTIONSリクエスト - サーバー機能を返す
 */
function handleOptions() {
    global $tus_version, $max_file_size;
    
    header('Tus-Version: ' . $tus_version);
    header('Tus-Extension: creation,expiration');
    header('Tus-Max-Size: ' . ($max_file_size * 1024 * 1024));
    header('Tus-Checksum-Algorithm: sha1,md5');
    http_response_code(204);
}

/**
 * POSTリクエスト - 新しいアップロードセッション作成
 */
function handleCreate() {
    global $db, $data_directory, $max_file_size;
    
    // Upload-Lengthヘッダーをチェック
    $uploadLength = $_SERVER['HTTP_UPLOAD_LENGTH'] ?? null;
    if (!$uploadLength || !is_numeric($uploadLength)) {
        http_response_code(400);
        echo json_encode(['error' => 'Upload-Length header required']);
        exit;
    }
    
    $fileSize = intval($uploadLength);
    
    // ファイルサイズチェック
    if ($fileSize > $max_file_size * 1024 * 1024) {
        http_response_code(413);
        echo json_encode(['error' => 'File size exceeds limit']);
        exit;
    }
    
    // メタデータの解析
    $metadata = parseMetadata($_SERVER['HTTP_UPLOAD_METADATA'] ?? '');
    
    // アップロードIDを生成
    $uploadId = generateUploadId();
    $currentTime = time();
    $expiresAt = $currentTime + (24 * 60 * 60); // 24時間で期限切れ
    
    // チャンクファイルのパス
    $chunkPath = $data_directory . '/chunks/' . $uploadId . '.chunk';
    
    // チャンクディレクトリが存在しない場合は作成
    $chunkDir = dirname($chunkPath);
    if (!is_dir($chunkDir)) {
        mkdir($chunkDir, 0755, true);
    }
    
    // データベースに記録
    try {
        // 差し替えキーの検証
        if (empty($metadata['replacekey'])) {
            http_response_code(400);
            echo json_encode(['error' => '差し替えキーは必須です。']);
            exit;
        }

        $sql = $db->prepare("
            INSERT INTO tus_uploads (
                id, file_size, offset, metadata, chunk_path, 
                created_at, updated_at, expires_at, comment, 
                dl_key, del_key, replace_key, max_downloads, share_expires_at, folder_id
            ) VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $sql->execute([
            $uploadId,
            $fileSize,
            json_encode($metadata),
            $chunkPath,
            $currentTime,
            $currentTime,
            $expiresAt,
            $metadata['comment'] ?? null,
            $metadata['dlkey'] ?? null,
            $metadata['delkey'] ?? null,
            $metadata['replacekey'],
            isset($metadata['max_downloads']) ? intval($metadata['max_downloads']) : null,
            isset($metadata['expires_days']) ? $currentTime + (intval($metadata['expires_days']) * 24 * 60 * 60) : null,
            isset($metadata['folder_id']) ? intval($metadata['folder_id']) : null
        ]);
        
        if (!$result) {
            throw new Exception('Database write failed');
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create upload session']);
        exit;
    }
    
    // 空のチャンクファイルを作成
    touch($chunkPath);
    
    // レスポンス - GETパラメータ形式を使用
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $baseUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/tus-upload.php';
    header('Location: ' . $baseUrl . '?upload_id=' . $uploadId);
    header('Upload-Expires: ' . date('r', $expiresAt));
    http_response_code(201);
}

/**
 * PATCHリクエスト - チャンクデータアップロード
 */
function handlePatch() {
    global $db, $key, $extension;
    
    $uploadId = getUploadIdFromPath();
    error_log("PATCH request - Upload ID: " . ($uploadId ? $uploadId : 'null'));
    
    if (!$uploadId) {
        http_response_code(404);
        echo json_encode(['error' => 'Upload not found', 'debug' => ['request_uri' => $_SERVER['REQUEST_URI'], 'path_info' => $_SERVER['PATH_INFO'] ?? 'not set']]);
        exit;
    }
    
    // アップロード情報を取得
    $upload = getUploadInfo($uploadId);
    if (!$upload) {
        http_response_code(404);
        echo json_encode(['error' => 'Upload not found']);
        exit;
    }
    
    // 期限切れチェック
    if ($upload['expires_at'] && time() > $upload['expires_at']) {
        http_response_code(410);
        echo json_encode(['error' => 'Upload expired']);
        exit;
    }
    
    // Upload-Offsetヘッダーをチェック
    $uploadOffset = $_SERVER['HTTP_UPLOAD_OFFSET'] ?? null;
    if (!is_numeric($uploadOffset)) {
        http_response_code(400);
        echo json_encode(['error' => 'Upload-Offset header required']);
        exit;
    }
    
    $offset = intval($uploadOffset);
    
    // チャンクファイルの実際のサイズを確認
    $actualOffset = file_exists($upload['chunk_path']) ? filesize($upload['chunk_path']) : 0;
    error_log("Offset check - DB: {$upload['offset']}, File: {$actualOffset}, Request: {$offset}");
    
    // データベースのオフセットと実際のファイルサイズが異なる場合は修正
    if ($actualOffset !== $upload['offset']) {
        error_log("Correcting offset mismatch - updating DB offset from {$upload['offset']} to {$actualOffset}");
        $sql = $db->prepare("UPDATE tus_uploads SET offset = ? WHERE id = ?");
        $sql->execute([$actualOffset, $uploadId]);
        $upload['offset'] = $actualOffset;
    }
    
    // オフセットが現在の進行状況と一致するかチェック
    if ($offset !== $upload['offset']) {
        error_log("Offset conflict - expected: {$upload['offset']}, received: {$offset}");
        http_response_code(409);
        echo json_encode([
            'error' => 'Offset conflict',
            'expected' => $upload['offset'],
            'received' => $offset,
            'actual_file_size' => $actualOffset
        ]);
        exit;
    }
    
    // チャンクデータを読み取り
    $input = fopen('php://input', 'r');
    $chunkFile = fopen($upload['chunk_path'], 'a');
    
    $bytesWritten = 0;
    while (!feof($input)) {
        $data = fread($input, 8192);
        if ($data === false) break;
        
        $written = fwrite($chunkFile, $data);
        if ($written === false) {
            fclose($input);
            fclose($chunkFile);
            http_response_code(500);
            echo json_encode(['error' => 'Write failed']);
            exit;
        }
        $bytesWritten += $written;
    }
    
    fclose($input);
    fclose($chunkFile);
    
    // 新しいオフセットを計算
    $newOffset = $offset + $bytesWritten;
    
    // データベースを更新
    try {
        $sql = $db->prepare("UPDATE tus_uploads SET offset = ?, updated_at = ? WHERE id = ?");
        $sql->execute([$newOffset, time(), $uploadId]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database update failed']);
        exit;
    }
    
    // アップロード完了チェック
    if ($newOffset >= $upload['file_size']) {
        completeUpload($uploadId, $upload);
    }
    
    header('Upload-Offset: ' . $newOffset);
    http_response_code(204);
}

/**
 * HEADリクエスト - アップロード進行状況確認
 */
function handleHead() {
    $uploadId = getUploadIdFromPath();
    error_log("HEAD request - Upload ID: " . ($uploadId ? $uploadId : 'null'));
    
    if (!$uploadId) {
        error_log("HEAD request failed - no upload ID found");
        http_response_code(404);
        echo json_encode(['error' => 'Upload not found', 'debug' => ['request_uri' => $_SERVER['REQUEST_URI'], 'query_string' => $_SERVER['QUERY_STRING'] ?? 'not set']]);
        exit;
    }
    
    $upload = getUploadInfo($uploadId);
    if (!$upload) {
        error_log("HEAD request failed - upload info not found for ID: " . $uploadId);
        http_response_code(404);
        echo json_encode(['error' => 'Upload session not found', 'upload_id' => $uploadId]);
        exit;
    }
    
    // 期限切れチェック
    if ($upload['expires_at'] && time() > $upload['expires_at']) {
        error_log("HEAD request failed - upload expired for ID: " . $uploadId);
        http_response_code(410);
        echo json_encode(['error' => 'Upload expired', 'upload_id' => $uploadId]);
        exit;
    }
    
    // チャンクファイルの実際のサイズを確認
    $actualOffset = file_exists($upload['chunk_path']) ? filesize($upload['chunk_path']) : 0;
    
    error_log("HEAD request success - ID: {$uploadId}, offset: {$actualOffset}, size: {$upload['file_size']}");
    
    header('Upload-Offset: ' . $actualOffset);
    header('Upload-Length: ' . $upload['file_size']);
    header('Cache-Control: no-store');
    http_response_code(200);
}

/**
 * アップロード完了処理
 */
function completeUpload($uploadId, $upload) {
    global $db, $data_directory, $key, $encrypt_filename, $extension;
    
    $metadata = json_decode($upload['metadata'], true);
    $originalFileName = $metadata['filename'] ?? 'unknown';
    
    // 拡張子チェック
    $ext = pathinfo($originalFileName, PATHINFO_EXTENSION);
    if (!in_array(strtolower($ext), $extension)) {
        // 不正な拡張子の場合は削除
        unlink($upload['chunk_path']);
        $db->prepare("DELETE FROM tus_uploads WHERE id = ?")->execute([$uploadId]);
        return false;
    }
    
    try {
        // uploadedテーブルに移動
        $sql = $db->prepare("
            INSERT INTO uploaded (
                origin_file_name, comment, size, count, input_date,
                dl_key, del_key, replace_key, max_downloads, expires_at, folder_id
            ) VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $sql->execute([
            htmlspecialchars($originalFileName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($upload['comment'] ?? '', ENT_QUOTES, 'UTF-8'),
            $upload['file_size'],
            time(),
            empty($upload['dl_key']) ? null : openssl_encrypt($upload['dl_key'], 'aes-256-ecb', $key),
            empty($upload['del_key']) ? null : openssl_encrypt($upload['del_key'], 'aes-256-ecb', $key),
            empty($upload['replace_key']) ? null : openssl_encrypt($upload['replace_key'], 'aes-256-ecb', $key),
            $upload['max_downloads'],
            $upload['share_expires_at'],
            $upload['folder_id']
        ]);
        
        if (!$result) {
            throw new Exception('Failed to insert into uploaded table');
        }
        
        $fileId = $db->lastInsertId();
        
        // 最終ファイルパスを決定
        if ($encrypt_filename) {
            $finalPath = $data_directory . '/file_' . str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '', openssl_encrypt($fileId, 'aes-256-ecb', $key)) . '.' . $ext;
        } else {
            $finalPath = $data_directory . '/file_' . $fileId . '.' . $ext;
        }
        
        // チャンクファイルを最終的な場所に移動
        if (!rename($upload['chunk_path'], $finalPath)) {
            throw new Exception('Failed to move file');
        }
        
        // tus_uploadsテーブルを更新
        $sql = $db->prepare("UPDATE tus_uploads SET completed = 1, final_file_id = ? WHERE id = ?");
        $sql->execute([$fileId, $uploadId]);
        
        return true;
        
    } catch (Exception $e) {
        // エラー時はチャンクファイルを削除
        if (file_exists($upload['chunk_path'])) {
            unlink($upload['chunk_path']);
        }
        return false;
    }
}

/**
 * ヘルパー関数群
 */
function parseMetadata($metadataHeader) {
    $metadata = [];
    if (empty($metadataHeader)) return $metadata;
    
    $pairs = explode(',', $metadataHeader);
    foreach ($pairs as $pair) {
        $parts = explode(' ', trim($pair), 2);
        if (count($parts) === 2) {
            $key = $parts[0];
            $value = base64_decode($parts[1]);
            $metadata[$key] = $value;
        }
    }
    return $metadata;
}

function generateUploadId() {
    return uniqid('tus_', true) . '_' . bin2hex(random_bytes(8));
}

function getCurrentUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $baseUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/tus-upload.php';
    return $baseUrl;
}

function getUploadIdFromPath() {
    // 1. GETパラメータから取得を試行
    if (isset($_GET['upload_id']) && strpos($_GET['upload_id'], 'tus_') === 0) {
        return $_GET['upload_id'];
    }
    
    // 2. PATH_INFOから取得を試行
    if (isset($_SERVER['PATH_INFO'])) {
        $pathInfo = trim($_SERVER['PATH_INFO'], '/');
        if (strpos($pathInfo, 'tus_') === 0) {
            return $pathInfo;
        }
    }
    
    // 3. REQUEST_URIから取得を試行
    $path = $_SERVER['REQUEST_URI'];
    $path = parse_url($path, PHP_URL_PATH);
    $parts = explode('/', $path);
    
    foreach ($parts as $part) {
        if (strpos($part, 'tus_') === 0) {
            return $part;
        }
    }
    
    // 4. デバッグ情報をログに記録
    error_log("Upload ID not found - REQUEST_URI: " . $_SERVER['REQUEST_URI'] . 
              ", PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'not set') . 
              ", QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'not set'));
    
    return null;
}

function getUploadInfo($uploadId) {
    global $db;
    $sql = $db->prepare("SELECT * FROM tus_uploads WHERE id = ?");
    $sql->execute([$uploadId]);
    return $sql->fetch();
}

?>