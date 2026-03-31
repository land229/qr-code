FROM php:8.2-apache

# Activer mod_rewrite
RUN a2enmod rewrite

# Installer extensions nécessaires
RUN docker-php-ext-install pdo pdo_mysql

# Installer git, unzip et autres outils nécessaires
RUN apt-get update && apt-get install -y git unzip zip

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copier le projet Symfony
COPY . /var/www/html/

# Aller dans le dossier projet
WORKDIR /var/www/html

# Installer dépendances Symfony
RUN composer install 
# Définir public/ comme racine Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Donner les permissions
RUN chown -R www-data:www-data /var/www/html/var /var/www/html/vendor

EXPOSE 80