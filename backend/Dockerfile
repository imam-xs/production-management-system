# ---------------------------------------------------------------------------
# Shared image for both roles of the backend:
#
#   APP_ROLE=app     — Apache + mod_php serving the REST API
#   APP_ROLE=worker  — the RabbitMQ consumer (same code, same extensions)
#
# Apache serves HTTP directly, so there is no separate web-server container and
# no vhost file to keep in sync — the document root and PHP overrides are
# written inline below.
# ---------------------------------------------------------------------------
FROM php:8.3-apache

# default-mysql-client — readiness probe (mysqladmin ping) and debugging
# curl                 — container healthcheck against /up
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

# pdo_mysql — database driver
# bcmath    — exact decimal arithmetic for inventory quantities
# sockets   — required by php-amqplib
# pcntl     — signal handling for graceful consumer shutdown
# opcache   — bytecode cache

# PHP overrides. default_socket_timeout is raised above the AMQP heartbeat so a
# quiet consumer connection is not torn down mid-wait.
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

# Laravel serves from public/; AllowOverride lets its shipped .htaccess apply.
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

# Application code is bind-mounted by docker-compose so edits are live and the
# first boot can install dependencies straight into the mount.

EXPOSE 80

ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
