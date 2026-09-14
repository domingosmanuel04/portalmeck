FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev \
        unzip curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql mysqli gd zip intl mbstring opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# DocumentRoot
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '%s\n' \
        '<Directory /var/www/html>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/meck-override.conf \
    && a2enconf meck-override

COPY scripts/docker-entrypoint.sh /usr/local/bin/meck-entrypoint.sh
RUN chmod +x /usr/local/bin/meck-entrypoint.sh

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p uploads/profiles uploads/company uploads/docs uploads/chat uploads/cvs \
        uploads/attendance uploads/activities uploads/tickets gravacoes backups tmp \
    && chown -R www-data:www-data uploads gravacoes backups tmp \
    && chmod -R ug+rwX uploads gravacoes backups tmp

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/meck-entrypoint.sh"]
CMD ["apache2-foreground"]
