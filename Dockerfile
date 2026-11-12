FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libonig-dev \
    && docker-php-ext-install mbstring pdo_mysql \
    && apt-get purge -y --auto-remove libonig-dev \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/fixpoint.ini
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p storage/logs storage/sessions storage/cache \
    && chown -R www-data:www-data storage \
    && chmod +x docker/php/entrypoint.sh

COPY docker/php/entrypoint.sh /usr/local/bin/fixpoint-entrypoint
COPY docker/php/wait-for-db.php /usr/local/bin/fixpoint-wait-for-db.php
RUN chmod +x /usr/local/bin/fixpoint-entrypoint

EXPOSE 80

ENTRYPOINT ["fixpoint-entrypoint"]
CMD ["apache2-foreground"]
