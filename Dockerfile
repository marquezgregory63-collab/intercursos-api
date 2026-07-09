FROM php:8.2-apache

# Instalamos extensiones necesarias
RUN docker-php-ext-install pdo pdo_mysql

# Deshabilitamos los módulos conflictivos de MPM de una vez
RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork

# Copiamos tus archivos
COPY . /var/www/html/

# Damos permisos
RUN chown -R www-data:www-data /var/www/html