FROM php:8.2-apache

# Extensions PHP nécessaires
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libssl-dev \
    pkg-config \
    && docker-php-ext-install pdo pdo_mysql \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copier la config Apache
COPY .docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Répertoire de travail
WORKDIR /var/www/html

COPY . .

RUN chown -R www-data:www-data /var/www/html
