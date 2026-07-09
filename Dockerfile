FROM php:8.2-apache

# Instalamos extensiones para MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Copiamos tus archivos
COPY . /var/www/html/

# Ajustamos permisos básicos
RUN chown -R www-data:www-data /var/www/html

# EXPOSE no es estrictamente necesario en Railway, 
# pero ayuda a documentar. Apache por defecto escucha en 80.
EXPOSE 80