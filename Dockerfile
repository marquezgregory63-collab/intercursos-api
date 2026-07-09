FROM php:8.2-fpm-alpine

# Instalar cliente de MySQL y dependencias
RUN docker-php-ext-install pdo pdo_mysql

# Instalar Nginx para servir la web
RUN apk add --no-cache nginx

# Copiar configuración de Nginx (esto asegura que funcione sin errores MPM)
RUN echo 'server { \
    listen 80; \
    root /var/www/html; \
    index index.php index.html; \
    location / { try_files $uri $uri/ /index.php?$query_string; } \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_index index.php; \
        include fastcgi_params; \
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
    } \
}' > /etc/nginx/conf.d/default.conf

COPY . /var/www/html/

# Iniciar PHP-FPM y Nginx
CMD php-fpm -D && nginx -g "daemon off;"