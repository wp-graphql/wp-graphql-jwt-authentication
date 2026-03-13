ARG PHP_VERSION=8.2
FROM wordpress:php${PHP_VERSION}-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    bash less mariadb-client git unzip wget \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN curl -sS https://getcomposer.org/installer | php -- \
    --filename=composer \
    --install-dir=/usr/local/bin

RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

ADD https://raw.githubusercontent.com/vishnubob/wait-for-it/master/wait-for-it.sh /usr/local/bin/wait-for-it
RUN chmod 755 /usr/local/bin/wait-for-it

# Remove exec statement from base entrypoint script so sourcing it doesn't exec.
RUN sed -i '$d' /usr/local/bin/docker-entrypoint.sh

# Disable SSL for MariaDB client connections (fails with MySQL 8.0 self-signed cert)
RUN mkdir -p /etc/mysql/conf.d && \
    echo '[client]' > /etc/mysql/conf.d/disable-ssl.cnf && \
    echo 'ssl=0' >> /etc/mysql/conf.d/disable-ssl.cnf

RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf \
    && a2enmod rewrite

ENV WP_ROOT_FOLDER="/var/www/html"
ENV PLUGINS_DIR="${WP_ROOT_FOLDER}/wp-content/plugins"
ENV PROJECT_DIR="${PLUGINS_DIR}/wp-graphql-jwt-authentication"

WORKDIR /var/www/html

COPY bin/entrypoint.sh /usr/local/bin/app-entrypoint.sh
RUN chmod 755 /usr/local/bin/app-entrypoint.sh

ENTRYPOINT ["app-entrypoint.sh"]
CMD ["apache2-foreground"]
