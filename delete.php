<?php
  $id     = $_GET['id'];
  $delkey = $_GET['key'];

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
    $db = new PDO('sqlite:'.$db_directory.'/uploader.db');
  }catch (Exception $e){
    exit;
  }

  // デフォルトのフェッチモードを連想配列形式に設定 
  // (毎回PDO::FETCH_ASSOCを指定する必要が無くなる)
  $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  // ファイル名取得
  $stmt = $db->prepare("SELECT * FROM uploaded WHERE id = $id");
  $stmt->execute();
  $result = $stmt->fetchAll();
  foreach($result as $s){
    $filename = $s['origin_file_name'];
    $origin_delkey = $s['del_key'];
  }

  // ハッシュを照合して認証が通ればDEL可
  if( $delkey !== bin2hex(openssl_encrypt($origin_delkey,'aes-256-ecb',$key, OPENSSL_RAW_DATA)) ){
    header('location: ./');
    exit;
  }

  // sqlから削除
  $sql  = $db->prepare("DELETE FROM uploaded WHERE " .
                      "id = :id");
  $arg  = array('id' => $id);
  if (! $sql->execute($arg)) {
    
  }

  //ディレクトリから削除
  $ext = substr( $filename, strrpos( $filename, '.') + 1);
  $path = $data_directory.'/' . 'file_' . $id . '.'.$ext;
  unlink($path);


  header('location: ./');
  exit();