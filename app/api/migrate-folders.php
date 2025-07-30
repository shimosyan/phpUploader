<?php

// フォルダ構造管理機能用データベースマイグレーション
ini_set('display_errors', 1);

// configをインクルード
include('../../config/config.php');
$config = new config();
$ret = $config->index();
// 配列キーが設定されている配列なら展開
if (!is_null($ret)) {
    if (is_array($ret)) {
        extract($ret);
    }
}

// データベースの作成・オープン
try {
    $db = new PDO('sqlite:../../' . $db_directory . '/uploader.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo 'データベース接続エラー: ' . $e->getMessage();
    exit;
}

// デフォルトのフェッチモードを連想配列形式に設定
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "フォルダ構造管理機能のマイグレーションを開始します...\n\n";

// 1. foldersテーブルが存在するかチェック
$stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='folders'");
$stmt->execute();
$foldersTableExists = $stmt->fetch();

if (!$foldersTableExists) {
    // foldersテーブルを作成
    $query = "
    CREATE TABLE folders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        parent_id INTEGER DEFAULT NULL,
        created_at INTEGER NOT NULL,
        FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE
    )";
    
    try {
        $db->exec($query);
        echo "✅ foldersテーブルを作成しました。\n";
    } catch (Exception $e) {
        echo "❌ foldersテーブルの作成に失敗しました: " . $e->getMessage() . "\n";
        exit;
    }
} else {
    echo "ℹ️ foldersテーブルは既に存在します。\n";
}

// 2. uploadedテーブルにfolder_idカラムが存在するかチェック
$stmt = $db->prepare("PRAGMA table_info(uploaded)");
$stmt->execute();
$columns = $stmt->fetchAll();

$hasFolderId = false;
foreach ($columns as $column) {
    if ($column['name'] === 'folder_id') {
        $hasFolderId = true;
        break;
    }
}

if (!$hasFolderId) {
    // folder_idカラムを追加
    $query = "ALTER TABLE uploaded ADD COLUMN folder_id INTEGER DEFAULT NULL";
    try {
        $db->exec($query);
        echo "✅ uploadedテーブルにfolder_idカラムを追加しました。\n";
        
        // FOREIGN KEY制約を追加（SQLiteの制限で後から追加は困難なので、インデックスのみ作成）
        $db->exec("CREATE INDEX IF NOT EXISTS idx_uploaded_folder_id ON uploaded(folder_id)");
        echo "✅ folder_idカラムにインデックスを作成しました。\n";
    } catch (Exception $e) {
        echo "❌ folder_idカラムの追加に失敗しました: " . $e->getMessage() . "\n";
        exit;
    }
} else {
    echo "ℹ️ uploadedテーブルのfolder_idカラムは既に存在します。\n";
}

// 3. デフォルトのルートフォルダを作成（parent_id = NULLのフォルダ）
$stmt = $db->prepare("SELECT COUNT(*) as count FROM folders WHERE parent_id IS NULL");
$stmt->execute();
$rootFolders = $stmt->fetch();

if ($rootFolders['count'] == 0) {
    // ルートフォルダを作成
    $stmt = $db->prepare("INSERT INTO folders (name, parent_id, created_at) VALUES ('ルート', NULL, ?)");
    $stmt->execute([time()]);
    echo "✅ デフォルトのルートフォルダを作成しました。\n";
} else {
    echo "ℹ️ ルートフォルダは既に存在します。\n";
}

echo "\n🎉 フォルダ構造管理機能のマイグレーションが完了しました！\n";
echo "次は設定ファイルを更新してフォルダ機能を有効化してください。\n";

// 現在のテーブル構造を表示
echo "\n📊 現在のテーブル構造:\n";
echo "=== foldersテーブル ===\n";
$stmt = $db->prepare("PRAGMA table_info(folders)");
$stmt->execute();
$folderColumns = $stmt->fetchAll();
foreach ($folderColumns as $col) {
    echo "- {$col['name']} ({$col['type']})\n";
}

echo "\n=== uploadedテーブル（抜粋） ===\n";
$stmt = $db->prepare("PRAGMA table_info(uploaded)");
$stmt->execute();
$uploadedColumns = $stmt->fetchAll();
foreach ($uploadedColumns as $col) {
    if (in_array($col['name'], ['id', 'origin_file_name', 'folder_id'])) {
        echo "- {$col['name']} ({$col['type']})\n";
    }
}

?>