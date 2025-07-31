<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../src/Core/Utils.php';

/**
 * システムAPI操作ハンドラー
 * システム情報・統計・ヘルスチェックなどを担当
 */
class SystemApiHandler
{
    private array $config;
    private $auth;
    private ResponseHandler $response;

    public function __construct($config, $auth, ResponseHandler $response)
    {
        $this->config = $config;
        $this->auth = $auth;
        $this->response = $response;
    }

    /**
     * システム状態取得
     */
    public function handleGetStatus(): void
    {
        try {
            // 基本情報
            $statusInfo = [
                'status' => 'ok',
                'version' => '2.0.0',
                'api_enabled' => $this->config['api_enabled'] ?? true,
                'folders_enabled' => $this->config['folders_enabled'] ?? false,
                'server_time' => date('c'),
                'timestamp' => time()
            ];

            // データベース統計情報を追加（権限がある場合）
            if ($this->auth->hasPermission('read')) {
                $statusInfo = array_merge($statusInfo, $this->getDatabaseStats());
            }

            // システム情報を追加（管理者権限がある場合）
            if ($this->auth->hasPermission('admin')) {
                $statusInfo = array_merge($statusInfo, $this->getSystemInfo());
            }

            $this->response->success('システム状態を取得しました', $statusInfo);

        } catch (Exception $e) {
            error_log('System status error: ' . $e->getMessage());
            $this->response->error('システム状態の取得に失敗しました', [], 500, 'SYSTEM_ERROR');
        }
    }

    /**
     * データベース統計情報を取得
     */
    private function getDatabaseStats(): array
    {
        try {
            $db_directory = '../../db';
            $dsn = 'sqlite:' . $db_directory . '/uploader.db';
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // ファイル統計
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_files, SUM(size) as total_size FROM uploaded");
            $stmt->execute();
            $fileStats = $stmt->fetch(PDO::FETCH_ASSOC);

            // フォルダ統計
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_folders FROM folders");
            $stmt->execute();
            $folderStats = $stmt->fetch(PDO::FETCH_ASSOC);

            // 最近のアップロード統計
            $last24h = time() - (24 * 60 * 60);
            $stmt = $pdo->prepare("SELECT COUNT(*) as recent_uploads FROM uploaded WHERE input_date > ?");
            $stmt->execute(array($last24h));
            $recentStats = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'database' => [
                    'total_files' => (int)$fileStats['total_files'],
                    'total_size' => (int)($fileStats['total_size'] ?? 0),
                    'total_folders' => (int)$folderStats['total_folders'],
                    'recent_uploads_24h' => (int)$recentStats['recent_uploads']
                ]
            ];

        } catch (PDOException $e) {
            error_log('Database stats error: ' . $e->getMessage());
            return ['database' => ['error' => 'データベース統計の取得に失敗しました']];
        }
    }

    /**
     * システム情報を取得（管理者用）
     */
    private function getSystemInfo(): array
    {
        try {
            return [
                'system' => [
                    'php_version' => PHP_VERSION,
                    'memory_usage' => memory_get_usage(true),
                    'memory_peak' => memory_get_peak_usage(true),
                    'disk_free_space' => disk_free_space('.'),
                    'disk_total_space' => disk_total_space('.'),
                    'uptime' => $this->getServerUptime(),
                    'config' => [
                        'max_file_size' => $this->config['max_file_size'] ?? 'unknown',
                        'allowed_extensions' => $this->config['extension'] ?? [],
                        'upload_directory' => realpath('../../data/'),
                        'database_file' => realpath('../../db/uploader.db')
                    ]
                ]
            ];

        } catch (Exception $e) {
            error_log('System info error: ' . $e->getMessage());
            return ['system' => ['error' => 'システム情報の取得に失敗しました']];
        }
    }

    /**
     * サーバーのアップタイムを取得
     */
    private function getServerUptime(): ?string
    {
        try {
            if (PHP_OS_FAMILY === 'Linux') {
                $uptimeString = file_get_contents('/proc/uptime');
                if ($uptimeString !== false) {
                    $uptime = (float)strtok($uptimeString, ' ');
                    return $this->formatUptime($uptime);
                }
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * アップタイムを人間が読みやすい形式にフォーマット
     */
    private function formatUptime(float $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days > 0) $parts[] = $days . '日';
        if ($hours > 0) $parts[] = $hours . '時間';
        if ($minutes > 0) $parts[] = $minutes . '分';

        return empty($parts) ? '1分未満' : implode(' ', $parts);
    }

    /**
     * システムヘルスチェック
     */
    public function handleHealthCheck(): void
    {
        $health = [
            'status' => 'healthy',
            'checks' => []
        ];

        try {
            // データベース接続チェック
            $health['checks']['database'] = $this->checkDatabase();

            // ファイルシステムチェック
            $health['checks']['filesystem'] = $this->checkFilesystem();

            // 設定チェック
            $health['checks']['config'] = $this->checkConfig();

            // 全体の健康状態を判定
            $allHealthy = true;
            foreach ($health['checks'] as $check) {
                if ($check['status'] !== 'ok') {
                    $allHealthy = false;
                    break;
                }
            }

            $health['status'] = $allHealthy ? 'healthy' : 'unhealthy';
            $health['timestamp'] = date('c');

            if ($allHealthy) {
                $this->response->success('システムは正常です', $health);
            } else {
                $this->response->error('システムに問題があります', $health, 503, 'SYSTEM_UNHEALTHY');
            }

        } catch (Exception $e) {
            error_log('Health check error: ' . $e->getMessage());
            $this->response->error('ヘルスチェックに失敗しました', [], 500, 'HEALTH_CHECK_ERROR');
        }
    }

    /**
     * データベース接続チェック
     */
    private function checkDatabase(): array
    {
        try {
            $db_directory = '../../db';
            $dsn = 'sqlite:' . $db_directory . '/uploader.db';
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // 簡単なクエリでテスト
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM uploaded");
            $stmt->execute();
            $stmt->fetchColumn();

            return ['status' => 'ok', 'message' => 'データベース接続正常'];

        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'データベース接続エラー: ' . $e->getMessage()];
        }
    }

    /**
     * ファイルシステムチェック
     */
    private function checkFilesystem(): array
    {
        try {
            $dataDir = '../../data/';
            $dbDir = '../../db/';

            $checks = [];

            // データディレクトリの存在・書き込み権限チェック
            if (!is_dir($dataDir)) {
                $checks[] = 'データディレクトリが存在しません';
            } elseif (!is_writable($dataDir)) {
                $checks[] = 'データディレクトリに書き込み権限がありません';
            }

            // データベースディレクトリの存在・書き込み権限チェック
            if (!is_dir($dbDir)) {
                $checks[] = 'データベースディレクトリが存在しません';
            } elseif (!is_writable($dbDir)) {
                $checks[] = 'データベースディレクトリに書き込み権限がありません';
            }

            // ディスク容量チェック
            $freeSpace = disk_free_space('.');
            $totalSpace = disk_total_space('.');
            if ($freeSpace && $totalSpace) {
                $usage = ($totalSpace - $freeSpace) / $totalSpace;
                if ($usage > 0.95) { // 95%以上使用
                    $checks[] = 'ディスク使用量が95%を超えています';
                }
            }

            if (empty($checks)) {
                return ['status' => 'ok', 'message' => 'ファイルシステム正常'];
            } else {
                return ['status' => 'error', 'message' => implode(', ', $checks)];
            }

        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'ファイルシステムチェックエラー: ' . $e->getMessage()];
        }
    }

    /**
     * 設定チェック
     */
    private function checkConfig(): array
    {
        try {
            $issues = [];

            // 必要な設定項目のチェック
            $requiredConfigs = ['key', 'extension', 'max_file_size'];
            foreach ($requiredConfigs as $key) {
                if (!isset($this->config[$key])) {
                    $issues[] = "設定項目 '{$key}' が未定義です";
                }
            }

            // セキュリティ設定のチェック
            if (!isset($this->config['key']) || strlen($this->config['key']) < 32) {
                $issues[] = '暗号化キーが短すぎます（32文字以上推奨）';
            }

            if (empty($issues)) {
                return ['status' => 'ok', 'message' => '設定正常'];
            } else {
                return ['status' => 'warning', 'message' => implode(', ', $issues)];
            }

        } catch (Exception $e) {
            return ['status' => 'error', 'message' => '設定チェックエラー: ' . $e->getMessage()];
        }
    }
}