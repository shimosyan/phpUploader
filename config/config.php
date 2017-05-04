<?php

/* 重要 ※必ずお読み下さい

このconfigフォルダ及び「データベースディレクトリ」のフォルダは
.htaccessなどを用いて必ず外部からのアクセスを遮断して下さい

*/


class config {
  function index() {
    return array(
      // タイトル
      'title'               => 'アップローダー',

      // コメントの最大文字数
      'max_comment'         => 80,

      // 1件あたりの最大ファイルサイズ(単位 : MByte)
      // php.iniのmemory_limit, post_max_size, upload_max_filesizeの値以下になるようにして下さい
      'max_file_size'       => 2,

      //アップロードできる拡張子
      'extension'           => array('zip','rar','lzh'),

      //データベースディレクトリ
      'db_directory'        => './db',

      //アップロードしたファイルを置くディレクトリ
      'data_directory'      => './data'
    );
  }
}