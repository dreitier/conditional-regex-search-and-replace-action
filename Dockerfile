FROM php:8.1-cli

RUN apt-get update && apt-get install -y git php-zip
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer and set up application
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN mkdir /application
COPY . /application/
RUN cd /application && composer install --no-dev
COPY entrypoint.sh /application/entrypoint.sh

ENTRYPOINT ["/application/entrypoint.sh"]