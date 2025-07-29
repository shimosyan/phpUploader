@echo off
setlocal enabledelayedexpansion

REM Docker を使ったリリース管理スクリプト（Windows版）
REM Usage: scripts\release.bat [version]

echo 🐳 Docker を使用してリリース管理を実行中...

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

REM バージョン引数をPHPスクリプトに渡す
if "%~1"=="" (
    echo 📋 現在のバージョンを確認中...
    docker-compose exec php-cli php scripts/release.php
) else (
    echo 🔄 バージョンを %~1 に更新中...
    docker-compose exec php-cli php scripts/release.php %~1
    
    if errorlevel 1 (
        echo ❌ バージョン更新に失敗しました
        exit /b 1
    )
    
    echo ✅ バージョン更新完了！
    echo.
    echo 次の手順:
    echo   1. git add .
    echo   2. git commit -m "Bump version to %~1"
    echo   3. git tag v%~1
    echo   4. git push origin main --tags
    echo.
    
    REM --push オプションが指定された場合、自動でGitプッシュまで実行
    if "%~2"=="--push" (
        echo 🚀 Gitに変更をプッシュ中...
        git add .
        git commit -m "Bump version to %~1"
        git tag v%~1
        git push origin main --tags
        echo ✅ リリース完了！GitHub Actionsがリリースを作成します
    )
)

echo 🎉 完了！
