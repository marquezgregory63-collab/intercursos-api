# Usamos una imagen de PHP con un servidor integrado (PHP-CLI)
FROM php:8.2-cli

# Instalamos las extensiones necesarias
RUN docker-php-ext-install pdo pdo_mysql

# Copiamos tus archivos
COPY . /usr/src/myapp

# Nos movemos a esa carpeta
WORKDIR /usr/src/myapp

# Iniciamos el servidor interno de PHP directamente en el puerto que Railway asigna
CMD php -S 0.0.0.0:${PORT}