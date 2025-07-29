FROM php:7.4.8-apache

# PHPの設定ファイルをコピー
COPY ./php/php.ini /usr/local/etc/php/

# 必要な拡張機能をインストール
RUN docker-php-ext-install pdo pdo_sqlite

# Composerのインストール
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Gitのインストール（開発時に便利）
RUN apt-get update && apt-get install -y git && rm -rf /var/lib/apt/lists/*

# 作業ディレクトリを設定
WORKDIR /var/www/html
