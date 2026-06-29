<?php

/**
 * バージョン確認テストスクリプト
 * config.phpがcomposer.jsonから正しくバージョンを読み取れるかテスト
 */

echo "=== バージョン情報テスト ===\n\n";

// config.phpが存在しない場合はテンプレートを直接使用
$configPath = './config/config.php';
if (!file_exists($configPath)) {
    $configPath = './config/config.php.example';
    if (!file_exists($configPath)) {
        echo "❌ config.php.example が見つかりません\n";
        exit(1);
    }

    echo "📋 config.php.exampleを使用します\n";
}

// composer.jsonのバージョンを取得
$composerJson = './composer.json';
if (!file_exists($composerJson)) {
    echo "❌ composer.json が見つかりません\n";
    exit(1);
}

$composerData = json_decode(file_get_contents($composerJson), true);
if (!$composerData || !isset($composerData['version'])) {
    echo "❌ composer.jsonからバージョンを取得できません\n";
    exit(1);
}

$expectedVersion = $composerData['version'];
echo "📦 composer.json バージョン: $expectedVersion\n";

// config.phpからバージョンを取得
ob_start();
include($configPath);
ob_end_clean();

// configクラスのインスタンス化
$config = new \PHPUploader\Config();
$configData = $config->index();

$configVersion = $configData['version'] ?? 'N/A';
echo "⚙️  config.php バージョン: $configVersion\n";

// 一致確認
if ($expectedVersion === $configVersion) {
    echo "✅ バージョンが一致しています！\n";
    echo "\n=== その他の設定情報 ===\n";
    echo 'Title: ' . $configData['title'] . "\n";
    echo 'Max file size: ' . $configData['maxFileSize'] . "MB\n";
    echo 'Allowed extensions: ' . implode(', ', $configData['extension']) . "\n";
    exit(0);
} else {
    echo "❌ バージョンが一致しません\n";
    echo "  期待値: $expectedVersion\n";
    echo "  実際の値: $configVersion\n";
    exit(1);
}
