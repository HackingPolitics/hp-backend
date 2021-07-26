# the different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/develop/develop-images/multistage-build/#stop-at-a-specific-build-stage
# https://docs.docker.com/compose/compose-file/#target

# "php" stage
FROM vrokdd/php:symfony AS hp_php

# build for production
ARG APP_ENV=prod
ENV APP_ENV $APP_ENV

# customize the config
COPY docker/php/crontab /etc/crontab
COPY docker/php/php.ini /usr/local/etc/php/conf.d/php.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.conf

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
VOLUME /var/www/html/var/storage

COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]

# "nginx" stage
# depends on the "php" stage above
FROM nginx:1-alpine AS hp_nginx

COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

WORKDIR /var/www/html

COPY --from=hp_php /var/www/html/public public/
