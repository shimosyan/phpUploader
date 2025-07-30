<?php

// データベースマイグレーション（共有リンク制限機能用）
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
} catch (Exception $e) {
    echo 'データベース接続エラー: ' . $e->getMessage();
    exit;
}

// デフォルトのフェッチモードを連想配列形式に設定
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// 共有制限カラムが存在するかチェック
$stmt = $db->prepare("PRAGMA table_info(uploaded)");
$stmt->execute();
$columns = $stmt->fetchAll();

$hasMaxDownloads = false;
$hasExpiresAt = false;

foreach ($columns as $column) {
    if ($column['name'] === 'max_downloads') {
        $hasMaxDownloads = true;
    }
    if ($column['name'] === 'expires_at') {
        $hasExpiresAt = true;
    }
}

// 必要なカラムを追加
if (!$hasMaxDownloads) {
    $query = "ALTER TABLE uploaded ADD COLUMN max_downloads INTEGER DEFAULT NULL";
    $result = $db->exec($query);
    if ($result !== false) {
        echo "max_downloads カラムを追加しました。\n";
    } else {
        echo "max_downloads カラムの追加に失敗しました。\n";
    }
}

if (!$hasExpiresAt) {
    $query = "ALTER TABLE uploaded ADD COLUMN expires_at INTEGER DEFAULT NULL";
    $result = $db->exec($query);
    if ($result !== false) {
        echo "expires_at カラムを追加しました。\n";
    } else {
        echo "expires_at カラムの追加に失敗しました。\n";
    }
}

if ($hasMaxDownloads && $hasExpiresAt) {
    echo "データベースは既に最新の状態です。\n";
} else {
    echo "マイグレーション完了！\n";
}