FROM trafex/php-nginx:latest

# Cambiamos a root para poder instalar cosas
USER root

# Instalamos las extensiones necesarias con los nombres correctos para Alpine
RUN apk add --no-cache php82-pdo_mysql php82-mysqli

# Si el comando anterior falla de nuevo, usaremos esta línea más genérica:
# RUN apk add --no-cache php82-pecl-mysqlnd 

# Volvemos al usuario nobody (importante para la seguridad de esta imagen)
USER nobody

# Copiar tus archivos
COPY --chown=nobody . /var/www/html/