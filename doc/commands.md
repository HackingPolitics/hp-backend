# Installation / Configuration
* create database tables (or update them after code changes)  
  `./bin/console doctrine:schema:update --force`
* create messenger table  
  `./bin/console messenger:setup-transports`
* create new (admin) user  
  `./bin/console app:add-user [username] [email] [password] [--admin]`
* create new process-manager  
  `./bin/console app:add-user [username] [email] [password] --process-manager`
* after changing config or adding translation files (automatically called
  for composer install|update)  
  `bin/console cache:clear`
* after composer install for production  
  `composer dump-autoload --no-dev --classmap-authoritative`
* Load database fixtures:  
  Default content: `./bin/console doctrine:fixtures:load --group initial --append`  
  Test: `./bin/console -etest doctrine:fixtures:load --group test --append`

## Create keys for JWT authentication  
1. Create a folder that will contain the public & private key, here we use
   _config/jwt/_.
2. Generate a random passphrase to secure the private key, we store it here in
   an ENV variable:  
   ``export JWT_PASSPHRASE=`openssl rand -hex 16` ``
3. Generate the private key:  
   `echo "$JWT_PASSPHRASE" | openssl genpkey -out config/jwt/private.pem -pass stdin -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096`
4. Generate the public key:  
   `echo "$JWT_PASSPHRASE" | openssl pkey -in config/jwt/private.pem -passin stdin -out config/jwt/public.pem -pubout`

# Development / Unittests
* run test suite (uses .env.test and replaces the test-database content)  
  `composer test`
* automatically fix code style (line breaking, quoting, argument spacing, ...)  
  `composer cs-fix`
  
# Debugging
* List configured services  
  `./bin/console debug:container`
* Display default config of symfony packages  
  `./bin/console config:dump-reference [framework|debug|...]`
* Show current (custom) config  
  `./bin/console debug:config [framework|debug|...]`
* Test CORS Headers  
 `curl -X OPTIONS -H "Accept: application/ld+json" -H "Origin: http://example.com" -H "Access-Control-Request-Method: GET"  https://api.test.futureprojects.de/projects --head`
