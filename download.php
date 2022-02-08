<?php
  // エラーを画面に表示(1を0にすると画面上にはエラーは出ない)
  ini_set('display_errors',0);

  $id    = $_GET['id'];
  $dlkey = $_GET['key'];

  if($id === null){
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