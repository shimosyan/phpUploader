## Name
phpUploader

## Description
サーバーに設置するだけで使える簡易PHPアップローダーです。

![スクリーンショット](https://cloud.githubusercontent.com/assets/26715606/25776917/1b5dbc02-3307-11e7-8155-e2d86c08f4a1.png)

## Requirement
・PHP Version 5.3.3+  
・SQLite (PHPにバンドルされたもので可)

## Usage
ものすごい簡易なアップローダーなので以下の利用を想定しています。  
・少人数且つ、不特定多数ではない利用者間でのファイルのやり取り

## Install
①下記URLからダウンロードしたファイルを任意のディレクトリに展開して下さい。

<https://github.com/shimosyan/phpUploader/releases>

**注意: v0.1及びv0.2からv1.0以降へのアップデートはできません。**

②config/config.phpを任意の値で編集して下さい。

③設置したディレクトリにapacheまたはnginxの実行権限を付与して下さい。

④この状態でサーバーに接続するとDBファイル(既定値 ./db/uploader.db)とデータ設置用のディレクトリ(既定値 ./data)が作成されます。

⑤configディレクトリとデータ設置用のディレクトリ(既定値 ./data)に.htaccessなどを用いて外部からの接続を遮断させて下さい。

## Licence
Copyright (c) 2017 shimosyan  
Released under the MIT license  
https://github.com/shimosyan/phpUploader/blob/master/MIT-LICENSE.txt