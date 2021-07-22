# Installation / Configuration
* after changing config or adding translation files (automatically called
  for composer install|update)  
  `bin/console cache:clear`
* after composer install for production  
  `composer dump-autoload --no-dev --classmap-authoritative`
* create messenger table  
  `./bin/console messenger:setup-transports`
* create new (admin) user  
  `./bin/console app:add-user [username] [email] [password] [--admin]`
* create keys for JWT auth  
  `openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096` 
  `openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout`

# Fixtures
* Load database content (works only in dev|test environments when the necessary
  dependencies are installed)  
  Dev: `php bin/console doctrine:fixtures:load --group initial --append`  
  Test: `php bin/console -etest doctrine:fixtures:load --group test --append`
  
# Development / Unittests
* run test suite (uses .env.test and replaces the test-database content)  
  `composer test`
* automatically fix code style (line breaking, quoting, argument spacing, ...)  
  `composer cs-fix`
  
# Debugging
* List configured services  
  `bin/console debug:container`
* Display default config of symfony packages  
  `bin/console config:dump-reference [framework|debug|...]`
* Show current (custom) config  
  `bin/console debug:config [framework|debug|...]`
