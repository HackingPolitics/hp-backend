@todo
* fixtures einspielen
* cache setup, redis nicht mit network mode sondern via link
* compose: nginx + mariadb

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
2. an Nginx webserver that serves static files and the application on port 80

Those containers are also available prebuilt with the latest stable version:
```shell
docker pull jschumanndd/hp-backend:main
docker pull jschumanndd/hp-backend:nginx-main
```

### Example docker-compose.yaml
```
version: '3.7'

networks:
    custom:
        driver: bridge

services:
    hpoweb:
        image: jschumanndd/hp-backend:nginx-main
        container_name: hpoweb
        deploy:
            resources:
                limits:
                    cpus: '2.00'
                    memory: 500M
            restart_policy:
                condition: on-failure
                max_attempts: 5
        networks:
            custom:
        security_opt:
            - apparmor=docker-default
        ports:
            - 80:80
        links:
            - hpoapi:php

    hpoapi:
        image: jschumanndd/hp-backend:main
        restart: on-failure:5
        container_name: hpoapi
        deploy:
            resources:
                limits:
                    cpus: '3.00'
                    memory: 1500M
            restart_policy:
                condition: on-failure
                max_attempts: 5
        dns: 8.8.8.8
        networks:
            custom:
        security_opt:
            - apparmor:docker-default
        environment:
            # generate a different value for each instance
            - APP_SECRET=!ChangeMe!
            - DATABASE_URL=//hpo_user:hpo_pwd@mysql/hpo_db
            - JWT_PASSPHRASE=!ChangeMe!
            # SMTP server credentials to send emails, special chars may be URL encoded
            - MAILER_DSN=smtp://username:password@server:587
            # Sender name & address for emails sent by the application
            - MAILER_SENDER="Your HP <email@domain.tld>"
        volumes:
            - /path/to/jwt-keys:/var/www/html/config/jwt:ro
            - /path/to/storage-dir:/var/www/html/var/storage
            # for FPM + PHP error log
            - /path/to/log-dir:/var/www/log
            # for Symfony error log
            - /path/to/log-dir:/var/www/html/var/log
        links:
            - hpodb:mysql
            - hporedis:redis

    hporedis:
        container_name: hporedis
        image: redis
        deploy:
            resources:
                limits:
                    cpus: '0.50'
                    memory: 200M
            restart_policy:
                condition: on-failure
                max_attempts: 5
        read_only: true
        networks:
            custom:
        security_opt:
            - apparmor:docker-default

    # this is only an example without persistent storage, replace with a
    # suitable container definition or your data will be lost when the container
    # is recreated!
    hpodb:
        image: mariadb:latest
        container_name: hpodb
        deploy:
            resources:
                limits:
                    cpus: '2.00'
                    memory: 1000M
            restart_policy:
                condition: on-failure
                max_attempts: 5
        networks:
            custom:
        environment:
            MYSQL_ROOT_PASSWORD: root
            MYSQL_DATABASE: hpo_db
            MYSQL_USER: hpo_user
            MYSQL_PASSWORD: hpo_pwd
```

### Application setup
1. create an empty database & corresponding db-user 
2. create the JWT keys (see commands.md) and note the used passphrase
3. collect the ENV variables necessary to run the container, and set them in your
   _docker-compose.yaml_, see example above for the minimum config, see _.env_
   for more options
4. deploy the compose file, e.g. using in the folder containing the file:
   ```
   docker-compose --compatibility build
   docker-compose --compatibility up -d --remove-orphans
   ```
5. Open a console inside the PHP container to finish setup:
   `docker exec -it hpoapi bash`
   * create the tables in the database  
   `./bin/console doctrine:schema:update --force`
   * create the symfony messenger table (see commands.md)
   * load the initial fixtures (see commands.md)
   * create an admin user and a process-manager (see commands.md)
6. Restart the PHP container with `docker restart hpoapi`, the application 
   should now be running and be accessible at http://localhost:80. You can now
   use a proxy like [Traefik](https://doc.traefik.io/traefik/) to access the
   application from the internet and manage HTTPS traffic, e.g. with the 
   Let's Encrypt-capabilities of Traefik. Or you can mount a customized webserver
   config in the Nginx container at _/etc/nginx/conf.d/default.conf_ (and
   optionally the SSL certificate files) to expose the Nginx directly to the
   internet.