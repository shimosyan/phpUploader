<?php

declare(strict_types=1);

/**
 * ファイルアップロードAPI (Ver.2.0)
 *
 * セキュリティ強化版のアップロード処理
 */

// エラー表示設定
ini_set('display_errors', '0');
ini_set('max_execution_time', 300);
set_time_limit(300);
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

    // CSRFトークンの検証
    if (!SecurityUtils::verifyCSRFToken($_POST['csrf_token'] ?? '', $config['security']['csrf_token_expiry'] * 60)) {
        $logger->warning('CSRF token validation failed', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        $responseHandler->authError('無効なリクエストです。ページを再読み込みしてください。');
    }

    // ファイルアップロードエラーチェック
    $uploadErrors = [];
    switch ($_FILES['file']['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
            $uploadErrors[] = 'アップロードされたファイルが大きすぎます。(' . ini_get('upload_max_filesize') . '以下)';
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $uploadErrors[] = 'アップロードされたファイルが大きすぎます。(' . ($_POST['MAX_FILE_SIZE'] / 1024) . 'KB以下)';
            break;
        case UPLOAD_ERR_PARTIAL:
            $uploadErrors[] = 'アップロードが途中で中断されました。もう一度お試しください。';
            break;
        case UPLOAD_ERR_NO_FILE:
            $uploadErrors[] = 'ファイルが選択されていません。';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $uploadErrors[] = 'サーバーエラーが発生しました。管理者にお問い合わせください。';
            break;
        default:
            $uploadErrors[] = 'アップロードに失敗しました。';
            break;
    }

    if (!empty($uploadErrors)) {
        $responseHandler->validationError($uploadErrors);
    }

    // アップロードファイルの検証
    if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
        $responseHandler->error('UPLOAD_ERROR', '不正なファイルアップロードです。');
    }

    // 入力データの取得とサニタイズ
    $fileName = htmlspecialchars($_FILES['file']['name'], ENT_QUOTES, 'UTF-8');
    $comment = htmlspecialchars($_POST['comment'] ?? '', ENT_QUOTES, 'UTF-8');
    $dlKey = $_POST['dlkey'] ?? '';
    $delKey = $_POST['delkey'] ?? '';
    $fileSize = filesize($_FILES['file']['tmp_name']);
    $fileTmpPath = $_FILES['file']['tmp_name'];

    // バリデーション
    $validationErrors = [];

    // ファイルサイズチェック
    if ($fileSize > $config['max_file_size'] * 1024 * 1024) {
        $validationErrors[] = "ファイルサイズが上限({$config['max_file_size']}MB)を超えています。";
    }

    // 拡張子チェック
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $config['extension'])) {
        $validationErrors[] = "許可されていない拡張子です。(" . implode(', ', $config['extension']) . "のみ)";
    }

    // コメント文字数チェック
    if (mb_strlen($comment) > $config['max_comment']) {
        $validationErrors[] = "コメントが長すぎます。({$config['max_comment']}文字以下)";
    }

    // キーの長さチェック
    if (!empty($dlKey) && mb_strlen($dlKey) < $config['security']['min_key_length']) {
        $validationErrors[] = "ダウンロードキーは{$config['security']['min_key_length']}文字以上で設定してください。";
    }

    if (!empty($delKey) && mb_strlen($delKey) < $config['security']['min_key_length']) {
        $validationErrors[] = "削除キーは{$config['security']['min_key_length']}文字以上で設定してください。";
    }

    if (!empty($validationErrors)) {
        $responseHandler->validationError($validationErrors);
    }

    // ファイル数制限チェックと古いファイルの削除
    $fileCountStmt = $db->prepare("SELECT COUNT(id) as count, MIN(id) as min_id FROM uploaded");
    $fileCountStmt->execute();
    $countResult = $fileCountStmt->fetch();

    if ($countResult['count'] >= $config['save_max_files']) {
        // 古いファイルを削除
        $oldFileStmt = $db->prepare("SELECT id, origin_file_name FROM uploaded WHERE id = :id");
        $oldFileStmt->execute(['id' => $countResult['min_id']]);
        $oldFile = $oldFileStmt->fetch();

        if ($oldFile) {
            // 物理ファイルの削除
            $oldFilePath = '../../' . $config['data_directory'] . '/' . 
                         SecurityUtils::generateSafeFileName($oldFile['id'], $config['key']) . 
                         '.' . pathinfo($oldFile['origin_file_name'], PATHINFO_EXTENSION);

            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }

            // データベースから削除
            $deleteStmt = $db->prepare("DELETE FROM uploaded WHERE id = :id");
            $deleteStmt->execute(['id' => $oldFile['id']]);

            $logger->info('Old file deleted due to storage limit', ['deleted_file_id' => $oldFile['id']]);
        }
    }

    // ファイルハッシュの生成
    $fileHash = SecurityUtils::generateFileHash($fileTmpPath);

    // 認証キーのハッシュ化
    $dlKeyHash = !empty($dlKey) ? SecurityUtils::hashPassword($dlKey, $config['session_salt']) : null;
    $delKeyHash = !empty($delKey) ? SecurityUtils::hashPassword($delKey, $config['session_salt']) : null;

    // データベースに登録
    $insertStmt = $db->prepare("
        INSERT INTO uploaded (
            origin_file_name, comment, size, count, input_date,
            dl_key_hash, del_key_hash, file_hash, ip_address
        ) VALUES (
            :origin_file_name, :comment, :size, :count, :input_date,
            :dl_key_hash, :del_key_hash, :file_hash, :ip_address
        )
    ");

    $insertData = [
        'origin_file_name' => $fileName,
        'comment' => $comment,
        'size' => $fileSize,
        'count' => 0,
        'input_date' => time(),
        'dl_key_hash' => $dlKeyHash,
        'del_key_hash' => $delKeyHash,
        'file_hash' => $fileHash,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ];

    if (!$insertStmt->execute($insertData)) {
        $responseHandler->error('DATABASE_ERROR', 'データベースへの保存に失敗しました。');
    }

    $fileId = (int)$db->lastInsertId();

    // ファイル保存
    $safeFileName = SecurityUtils::generateSafeFileName($fileId, $config['key']);
    $saveFilePath = '../../' . $config['data_directory'] . '/' . $safeFileName . '.' . $fileExtension;

    if (!move_uploaded_file($fileTmpPath, $saveFilePath)) {
        // データベースからも削除
        $db->prepare("DELETE FROM uploaded WHERE id = :id")->execute(['id' => $fileId]);
        $responseHandler->error('FILE_SAVE_ERROR', 'ファイルの保存に失敗しました。');
    }

    // アクセスログの記録
    $logger->access($fileId, 'upload', 'success');

    // 成功レスポンス
    $responseHandler->success([
        'file_id' => $fileId,
        'file_name' => $fileName,
        'file_size' => $fileSize
    ], 'ファイルのアップロードが完了しました。');

} catch (Exception $e) {
    // 緊急時のエラーハンドリング
    if (isset($logger)) {
        $logger->error('Upload API Error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine()
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
