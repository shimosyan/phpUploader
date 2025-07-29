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

if (function_exists('getVersion')) {
    $configVersion = getVersion();
    echo "⚙️  config.php バージョン: $configVersion\n";

    if ($expectedVersion === $configVersion) {
        echo "✅ バージョンが一致しています！\n";
        exit(0);
    } else {
        echo "❌ バージョンが一致しません\n";
        echo "  期待値: $expectedVersion\n";
        echo "  実際の値: $configVersion\n";
        exit(1);
    }
} else {
    echo "❌ getVersion()関数が見つかりません\n";
    exit(1);
}

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
