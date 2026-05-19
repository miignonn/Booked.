FROM php:8.3-cli

RUN docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /var/www/html

COPY . .

EXPOSE 8080

CMD php -S 0.0.0.0:8080 -t public
