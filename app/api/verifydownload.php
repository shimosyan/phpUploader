<?php

declare(strict_types=1);

/**
 * ダウンロード検証API (Ver.2.0)
 * 
 * ハッシュ化された認証キーの検証とワンタイムトークンの生成
 */

// エラー表示設定
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // 設定とユーティリティの読み込み
    require_once '../../config/config.php';
    require_once '../../src/Core/Utils.php';

    $configInstance = new config();
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
        $responseHandler->validationError(['ファイルIDが指定されていません。']);
    }

    // CSRFトークンの検証
    if (!SecurityUtils::verifyCSRFToken($_POST['csrf_token'] ?? '', $config['security']['csrf_token_expiry'] * 60)) {
        $logger->warning('CSRF token validation failed in download verify', ['file_id' => $fileId]);
        $responseHandler->authError('無効なリクエストです。ページを再読み込みしてください。');
    }

    // ファイル情報の取得
    $fileStmt = $db->prepare("SELECT * FROM uploaded WHERE id = :id");
    $fileStmt->execute(['id' => $fileId]);
    $fileData = $fileStmt->fetch();

    if (!$fileData) {
        $logger->warning('File not found for download', ['file_id' => $fileId]);
        $responseHandler->error('FILE_NOT_FOUND', 'ファイルが見つかりません。');
    }

    // マスターキーチェック
    $isValidAuth = false;
    if ($inputKey === $config['master']) {
        $isValidAuth = true;
        $logger->info('Master key used for download', ['file_id' => $fileId]);
    } else {
        // ダウンロードキーが設定されていない場合
        if (empty($fileData['dl_key_hash'])) {
            $isValidAuth = true;
        } else {
            // ハッシュ化されたキーとの照合
            if (!empty($inputKey)) {
                $isValidAuth = SecurityUtils::verifyPassword($inputKey, $fileData['dl_key_hash'], $config['session_salt']);
            }
        }
    }

    if (!$isValidAuth) {
        $logger->warning('Invalid download key', ['file_id' => $fileId]);
        $responseHandler->authError('ダウンロードキーが正しくありません。');
    }

    // 既存の期限切れトークンをクリーンアップ
    $cleanupStmt = $db->prepare("DELETE FROM access_tokens WHERE expires_at < :now");
    $cleanupStmt->execute(['now' => time()]);

    // ワンタイムトークンの生成
    $token = SecurityUtils::generateOneTimeToken();
    $expiresAt = time() + ($config['token_expiry_minutes'] * 60);

    // トークンをデータベースに保存
    $tokenStmt = $db->prepare("
        INSERT INTO access_tokens (file_id, token, token_type, expires_at, ip_address)
        VALUES (:file_id, :token, :token_type, :expires_at, :ip_address)
    ");

    $tokenData = [
        'file_id' => $fileId,
        'token' => $token,
        'token_type' => 'download',
        'expires_at' => $expiresAt,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ];

    if (!$tokenStmt->execute($tokenData)) {
        $responseHandler->error('TOKEN_GENERATION_ERROR', 'ダウンロードトークンの生成に失敗しました。');
    }

    // アクセスログの記録
    $logger->access($fileId, 'download_verify', 'success');

    // 成功レスポンス
    $responseHandler->success([
        'id' => $fileId,
        'token' => $token,
        'expires_at' => $expiresAt,
        'file_name' => $fileData['origin_file_name']
    ], 'ダウンロード準備が完了しました。');

} catch (Exception $e) {
    // 緊急時のエラーハンドリング
    if (isset($logger)) {
        $logger->error('Download verify API Error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'file_id' => $fileId ?? null
        ]);
    }

    if (isset($responseHandler)) {
        $responseHandler->error('INTERNAL_ERROR', 'システムエラーが発生しました。');
    } else {
        // 最低限のエラーレスポンス
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error_code' => 'INTERNAL_ERROR',
            'message' => 'システムエラーが発生しました。'
        ], JSON_UNESCAPED_UNICODE);
    }
}
