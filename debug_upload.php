<?php
// デバッグ用アップロードテスト
echo "=== Upload API Debug Test ===\n";

// 設定ファイルのチェック
if (file_exists(__DIR__ . '/config/config.php')) {
    echo "✓ config.php exists\n";
    require_once __DIR__ . '/config/config.php';
    $configInstance = new config();
    $config = $configInstance->index();
    echo "✓ Config loaded successfully\n";
    echo "- max_file_size: " . $config['max_file_size'] . "MB\n";
    echo "- data_directory: " . $config['data_directory'] . "\n";
} else {
    echo "✗ config.php not found\n";
    exit(1);
}

// データベースのチェック
if (file_exists(__DIR__ . '/db/uploader.db')) {
    echo "✓ Database exists\n";
    try {
        $db = new PDO('sqlite:' . __DIR__ . '/db/uploader.db');
        echo "✓ Database connection successful\n";
        
        // テーブル存在チェック
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        echo "- Tables: " . implode(', ', $tables) . "\n";
    } catch (Exception $e) {
        echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ Database not found\n";
}

// データディレクトリのチェック
if (is_dir($config['data_directory'])) {
    echo "✓ Data directory exists: " . $config['data_directory'] . "\n";
    echo "- Writable: " . (is_writable($config['data_directory']) ? 'Yes' : 'No') . "\n";
} else {
    echo "✗ Data directory not found: " . $config['data_directory'] . "\n";
}

echo "\n=== End Debug Test ===\n";
?>