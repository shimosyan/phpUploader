<?php

/* 重要 ※必ずお読み下さい

このconfigフォルダ及び「データベースディレクトリ」のフォルダは
.htaccessなどを用いて必ず外部からのアクセスを遮断して下さい

*/


class config {
  function index() {
    return array(
      // 管理用パスワード
      // 全てのファイルのDLキー、DELキーとして使えます。
      'master'              =>'hogehoge',

      // タイトル
      'title'               => 'アップローダー',

      // 最大保存件数
      'max_number_of_file'  => 500,

      // 1件あたりの最大ファイルサイズ(単位 : KByte)
      'max_file_size'       => 1024,

      //アップロードできる拡張子
      'extension'           => array('zip','rar','lzh'),

      //データベースディレクトリ
      'db_directory'        => './db'
    );
  }
}