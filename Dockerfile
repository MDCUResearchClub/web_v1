FROM php:apache AS php-apache

ARG ENV

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# https://github.com/docker-library/drupal/blob/ceed8c29e38959d66e28554ec9aae1cc65a66a9d/8.9/fpm/Dockerfile
# install the PHP extensions we need
RUN set -eux; \
        \
        if command -v a2enmod; then \
            a2enmod rewrite; \
            a2enmod headers; \
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

# Enable xdebug
RUN if [ "$ENV" = "dev" ] ; \
    then pecl install xdebug \
    && docker-php-ext-enable xdebug; fi

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

FROM node AS themes

COPY web/ web/
WORKDIR web/themes
RUN for d in ./custom/*/ ; do (cd "$d" && npm install && npm run build); done

FROM php-apache

# Copy precompiled codebase into the container.
COPY --from=vendor --chown=www-data:www-data /app/ /var/www/html/
COPY --from=themes --chown=www-data:www-data ./ /var/www/html/web/themes/

# Make sure file ownership is correct on the document root.
RUN mkdir /var/www/html/files /var/www/html/web/sites/default/files && \
    chown -R www-data:www-data /var/www/html/web /var/www/html/files

VOLUME /var/www/html/files /var/www/html/web/sites/default/files

# Add Drush Launcher.
RUN curl -OL https://github.com/drush-ops/drush-launcher/releases/download/0.6.0/drush.phar \
 && chmod +x drush.phar \
 && mv drush.phar /usr/local/bin/drush

# Adjust the Apache docroot.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/web

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy other required configuration into the container.
COPY settings.php /var/www/html/web/sites/default/settings.php
COPY config/ /var/www/html/config/
COPY php.ini $PHP_INI_DIR/conf.d/
