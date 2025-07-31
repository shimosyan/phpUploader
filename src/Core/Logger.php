<?php

declare(strict_types=1);

// Security.phpが必要な場合のため
require_once __DIR__ . '/Security.php';

/**
 * ロガークラス
 * ファイルとデータベースの両方にログを記録
 */
class Logger
{
    private string $logDirectory;
    private string $logLevel;
    private ?PDO $db;

    public const LOG_EMERGENCY = 'emergency';
    public const LOG_ALERT = 'alert';
    public const LOG_CRITICAL = 'critical';
    public const LOG_ERROR = 'error';
    public const LOG_WARNING = 'warning';
    public const LOG_NOTICE = 'notice';
    public const LOG_INFO = 'info';
    public const LOG_DEBUG = 'debug';

    private const LOG_LEVELS = [
        self::LOG_EMERGENCY => 0,
        self::LOG_ALERT => 1,
        self::LOG_CRITICAL => 2,
        self::LOG_ERROR => 3,
        self::LOG_WARNING => 4,
        self::LOG_NOTICE => 5,
        self::LOG_INFO => 6,
        self::LOG_DEBUG => 7,
    ];

    public function __construct(string $logDirectory, string $logLevel = self::LOG_INFO, ?PDO $db = null)
    {
        $this->logDirectory = rtrim($logDirectory, '/');
        $this->logLevel = $logLevel;
        $this->db = $db;

        // ログディレクトリが存在しない場合は作成
        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0755, true);
        }
    }

    /**
     * ログレベルに応じてログを記録するか判定
     */
    private function shouldLog(string $level): bool
    {
        $currentLevel = self::LOG_LEVELS[$this->logLevel] ?? 6;
        $messageLevel = self::LOG_LEVELS[$level] ?? 6;

        return $messageLevel <= $currentLevel;
    }

    /**
     * 汎用ログメソッド
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        // ファイルログ
        $logFile = $this->logDirectory . '/' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

        // データベースログ（利用可能な場合）
        if ($this->db) {
            try {
                $stmt = $this->db->prepare('
                    INSERT INTO access_logs (file_id, action, ip_address, user_agent, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                
                if ($stmt) {
                    $stmt->execute([
                        null, // file_id は通常のログでは null
                        $level,
                        SecurityUtils::getClientIP(),
                        SecurityUtils::getUserAgent(),
                        'logged',
                        time()
                    ]);
                }
            } catch (Exception $e) {
                // データベースログに失敗してもファイルログは残す
                error_log("Database logging failed: " . $e->getMessage());
            }
        }
    }

    // 各ログレベルのショートカットメソッド
    public function emergency(string $message, array $context = []): void { $this->log(self::LOG_EMERGENCY, $message, $context); }
    public function alert(string $message, array $context = []): void { $this->log(self::LOG_ALERT, $message, $context); }
    public function critical(string $message, array $context = []): void { $this->log(self::LOG_CRITICAL, $message, $context); }
    public function error(string $message, array $context = []): void { $this->log(self::LOG_ERROR, $message, $context); }
    public function warning(string $message, array $context = []): void { $this->log(self::LOG_WARNING, $message, $context); }
    public function notice(string $message, array $context = []): void { $this->log(self::LOG_NOTICE, $message, $context); }
    public function info(string $message, array $context = []): void { $this->log(self::LOG_INFO, $message, $context); }
    public function debug(string $message, array $context = []): void { $this->log(self::LOG_DEBUG, $message, $context); }

    /**
     * アクセスログ専用メソッド
     */
    public function access(int|string|null $fileId, string $action, string $status, array $context = []): void
    {
        $message = "Access: {$action} | Status: {$status}";
        if ($fileId) {
            $message .= " | File ID: {$fileId}";
        }

        $accessContext = array_merge($context, [
            'ip' => SecurityUtils::getClientIP(),
            'user_agent' => SecurityUtils::getUserAgent(),
            'referer' => SecurityUtils::getReferer(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
        ]);

        // ファイルログ
        $this->info($message, $accessContext);

        // データベースログ（access_logs テーブル用）
        if ($this->db) {
            try {
                $stmt = $this->db->prepare('
                    INSERT INTO access_logs (file_id, action, ip_address, user_agent, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ');

                if ($stmt) {
                    $stmt->execute([
                        $fileId ? (int)$fileId : null,
                        $action,
                        SecurityUtils::getClientIP(),
                        SecurityUtils::getUserAgent(),
                        $status,
                        time()
                    ]);
                }
            } catch (Exception $e) {
                error_log("Access log to database failed: " . $e->getMessage());
            }
        }
    }
}