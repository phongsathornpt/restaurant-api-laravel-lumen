FROM alpine:latest

# for laravel lumen run smoothly
RUN apk add \
php \
php-fpm \
php-pdo \
php-mbstring \
php-openssl \ 
php-tokenizer \ 
php-json \
php-dom \
curl \
php-curl \
php-phar \
php-xml \
php-xmlwriter


# if need composer to update plugin / vendor used
RUN php -r "copy('http://getcomposer.org/installer', 'composer-setup.php');" && \
php composer-setup.php --install-dir=/usr/bin --filename=composer && \
php -r "unlink('composer-setup.php');"

# copy all of the file in folder to /src
COPY . /src
WORKDIR /src

RUN composer update

CMD php -S 0.0.0.0:8080 public/index.php
EXPOSE 8080