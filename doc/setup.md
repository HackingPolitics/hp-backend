@todo
* Docker Ordner einrichten
* Docker-Compose setup
* clone
* log ordner anlegen
* DB einrichten
* smtp account für mails
* .env.local anpassen
  * app secret
  * jwt passphrase
* compose: mount points anpassen
* nginx vhost erstellen
* cert erstellen
* fixtures einspielen

# Installation & Setup

The application is designed to run within a docker container that provides
php-fpm. Use a reverse proxy like Nginx to serve any static files, add SSL and
define the URL the API runs on.
Requirements are a MariaDB/MySQL database (e.g. in separate container) and
a Redis container for caching.

## Development

For development the project contains a folder `.dev-hpo`. This demonstrates how
to setup a DEV environment including PHP + MySQL. This expects the source code
(and all other files) to be mounted. That folder can be used in _PhpStorm_ to
run unit-tests & debug session from within the IDE by using File > Settings >
PHP > CLI Interpreter and then adding a new entry "from Docker" and
configuring the _docker-compose.yml_ from the `.dev-hpo` folder.  
The _docker-compose.yml_ needs to be adjusted to your system, depending on
wether your docker service runs locally or within a VM etc.

## Production setup

The repository contains a _Dockerfile_ in the root directory and additional setup
files in _/docker_. You can use this to build two containers:

1. an all-in-one PHP container that runs PHP-FPM to serve web requests, runs
   cron to trigger hourly/daily tasks and runs the messenger queue worker to
   process background tasks.
2. a Nginx webserver that serves static files and the application on port 80

Those containers are also available prebuilt with the latest stable version:
```shell
docker pull jschumanndd/hp-backend:main
docker pull jschumanndd/hp-backend:nginx-main
```

### Example docker-compose.yaml
```
 hpoapi:
    image: jschumanndd/hp-backend:main
    restart: on-failure:5
    container_name: hpoapi
    cpu_shares: 512
    mem_limit: 1500m
    dns: 8.8.8.8
    networks:
      custom:
    security_opt:
      - apparmor:docker-default
    environment:
      - APP_ENV=prod
      - APP_SECRET=!ChangeMe!
      - DATABASE_URL=//dbuser:dbpasswd@mysql/dbname
      - JWT_PASSPHRASE=!ChangeMe!
      - MAILER_DSN=smtp://username:password@server:587
      - MAILER_SENDER="Your HP <email@domain.tld>"
    volumes:
      - /path/to/storage-dir:/var/www/html
      - /path/to/log-dir:/var/www/log
    links:
      - your-db-container:mysql
  redishpoapi:
    container_name: redishpoai
    image: redis
    restart: on-failure:5
    network_mode: service:hpoapi
    cpu_shares: 128
    mem_limit: 256m
    read_only: true
    security_opt:
      - apparmor:docker-default
```

### Application setup
* create an empty database & corresponding db-user 
* prepare the environment
* for production: create the JWT keys (see commands.md)
  * set the passphrase used in .env.local
* `composer install` (inside or outside of the container)
* inside the container: `./bin/console doctrine:schema:update --force` to
  create the tables
* inside the container: create the symfony messenger table (see commands.md)
* inside the container: create an admin user and a process-manager (see commands.md)

## Serve the application through an reverse proxy

Example vhost config for Nginx:
```
server {
    listen  80;
    listen  [::]:80;
    server_name  your-domain;
    return  301 https://your-domain$request_uri;
}
server {
    listen  443 ssl http2;
    listen  [::]:443 ssl http2;

    server_name  your-domain;
    root  /var/www/your-vhost/application-dir/public;

    ssl_certificate  /var/www/letsencrypt/certs/your-domain/fullchain.pem;
    ssl_certificate_key  /var/www/letsencrypt/certs/your-domain/privkey.pem;
    add_header  Strict-Transport-Security "max-age=315360000; includeSubdomains; preload;";

    error_log /var/www/your-vhost/log/error.log;
    access_log /var/www/your-vhost/log/access.log main;

    # for letsencrypt /.well-known/acme-challenge
    include /etc/nginx/global/letsencrypt.conf;

    location / {
        # try to serve file directly, fallback to index.php
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/.+\.php(/|$) {
        fastcgi_pass hpoapi:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/html/public$fastcgi_script_name;

        fastcgi_connect_timeout  120;
        fastcgi_send_timeout  180;
        fastcgi_read_timeout  180;
    }
}
```

## Optimizing php.ini
```
; symfony performance guide
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
realpath_cache_size = 4096K
realpath_cache_ttl = 600

; for production
opcache.validate_timestamps = 0
opcache.preload = /var/www/html/var/cache/prod/App_KernelProdContainer.preload.php
opcache.preload_user = www-data
```