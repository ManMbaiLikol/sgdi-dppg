# Image PHP avec Apache
FROM php:8.1-apache

# Installation des extensions PHP nécessaires
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Activation des modules Apache
RUN a2enmod rewrite

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
