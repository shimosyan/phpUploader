<?php

/* 重要 ※必ずお読み下さい

このconfigフォルダ及び「データベースディレクトリ」のフォルダは
.htaccessなどを用いて必ず外部からのアクセスを遮断して下さい

*/


class config {
  function index() {
    return array(
      // 管理者用キー
      // DLキーとDELキーで使用するマスターキーです。
      'master'              => 'hoge',

      // 各キーの暗号化用ハッシュ
      // ランダムな英数字の羅列を設定してください。
      'key'                 => 'hogehoge',

      // タイトル
      'title'               => 'アップローダー',

      // 保存ファイル数
      'save_max_files'      => 500,

      // コメントの最大文字数
      'max_comment'         => 80,

      // 1件あたりの最大ファイルサイズ(単位 : MByte)
      // php.iniのmemory_limit, post_max_size, upload_max_filesizeの値以下になるようにして下さい。
      // nginxを使用している場合はサーバー設定にclient_max_body_sizeをこの値で設定してください。
      'max_file_size'       => 2,

      // アップロードできる拡張子
      // nullならば制限なし
      'extension'           => array('zip','rar','lzh'),

      // データベースディレクトリ
      'db_directory'        => './db',

      // アップロードしたファイルを置くディレクトリ
      'data_directory'      => './data',

      // アップロードされたファイル名を暗号化して管理する (trueまたはfalse デフォルト: false)
      // サーバー内ではIDで格納されているファイル名を暗号化された文字列で格納します。
      // 暗号に使用するsaltは「key」で指定した値を使用します。
      // セキュリティを向上したいときにお使いください。
      'encrypt_filename'    => false,

      // バージョン情報です。書き換えないでください。
      'version'             => '1.2.1'
    );
  }
}