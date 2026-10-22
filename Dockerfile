FROM php:8.2-apache

# System deps
RUN apt-get update && apt-get install -y --no-install-recommends \
        default-mysql-client \
        python3 \
        python3-pip \
        curl \
        unzip \
    && rm -rf /var/lib/apt/lists/* \
    && pip3 install --no-cache-dir --break-system-packages pymysql

# PHP extensions
RUN docker-php-ext-install mysqli pdo_mysql \
    && pecl install redis \
    && docker-php-ext-enable redis

# Apache modules
RUN a2enmod rewrite headers

# Work dirs
RUN mkdir -p /var/www/pouet /var/www/pouet-api /var/www/pouet/cache

# Copy pouet-api source (will be overridden by volume in dev, but needed for image)
COPY . /var/www/pouet-api

# Config templates for pouet-api and pouet-www are generated at runtime by entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV POUET_WWW_PATH=/var/www/pouet \
    POUET_API_PATH=/var/www/pouet-api \
    APACHE_DOCUMENT_ROOT=/var/www/pouet-api

# Apache document root -> pouet-api (so .htaccess rewrite works)
RUN sed -ri 's#DocumentRoot /var/www/html#DocumentRoot /var/www/pouet-api#' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's#<Directory /var/www/>#<Directory /var/www/pouet-api/>#' /etc/apache2/apache2.conf \
    && printf '<Directory /var/www/pouet-api/>\n\tOptions Indexes FollowSymLinks\n\tAllowOverride All\n\tRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/pouet.conf \
    && a2enconf pouet

# PHP settings matching prod
RUN printf 'display_errors=Off\nshort_open_tag=Off\ndate.timezone=UTC\n' > /usr/local/etc/php/conf.d/pouet.ini

WORKDIR /var/www/pouet-api

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
