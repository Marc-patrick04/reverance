FROM php:8.2-apache

# Enable PostgreSQL PDO
RUN docker-php-ext-install pdo pdo_pgsql

# Copy project files
COPY . /var/www/html/

# Allow Apache to use Render port
RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

EXPOSE ${PORT}
