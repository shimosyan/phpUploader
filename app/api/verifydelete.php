<?php

declare(strict_types=1);

/**
 * 削除検証API
 *
 * ハッシュ化された削除キーの検証とワンタイムトークンの生成
 */

// エラー表示設定
ini_set('display_errors', '0');
ini_set('log_errors', '1'); // ログファイルにエラーを記録
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // 設定とユーティリティの読み込み
    require_once '../../config/config.php';
    require_once '../../src/Core/SecurityUtils.php';
    require_once '../../src/Core/Logger.php';
    require_once '../../src/Core/ResponseHandler.php';

    use phpUploader\Config\Config;
    use phpUploader\Core\SecurityUtils;
    use phpUploader\Core\Logger;
    use phpUploader\Core\ResponseHandler;

    $configInstance = new Config();
    $config = $configInstance->index();

    // アプリケーション初期化
    require_once '../../app/models/init.php';
    $db = initializeApp($config);

    // ログとレスポンスハンドラーの初期化
    $logger = new Logger($config['log_directory'], $config['log_level'], $db);
    $responseHandler = new ResponseHandler($logger);

    // 入力データの取得
    $fileId = (int)($_POST['id'] ?? 0);
    $inputKey = $_POST['key'] ?? '';

    if ($fileId <= 0) {
        $responseHandler->error('ファイルIDが指定されていません。', [], 400);
    }

    // CSRFトークンの検証
    if (!SecurityUtils::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $logger->warning('CSRF token validation failed in delete verify', ['file_id' => $fileId]);
        $responseHandler->error('無効なリクエストです。ページを再読み込みしてください。', [], 403);
    }

    // ファイル情報の取得
    $fileStmt = $db->prepare("SELECT * FROM uploaded WHERE id = :id");
    $fileStmt->execute(['id' => $fileId]);
    $fileData = $fileStmt->fetch();

    if (!$fileData) {
        $logger->warning('File not found for delete', ['file_id' => $fileId]);
        $responseHandler->error('ファイルが見つかりません。', [], 404);
    }

    // マスターキーチェック
    $isValidAuth = false;
    if ($inputKey === $config['master']) {
        $isValidAuth = true;
        $logger->info('Master key used for delete', ['file_id' => $fileId]);
    } else {
        // 削除キーが設定されていない場合
        if (empty($fileData['del_key_hash'])) {
            $isValidAuth = true;
        } else {
            // ハッシュ化されたキーとの照合
            if (!empty($inputKey)) {
                $isValidAuth = SecurityUtils::verifyPassword($inputKey, $fileData['del_key_hash']);
            } else {
                // キーが設定されているが、入力されていない場合
                $isValidAuth = false;
            }
        }
    }

    if (!$isValidAuth) {
        // 削除キーが設定されているが、入力されていない場合
        if (!empty($fileData['del_key_hash']) && empty($inputKey)) {
            $logger->info('Delete key required', ['file_id' => $fileId]);
            $responseHandler->error('削除キーの入力が必要です。', [], 200, 'AUTH_REQUIRED');
        } else {
            // キーが間違っている場合
            $logger->warning('Invalid delete key', ['file_id' => $fileId]);
            $responseHandler->error('削除キーが正しくありません。', [], 200, 'INVALID_KEY');
        }
    }

    // 既存の期限切れトークンをクリーンアップ
    $cleanupStmt = $db->prepare("DELETE FROM access_tokens WHERE expires_at < :now");
    $cleanupStmt->execute(['now' => time()]);

    // ワンタイムトークンの生成
    $token = SecurityUtils::generateRandomToken(32);
    $expiresAt = time() + ($config['token_expiry_minutes'] * 60);

    // トークンをデータベースに保存
    $tokenStmt = $db->prepare("
        INSERT INTO access_tokens (file_id, token, token_type, expires_at, ip_address)
        VALUES (:file_id, :token, :token_type, :expires_at, :ip_address)
    ");

    $tokenData = [
        'file_id' => $fileId,
        'token' => $token,
        'token_type' => 'delete',
        'expires_at' => $expiresAt,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ];

    if (!$tokenStmt->execute($tokenData)) {
        $responseHandler->error('削除トークンの生成に失敗しました。', [], 500);
    }

    // アクセスログの記録
    $logger->access($fileId, 'delete_verify', 'success');

    // 成功レスポンス
    $responseHandler->success('削除準備が完了しました。', [
        'id' => $fileId,
        'token' => $token,
        'expires_at' => $expiresAt,
        'file_name' => $fileData['origin_file_name']
    ]);
} catch (Exception $e) {
    // 緊急時のエラーハンドリング
    if (isset($logger)) {
        $logger->error('Delete verify API Error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'file_id' => $fileId ?? null
        ]);
    }

    if (isset($responseHandler)) {
        $responseHandler->error('システムエラーが発生しました。', [], 500);
    } else {
        // 最低限のエラーレスポンス
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'システムエラーが発生しました。'
        ], JSON_UNESCAPED_UNICODE);
    }
}
