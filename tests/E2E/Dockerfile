# Dedicated offline E2E runtime. Does not run WordPress or reach CertPSU.
FROM php:8.3-cli-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip zip libzip-dev libonig-dev libxml2-dev \
    && docker-php-ext-install -j"$(nproc)" zip mbstring dom xml xmlwriter \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /workspace
