FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    NODE_MAJOR=18

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl git unzip zip \
    php8.1 php8.1-cli php8.1-mbstring php8.1-xml php8.1-curl php8.1-intl php8.1-zip \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js LTS
RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_MAJOR}.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer -o composer-setup.php \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

WORKDIR /app

# Copy only manifests first for better caching
COPY fp-digital-publisher/composer.json fp-digital-publisher/composer.lock ./fp-digital-publisher/
COPY fp-digital-publisher/package.json fp-digital-publisher/package-lock.json ./fp-digital-publisher/

WORKDIR /app/fp-digital-publisher

RUN composer install --no-interaction --no-progress --prefer-dist || true
RUN [ -f package-lock.json ] && npm ci --no-audit --no-fund || true

# Copy the rest of the source
WORKDIR /app
COPY . .
WORKDIR /app/fp-digital-publisher

# Default command runs build and tests
CMD npm run build && \
    composer install --no-interaction --no-progress --prefer-dist && \
    composer test && \
    composer test:integration || true && \
    ./vendor/bin/phpcs --standard=phpcs.xml.dist src || true

