<?php
/**
 * バージョン確認テストスクリプト
 * config.phpがcomposer.jsonから正しくバージョンを読み取れるかテスト
 */

// configをインクルード
include('./config/config.php');

echo "=== バージョン情報テスト ===\n\n";

// configクラスのインスタンス化
$config = new config();
$configData = $config->index();

// composer.jsonから直接読み取り
$composerData = json_decode(file_get_contents('composer.json'), true);

echo "📦 composer.json version: " . ($composerData['version'] ?? 'N/A') . "\n";
echo "⚙️  config.php version:   " . ($configData['version'] ?? 'N/A') . "\n";

// 一致確認
if (($composerData['version'] ?? '') === ($configData['version'] ?? '')) {
    echo "✅ バージョン情報が一致しています！\n";
} else {
    echo "❌ バージョン情報が一致していません。\n";
}

echo "\n=== その他の設定情報 ===\n";
echo "Title: " . $configData['title'] . "\n";
echo "Max file size: " . $configData['max_file_size'] . "MB\n";
echo "Allowed extensions: " . implode(', ', $configData['extension']) . "\n";
