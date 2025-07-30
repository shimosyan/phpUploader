<?php

declare(strict_types=1);

/**
 * ファイル削除処理 (Ver.2.0)
 *
 * ワンタイムトークンによる安全なファイル削除
 */

// エラー表示設定
ini_set('display_errors', '0');

try {
    // 設定とユーティリティの読み込み
    require_once './config/config.php';
    require_once './src/Core/Utils.php';

    $configInstance = new config();
    $config = $configInstance->index();

    // アプリケーション初期化
    require_once './app/models/init.php';
    $db = initializeApp($config);

    // ログ機能の初期化
    $logger = new Logger($config['log_directory'], $config['log_level'], $db);

    // パラメータの取得
    $fileId = (int)($_GET['id'] ?? 0);
    $token = $_GET['key'] ?? '';

    if ($fileId <= 0 || empty($token)) {
        $logger->warning('Invalid delete parameters', ['file_id' => $fileId, 'token_provided' => !empty($token)]);
        header('Location: ./');
        exit;
    }

    // トランザクション開始
    $db->beginTransaction();

    try {
        // トークンの検証
        $tokenStmt = $db->prepare("
            SELECT t.*, u.origin_file_name, u.file_hash
            FROM access_tokens t
            JOIN uploaded u ON t.file_id = u.id
            WHERE t.token = :token AND t.token_type = 'delete' AND t.file_id = :file_id AND t.expires_at > :now
        ");

        $tokenStmt->execute([
            'token' => $token,
            'file_id' => $fileId,
            'now' => time()
        ]);

        $tokenData = $tokenStmt->fetch();

        if (!$tokenData) {
            $logger->warning('Invalid or expired delete token', ['file_id' => $fileId, 'token' => substr($token, 0, 8) . '...']);
            $db->rollBack();
            header('Location: ./');
            exit;
        }

        // IPアドレスの検証（設定で有効な場合）
        if ($config['security']['log_ip_address'] && !empty($tokenData['ip_address'])) {
            $currentIP = $_SERVER['REMOTE_ADDR'] ?? '';
            if ($currentIP !== $tokenData['ip_address']) {
                $logger->warning('IP address mismatch for delete', [
                    'file_id' => $fileId,
                    'token_ip' => $tokenData['ip_address'],
                    'current_ip' => $currentIP
                ]);
                // IPアドレスが異なる場合は警告ログのみで、削除は継続
            }
        }

        // ファイルパスの生成
        $fileName = $tokenData['origin_file_name'];
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $safeFileName = SecurityUtils::generateSafeFileName($fileId, $config['key']);
        $filePath = $config['data_directory'] . '/' . $safeFileName . '.' . $fileExtension;

        // 物理ファイルの削除
        $fileDeleted = false;
        if (file_exists($filePath)) {
            // ファイルハッシュの検証（ファイル整合性チェック）
            if (!empty($tokenData['file_hash'])) {
                $currentHash = SecurityUtils::generateFileHash($filePath);
                if ($currentHash !== $tokenData['file_hash']) {
                    $logger->warning('File integrity check failed during delete', [
                        'file_id' => $fileId,
                        'expected_hash' => $tokenData['file_hash'],
                        'current_hash' => $currentHash
                    ]);
                    // 整合性チェックに失敗した場合でも削除は継続（ファイルが破損している可能性）
                }
            }

            if (unlink($filePath)) {
                $fileDeleted = true;
                $logger->info('Physical file deleted', ['file_id' => $fileId, 'path' => $filePath]);
            } else {
                $logger->error('Failed to delete physical file', ['file_id' => $fileId, 'path' => $filePath]);
            }
        } else {
            $logger->warning('Physical file not found for delete', ['file_id' => $fileId, 'path' => $filePath]);
            $fileDeleted = true; // ファイルが存在しない場合は削除済みとみなす
        }

        // データベースからファイル情報を削除
        $deleteStmt = $db->prepare("DELETE FROM uploaded WHERE id = :id");
        if (!$deleteStmt->execute(['id' => $fileId])) {
            throw new Exception('Failed to delete file record from database');
        }

        // 関連するアクセストークンを削除
        $deleteTokensStmt = $db->prepare("DELETE FROM access_tokens WHERE file_id = :file_id");
        $deleteTokensStmt->execute(['file_id' => $fileId]);

        // トランザクションコミット
        $db->commit();

        // アクセスログの記録
        $logger->access($fileId, 'delete', 'success');

        // 成功時のリダイレクト
        header('Location: ./?deleted=success');
        exit;

    } catch (Exception $e) {
        // トランザクションロールバック
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    // 緊急時のエラーハンドリング
    if (isset($logger)) {
        $logger->error('Delete Error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'file_id' => $fileId ?? null
        ]);
    }

    header('Location: ./?deleted=error');
    exit;
}

// ファイル名取得
$stmt = $db->prepare("SELECT * FROM uploaded WHERE id = :id");
$stmt->bindValue(':id', $id); //ID
$stmt->execute();
$result = $stmt->fetchAll();
foreach($result as $s){
    $filename = $s['origin_file_name'];
    $origin_delkey = $s['del_key'];
}

// ハッシュを照合して認証が通ればDEL可
if ( PHP_MAJOR_VERSION == '5' and PHP_MINOR_VERSION == '3') {
    if( $delkey !== bin2hex(openssl_encrypt($origin_delkey,'aes-256-ecb',$key, true)) ){
        header('location: ./');
        exit;
    }
}else{
    if( $delkey !== bin2hex(openssl_encrypt($origin_delkey,'aes-256-ecb',$key, OPENSSL_RAW_DATA)) ){
        header('location: ./');
        exit;
    }
}

// sqlから削除
$sql = $db->prepare("DELETE FROM uploaded WHERE id = :id");
$sql->bindValue(':id', $id); //ID
if (! $sql->execute()) {
    // 削除を実施
}

//ディレクトリから削除
$ext = substr( $filename, strrpos( $filename, '.') + 1);
if ($encrypt_filename) {
    $path = $data_directory.'/' . 'file_' . str_replace(array('\\', '/', ':', '*', '?', '\"', '<', '>', '|'), '',openssl_encrypt($id,'aes-256-ecb',$key)) . '.'.$ext;
    if (!file_exists ( $path )) {
        $path = $data_directory.'/' . 'file_' . $id . '.'.$ext;
    }
} else {
    $path = $data_directory.'/' . 'file_' . $id . '.'.$ext;
}
unlink($path);


header('location: ./');
exit();
