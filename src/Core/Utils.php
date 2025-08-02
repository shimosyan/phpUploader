<?php

/**
 * セキュリティユーティリティクラス
 * Ver.2.0で追加されたセキュリティ機能
 */

declare(strict_types=1);

class SecurityUtils
{
    /**
     * CSRFトークンを生成
     */
    public static function generateCSRFToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * CSRFトークンを検証
     */
    public static function validateCSRFToken(?string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || $token === null) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * ファイル名をサニタイズ
     */
    public static function sanitizeFilename(string $filename): string
    {
        // 危険な文字を除去
        $filename = preg_replace('/[^\w\-_\.\s]/', '', $filename);
        // 複数のドットを単一のドットに
        $filename = preg_replace('/\.+/', '.', $filename);
        // 先頭末尾の空白とドットを除去
        $filename = trim($filename, ' .');

        return $filename;
    }

    /**
     * アップロードファイルの検証
     */
    public static function validateUploadedFile(array $file, array $config): array
    {
        $errors = [];

        // ファイルが選択されているか
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'ファイルが選択されていません。';
            return $errors;
        }

        // アップロードエラーのチェック
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = 'ファイルサイズが大きすぎます。';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = 'ファイルのアップロードが途中で失敗しました。';
                    break;
                default:
                    $errors[] = 'ファイルのアップロードに失敗しました。';
            }
            return $errors;
        }

        // ファイルサイズのチェック
        $maxSize = ($config['max_file_size'] ?? 10) * 1024 * 1024; // MB to bytes
        if ($file['size'] > $maxSize) {
            $errors[] = "ファイルサイズが制限を超えています。最大: " . ($config['max_file_size'] ?? 10) . "MB";
        }

        // 拡張子のチェック
        $allowedExtensions = $config['extension'] ?? ['jpg', 'png', 'gif', 'pdf', 'txt'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, $allowedExtensions, true)) {
            $errors[] = "許可されていない拡張子です。許可されている拡張子: " . implode(', ', $allowedExtensions);
        }

        // MIMEタイプの基本チェック
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimeTypes = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain'],
            'zip' => ['application/zip'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        ];

        if (isset($allowedMimeTypes[$fileExtension])) {
            if (!in_array($mimeType, $allowedMimeTypes[$fileExtension], true)) {
                $errors[] = "ファイルの内容が拡張子と一致しません。";
            }
        }

        return $errors;
    }

    /**
     * 安全なファイルパスを生成
     */
    public static function generateSafeFilePath(string $uploadDir, string $filename): string
    {
        // ディレクトリトラバーサル攻撃を防ぐ
        $filename = basename($filename);
        $filename = self::sanitizeFilename($filename);

        // ファイル名が空の場合はランダムな名前を生成
        if (empty($filename)) {
            $filename = 'file_' . time() . '_' . bin2hex(random_bytes(4));
        }

        // 重複を避けるためにタイムスタンプを追加
        $pathInfo = pathinfo($filename);
        $baseName = $pathInfo['filename'] ?? 'file';
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

        $counter = 0;
        do {
            if ($counter === 0) {
                $newFilename = $baseName . $extension;
            } else {
                $newFilename = $baseName . '_' . $counter . $extension;
            }
            $fullPath = rtrim($uploadDir, '/') . '/' . $newFilename;
            $counter++;
        } while (file_exists($fullPath) && $counter < 1000);

        return $fullPath;
    }

    /**
     * IPアドレスを取得（プロキシ対応）
     */
    public static function getClientIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // プロキシ
            'HTTP_X_FORWARDED_FOR',      // ロードバランサー/プロキシ
            'HTTP_X_FORWARDED',          // プロキシ
            'HTTP_X_CLUSTER_CLIENT_IP',  // クラスター
            'HTTP_FORWARDED_FOR',        // プロキシ
            'HTTP_FORWARDED',            // プロキシ
            'REMOTE_ADDR'                // 標準
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                // プライベートIPと予約済みIPを除外
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * ユーザーエージェントを安全に取得
     */
    public static function getUserAgent(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return htmlspecialchars($userAgent, ENT_QUOTES, 'UTF-8');
    }

    /**
     * リファラーを安全に取得
     */
    public static function getReferer(): string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        return htmlspecialchars($referer, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 安全な文字列エスケープ
     */
    public static function escapeHtml(?string $string): string
    {
        if ($string === null) {
            return '';
        }
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * ランダムなトークンを生成
     */
    public static function generateRandomToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * パスワードハッシュを生成（Argon2ID）
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);
    }

    /**
     * パスワードハッシュを検証
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * セキュアなファイル名を生成（ハッシュ化）
     */
    public static function generateSecureFileName(int $fileId, string $originalName): string
    {
        // ファイルIDと元のファイル名、現在時刻を組み合わせてハッシュ化
        $data = $fileId . '_' . $originalName . '_' . time() . '_' . bin2hex(random_bytes(8));
        return hash('sha256', $data);
    }

    /**
     * ハッシュ化されたファイル名から拡張子付きのフルファイル名を生成
     */
    public static function generateStoredFileName(string $hashedName, string $extension): string
    {
        return $hashedName . '.' . strtolower($extension);
    }
}

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

/**
 * レスポンスハンドラークラス
 * API応答を統一化
 */
class ResponseHandler
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * 成功応答を送信
     */
    public function success(string $message, array $data = []): void
    {
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ];

        $this->sendJson($response);
    }

    /**
     * エラー応答を送信
     */
    public function error(string $message, array $validationErrors = [], int $httpCode = 400, ?string $errorCode = null): void
    {
        $response = [
            'status' => 'error',
            'message' => $message,
            'timestamp' => date('c')
        ];

        if (!empty($validationErrors)) {
            $response['validation_errors'] = $validationErrors;
        }

        if ($errorCode !== null) {
            $response['error_code'] = $errorCode;
        }

        http_response_code($httpCode);
        $this->sendJson($response);
    }

    /**
     * JSON応答を送信
     */
    private function sendJson(array $data): void
    {
        // 出力バッファをクリア
        if (ob_get_level()) {
            ob_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * リダイレクト
     */
    public function redirect(string $url, int $httpCode = 302): void
    {
        http_response_code($httpCode);
        header("Location: {$url}");
        exit;
    }
}
