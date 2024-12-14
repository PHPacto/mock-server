FROM php:8.3-alpine AS dev

RUN apk add --update git

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin  --filename=composer

# Start
WORKDIR /srv
USER www-data
CMD php -S 0.0.0.0:8000 bin/server_mock.php
EXPOSE 8000



FROM dev AS build

# Inject application code
COPY . /srv/

USER root

RUN composer install --optimize-autoloader --no-dev

ENV CONTRACTS_DIR=examples
RUN <<EOF
VERSION=$(sed -n 's/.*"bigfoot90\/phpacto": "\([^"]*\)".*/\1/p' composer.json)
LOCK=$(awk '/"name": "bigfoot90\/phpacto"/{flag=1; next} /"version":/{if(flag){print $2; exit}}' composer.lock | tr -d ',"')

if [ "$VERSION" != "$LOCK" ]; then
    echo "Phpacto version mismatch \"$VERSION\" <> \"$LOCK\""
    exit 1
fi

echo "VERSION $VERSION"
./vendor/bin/phpacto validate
EOF

USER www-data
