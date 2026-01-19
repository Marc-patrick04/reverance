# Use PHP + Apache
FROM php:8.2-apache

# Install PostgreSQL PDO extension
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && docker-php-ext-enable pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Copy project files
COPY . /var/www/html/

# Create uploads directory and set up symlink for persistent storage on Render
RUN mkdir -p /var/www/html/uploads/images && \
    chmod 755 /var/www/html/uploads/images && \
    chown www-data:www-data /var/www/html/uploads/images && \
    # Create symlink to persistent disk location for Render
    ln -sf /opt/render/project/src/uploads /var/www/html/uploads

# Expose default Render port
EXPOSE 10000
