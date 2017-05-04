## Name
phpUploader

## Description
サーバーに設置するだけで使える簡易PHPアップローダーです。

![スクリーンショット](https://cloud.githubusercontent.com/assets/26715606/25690448/622ac98c-30cc-11e7-8ff7-044f34d225d4.png)

## Requirement
・PHP Version 5.3.3  
・SQLite (PHPにバンドルされたもので可)

## Usage
ものすごい簡易なアップローダーなので以下の利用を想定しています。  
・少人数且つ、不特定多数ではない利用者間でのファイルのやり取り

## Install
①ダウンロードしたファイルを任意のディレクトリに展開して下さい。

<https://github.com/shimosyan/phpUploader/releases>

②config/config.phpを任意の値で編集して下さい。

③設置したディレクトリにapacheまたはnginxの実行権限を付与して下さい。

④この状態でサーバーに接続するとDBファイル(既定値 /db/uploader.db)とデータ設置用のディレクトリ(既定値 /data)が作成されます。

⑤/configディレクトリとデータ設置用のディレクトリ(既定値 /data)に.htaccessを設置して外部からの接続を遮断させます。

## Licence
Copyright (c) 2017 shimosyan  
Released under the MIT license  
https://github.com/shimosyan/phpUploader/blob/master/MIT-LICENSE.txt