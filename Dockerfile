FROM php:fpm AS php-fpm

# https://github.com/docker-library/drupal/blob/ceed8c29e38959d66e28554ec9aae1cc65a66a9d/8.9/fpm/Dockerfile
# install the PHP extensions we need
RUN set -eux; \
        \
        if command -v a2enmod; then \
            a2enmod rewrite; \
        fi; \
        \
        savedAptMark="$(apt-mark showmanual)"; \
        \
        apt-get update; \
        apt-get install -y --no-install-recommends \
                libfreetype6-dev \
                libjpeg-dev \
                libpng-dev \
                libpq-dev \
                libzip-dev \
        ; \
        \
        docker-php-ext-configure gd \
                --with-freetype \
                --with-jpeg \
        ; \
        \
        docker-php-ext-install -j "$(nproc)" \
                gd \
                opcache \
                pdo_mysql \
                pdo_pgsql \
                zip \
        ; \
        \
# reset apt-mark's "manual" list so that "purge --auto-remove" will remove all build dependencies
        apt-mark auto '.*' > /dev/null; \
        apt-mark manual $savedAptMark; \
        ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
                | awk '/=>/ { print $3 }' \
                | sort -u \
                | xargs -r dpkg-query -S \
                | cut -d: -f1 \
                | sort -u \
                | xargs -rt apt-mark manual; \
        \
        apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
        rm -rf /var/lib/apt/lists/*

# set recommended PHP.ini settings
# see https://secure.php.net/manual/en/opcache.installation.php
RUN { \
                echo 'opcache.memory_consumption=128'; \
                echo 'opcache.interned_strings_buffer=8'; \
                echo 'opcache.max_accelerated_files=4000'; \
                echo 'opcache.revalidate_freq=60'; \
                echo 'opcache.fast_shutdown=1'; \
        } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# https://github.com/geerlingguy/drupal-for-kubernetes/blob/b2469d5efaac2381c6ddde1fe5ff2636f9657ab8/Dockerfile
FROM composer AS vendor

COPY composer.json composer.json
COPY composer.lock composer.lock
COPY web/ web/

RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-dev \
    --prefer-dist

FROM php-fpm

# Copy precompiled codebase into the container.
COPY --from=vendor /app/ /var/www/html/

# Make sure file ownership is correct on the document root.
RUN chown -R www-data:www-data /var/www/html/web

# Add Drush Launcher.
RUN curl -OL https://github.com/drush-ops/drush-launcher/releases/download/0.6.0/drush.phar \
 && chmod +x drush.phar \
 && mv drush.phar /usr/local/bin/drush

