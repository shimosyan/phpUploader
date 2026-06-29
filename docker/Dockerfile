# PHP 8.1に固定（アプリケーションの安定版として固定）
FROM php:8.1-apache

# 作業ディレクトリを設定
WORKDIR /var/www/html

# パッケージリストの更新とクリーンアップの改善
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
    ; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

# 必要な拡張機能が利用可能であることを確認
RUN set -eux; \
    php -m | grep -q '^mbstring$'; \
    php -m | grep -q '^PDO$'; \
    php -m | grep -q '^pdo_sqlite$'; \
    php -m | grep -q '^sqlite3$'

# Composerのインストール
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && chmod +x /usr/local/bin/composer

# PHPの設定ファイルをコピー
COPY ./php/php.ini /usr/local/etc/php/

# Apache設定の最適化
RUN a2enmod rewrite
