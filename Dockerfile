# Use an official PHP runtime as the base image
FROM php:8.1-apache

# Copy PHP files to the Apache web root
COPY . /var/www/html/

# Expose port 80 to the outside world
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]
