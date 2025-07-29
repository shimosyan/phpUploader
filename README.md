# Name

phpUploader

## Description

サーバーに設置するだけで使える簡易PHPアップローダーです。

![スクリーンショット](https://cloud.githubusercontent.com/assets/26715606/25776917/1b5dbc02-3307-11e7-8155-e2d86c08f4a1.png)

## Requirement

- PHP Version 5.3.3+
- SQLite (PHPにバンドルされたもので可、一部の環境によってはphp○-sqliteのインストールが必要です。)

## Usage

ものすごい簡易なアップローダーなので以下の利用を想定しています。

- 少人数且つ、不特定多数ではない利用者間でのファイルのやり取り

## Install

①下記URLからダウンロードしたファイルを任意のディレクトリに展開して下さい。

<https://github.com/shimosyan/phpUploader/releases>

**注意: v0.1及びv0.2からv1.0以降へのアップデートはできません。**

②設定ファイルを作成して下さい。

```bash
# config.php.exampleをコピーして設定ファイルを作成
cp config/config.php.example config/config.php
```

③config/config.phpを任意の値で編集して下さい。

**重要**: 以下の項目は必ず変更してください：

- `master`: 管理者用キー（DLキー・DELキーのマスターキー）
- `key`: 暗号化用ハッシュ（ランダムな英数字）

```php
// 例：セキュリティのため必ず変更してください
'master' => 'your_secure_master_key_here',
'key'    => 'your_random_encryption_key_here',
```

④設置したディレクトリにapacheまたはnginxの実行権限を付与して下さい。

④この状態でサーバーに接続するとDBファイル(既定値 ./db/uploader.db)とデータ設置用のディレクトリ(既定値 ./data)が作成されます。

⑤configディレクトリとデータ設置用のディレクトリ(既定値 ./data)に.htaccessなどを用いて外部からの接続を遮断させて下さい。

**セキュリティ設定例（Apache）:**

```apache
# config/.htaccess
<Files "*">
    Deny from all
</Files>

# data/.htaccess
<Files "*">
    Deny from all
</Files>
```

⑥ファイルがアップロードできるよう、PHPとapacheまたはnginxの設定を変更してください。

## Quick Start (Docker)

Dockerを使用して素早く動作確認できます：

```bash
# 1. リポジトリをクローン
git clone https://github.com/shimosyan/phpUploader.git
cd phpUploader

# 2. 設定ファイルを作成
cp config/config.php.example config/config.php

# 3. 設定ファイルを編集（master, keyを変更）
# エディタで config/config.php を開いて編集

# 4. Dockerでサーバー起動
docker-compose up -d web

# 5. ブラウザで http://localhost にアクセス
```

## Security Notes

**設定ファイルのセキュリティ**:

- `config/config.php`は機密情報を含むため、必ず外部アクセスを遮断してください
- `master`と`key`には推測困難なランダムな値を設定してください
- 本番環境では`config`と`data`ディレクトリへの直接アクセスを禁止してください

**推奨セキュリティ設定**:

```php
// 強力なキーの例（実際は異なる値を使用してください）
'master' => bin2hex(random_bytes(16)), // 32文字のランダム文字列
'key'    => bin2hex(random_bytes(32)), // 64文字のランダム文字列
```

## Development

### 初期セットアップ

開発を始める前に、設定ファイルを準備してください：

```bash
# 設定ファイルのテンプレートをコピー
cp config/config.php.example config/config.php

# 設定ファイルを編集（開発用の値に変更）
# master, key などをローカル開発用の値に設定
```

**注意**: `config/config.php`は`.gitignore`に含まれており、リポジトリにはコミットされません。

### バージョン管理

このプロジェクトでは`composer.json`でバージョンを一元管理しています。

- **composer.json**: マスターバージョン情報
- **config.php**: composer.jsonから自動的にバージョンを読み取り

バージョン確認テスト:

```bash
# Docker環境で実行
docker-compose exec php-cli php scripts/test-version.php
```

### Docker環境での開発

PHPがローカルにインストールされていなくても、Dockerを使って開発できます。

```bash
# Webサーバーの起動
docker-compose up -d web

# リリース管理（Linux/Mac）
./scripts/release.sh 1.3.0

# リリース管理（Windows）
scripts\release.bat 1.3.0

# 自動プッシュ付きリリース
./scripts/release.sh 1.3.0 --push

# Composer管理
./scripts/composer.sh install
./scripts/composer.sh update
```

### リリース手順

1. **バージョン更新**: `./scripts/release.sh x.x.x`
2. **変更確認**: 自動的に`composer.json`が更新され、`config.php`は動的に読み取ります
3. **Git操作**: 表示される手順に従ってコミット・タグ・プッシュ
4. **自動リリース**: GitHub Actionsが自動でリリースを作成

**重要**: リリース時は`config/config.php.example`テンプレートが配布され、エンドユーザーが自分で設定ファイルを作成する必要があります。

## License

Copyright (c) 2017 shimosyan
Released under the MIT license
<https://github.com/shimosyan/phpUploader/blob/master/MIT-LICENSE.txt>
