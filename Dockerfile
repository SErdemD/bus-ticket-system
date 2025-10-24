FROM php:8.2-cli

# Install required system packages for SQLite
RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev pkg-config \
 && docker-php-ext-install pdo pdo_sqlite \
 && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Expose port
EXPOSE 8000

# Run PHP’s built-in web server on all interfaces
CMD ["php", "-S", "0.0.0.0:8000", "index.php"]
