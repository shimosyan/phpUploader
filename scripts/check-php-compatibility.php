<?php
/**
 * PHP 5.6互換性チェックスクリプト
 * 使用している機能がPHP 5.6で動作するかチェック
 */

echo "=== PHP 5.6 互換性チェック ===\n\n";

$phpVersion = PHP_VERSION;
echo "現在のPHPバージョン: $phpVersion\n\n";

// 必要な拡張機能のチェック
$requiredExtensions = ['pdo', 'pdo_sqlite', 'openssl', 'json'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
        echo "❌ 拡張機能 '$ext' が利用できません\n";
    } else {
        echo "✅ 拡張機能 '$ext' が利用可能\n";
    }
}

echo "\n";

// 必要な関数のチェック
$requiredFunctions = [
    'json_decode' => '5.2.0',
    'json_encode' => '5.2.0', 
    'openssl_encrypt' => '5.3.0',
    'file_get_contents' => '4.3.0',
    'file_put_contents' => '5.0.0'
];

foreach ($requiredFunctions as $func => $minVersion) {
    if (!function_exists($func)) {
        echo "❌ 関数 '$func' が利用できません (PHP $minVersion+ で必要)\n";
    } else {
        echo "✅ 関数 '$func' が利用可能\n";
    }
}

echo "\n";

// PDOドライバーのチェック
if (extension_loaded('pdo')) {
    $drivers = PDO::getAvailableDrivers();
    if (in_array('sqlite', $drivers)) {
        echo "✅ PDO SQLiteドライバが利用可能\n";
    } else {
        echo "❌ PDO SQLiteドライバが利用できません\n";
    }
} else {
    echo "❌ PDOが利用できません\n";
}

echo "\n";

// 基本設定チェック
$configTest = true;

try {
    // configファイルの基本テスト
    if (file_exists('config/config.php.example')) {
        echo "✅ config.php.example が存在します\n";

        // 一時的にconfig.phpを作成してテスト
        copy('config/config.php.example', 'config/config.php.test');
        include 'config/config.php.test';

        $config = new config();
        $settings = $config->index();

        if (isset($settings['version'])) {
            echo "✅ バージョン情報取得: " . $settings['version'] . "\n";
        }

        unlink('config/config.php.test');

    } else {
        echo "❌ config.php.example が見つかりません\n";
        $configTest = false;
    }
} catch (Exception $e) {
    echo "❌ 設定ファイルテストでエラー: " . $e->getMessage() . "\n";
    $configTest = false;
}

echo "\n=== 結果 ===\n";

if (empty($missingExtensions) && $configTest) {
    echo "✅ PHP 5.6互換性チェック合格！\n";
    echo "このアプリケーションは現在のPHP環境で動作する可能性が高いです。\n";
} else {
    echo "❌ 互換性に問題があります:\n";
    if (!empty($missingExtensions)) {
        echo "- 不足している拡張機能: " . implode(', ', $missingExtensions) . "\n";
    }
    if (!$configTest) {
        echo "- 設定ファイルの読み込みに問題があります\n";
    }
}

echo "\n推奨環境: PHP 7.4以上\n";
echo "最小要件: PHP 5.6以上\n";
?>
