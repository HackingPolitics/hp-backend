# the different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/develop/develop-images/multistage-build/#stop-at-a-specific-build-stage
# https://docs.docker.com/compose/compose-file/#target

# "php" stage
FROM vrokdd/php:api AS api_platform_php

RUN ln -s $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini
COPY docker/php/php.ini $PHP_INI_DIR/conf.d/custom.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.conf

ENV START_FPM=true
ENV START_CRON=false
ENV START_MESSENGER=false
COPY docker/php/supervisord.conf /etc/supervisor/supervisord.conf

# build for production
ARG APP_ENV=prod
ENV APP_ENV $APP_ENV

# prevent the reinstallation of dependencies at every change in the source code
COPY composer.json composer.lock symfony.lock ./
RUN set -eux; \
	COMPOSER_MEMORY_LIMIT=-1 composer install --prefer-dist --no-dev --no-scripts --no-progress; \
	composer clear-cache

# do not use .env files in production
COPY .env ./
RUN composer dump-env prod; \
	rm .env

# copy only specifically what we need
COPY bin bin/
COPY config config/
COPY public public/
COPY src src/
COPY templates templates/
COPY translations translations/

RUN set -eux; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer run-script --no-dev post-install-cmd; \
	chmod +x bin/console; \
	sync
VOLUME /srv/api/var/storage

COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]

# "nginx" stage
# depends on the "php" stage above
FROM nginx:1-alpine AS api_platform_nginx

COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

WORKDIR /srv/api

COPY --from=api_platform_php /srv/api/public public/
