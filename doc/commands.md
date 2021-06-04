# Installation / Configuration
* create messenger table
  `./bin/console messenger:setup-transports`
* create new (admin) user  
  `./bin/console app:add-user [username] [email] [password] [--admin]`
* create keys for JWT auth  
  `openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096` 
  `openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout`

# Fixtures
* Datenbank befüllen (funktioniert nur in dev/test Umgebung)  
  Dev: `php bin/console doctrine:fixtures:load --group initial --append`  
  Test: `php bin/console -etest doctrine:fixtures:load --group test --append`
  
# Unittests
* Test-Suite ausführen (verwendet .env.test und überschreibt die Testdatenbank)  
  `php vendor/bin/simple-phpunit`
  
# Debugging
* Alle konfigurierten Services auflisten  
  `php bin/console debug:container`
* Standard-Config der Symfony-Pakete anzeigen  
  `php bin/console config:dump-reference [framework|debug|...]`
* Aktuelle eigene Config anzeigen  
  `php bin/console debug:config [framework|debug|...]`

# Deployment

* `bin/console cache:clear`
* `composer dump-autoload --no-dev --classmap-authoritative`
