@echo off
setlocal enabledelayedexpansion

REM 開発用コード品質チェックスクリプト（Windows版）

echo 🔍 コード品質チェックを開始...

REM Dockerコンテナが起動しているかチェック
docker-compose ps | findstr "php_cli" >nul
if errorlevel 1 (
    echo 📦 PHP CLIコンテナを起動中...
    docker-compose --profile tools up -d php-cli
    if errorlevel 1 (
        echo ❌ Dockerコンテナの起動に失敗しました
        exit /b 1
    )
)

echo 1. PHP構文チェック
docker-compose exec php-cli find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l
if errorlevel 1 goto :error

echo 2. Composer検証
docker-compose exec php-cli composer validate --strict
if errorlevel 1 goto :error

echo 3. 依存関係インストール
docker-compose exec php-cli composer install --dev
if errorlevel 1 goto :error

echo 4. PHP CodeSniffer実行
docker-compose exec php-cli vendor/bin/phpcs
if errorlevel 1 (
    echo ⚠️ コーディング規約違反が見つかりました
)

echo 5. PHPStan実行
docker-compose exec php-cli vendor/bin/phpstan analyse
if errorlevel 1 (
    echo ⚠️ 静的解析で問題が見つかりました
)

echo 6. バージョン同期テスト
docker-compose exec php-cli php scripts/test-version.php
if errorlevel 1 goto :error

echo 7. 設定ファイルテスト
docker-compose exec php-cli cp config/config.php.example config/config.php
docker-compose exec php-cli php -l config/config.php
if errorlevel 1 goto :error

echo ✅ すべてのチェックが完了しました！
exit /b 0

:error
echo ❌ チェック中にエラーが発生しました
exit /b 1
