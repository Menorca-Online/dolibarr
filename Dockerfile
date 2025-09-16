FROM php:8.3-apache


RUN apt-get update -y && apt-get install -y \
  libpng-dev libxml2-dev libtool \
  libzip-dev zip unzip \
  libicu-dev




RUN docker-php-ext-install -j "$(nproc)" mysqli opcache pdo pdo_mysql gd zip soap intl calendar


RUN apt-get update; \
    apt-get install -y libmagickwand-dev; \
    pecl install imagick; \
docker-php-ext-enable imagick;






RUN a2enmod rewrite
RUN service apache2 restart


RUN set -ex; \
  { \
    echo "; Cloud Run enforces memory & timeouts"; \
    echo "memory_limit = -1"; \
    echo "max_execution_time = 0"; \
    echo "; File upload at Cloud Run network limit"; \
    echo "upload_max_filesize = 32M"; \
    echo "post_max_size = 32M"; \
    echo "; Configure Opcache for Containers"; \
    echo "opcache.enable = Off"; \
    echo "opcache.validate_timestamps = Off"; \
    echo "; Configure Opcache Memory (Application-specific)"; \
    echo "opcache.memory_consumption = 32"; \
  } > "$PHP_INI_DIR/conf.d/cloud-run.ini"

# Copy in custom code from the host machine.
WORKDIR /var/www/html

RUN chmod +777 -R /var/www/html
RUN chown www-data:www-data -R /var/www/html

RUN sed -i 's/\/var\/www\/html/\/var\/www\/html\/htdocs/g' /etc/apache2/sites-available/000-default.conf

RUN sed -i 's/80/80/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf


RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"


CMD ["apache2-foreground"]
EXPOSE 80
