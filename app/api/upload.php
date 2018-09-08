<?php

// エラーを画面に表示(1を0にすると画面上にはエラーは出ない)
ini_set('display_errors',1);
header('Content-Type: application/json');

// 一時アップロード先ファイルパス
$file_tmp  = $_FILES['file']['tmp_name'];

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
  echo json_encode( $response );
  exit;
}

//ファイル拡張子
$ext = substr( $_FILES['file']['name'], strrpos( $_FILES['file']['name'], '.') + 1);
if(in_array(mb_strtolower($ext), $extension) === false){
  $response = array('status' => 'extension_error', 'ext' => $ext);
  //JSON形式で出力する
  echo json_encode( $response );
  exit;
}

//コメント文字数
if(mb_strlen($_POST['comment']) > $max_comment){
  $response = array('status' => 'comment_error');
  //JSON形式で出力する
  echo json_encode( $response );
  exit;
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


// ファイル件数を調べて設定値より多ければ一番古いものを削除
$fileCount = $db->prepare("SELECT count(id) as count , min(id) as min FROM uploaded");
$fileCount->execute();
$countResult = $fileCount->fetchAll();

$count  = $countResult[0]['count'];
$min_id = $countResult[0]['min'];

if($count >= $save_max_files){
  $sql  = $db->prepare("DELETE FROM uploaded WHERE " .
                      "id = :id");
  $arg  = array('id' => $min_id);
  if (! $sql->execute($arg)) {
    
  }
}


// ファイルの登録・ディレクトリに保存

$sql  = $db->prepare("INSERT INTO uploaded(origin_file_name, comment, size, count, input_date, dl_key, del_key) " . 
                    "VALUES (:origin_file_name, :comment, :size, :count, :input_date, :dl_key, :del_key)");

$escape = array('<','>','&','\'','"','\\');
$arg  = array(':origin_file_name' => $_FILES['file']['name'],
              ':comment'          => str_replace($escape,'',$_POST['comment']),
              ':size'             => $filesize,
              ':count'            => 0,
              ':input_date'       => strtotime(date('Y/m/d H:i:s')),
              ':dl_key'           => openssl_encrypt($_POST['dlkey'],'aes-256-ecb',$key),
              ':del_key'          => openssl_encrypt($_POST['delkey'],'aes-256-ecb',$key)
              );
if (! $sql->execute($arg)) {
  $response = array('status' => 'sqlwrite_error');
  //JSON形式で出力する
  echo json_encode( $response );
  exit;
}
$id = $db->lastInsertId('id');



// 正式保存先ファイルパス
$file_save = '../../'.$data_directory.'/' . 'file_'.$id.'.'.$ext;

// ファイル移動
$result = @move_uploaded_file($file_tmp, $file_save);


if ( $result === true ) {
    $response = array('status' => 'ok');
} else {
    $response = array('status' => 'ng');
}

//JSON形式で出力する
echo json_encode( $response );
?>
