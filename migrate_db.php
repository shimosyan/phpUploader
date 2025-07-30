<?php
// データベースマイグレーションスクリプト
echo "=== データベースマイグレーション開始 ===\n";

try {
    $db = new PDO('sqlite:./data/data.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. uploadedテーブルを作成中...\n";
    
    // uploadedテーブルの作成
    $uploadedQuery = "
    CREATE TABLE IF NOT EXISTS uploaded (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        origin_file_name TEXT NOT NULL,
        comment TEXT,
        size INTEGER NOT NULL,
        count INTEGER NOT NULL DEFAULT 0,
        input_date INTEGER NOT NULL,
        dl_key TEXT,
        del_key TEXT,
        max_downloads INTEGER,
        expires_at INTEGER,
        folder_id INTEGER,
        replace_key TEXT,
        FOREIGN KEY (folder_id) REFERENCES folders (id) ON DELETE CASCADE
    )";
    
    $db->exec($uploadedQuery);
    echo "✅ uploadedテーブル作成完了\n";
    
    echo "2. tus_uploadsテーブルを作成中...\n";
    
    // tus_uploadsテーブルの作成
    $tusQuery = "
    CREATE TABLE IF NOT EXISTS tus_uploads (
        id TEXT PRIMARY KEY,
        file_size INTEGER NOT NULL,
        offset INTEGER NOT NULL DEFAULT 0,
        metadata TEXT,
        chunk_path TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL,
        expires_at INTEGER NOT NULL,
        completed BOOLEAN DEFAULT FALSE,
        comment TEXT,
        dl_key TEXT,
        del_key TEXT,
        max_downloads INTEGER,
        share_expires_at INTEGER,
        folder_id INTEGER,
        replace_key TEXT,
        FOREIGN KEY (folder_id) REFERENCES folders (id) ON DELETE CASCADE
    )";
    
    $db->exec($tusQuery);
    echo "✅ tus_uploadsテーブル作成完了\n";
    
    echo "3. foldersテーブルを作成中...\n";
    
    // foldersテーブルの作成
    $foldersQuery = "
    CREATE TABLE IF NOT EXISTS folders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        parent_id INTEGER DEFAULT NULL,
        created_at INTEGER NOT NULL,
        FOREIGN KEY (parent_id) REFERENCES folders (id) ON DELETE CASCADE
    )";
    
    $db->exec($foldersQuery);
    echo "✅ foldersテーブル作成完了\n";
    
    echo "4. file_historyテーブルを作成中...\n";
    
    // file_historyテーブルの作成
    $historyQuery = "
    CREATE TABLE IF NOT EXISTS file_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file_id INTEGER NOT NULL,
        old_filename TEXT,
        new_filename TEXT,
        old_comment TEXT,
        new_comment TEXT,
        change_type TEXT NOT NULL,
        changed_at INTEGER NOT NULL,
        changed_by TEXT,
        FOREIGN KEY (file_id) REFERENCES uploaded (id) ON DELETE CASCADE
    )";
    
    $db->exec($historyQuery);
    echo "✅ file_historyテーブル作成完了\n";
    
    echo "5. インデックスを作成中...\n";
    
    // インデックスの作成
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_tus_uploads_expires ON tus_uploads(expires_at)",
        "CREATE INDEX IF NOT EXISTS idx_tus_uploads_created ON tus_uploads(created_at)",
        "CREATE INDEX IF NOT EXISTS idx_tus_uploads_completed ON tus_uploads(completed)",
        "CREATE INDEX IF NOT EXISTS idx_uploaded_folder ON uploaded(folder_id)",
        "CREATE INDEX IF NOT EXISTS idx_folders_parent ON folders(parent_id)"
    ];

    foreach ($indexes as $indexQuery) {
        $db->exec($indexQuery);
    }
    echo "✅ インデックス作成完了\n";
    
    echo "\n=== マイグレーション完了 ===\n";
    echo "データベースが正常に初期化されました！\n";
    
} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    exit(1);
}
?>