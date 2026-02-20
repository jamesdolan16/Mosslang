FROM php:8.5-cli

# System deps
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    iputils-ping \
    && rm -rf /var/lib/apt/lists/*

# Install Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Xdebug config
COPY xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

CMD ["php"]
