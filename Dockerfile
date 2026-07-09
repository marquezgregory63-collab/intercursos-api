FROM php:8.2-apache

# Instalamos las dependencias de MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Esto elimina la configuración de Apache que causa el choque de MPM
RUN rm -rf /etc/apache2/mods-enabled/mpm_*.load

# Esto habilita solo el módulo necesario (prefork)
RUN a2enmod mpm_prefork

# Copiamos tus archivos
COPY . /var/www/html/

# Ajustamos permisos
RUN chown -R www-data:www-data /var/www/html