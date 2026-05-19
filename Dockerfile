FROM php:8.3-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /var/www/html/public

RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

EXPOSE 80

CMD ["apache2-foreground"]
