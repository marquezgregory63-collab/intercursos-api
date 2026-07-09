FROM php:8.2-apache

# Instalamos las extensiones de MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Copiamos nuestra configuración de Apache para evitar errores de MPM
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Copiamos todos tus archivos del proyecto
COPY . /var/www/html/

# Ajustamos los permisos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80