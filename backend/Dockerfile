#
# One image for both the API and the queue worker. Apache serves PHP directly,
# so there is no separate web server container to keep in sync.
#
FROM php:8.3-apache

# bcmath for exact decimal quantities, sockets for php-amqplib, pcntl for a
# clean consumer shutdown. mysql-client and curl are for the readiness probes.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        default-mysql-client \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        sockets \
        pcntl \
        opcache \
    && a2enmod rewrite \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

# default_socket_timeout sits above the AMQP heartbeat, otherwise a quiet
# consumer connection gets torn down while it waits.
RUN { \
        echo 'memory_limit = 512M'; \
        echo 'max_execution_time = 60'; \
        echo 'upload_max_filesize = 16M'; \
        echo 'post_max_size = 16M'; \
        echo 'serialize_precision = -1'; \
        echo 'default_socket_timeout = 130'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.memory_consumption = 128'; \
        echo 'opcache.max_accelerated_files = 10000'; \
        echo 'opcache.revalidate_freq = 0'; \
    } > /usr/local/etc/php/conf.d/99-pms.ini

# Laravel serves from public/. AllowOverride lets its .htaccess do the routing.
RUN { \
        echo 'ServerName localhost'; \
        echo '<VirtualHost *:80>'; \
        echo '    DocumentRoot /var/www/html/public'; \
        echo '    <Directory /var/www/html/public>'; \
        echo '        AllowOverride All'; \
        echo '        Require all granted'; \
        echo '    </Directory>'; \
        echo '    ErrorLog /dev/stderr'; \
        echo '    CustomLog /dev/stdout combined'; \
        echo '</VirtualHost>'; \
    } > /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker-entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

# The code itself is bind-mounted by compose, so edits are live.
EXPOSE 80

ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
