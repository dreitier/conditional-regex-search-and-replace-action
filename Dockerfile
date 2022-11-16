FROM php:8.1-cli

RUN apt-get update && apt-get install -y git zip libzip-dev
RUN docker-php-ext-install zip
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer and set up application
COPY --from=docker.io/composer:latest /usr/bin/composer /usr/bin/composer
RUN mkdir /application
COPY . /application/
RUN cd /application && composer install --no-dev
COPY entrypoint.sh /application/entrypoint.sh

RUN chmod +x /application/entrypoint.sh

ENTRYPOINT ["/application/entrypoint.sh"]
