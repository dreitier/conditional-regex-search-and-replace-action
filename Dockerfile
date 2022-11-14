FROM php:8.0-cli

RUN apt-get update && apt-get install -y git

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer and set up application
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN mkdir /application
COPY . /application/
RUN cd /application && composer install
COPY entrypoint.sh entrypoint.sh

ENTRYPOINT ["/application/entrypoint.sh"]