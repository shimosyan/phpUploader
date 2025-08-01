<?php

declare(strict_types=1);

namespace phpUploader\Core;

/**
 * セキュリティユーティリティクラス
 * Ver.2.0で追加されたセキュリティ機能
 */
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
