<?php

declare(strict_types=1);

/**
 * ファイルダウンロード処理
 *
 * ワンタイムトークンによる安全なダウンロード
 */

// エラー表示設定
ini_set('display_errors', '0');
ini_set('log_errors', '1'); // ログファイルにエラーを記録
error_reporting(E_ALL);

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
        $logger->warning('Invalid download parameters', ['file_id' => $fileId, 'token_provided' => !empty($token)]);
        header('Location: ./');
        exit;
    }

    // トークンの検証
    $tokenStmt = $db->prepare("
        SELECT t.*, u.origin_file_name, u.stored_file_name, u.size, u.file_hash
        FROM access_tokens t
        JOIN uploaded u ON t.file_id = u.id
        WHERE t.token = :token AND t.token_type = 'download' AND t.file_id = :file_id AND t.expires_at > :now
    ");

    $tokenStmt->execute([
        'token' => $token,
        'file_id' => $fileId,
        'now' => time()
    ]);

    $tokenData = $tokenStmt->fetch();

    if (!$tokenData) {
        $logger->warning('Invalid or expired download token', ['file_id' => $fileId, 'token' => substr($token, 0, 8) . '...']);
        header('Location: ./');
        exit;
    }

    // IPアドレスの検証（設定で有効な場合）
    if ($config['security']['log_ip_address'] && !empty($tokenData['ip_address'])) {
        $currentIP = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($currentIP !== $tokenData['ip_address']) {
            $logger->warning('IP address mismatch for download', [
                'file_id' => $fileId,
                'token_ip' => $tokenData['ip_address'],
                'current_ip' => $currentIP
            ]);
            // IPアドレスが異なる場合は警告ログのみで、ダウンロードは継続
        }
    }

    // ファイルパスの生成（ハッシュ化されたファイル名または旧形式に対応）
    $fileName = $tokenData['origin_file_name'];

    if (!empty($tokenData['stored_file_name'])) {
        // 新形式（ハッシュ化されたファイル名）
        $filePath = $config['data_directory'] . '/' . $tokenData['stored_file_name'];
    } else {
        // 旧形式（互換性のため）
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $filePath = $config['data_directory'] . '/file_' . $fileId . '.' . $fileExtension;
    }

    // ファイルの存在確認
    if (!file_exists($filePath)) {
        $logger->error('Physical file not found for download', ['file_id' => $fileId, 'path' => $filePath]);
        header('Location: ./');
        exit;
    }

    // ファイルハッシュの検証（ファイル整合性チェック）
    if (!empty($tokenData['file_hash'])) {
        $currentHash = hash_file('sha256', $filePath);
        if ($currentHash !== $tokenData['file_hash']) {
            $logger->error('File integrity check failed', [
                'file_id' => $fileId,
                'expected_hash' => $tokenData['file_hash'],
                'current_hash' => $currentHash
            ]);
            header('Location: ./');
            exit;
        }
    }

    // ダウンロード回数の更新
    $updateStmt = $db->prepare("UPDATE uploaded SET count = count + 1, updated_at = :updated_at WHERE id = :id");
    $updateStmt->execute([
        'id' => $fileId,
        'updated_at' => time()
    ]);

    // 使用済みトークンの削除（ワンタイム）
    $deleteTokenStmt = $db->prepare("DELETE FROM access_tokens WHERE token = :token");
    $deleteTokenStmt->execute(['token' => $token]);

    // アクセスログの記録
    $logger->access($fileId, 'download', 'success');

    // ファイルダウンロードの実行
    $fileSize = filesize($filePath);

    // ヘッダーの設定
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($fileName));
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    // 出力バッファのクリア
    if (ob_get_level()) {
        ob_end_clean();
    }

    // ファイルの出力
    $handle = fopen($filePath, 'rb');
    if ($handle) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    } else {
        $logger->error('Failed to open file for download', ['file_id' => $fileId, 'path' => $filePath]);
        header('Location: ./');
    }

} catch (Exception $e) {
    // 緊急時のエラーハンドリング
    if (isset($logger)) {
        $logger->error('Download Error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'file_id' => $fileId ?? null
        ]);
    }

    header('Location: ./');
    exit;
  }

  //configをインクルード
  include('./config/config.php');
  $config = new config();
  $ret = $config->index();
  //配列キーが設定されている配列なら展開
  if (!is_null($ret)) {
    if(is_array($ret)){
      extract($ret);
    }
  }

  //データベースの作成・オープン
  try{
    $db = new PDO("sqlite:".$db_directory."/uploader.db");
  }catch (Exception $e){
    exit;
  }

  // デフォルトのフェッチモードを連想配列形式に設定
  // (毎回PDO::FETCH_ASSOCを指定する必要が無くなる)
  $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  // 選択 (プリペアドステートメント)
  $stmt = $db->prepare("SELECT * FROM uploaded WHERE id = :id");
  $stmt->bindValue(':id', $id); //ID
  $stmt->execute();
  $result = $stmt->fetchAll();
  foreach($result as $s){
    $filename = $s['origin_file_name'];
    $origin_dlkey = $s['dl_key'];
    $current_count = $s['count'];
    $max_downloads = $s['max_downloads'];
    $expires_at = $s['expires_at'];
  }

  // トークンを照合して認証が通ればDL可
  if ( PHP_MAJOR_VERSION == '5' and PHP_MINOR_VERSION == '3') {
    if( $dlkey !== bin2hex(openssl_encrypt($origin_dlkey,'aes-256-ecb',$key, true)) ){
      header('location: ./');
      exit;
    }
  }else{
    if( $dlkey !== bin2hex(openssl_encrypt($origin_dlkey,'aes-256-ecb',$key, OPENSSL_RAW_DATA)) ){
      header('location: ./');
      exit;
    }
  }

  // 制限チェック
  
  // 有効期限チェック
  if ($expires_at !== null && time() > $expires_at) {
    header('location: ./?error=expired');
    exit;
  }
  
  // ダウンロード回数制限チェック
  if ($max_downloads !== null && $current_count >= $max_downloads) {
    header('location: ./?error=limit_exceeded');
    exit;
  }

  // カウンターを増やす
  $upd = $db->prepare("UPDATE uploaded SET count = count + 1 WHERE id = :id");
  $upd->bindValue(':id', $id); //ID
  $upd->execute();


  $ext = substr( $filename, strrpos( $filename, '.') + 1);
  if ($encrypt_filename) {
    $path = $data_directory.'/' . 'file_' . str_replace(array('\\', '/', ':', '*', '?', '\"', '<', '>', '|'), '',openssl_encrypt($id,'aes-256-ecb',$key)) . '.'.$ext;
    if (!file_exists ( $path )) {
      $path = $data_directory.'/' . 'file_' . $id . '.'.$ext;
    }
  } else {
    $path = $data_directory.'/' . 'file_' . $id . '.'.$ext;
  }

  //var_dump($path);

  header('Content-Type: application/force-download');
  header('Content-Disposition: attachment; filename*=UTF-8\'\''.rawurlencode($filename));
  header('Content-Length: ' . filesize($path));
  ob_end_clean();//ファイル破損を防ぐ //出力バッファのゴミ捨て
  readfile($path);
?>
