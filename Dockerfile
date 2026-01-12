# Image PHP avec Apache
FROM php:8.1-apache

# Installation des extensions PHP nécessaires
RUN docker-php-ext-install pdo pdo_mysql mysqli

# FORCER la suppression des MPM en conflit (supprimer physiquement les fichiers)
# Version 2026-01-12-v3
RUN rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* 2>/dev/null; \
    rm -f /etc/apache2/mods-available/mpm_event.* /etc/apache2/mods-available/mpm_worker.* 2>/dev/null; \
    echo "=== MPM cleanup done ===" && ls -la /etc/apache2/mods-enabled/ | grep mpm || echo "No MPM in enabled"

# Activation des modules Apache
RUN a2enmod rewrite

# Configuration PHP pour variables d'environnement et paramètres
RUN echo "variables_order = \"EGPCS\"" >> /usr/local/etc/php/conf.d/railway.ini && \
    echo "display_errors = Off" >> /usr/local/etc/php/conf.d/railway.ini && \
    echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/railway.ini && \
    echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/railway.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/railway.ini

# Copie des fichiers de l'application
COPY . /var/www/html/

# Création des répertoires nécessaires avec permissions
RUN mkdir -p /var/www/html/uploads /var/www/html/logs /var/www/html/cache && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Exposition du port 80
EXPOSE 80

# Démarrage d'Apache
CMD ["apache2-foreground"]
