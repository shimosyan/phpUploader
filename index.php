<?php

/**
 * PHP Uploader Ver.2.0 - メインエントリーポイント
 *
 * 簡易フレームワーク with モダンPHP対応
 */

declare(strict_types=1);

// エラー表示設定（本番環境用）
ini_set('display_errors', '0'); // 本番環境では 0 に設定
error_reporting(E_ALL);

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // 設定ファイルの読み込み
    if (!file_exists('./config/config.php')) {
        throw new Exception('設定ファイルが見つかりません。config.php.example を参考に config.php を作成してください。');
    }

    require_once './config/config.php';
    require_once './src/Core/Utils.php';

    $configInstance = new config();
    $config = $configInstance->index();

    // 設定の検証
    if (!$configInstance->validateSecurityConfig()) {
        throw new Exception('設定ファイルのセキュリティ設定が不完全です。config.php を確認してください。');
    }

    // ページパラメータの取得
    $page = $_GET['page'] ?? 'index';
    $page = preg_replace('/[^a-zA-Z0-9_]/', '', $page); // セキュリティ: 英数字とアンダースコアのみ許可

    // アプリケーション初期化
    require_once './app/models/init.php';
    $db = initializeApp($config);

    // ログ機能の初期化
    $logger = new Logger(
        $config['log_directory'],
        $config['log_level'],
        $db
    );

    // レスポンスハンドラーの初期化
    $responseHandler = new ResponseHandler($logger);

    // アクセスログの記録
    $logger->access(null, 'page_view', 'success');

    // モデルの読み込みと実行
    $modelData = [];
    $modelPath = "./app/models/{$page}.php";

    if (file_exists($modelPath)) {
        require_once $modelPath;

        if (class_exists($page)) {
            $model = new $page();
            if (method_exists($model, 'index')) {
                $result = $model->index();
                if (is_array($result)) {
                    $modelData = $result;
                }
            }
        }
    }

    // ビューの描画
    $viewData = array_merge($config, $modelData, [
        'logger' => $logger,
        'responseHandler' => $responseHandler,
        'db' => $db,
        'csrf_token' => SecurityUtils::generateCSRFToken(),
        'status_message' => $_GET['deleted'] ?? null
    ]);

    // 変数の展開
    extract($viewData);

    // ヘッダーの出力
    require './app/views/header.php';

    // メインコンテンツの出力
    $viewPath = "./app/views/{$page}.php";
    if (file_exists($viewPath)) {
        require $viewPath;
    } else {
        $error = '404 - ページが見つかりません。';
        require './app/views/error.php';
    }

    // フッターの出力
    require './app/views/footer.php';
} catch (Exception $e) {
    // 緊急時のエラーハンドリング
    $errorMessage = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');

    // ログが利用可能な場合はエラーログに記録
    if (isset($logger)) {
        $logger->error('Application Error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    } else {
        // ログが利用できない場合はファイルに直接記録
        $logMessage = date('Y-m-d H:i:s') .
            ' [CRITICAL] ' . $e->getMessage() .
            ' in ' .
            $e->getFile() .
            ' on line ' .
            $e->getLine() .
            PHP_EOL;
        @file_put_contents('./logs/critical.log', $logMessage, FILE_APPEND | LOCK_EX);
    }

    // シンプルなエラーページの表示
    http_response_code(500);
    echo '<!DOCTYPE html>';
    echo '<html><head><meta charset="UTF-8"><title>エラー</title></head>';
    echo '<body><h1>システムエラー</h1>';
    echo '<p>' . $errorMessage . '</p>';
    echo '<p><a href="./index.php">トップページに戻る</a></p>';
    echo '</body></html>';
}
