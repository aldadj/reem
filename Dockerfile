FROM php:8.3-apache

# ffmpeg est nécessaire à VideoThumbnailService pour générer les miniatures.
RUN apt-get update && apt-get install -y \
    ffmpeg \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    unzip \
    git \
    libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Sans ce fichier, PHP conserve ses valeurs par défaut (2 Mo / 8 Mo) et refuse
# toute vidéo réelle avec « The video failed to upload. ».
COPY docker/uploads.ini /usr/local/etc/php/conf.d/zz-reem-uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN a2enmod rewrite

RUN printf '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

RUN mkdir -p \
        storage/app/public/videos \
        storage/app/public/thumbnails \
        storage/app/public/profiles \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Vérification de la configuration PHP d'upload
RUN echo "=== CONFIGURATION PHP UPLOAD ===" \
    && php -i | grep -E "upload_max_filesize|post_max_size|max_execution_time|max_input_time|memory_limit"

EXPOSE 80

# storage:link  -> expose storage/app/public sous /storage/...
#                  sans ce lien, /storage/videos/xxx.mp4 répond 404
# migrate       -> met la base à jour au démarrage
CMD ["sh", "-c", "php artisan storage:link --force && php artisan migrate --force && apache2-foreground"]
