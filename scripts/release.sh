#!/bin/bash

# Docker を使ったリリース管理スクリプト
# Usage: ./scripts/release.sh [version]

set -e

# カラー出力用
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🐳 Docker を使用してリリース管理を実行中...${NC}"

# Dockerコンテナが起動しているかチェック
if ! docker-compose ps | grep -q "php_cli"; then
    echo -e "${YELLOW}📦 PHP CLIコンテナを起動中...${NC}"
    docker-compose --profile tools up -d php-cli
fi

# バージョン引数をPHPスクリプトに渡す
if [ -n "$1" ]; then
    echo -e "${GREEN}🔄 バージョンを $1 に更新中...${NC}"
    docker-compose exec php-cli php scripts/release.php "$1"

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ バージョン更新完了！${NC}"
        echo -e "${YELLOW}次の手順:${NC}"
        echo -e "  1. git add ."
        echo -e "  2. git commit -m \"Bump version to $1\""
        echo -e "  3. git tag v$1"
        echo -e "  4. git push origin main --tags"
        echo ""
        echo -e "${YELLOW}または一括実行:${NC}"
        echo -e "  ./scripts/release.sh $1 --push"
    else
        echo -e "${RED}❌ バージョン更新に失敗しました${NC}"
        exit 1
    fi

    # --push オプションが指定された場合、自動でGitプッシュまで実行
    if [ "$2" = "--push" ]; then
        echo -e "${YELLOW}🚀 Gitに変更をプッシュ中...${NC}"
        git add .
        git commit -m "Bump version to $1"
        git tag "v$1"
        git push origin main --tags
        echo -e "${GREEN}✅ リリース完了！GitHub Actionsがリリースを作成します${NC}"
    fi
else
    echo -e "${YELLOW}📋 現在のバージョンを確認中...${NC}"
    docker-compose exec php-cli php scripts/release.php
fi

echo -e "${GREEN}🎉 完了！${NC}"
