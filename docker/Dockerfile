FROM php:8.1-apache

# 作業ディレクトリを設定
WORKDIR /var/www/html

# パッケージリストの更新とクリーンアップの改善
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        git \
        unzip \
        curl \
    ; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

# 必要な拡張機能をインストール
RUN docker-php-ext-install pdo pdo_sqlite

# Composerのインストール
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && chmod +x /usr/local/bin/composer

# PHPの設定ファイルをコピー
COPY ./php/php.ini /usr/local/etc/php/

# Apache設定の最適化
RUN a2enmod rewrite
