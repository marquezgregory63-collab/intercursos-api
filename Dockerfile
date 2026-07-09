FROM php:8.2-apache

# Habilitar mod_rewrite (necesario para que las rutas funcionen)
RUN a2enmod rewrite

# Instalar dependencias para MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Copiar todo el código al directorio de Apache
COPY . /var/www/html/

# Dar permisos al usuario de Apache
RUN chown -R www-data:www-data /var/www/html

# Configurar Apache para que escuche en el puerto que Railway le asigne
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE ${PORT}