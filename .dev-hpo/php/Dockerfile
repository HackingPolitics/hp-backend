FROM vrokdd/php:symfony

RUN apt-get install $PHPIZE_DEPS \
    && pecl install xdebug-3.0.4 \
    && docker-php-ext-enable xdebug

COPY php.ini /usr/local/etc/php/conf.d/php.ini

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
