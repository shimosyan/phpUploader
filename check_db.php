<?php
// データベース構造確認スクリプト
require_once './config/config.php';

try {
    $db = new PDO('sqlite:./data/data.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== uploadedテーブルの構造 ===\n";
    $result = $db->query("PRAGMA table_info(uploaded)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    $hasReplaceKey = false;
    foreach ($columns as $column) {
        echo "- " . $column['name'] . " (" . $column['type'] . ")\n";
        if ($column['name'] === 'replace_key') {
            $hasReplaceKey = true;
        }
    }
    
    echo "\n=== tus_uploadsテーブルの構造 ===\n";
    $result = $db->query("PRAGMA table_info(tus_uploads)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    $hasTusReplaceKey = false;
    foreach ($columns as $column) {
        echo "- " . $column['name'] . " (" . $column['type'] . ")\n";
        if ($column['name'] === 'replace_key') {
            $hasTusReplaceKey = true;
        }
    }
    
    echo "\n=== マイグレーション状況 ===\n";
    echo "uploaded.replace_key: " . ($hasReplaceKey ? "存在" : "不在") . "\n";
    echo "tus_uploads.replace_key: " . ($hasTusReplaceKey ? "存在" : "不在") . "\n";
    
    if (!$hasReplaceKey || !$hasTusReplaceKey) {
        echo "\n⚠️ マイグレーションが必要です！\n";
    } else {
        echo "\n✅ データベースは最新状態です\n";
    }
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
}
?>