FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpq-dev \
    icu-dev \
    libzip-dev \
    unzip \
    git \
    curl

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    intl \
    zip \
    opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/api

# Copy composer files first for layer caching
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-scripts 2>/dev/null || true

# Copy application source
COPY . .

# Nginx config
RUN echo 'server { \
    listen 80; \
    root /var/www/api/public; \
    index index.php; \
    location / { try_files $uri /index.php$is_args$args; } \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
        include fastcgi_params; \
    } \
}' > /etc/nginx/http.d/default.conf

# Supervisor config
RUN printf '%s\n' \
    '[supervisord]' \
    'nodaemon=true' \
    '' \
    '[program:php-fpm]' \
    'command=php-fpm -F' \
    '' \
    '[program:nginx]' \
    'command=nginx -g "daemon off;"' \
    > /etc/supervisord.conf

RUN mkdir -p var/cache var/log && chmod -R 777 var/

EXPOSE 80

CMD ["supervisord", "-c", "/etc/supervisord.conf"]
