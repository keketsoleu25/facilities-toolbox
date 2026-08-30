# PHP 8.3 with Apache
FROM php:8.3-apache

# Application files are served by Apache from this directory.
WORKDIR /var/www/html

# Copy the Facilities Portal into the container.
COPY . .

# Apache serves the portal on port 80.
EXPOSE 80