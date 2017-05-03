<?php

//簡易フレームワーク


if($_GET['page'] !== null){
  $call = $_GET['page'];
}else{
  $call = 'index';
}

//modelをインクルードします
if (file_exists('./app/models/'.$call.'.php')) {

  include('./app/models/'.$call.'.php');
  //$call名のクラスをインスタンス化します
  $class = new $call();
  //modelのindexメソッドを呼ぶ仕様です
  $ret = $class->index($analysis);
  //配列キーが設定されている配列なら展開します
  if (!is_null($ret)) {
    if(is_array($ret)){
      extract($ret);
    }
  }
}

//viewをインクルードします
include('./app/views/header.php');
if (file_exists('./app/views/'.$call.'.php')) {
  include('./app/views/'.$call.'.php');
} else {
  include('./app/views/error.php');
}
include('./app/views/footer.php');