# eForms rendezvény-regisztráció — Drupal 11 + Apache + SQLite.
FROM php:8.3-apache

# PHP kiterjesztések és rendszereszközök.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip sqlite3 \
        libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd zip opcache pdo_sqlite

# Apache: a webroot a Composer projekt web/ könyvtára, .htaccess engedélyezve.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/web
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}/!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '<Directory ${APACHE_DOCUMENT_ROOT}>\n  AllowOverride All\n  Require all granted\n</Directory>\n' > /etc/apache2/conf-available/drupal.conf \
    && a2enconf drupal \
    && a2enmod rewrite headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Függőségek (külön rétegben, hogy a kód módosítása ne triggerelje újra).
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress

# Egyedi modul és téma.
COPY web/modules/custom web/modules/custom
COPY web/themes/custom web/themes/custom

COPY docker/entrypoint.sh /usr/local/bin/eforms-entrypoint
RUN chmod +x /usr/local/bin/eforms-entrypoint

ENTRYPOINT ["eforms-entrypoint"]
