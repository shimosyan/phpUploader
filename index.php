<?php

//簡易フレームワーク


if($_GET['page'] !== null){
  $call = $_GET['page'];
}else{
  $call = 'index';
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

//modelをインクルード
if (file_exists('./app/models/'.$call.'.php')) {

  include('./app/models/'.$call.'.php');
  //$call名のクラスをインスタンス化
  $class = new $call();
  //modelのindexメソッドを呼ぶ仕様
  $ret = $class->index();
  //配列キーが設定されている配列なら展開
  if (!is_null($ret)) {
    if(is_array($ret)){
      extract($ret);
    }
  }
}

//viewをインクルード
include('./app/views/header.php');
if (file_exists('./app/views/'.$call.'.php')) {
  include('./app/views/'.$call.'.php');
} else {
  include('./app/views/error.php');
}
include('./app/views/footer.php');