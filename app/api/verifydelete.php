<?php

// エラーを画面に表示(1を0にすると画面上にはエラーは出ない)
ini_set('display_errors',1);

$id       = $_POST['id'];
$post_key = $_POST['key'];

header('Content-Type: application/json');

if($id === null){
  //JSON形式で出力する
  echo json_encode( array('status' => 'ng') );
  exit;
}

//configをインクルード
include('../../config/config.php');
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
  $db = new PDO('sqlite:../../'.$db_directory.'/uploader.db');
}catch (Exception $e){
  $response = array('status' => 'sqlerror');
  //JSON形式で出力する
  echo json_encode( $response );
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
  $origin_delkey = $s['del_key'];
}

// マスターキーが入力されていたら認証をスキップする
if($post_key !== $master){
  if(openssl_encrypt($post_key,'aes-256-ecb',$key) !== $origin_delkey){
    //JSON形式で出力する
    echo json_encode( array('status' => 'failed') );
    exit;
  }
}

// DL用のトークンを生成
if ( PHP_MAJOR_VERSION == '5' and PHP_MINOR_VERSION == '3') {
  $del_key = bin2hex(openssl_encrypt($origin_delkey,'aes-256-ecb',$key, true));
}else{
  $del_key = bin2hex(openssl_encrypt($origin_delkey,'aes-256-ecb',$key, OPENSSL_RAW_DATA));
}


//JSON形式で出力する
echo json_encode( array('status' => 'ok', 'id' => $id, 'key' => $del_key) );
?>