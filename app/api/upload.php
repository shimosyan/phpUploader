<?php

// 一時アップロード先ファイルパス
$file_tmp  = $_FILES["file"]["tmp_name"];

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

//データのチェック
//ファイル容量
$filesize = filesize($file_tmp);
if($filesize > $max_file_size*1024*1024){
  $response = array('status' => 'filesize_over', 'filesize' => $filesize);
  //JSON形式で出力する
  header('Content-Type: application/json');
  echo json_encode( $response );
  exit;
}

//ファイル拡張子
$ext = substr( $_FILES["file"]["name"], strrpos( $_FILES["file"]["name"], '.') + 1);
if(in_array($ext, $extension) === false){
  $response = array('status' => 'extension_error', 'ext' => $ext);
  //JSON形式で出力する
  header('Content-Type: application/json');
  echo json_encode( $response );
  exit;
}

//コメント文字数
if(mb_strlen($_POST["comment"]) > $max_comment){
  $response = array('status' => 'comment_error');
  //JSON形式で出力する
  header('Content-Type: application/json');
  echo json_encode( $response );
  exit;
}

//データベースの作成・オープン
try{
  $db = new PDO("sqlite:../../".$db_directory."/uploader.db");
}catch (Exception $e){
  $response = array('status' => 'sqlerror');
  //JSON形式で出力する
  header('Content-Type: application/json');
  echo json_encode( $response );
  exit;
}

// デフォルトのフェッチモードを連想配列形式に設定 
// (毎回PDO::FETCH_ASSOCを指定する必要が無くなる)
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$sql  = $db->prepare("INSERT INTO uploaded(origin_file_name, comment, size, input_date) " . 
                    "VALUES (:origin_file_name, :comment, :size, :input_date)");

$escape = array('<','>','&','\'','"','\\');
$arg  = array(':origin_file_name' => $_FILES["file"]["name"],
              ':comment'          => str_replace($escape,'',$_POST["comment"]),
              ':size'             => $filesize,
              ':input_date'       => strtotime(date("Y/m/d H:i:s"))
              );
if (! $sql->execute($arg)) {
  $response = array('status' => 'sqlwrite_error');
  //JSON形式で出力する
  header('Content-Type: application/json');
  echo json_encode( $response );
  exit;
}
$id = $db->lastInsertId('id');



// 正式保存先ファイルパス
$file_save = "../../".$data_directory.'/' . 'file_'.$id.'.'.$ext;

// ファイル移動
$result = @move_uploaded_file($file_tmp, $file_save);


if ( $result === true ) {
    $response = array('status' => 'ok');
} else {
    $response = array('status' => 'ng');
}

//JSON形式で出力する
header('Content-Type: application/json');
echo json_encode( $response );
?>