<?php
/**
 * コメント編集専用エンドポイント
 * CSRFトークンによる保護で安全な編集機能を提供
 */

// セキュリティヘッダー
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'POSTリクエストのみ許可されています',
        'timestamp' => date('c')
    ]);
    exit;
}

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 必要なクラスを読み込み
$utilsPath = '../../src/Core/Utils.php';
if (!file_exists($utilsPath)) {
    error_log('Utils.php not found at: ' . $utilsPath);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'システムファイルが見つかりません',
        'error_code' => 'SYSTEM_FILE_MISSING',
        'debug' => 'Utils.php not found',
        'timestamp' => date('c')
    ]);
    exit;
}

require_once $utilsPath;

try {
    // CSRFトークン検証
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!SecurityUtils::validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'セキュリティトークンが無効です',
            'error_code' => 'CSRF_TOKEN_INVALID',
            'timestamp' => date('c')
        ]);
        exit;
    }

    // パラメータ取得
    $fileId = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    if (!$fileId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ファイルIDが必要です',
            'error_code' => 'FILE_ID_REQUIRED',
            'timestamp' => date('c')
        ]);
        exit;
    }

    // 設定読み込み
    $configPath = '../../config/config.php';
    if (!file_exists($configPath)) {
        error_log('config.php not found at: ' . $configPath);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => '設定ファイルが見つかりません',
            'error_code' => 'CONFIG_FILE_MISSING',
            'debug' => 'config.php not found',
            'timestamp' => date('c')
        ]);
        exit;
    }
    
    require_once $configPath;
    $configObj = new config();
    $config = $configObj->index();

    // コメント編集機能の有効性チェック
    if (!isset($config['allow_comment_edit']) || !$config['allow_comment_edit']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'コメント編集機能が無効です',
            'error_code' => 'COMMENT_EDIT_DISABLED',
            'timestamp' => date('c')
        ]);
        exit;
    }

    // コメント長さチェック
    $maxComment = $config['max_comment'] ?? 200;
    if (mb_strlen($comment) > $maxComment) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "コメントが長すぎます（最大{$maxComment}文字）",
            'error_code' => 'COMMENT_TOO_LONG',
            'timestamp' => date('c')
        ]);
        exit;
    }

    // データベース接続
    $dbDirectory = '../../db';
    $dsn = 'sqlite:' . $dbDirectory . '/uploader.db';
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // ファイル存在確認
    $stmt = $pdo->prepare("SELECT * FROM uploaded WHERE id = ?");
    $stmt->execute([$fileId]);
    $existingFile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingFile) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'ファイルが見つかりません',
            'error_code' => 'FILE_NOT_FOUND',
            'timestamp' => date('c')
        ]);
        exit;
    }

    // コメント更新
    $sanitizedComment = SecurityUtils::escapeHtml($comment);
    $stmt = $pdo->prepare("UPDATE uploaded SET comment = ? WHERE id = ?");
    $success = $stmt->execute([$sanitizedComment, $fileId]);

    if ($success) {
        // ログ記録（引数付きでLogger初期化）
        try {
            $logger = new Logger('../../logs');
            $logger->info('Comment updated', [
                'file_id' => $fileId,
                'old_comment' => $existingFile['comment'],
                'new_comment' => $sanitizedComment,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $logError) {
            // ログ記録失敗時は処理を継続
            error_log('Logger initialization failed: ' . $logError->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'コメントを更新しました',
            'data' => [
                'file_id' => $fileId,
                'new_comment' => $sanitizedComment
            ],
            'timestamp' => date('c')
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'コメントの更新に失敗しました',
            'error_code' => 'DATABASE_UPDATE_FAILED',
            'timestamp' => date('c')
        ]);
    }

} catch (PDOException $e) {
    error_log('Comment edit database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラーが発生しました',
        'error_code' => 'DATABASE_ERROR',
        'timestamp' => date('c')
    ]);
} catch (Exception $e) {
    error_log('Comment edit error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'サーバー内部エラーが発生しました',
        'error_code' => 'INTERNAL_ERROR',
        'timestamp' => date('c')
    ]);
}
?>