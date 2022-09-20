<?php

// エラーを画面に表示(1を0にすると画面上にはエラーは出ない)
ini_set('display_errors',0);
ini_set('max_execution_time',300);
set_time_limit(300);
header('Content-Type: application/json');

$messages = array();
switch ($_FILES['file']['error']) {
	case UPLOAD_ERR_OK:
		//値: 0; この場合のみ、ファイルあり
		break;

	case UPLOAD_ERR_INI_SIZE:
		//値: 1; アップロードされたファイルは、php.ini の upload_max_filesize ディレクティブの値を超えています（post_max_size, upload_max_filesize）
		$messages[] = 'アップロードされたファイルが大きすぎます。' . ini_get('upload_max_filesize') . '以下のファイルをアップロードしてください。';
		break;

	case UPLOAD_ERR_FORM_SIZE:
		//値: 2; アップロードされたファイルは、HTML フォームで指定された MAX_FILE_SIZE を超えています。
		$messages[] = 'アップロードされたファイルが大きすぎます。' . ($_POST['MAX_FILE_SIZE'] / 1000) . 'KB以下のファイルをアップロードしてください。';
		break;

	case UPLOAD_ERR_PARTIAL:
		//値: 3; アップロードされたファイルは一部のみしかアップロードされていません。
		$messages[] = 'アップロードに失敗しています（通信エラー）。もう一度アップロードをお試しください。';
		break;

	case UPLOAD_ERR_NO_FILE:
		//値: 4; ファイルはアップロードされませんでした。（この場合のみ、ファイルがないことを表している）
		$messages[] = 'ファイルをアップロードしてください';
		break;

	case UPLOAD_ERR_NO_TMP_DIR:
		//値: 6; テンポラリフォルダがありません。PHP 4.3.10 と PHP 5.0.3 で導入されました。
		$messages[] = 'アップロードに失敗しています（システムエラー）。もう一度アップロードをお試しください。';
		break;

	default:
		//UPLOAD_ERR_CANT_WRITE 値: 7; ディスクへの書き込みに失敗しました。PHP 5.1.0 で導入されました。
		//UPLOAD_ERR_EXTENSION 値: 8; ファイルのアップロードが拡張モジュールによって停止されました。 PHP 5.2.0 で導入されました。 
		//何かおかしい
		$messages[] = 'アップロードファイルをご確認ください。 - 1';
		break;
}
if (!$messages && !is_uploaded_file($_FILES["file"]['tmp_name'])) {
	//何か妙なことがおきているようだ
	$messages[] = 'アップロードファイルをご確認ください。 - 0';
}

if ($messages) {
  $response = array('status' => 'upload_error', 'message' => $messages);
  //JSON形式で出力する
  echo json_encode( $response );
  exit;
}

// 一時アップロード先ファイルパス
$file_tmp  = $_FILES['file']['tmp_name'];

// ファイル名、コメントからHTMLタグを無効化
$escaped_file_name = htmlspecialchars($_FILES['file']['name'], ENT_QUOTES, 'UTF-8');
$escaped_comment   = htmlspecialchars($_POST['comment'], ENT_QUOTES, 'UTF-8');

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
$ext = substr( $escaped_file_name, strrpos( $escaped_file_name, '.') + 1);
if(!is_null($extension) and in_array(mb_strtolower($ext), $extension) === false){
  $response = array('status' => 'extension_error', 'ext' => $ext);
  //JSON形式で出力する
  echo json_encode( $response );
  exit;
}

//コメント文字数
if(mb_strlen($escaped_comment) > $max_comment){
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
  $sql = $db->prepare("DELETE FROM uploaded WHERE id = :id");
  $sql->bindValue(':id', $min_id); //ID
  if (! $sql->execute()) {
    // 削除を実施
  }
}


// ファイルの登録・ディレクトリに保存

$sql  = $db->prepare("INSERT INTO uploaded(origin_file_name, comment, size, count, input_date, dl_key, del_key) " . 
                    "VALUES (:origin_file_name, :comment, :size, :count, :input_date, :dl_key, :del_key)");

$arg  = array(':origin_file_name' => $escaped_file_name,
              ':comment'          => $escaped_comment,
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
if ($encrypt_filename) {
  $file_save = '../../'.$data_directory.'/' . 'file_'.str_replace(array('\\', '/', ':', '*', '?', '\"', '<', '>', '|'), '',openssl_encrypt($id,'aes-256-ecb',$key)).'.'.$ext;
} else {
  $file_save = '../../'.$data_directory.'/' . 'file_'.$id.'.'.$ext;
}

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
