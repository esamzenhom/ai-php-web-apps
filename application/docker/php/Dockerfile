FROM php:8.3-fpm-alpine
RUN apk add --no-cache libpq-dev curl-dev sqlite-dev \
    && docker-php-ext-install pdo_pgsql curl pdo_sqlite \
    && apk add --no-cache postgresql16-client
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'session.save_path=/var/www/html/data/sessions\nsession.use_strict_mode=1\nexpose_php=Off\nopcache.revalidate_freq=0\nrealpath_cache_ttl=0\n' > "$PHP_INI_DIR/conf.d/app.ini"
WORKDIR /var/www/html
