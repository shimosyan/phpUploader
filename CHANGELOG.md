# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.1] - 2025-08-03

### Fixed

- 古いライブラリが残ったままになっている問題に対処

### Changed

- サーバー側の処理の信頼性を向上
- コーディング規約に準じた書き方を反映

### Other

- その他開発者向けワークフローの充実化

## [2.0.0] - 2025-07-31

**🚨 BREAKING CHANGES: Ver.2.0は内部DBの仕様が刷新されているため、Ver.1.x系との互換性がありません。**

### Changed

- **PHP要件を8.1+に更新**
- サーバー処理を全面更新
- ファイル一覧のUIを刷新
- config.phpをテンプレート化（config.php.example）
- バージョン情報をcomposer.jsonから動的取得に変更
- リリース管理プロセスの改善
- アクセスログ機能を実装

### Security

- 各認証用文字列の暗号化方式を変更
- レインボーテーブル攻撃対策として、Argon2ID パスワードハッシュ化
- CSRF保護を導入
- セッション強化
- ディレクトリトラバーサル対策として、ファイル名ハッシュ化を強制
- SQL インジェクション完全対策として、PDO PreparedStatement に移行

## [1.2.1] - 2022-02-09

本アップデートには以下の脆弱性に対する対応が実施されています。
影響を受けるバージョンは以下のとおりです。

- phpUploader v1.2 以前の全てのバージョン

該当バージョンの確認方法は v1.2 までは提供しておりません。トップページ右下のクレジットが以下の表記の場合はすべて v1.2 以前となります。
`@shimosyan/phpUploader (GitHub)`

The following vulnerabilities have been addressed in this update.
Affected versions are as follows

- All versions of phpUploader v1.2 and earlier

We do not provide a way to check the affected versions until v1.2. If the credit in the lower right corner of the top page is as follows, all versions are v1.2 or earlier.
`@shimosyan/phpUploader (GitHub)`.

### 更新方法

はじめに、設定ファイル（`./config/config.php`）をバックアップしてください。
バージョン 1.0 より前の製品を利用されている方は、データベースファイルなどを含むソフトウェア全データを消去してから本対策版バージョンをインストールしてください。
バージョン 1.0 以降の製品を利用されている方は、ソフトウェア本体を消去してから本対策版バージョンをインストールしてください。
最後に、バックアップした設定ファイルの各値を新しくインストールした設定ファイル（`./config/config.php`）に登録してください。

本対策版バージョンは画面下部の `Assets` 欄から入手してください。

First, back up your configuration file (`. /config/config.php`).
If you are using a product earlier than version 1.0, please delete all data including database files before installing this countermeasure version.
If you are using a product with version 1.0 or later, delete the software itself before installing this countermeasure version.
Finally, add each value of the backed up configuration file to the newly installed configuration file (`. /config/config.php`).

You can get this countermeasure version from the `Assets` field at the bottom of the screen.

### 脆弱性対応

- Stored XSS に関する脆弱性修正を実施しました。
- SQL インジェクション に関する脆弱性修正を実施しました。

### その他対応

- トップページ右下のクレジット欄にバージョン情報を明記するようにしました。
  - 例：`@shimosyan/phpUploader v1.2.1 (GitHub)`
- ファイル内の余剰な空白の消去、EOL の追加などファイルの体裁を整えました。

## [1.2] - 2019-01-07

### Added

- サーバー内で格納するアップロードファイルの名称をハッシュ化するオプションを追加しました。セキュリティをより向上させたいときにお使いください。

## [1.1] - 2019-01-06

### Fixed

- IE系のブラウザで日本語ファイルをダウンロードすると文字化けする問題を修正。
- アップロード時に拡張子が大文字であっても通るように修正。
- アップロード時にPHPで発生したエラーを表示するよう変更。
- HTTPS環境では外部ライブラリが正しく参照されない問題を修正。

## [1.0] - 2017-05-07

### Added

- DL及び削除にパスワードを設定できるようにしました。
- 管理者用のパスワードから全てのファイルに対してDL及び削除ができるようにしました。
- 各ファイルのDL数を表示できるようにしました。

### Changed

- ファイルリストの並び順を新しいファイル順に変更しました。
- DBファイルの構成を変更しました。

## [0.2] - 2017-05-04

### Fixed

- DL時の出力バッファのゴミがダウンロードファイルに混入する不具合を修正

## [0.1] - 2017-05-04

### Added

- 新規リリース
