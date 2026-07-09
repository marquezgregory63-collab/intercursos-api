FROM trafex/php-nginx:latest

# Instalar las extensiones de MySQL que necesitas
USER root
RUN apk add --no-cache php82-pdo_mysql php82-mysqli
USER nobody

# Copiar tus archivos al directorio que usa esta imagen
COPY --chown=nobody . /var/www/html/