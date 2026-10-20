FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite headers \
    && sed -ri 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's/\/var\/www\/html/\/var\/www\/html/g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's/DocumentRoot \/var\/www\/html/DocumentRoot \/var\/www\/html\/public/' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's/^export APACHE_RUN_USER=.*/export APACHE_RUN_USER=www-data/' /etc/apache2/envvars \
    && sed -ri 's/^export APACHE_RUN_GROUP=.*/export APACHE_RUN_GROUP=www-data/' /etc/apache2/envvars \
    && sed -ri 's/^export APACHE_PID_FILE=.*/export APACHE_PID_FILE=\/tmp\/apache2.pid/' /etc/apache2/envvars \
    && sed -ri 's/^export APACHE_RUN_DIR=.*/export APACHE_RUN_DIR=\/tmp/' /etc/apache2/envvars \
    && sed -ri 's/^export APACHE_LOCK_DIR=.*/export APACHE_LOCK_DIR=\/tmp/' /etc/apache2/envvars \
    && sed -ri 's/^export APACHE_LOG_DIR=.*/export APACHE_LOG_DIR=\/dev\/stdout/' /etc/apache2/envvars \
    && printf '\n<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' >> /etc/apache2/apache2.conf \
    && chown -R www-data:www-data /var/www/html
COPY --chown=www-data:www-data public/ /var/www/html/public/
USER www-data
EXPOSE 8080
