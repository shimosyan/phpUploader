<?php
/**
 * Tus.io セッション清理処理
 * 期限切れのアップロードセッションとチャンクファイルを削除
 * phpUploader - Tus.io Cleanup Script
 */

//configをインクルード
include(__DIR__ . '/../../config/config.php');
$config = new config();
$ret = $config->index();
if (!is_null($ret) && is_array($ret)) {
    extract($ret);
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../../' . $db_directory . '/uploader.db');
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    $currentTime = time();
    $deletedCount = 0;
    
    // 期限切れのセッションを取得
    $sql = $db->prepare("
        SELECT id, chunk_path 
        FROM tus_uploads 
        WHERE expires_at < ? AND completed = 0
    ");
    $sql->execute([$currentTime]);
    $expiredUploads = $sql->fetchAll();
    
    foreach ($expiredUploads as $upload) {
        // チャンクファイルを削除
        if (file_exists($upload['chunk_path'])) {
            unlink($upload['chunk_path']);
        }
        
        // データベースから削除
        $deleteSql = $db->prepare("DELETE FROM tus_uploads WHERE id = ?");
        $deleteSql->execute([$upload['id']]);
        
        $deletedCount++;
    }
    
    // 24時間以上前の完了済みセッションも削除
    $oldCompletedSql = $db->prepare("
        DELETE FROM tus_uploads 
        WHERE completed = 1 AND updated_at < ?
    ");
    $oldCompletedSql->execute([$currentTime - (24 * 60 * 60)]);
    
    echo "Cleaned up $deletedCount expired upload sessions\n";
    
} catch (Exception $e) {
    echo "Cleanup failed: " . $e->getMessage() . "\n";
}
?>