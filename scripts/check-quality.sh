#!/bin/bash

# 開発用コード品質チェックスクリプト

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${GREEN}🔍 コード品質チェックを開始...${NC}"

# Dockerコンテナが起動しているかチェック
if ! docker-compose ps | grep -q "php_cli"; then
    echo -e "${YELLOW}📦 PHP CLIコンテナを起動中...${NC}"
    docker-compose --profile tools up -d php-cli
fi

echo -e "${YELLOW}1. PHP構文チェック${NC}"
docker-compose exec php-cli find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l

echo -e "${YELLOW}2. Composer検証${NC}"
docker-compose exec php-cli composer validate --strict

echo -e "${YELLOW}3. 依存関係インストール${NC}"
docker-compose exec php-cli composer install --dev

echo -e "${YELLOW}4. PHP CodeSniffer実行${NC}"
if docker-compose exec php-cli test -f "vendor/bin/phpcs"; then
    docker-compose exec php-cli vendor/bin/phpcs
else
    echo -e "${RED}PHPCS not found, installing...${NC}"
    docker-compose exec php-cli composer require --dev squizlabs/php_codesniffer
    docker-compose exec php-cli vendor/bin/phpcs
fi

echo -e "${YELLOW}5. PHPStan実行${NC}"
if docker-compose exec php-cli test -f "vendor/bin/phpstan"; then
    docker-compose exec php-cli vendor/bin/phpstan analyse
else
    echo -e "${RED}PHPStan not found, installing...${NC}"
    docker-compose exec php-cli composer require --dev phpstan/phpstan
    docker-compose exec php-cli vendor/bin/phpstan analyse
fi

echo -e "${YELLOW}6. バージョン同期テスト${NC}"
docker-compose exec php-cli php scripts/test-version.php

echo -e "${YELLOW}7. 設定ファイルテスト${NC}"
docker-compose exec php-cli cp config/config.php.example config/config.php
docker-compose exec php-cli php -l config/config.php

echo -e "${GREEN}✅ すべてのチェックが完了しました！${NC}"
