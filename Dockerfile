# Image PHP-FPM avec Nginx
FROM php:8.2-fpm-alpine

# Installation de Nginx et extensions PHP
RUN apk add --no-cache nginx && \
    docker-php-ext-install pdo pdo_mysql mysqli opcache

# OPcache : le code PHP compilé reste en mémoire (le conteneur est reconstruit à chaque déploiement)
RUN { \
        echo "opcache.enable=1"; \
        echo "opcache.memory_consumption=128"; \
        echo "opcache.interned_strings_buffer=16"; \
        echo "opcache.max_accelerated_files=10000"; \
        echo "opcache.validate_timestamps=0"; \
        echo "realpath_cache_size=4096K"; \
        echo "realpath_cache_ttl=600"; \
    } > /usr/local/etc/php/conf.d/opcache.ini

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
