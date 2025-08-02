<?php

/**
 * バージョン確認テストスクリプト
 * config.phpがcomposer.jsonから正しくバージョンを読み取れるかテスト
 */

echo "=== バージョン情報テスト ===\n\n";

// config.phpが存在しない場合はテンプレートからコピー
if (!file_exists('./config/config.php')) {
    if (file_exists('./config/config.php.example')) {
        copy('./config/config.php.example', './config/config.php');
        echo "📋 config.php.exampleからconfig.phpを作成しました\n";
    } else {
        echo "❌ config.php.example が見つかりません\n";
        exit(1);
    }
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
include('./config/config.php');
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
