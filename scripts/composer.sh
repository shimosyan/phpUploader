#!/bin/bash

# Dockerを使ったComposer管理スクリプト
# Usage: ./scripts/composer.sh [composer command]

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}🐳 Docker Composer を実行中...${NC}"

# Dockerコンテナが起動しているかチェック
if ! docker-compose ps | grep -q "php_cli"; then
    echo -e "${YELLOW}📦 PHP CLIコンテナを起動中...${NC}"
    docker-compose --profile tools up -d php-cli
fi

# Composerコマンドを実行
if [ -n "$1" ]; then
    docker-compose exec php-cli composer "$@"
else
    echo -e "${YELLOW}使用方法:${NC}"
    echo "  ./scripts/composer.sh install"
    echo "  ./scripts/composer.sh update"
    echo "  ./scripts/composer.sh require package-name"
fi
