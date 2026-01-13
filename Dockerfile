FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip sockets \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Create necessary directories
RUN mkdir -p /app/audio /app/logs

# Set permissions
RUN chmod +x production_server.php production_encode.php

# Expose port
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD php -r "echo 'OK';" || exit 1

# Default command
CMD ["php", "production_server.php"]