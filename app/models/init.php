<?php

  //ディレクトリ初期設定
  if(file_exists($db_directory) == false){
    //フォルダが存在しない時
    if(mkdir($db_directory)){
      //作成に成功した時の処理
    }else{
      //作成に失敗した時の処理
      $error = '500 - ディレクトリの作成に失敗しました。';
      include('./app/views/header.php');
      include('./app/views/error.php');
      include('./app/views/footer.php');
      exit;
    }
  }

  if(file_exists($data_directory) == false){
    //フォルダが存在しない時
    if(mkdir($data_directory)){
      //作成に成功した時の処理
    }else{
      //作成に失敗した時の処理
      $error = '500 - ディレクトリの作成に失敗しました。';
      include('./app/views/header.php');
      include('./app/views/error.php');
      include('./app/views/footer.php');
      exit;
    }
  }

  //データベースの作成・オープン
  try{
    $db = new PDO('sqlite:'.$db_directory.'/uploader.db');
  }catch (Exception $e){
    $error = '500 - データベースの接続に失敗しました。';
    include('./app/views/header.php');
    include('./app/views/error.php');
    include('./app/views/footer.php');
    exit;
  }

  // デフォルトのフェッチモードを連想配列形式に設定 
  // (毎回PDO::FETCH_ASSOCを指定する必要が無くなる)
  $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

  //データベース初期設定
  $query = "
  CREATE TABLE IF NOT EXISTS uploaded(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  origin_file_name text,
  comment text,
  size INTEGER,
  count INTEGER,
  input_date INTEGER,
  dl_key text,
  del_key text
  )";

  $result = $db->exec($query);

  //エラーの処理
  if($result === false){
    $error = '500 - データベースの初期化に失敗しました。';
    include('./app/views/header.php');
    include('./app/views/error.php');
    include('./app/views/footer.php');
    exit;
  }

  $db = null;