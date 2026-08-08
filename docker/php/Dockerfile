FROM node:22-bookworm-slim AS vite-assets

WORKDIR /build

COPY package.json package-lock.json ./

RUN npm ci --include=dev --no-audit --no-fund

COPY . .

RUN rm -f public/hot \
    && npm run build \
    && test -s public/build/manifest.json

FROM php:8.2-apache-bookworm

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libwebp-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        mysqli \
        opcache \
        pcntl \
        pdo_mysql \
        sockets \
        zip \
    && a2enmod rewrite headers expires \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-art-inpa.ini
COPY docker/apache-servername.conf /etc/apache2/conf-available/art-inpa-servername.conf
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/entrypoint.sh /usr/local/bin/art-inpa-entrypoint
COPY . /var/www/html
COPY --from=vite-assets /build/public/build /opt/art-inpa/public/build

RUN rm -f bootstrap/cache/*.php \
    && composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --optimize-autoloader \
    && a2enconf art-inpa-servername \
    && chmod +x /usr/local/bin/art-inpa-entrypoint

WORKDIR /var/www/html
ENTRYPOINT ["art-inpa-entrypoint"]
CMD ["apache2-foreground"]
