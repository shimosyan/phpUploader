<?php

/**
 * ファイルアップロードAPI
 *
 * セキュリティ強化版のアップロード処理
 */

// 出力バッファリング開始

declare(strict_types=1);

ob_start();

// エラー表示設定（デバッグ用）
ini_set('display_errors', '0');
ini_set('log_errors', '1'); // ログファイルにエラーを記録
error_reporting(E_ALL);
ini_set('max_execution_time', 300);
set_time_limit(300);

// ヘッダー設定
header('Content-Type: application/json; charset=utf-8');

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // 設定とユーティリティの読み込み（絶対パスで修正）
    $baseDir = dirname(dirname(__DIR__)); // アプリケーションルートディレクトリ
    require_once $baseDir . '/config/config.php';
    require_once $baseDir . '/src/Core/Utils.php';

    $configInstance = new \PHPUploader\Config();
    $config = $configInstance->index();

    // アプリケーション初期化
    require_once $baseDir . '/app/models/init.php';

    $initInstance = new \PHPUploader\Model\Init($config);
    $db = $initInstance -> initialize();

    // ログとレスポンスハンドラーの初期化
    $logger = new Logger($config['logDirectoryPath'], $config['logLevel'], $db);
    $responseHandler = new ResponseHandler($logger);

    // リクエストメソッドの確認
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $responseHandler->error('無効なリクエストメソッドです。', [], 405);
    }

    // ファイルがアップロードされているかチェック
    if (!isset($_FILES['file'])) {
        $responseHandler->error('ファイルが選択されていません。', [], 400);
    }

    // CSRFトークンの検証
    if (!SecurityUtils::validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $logger->warning('CSRF token validation failed', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        $responseHandler->error('無効なリクエストです。ページを再読み込みしてください。', [], 403);
    }

    // ファイルアップロードエラーチェック
    $uploadErrors = [];
    if (!isset($_FILES['file'])) {
        $uploadErrors[] = 'ファイルが選択されていません。';
    } else {
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
                $uploadErrors[] = 'アップロードされたファイルが大きすぎます。(' . ini_get('upload_max_filesize') . '以下)';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $uploadErrors[] = 'アップロードされたファイルが大きすぎます。(' . ($_POST['maxFileSize'] / 1024) . 'KB以下)';
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
    }

    if (!empty($uploadErrors)) {
        $responseHandler->error('アップロードエラー', $uploadErrors, 400);
    }

    // アップロードファイルの検証
    if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
        $responseHandler->error('不正なファイルアップロードです。', [], 400);
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
    if ($fileSize > $config['maxFileSize'] * 1024 * 1024) {
        $validationErrors[] = "ファイルサイズが上限({$config['maxFileSize']}MB)を超えています。";
    }

    // 拡張子チェック
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $config['extension'])) {
        $validationErrors[] = '許可されていない拡張子です。(' . implode(', ', $config['extension']) . 'のみ)';
    }

    // コメント文字数チェック
    if (mb_strlen($comment) > $config['maxComment']) {
        $validationErrors[] = "コメントが長すぎます。({$config['maxComment']}文字以下)";
    }

    // キーの長さチェック
    if (!empty($dlKey) && mb_strlen($dlKey) < $config['security']['minKeyLength']) {
        $validationErrors[] = "ダウンロードキーは{$config['security']['minKeyLength']}文字以上で設定してください。";
    }

    if (!empty($delKey) && mb_strlen($delKey) < $config['security']['minKeyLength']) {
        $validationErrors[] = "削除キーは{$config['security']['minKeyLength']}文字以上で設定してください。";
    }

    if (!empty($validationErrors)) {
        $responseHandler->error('バリデーションエラー', $validationErrors, 400);
    }

    // ファイル数制限チェックと古いファイルの削除
    $fileCountStmt = $db->prepare('SELECT COUNT(id) as count, MIN(id) as min_id FROM uploaded');
    $fileCountStmt->execute();
    $countResult = $fileCountStmt->fetch();

    if ($countResult['count'] >= $config['saveMaxFiles']) {
        // 古いファイルを削除
        $oldFileStmt = $db->prepare('SELECT id, origin_file_name, stored_file_name FROM uploaded WHERE id = :id');
        $oldFileStmt->execute(['id' => $countResult['min_id']]);
        $oldFile = $oldFileStmt->fetch();

        if ($oldFile) {
            // 物理ファイルの削除（ハッシュ化されたファイル名または旧形式に対応）
            if (!empty($oldFile['stored_file_name'])) {
                // 新形式（ハッシュ化されたファイル名）
                $oldFilePath = $config['dataDirectoryPath'] . '/' . $oldFile['stored_file_name'];
            } else {
                // 旧形式（互換性のため）
                $oldFilePath = $config['dataDirectoryPath'] . '/file_' . $oldFile['id'] .
                             '.' . pathinfo($oldFile['origin_file_name'], PATHINFO_EXTENSION);
            }

            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }

            // データベースから削除
            $deleteStmt = $db->prepare('DELETE FROM uploaded WHERE id = :id');
            $deleteStmt->execute(['id' => $oldFile['id']]);

            $logger->info('Old file deleted due to storage limit', ['deleted_file_id' => $oldFile['id']]);
        }
    }

    // ファイルハッシュの生成
    $fileHash = hash_file('sha256', $fileTmpPath);

    // 認証キーのハッシュ化（空の場合はnull）
    $dlKeyHash = (!empty($dlKey) && trim($dlKey) !== '') ? SecurityUtils::hashPassword($dlKey) : null;
    $delKeyHash = (!empty($delKey) && trim($delKey) !== '') ? SecurityUtils::hashPassword($delKey) : null;

    // まず仮のデータベース登録（stored_file_nameは後で更新）
    $insertStmt = $db->prepare('
        INSERT INTO uploaded (
            origin_file_name, comment, size, count, input_date,
            dl_key_hash, del_key_hash, file_hash, ip_address
        ) VALUES (
            :origin_file_name, :comment, :size, :count, :input_date,
            :dl_key_hash, :del_key_hash, :file_hash, :ip_address
        )
    ');

    $insertData = [
        'origin_file_name' => $fileName,
        'comment' => $comment,
        'size' => $fileSize,
        'count' => 0,
        'input_date' => time(),
        'dl_key_hash' => $dlKeyHash,
        'del_key_hash' => $delKeyHash,
        'file_hash' => $fileHash,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    if (!$insertStmt->execute($insertData)) {
        $errorInfo = $insertStmt->errorInfo();
        error_log('Database insert failed: ' . print_r($errorInfo, true));
        $responseHandler->error('データベースへの保存に失敗しました。', [], 500);
    }

    $fileId = (int)$db->lastInsertId();

    // セキュアなファイル名の生成（ハッシュ化）
    $hashedFileName = SecurityUtils::generateSecureFileName($fileId, $fileName);
    $storedFileName = SecurityUtils::generateStoredFileName($hashedFileName, $fileExtension);
    $saveFilePath = $config['dataDirectoryPath'] . '/' . $storedFileName;

    // ファイル保存
    if (!move_uploaded_file($fileTmpPath, $saveFilePath)) {
        // データベースからも削除
        $db->prepare('DELETE FROM uploaded WHERE id = :id')->execute(['id' => $fileId]);
        $responseHandler->error('ファイルの保存に失敗しました。', [], 500);
    }

    // データベースにハッシュ化されたファイル名を記録
    $updateStmt = $db->prepare('UPDATE uploaded SET stored_file_name = :stored_file_name WHERE id = :id');
    if (!$updateStmt->execute(['stored_file_name' => $storedFileName, 'id' => $fileId])) {
        // ファイルを削除してデータベースからも削除
        if (file_exists($saveFilePath)) {
            unlink($saveFilePath);
        }
        $db->prepare('DELETE FROM uploaded WHERE id = :id')->execute(['id' => $fileId]);
        $responseHandler->error('ファイル情報の更新に失敗しました。', [], 500);
    }

    // アクセスログの記録
    $logger->access($fileId, 'upload', 'success');

    // 成功レスポンス
    $responseHandler->success('ファイルのアップロードが完了しました。', [
        'file_id' => $fileId,
        'file_name' => $fileName,
        'file_size' => $fileSize,
    ]);
} catch (Exception $e) {
    // 出力バッファをクリア
    if (ob_get_level()) {
        ob_clean();
    }

    // 緊急時のエラーハンドリング
    if (isset($logger)) {
        $logger->error('Upload API Error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
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
            'message' => 'システムエラーが発生しました。',
        ], JSON_UNESCAPED_UNICODE);
    }
}
