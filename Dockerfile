# Image PHP-FPM avec Nginx
FROM php:8.2-fpm-alpine

# Installation de Nginx et extensions PHP
RUN apk add --no-cache nginx && \
    docker-php-ext-install pdo pdo_mysql mysqli

# Configuration PHP
RUN echo "variables_order = EGPCS" >> /usr/local/etc/php/conf.d/railway.ini && \
    echo "display_errors = Off" >> /usr/local/etc/php/conf.d/railway.ini && \
    echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/railway.ini && \
    echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/railway.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/railway.ini

# Copie de la configuration Nginx
COPY nginx.conf /etc/nginx/http.d/default.conf

# Copie des fichiers de l'application
COPY . /var/www/html/

# Permissions
RUN mkdir -p /var/www/html/uploads /var/www/html/logs /var/www/html/cache && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    mkdir -p /run/nginx

# Script de démarrage
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
