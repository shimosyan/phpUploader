<?php

declare(strict_types=1);

/**
 * アプリケーション初期化スクリプト (Ver.2.0)
 *
 * 必要なディレクトリの作成、データベースの初期化、
 * セキュリティチェックを行います。
 */

class AppInitializer {

    private array $config;
    private ?PDO $db = null;

    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * 初期化メイン処理
     */
    public function initialize(): PDO {
        $this->validateConfig();
        $this->createDirectories();
        $this->initializeDatabase();
        $this->setupDatabase();

        return $this->db;
    }

    /**
     * 設定ファイルの検証
     */
    private function validateConfig(): void {
        // セキュリティ設定の検証
        if ($this->config['master'] === 'CHANGE_THIS_MASTER_KEY') {
            $this->throwError('マスターキーが設定されていません。config.phpを確認してください。');
        }

        if ($this->config['key'] === 'CHANGE_THIS_ENCRYPTION_KEY') {
            $this->throwError('暗号化キーが設定されていません。config.phpを確認してください。');
        }

        if ($this->config['session_salt'] === 'CHANGE_THIS_SESSION_SALT') {
            $this->throwError('セッションソルトが設定されていません。config.phpを確認してください。');
        }

        // 必要な拡張モジュールの確認
        $required_extensions = ['pdo', 'sqlite3', 'openssl', 'json'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $this->throwError("必要なPHP拡張モジュール '{$ext}' がロードされていません。");
            }
        }
    }

    /**
     * 必要なディレクトリの作成
     */
    private function createDirectories(): void {
        $directories = [
            $this->config['db_directory'],
            $this->config['data_directory'],
            $this->config['log_directory']
        ];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    $this->throwError("ディレクトリ '{$dir}' の作成に失敗しました。");
                }
            }

            // 書き込み権限の確認
            if (!is_writable($dir)) {
                $this->throwError("ディレクトリ '{$dir}' に書き込み権限がありません。");
            }
        }
    }

    /**
     * データベース接続の初期化
     */
    private function initializeDatabase(): void {
        try {
            $dsn = 'sqlite:' . $this->config['db_directory'] . '/uploader.db';
            $this->db = new PDO($dsn);

            // エラーモードを例外に設定
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // デフォルトのフェッチモードを連想配列形式に設定
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $this->throwError('データベースの接続に失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * データベーステーブルの作成・更新
     */
    private function setupDatabase(): void {
        try {
            // メインテーブルの作成
            $query = "
                CREATE TABLE IF NOT EXISTS uploaded(
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    origin_file_name text NOT NULL,
                    stored_file_name text,
                    comment text,
                    size INTEGER NOT NULL,
                    count INTEGER DEFAULT 0,
                    input_date INTEGER NOT NULL,
                    dl_key_hash text,
                    del_key_hash text,
                    file_hash text,
                    ip_address text,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    updated_at INTEGER DEFAULT (strftime('%s', 'now'))
                )";

            $this->db->exec($query);

            // トークンテーブルの作成（ワンタイムトークン用）
            $tokenQuery = "
                CREATE TABLE IF NOT EXISTS access_tokens(
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    file_id INTEGER NOT NULL,
                    token text NOT NULL UNIQUE,
                    token_type text NOT NULL, -- 'download' or 'delete'
                    expires_at INTEGER NOT NULL,
                    ip_address text,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    FOREIGN KEY (file_id) REFERENCES uploaded (id) ON DELETE CASCADE
                )";

            $this->db->exec($tokenQuery);

            // ログテーブルの作成
            $logQuery = "
                CREATE TABLE IF NOT EXISTS access_logs(
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    file_id INTEGER,
                    action text NOT NULL, -- 'upload', 'download', 'delete', 'view'
                    ip_address text,
                    user_agent text,
                    status text DEFAULT 'success', -- 'success', 'error', 'denied'
                    error_message text,
                    created_at INTEGER DEFAULT (strftime('%s', 'now'))
                )";

            $this->db->exec($logQuery);

            // インデックスの作成
            $this->createIndexes();

            // 既存データの移行（必要に応じて）
            $this->migrateExistingData();

        } catch (PDOException $e) {
            $this->throwError('データベースの初期化に失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * データベースインデックスの作成
     */
    private function createIndexes(): void {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_uploaded_input_date ON uploaded(input_date)",
            "CREATE INDEX IF NOT EXISTS idx_uploaded_file_hash ON uploaded(file_hash)",
            "CREATE INDEX IF NOT EXISTS idx_tokens_expires_at ON access_tokens(expires_at)",
            "CREATE INDEX IF NOT EXISTS idx_tokens_file_id ON access_tokens(file_id)",
            "CREATE INDEX IF NOT EXISTS idx_logs_created_at ON access_logs(created_at)",
            "CREATE INDEX IF NOT EXISTS idx_logs_file_id ON access_logs(file_id)"
        ];

        foreach ($indexes as $indexQuery) {
            $this->db->exec($indexQuery);
        }
    }

    /**
     * 既存データの移行処理
     */
    private function migrateExistingData(): void {
        // uploaded テーブルに新しいカラムが存在するかチェック
        $columns = $this->db->query("PRAGMA table_info(uploaded)")->fetchAll();
        $columnNames = array_column($columns, 'name');

        // 新しいカラムの追加
        $newColumns = [
            'stored_file_name' => 'ALTER TABLE uploaded ADD COLUMN stored_file_name text',
            'file_hash' => 'ALTER TABLE uploaded ADD COLUMN file_hash text',
            'ip_address' => 'ALTER TABLE uploaded ADD COLUMN ip_address text',
            'created_at' => 'ALTER TABLE uploaded ADD COLUMN created_at INTEGER DEFAULT (strftime(\'%s\', \'now\'))',
            'updated_at' => 'ALTER TABLE uploaded ADD COLUMN updated_at INTEGER DEFAULT (strftime(\'%s\', \'now\'))'
        ];

        foreach ($newColumns as $columnName => $alterQuery) {
            if (!in_array($columnName, $columnNames)) {
                try {
                    $this->db->exec($alterQuery);
                } catch (PDOException $e) {
                    // カラム追加に失敗した場合はログに記録するが処理は続行
                    error_log("Column migration failed for {$columnName}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * エラー処理
     */
    private function throwError(string $message): void {
        // ログディレクトリが存在する場合はエラーログを記録
        if (isset($this->config['log_directory']) && is_dir($this->config['log_directory'])) {
            $logFile = $this->config['log_directory'] . '/error.log';
            $logMessage = date('Y-m-d H:i:s') . " [ERROR] " . $message . PHP_EOL;
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }

        // エラーページの表示
        $error = '500 - ' . $message;
        include('./app/views/header.php');
        include('./app/views/error.php');
        include('./app/views/footer.php');
        exit;
    }
}

// 従来の処理との互換性のため、関数形式でのラッパーを提供
function initializeApp(array $config): PDO {
    $initializer = new AppInitializer($config);
    return $initializer->initialize();
}

// 従来のinit.phpとの互換性を保つため、直接実行された場合の処理
if (isset($config) && is_array($config)) {
    $db = initializeApp($config);
}
