FROM php:8.2-apache

# Instalamos las extensiones necesarias
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copiamos tus archivos al servidor
COPY . /var/www/html/

# Ajustamos permisos para evitar errores de escritura
RUN chown -R www-data:www-data /var/www/html

# Aseguramos que el puerto sea el 80
EXPOSE 80