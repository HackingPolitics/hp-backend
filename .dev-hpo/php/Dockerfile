FROM vrokdd/php:api

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install xdebug-3.0.3 \
    && docker-php-ext-enable xdebug

RUN ln -s $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini
COPY php.ini $PHP_INI_DIR/conf.d/custom.ini
COPY supervisord.conf /etc/supervisor/supervisord.conf

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENV START_FPM=true
ENV START_CRON=true
ENV START_MESSENGER=true

ENTRYPOINT ["docker-entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
