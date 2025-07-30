<?php
// アップロードテスト用デバッグスクリプト
echo "=== アップロードAPIテスト ===\n";

// 設定読み込み
require_once './config/config.php';

// テストデータ
$testData = [
    'comment' => 'デバッグテスト',
    'replacekey' => 'debug-test-key-2025'
];

echo "テストデータ:\n";
print_r($testData);

// 疑似ファイルデータを作成
$_POST = $testData;
$_FILES = [
    'file' => [
        'name' => 'debug-test.txt',
        'type' => 'text/plain',
        'size' => 100,
        'tmp_name' => '/tmp/test',
        'error' => UPLOAD_ERR_OK
    ]
];

echo "\n=== $_POST データ ===\n";
print_r($_POST);

echo "\n=== $_FILES データ ===\n";
print_r($_FILES);

// upload.phpの処理をシミュレート
try {
    echo "\n=== データベース接続テスト ===\n";
    $db = new PDO('sqlite:./data/data.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ データベース接続成功\n";
    
    // 差し替えキーのバリデーション
    $replacekey = $_POST['replacekey'] ?? '';
    echo "\n=== 差し替えキー検証 ===\n";
    echo "入力された差し替えキー: '$replacekey'\n";
    
    if (empty($replacekey)) {
        echo "❌ 差し替えキーが空です\n";
    } else {
        echo "✅ 差し替えキーが存在します\n";
    }
    
    echo "\n=== ファイル検証 ===\n";
    if (!isset($_FILES['file'])) {
        echo "❌ \$_FILES['file'] が存在しません\n";
    } else {
        echo "✅ \$_FILES['file'] が存在します\n";
        echo "エラーコード: " . $_FILES['file']['error'] . "\n";
        
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo "❌ ファイルアップロードエラー\n";
        } else {
            echo "✅ ファイルアップロード正常\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
}

echo "\n=== テスト完了 ===\n";
?>