FROM php:8.2-apache

# Instalar dependencias del sistema y la extensión de MongoDB
RUN apt-get update && apt-get install -y \
    libssl-dev \
    git \
    unzip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar los archivos del proyecto al servidor web de Apache
COPY . /var/www/html/

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html

# Instalar dependencias de composer del proyecto
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader

EXPOSE 80