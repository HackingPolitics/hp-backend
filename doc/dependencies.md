# Used dependencies
* catoth/html2opendocument - to generate ODT files from HTML entered by the users
  as phpoffice/phpword cannot really handle HTML conversion (2021-07-20)
* league/flysystem-bundle - filesystem abstraction so we can switch the storage
  of private files (document exports) or public files (user upload like project
  pictures) to different backends (local filesystem, AWS S3, ...)
* lexik/jwt-authentication-bundle - JWT generation & handling for api platform
  * janakdom/jwt-refresh-token-bundle - manage Refresh Tokens for lexik/jwt
* stof/doctrine-extensions-bundle - automatically manage
  slugs, creation date etc for our entities
* symfony/monolog-bundle + graylog2/gelf-php - to be able to filter logs for
  different environments and log to different backends (file, GELF / logstash, ...)
* tuupola/base62 - to generate tokens with [A-Za-z0-9] instead of hex chars to
  reduce URL length, e.g. for validation URLs
* twig/extensions - for localizedDate in templates etc.
* ueberdosis/html-to-prosemirror / ueberdosis/prosemirror-to-html to support
  collaborative real-time editing
* vich/uploader-bundle - for file uploads and downloads to/from our storage
  backend (flysystem)


## Development-only dependencies
* doctrine/doctrine-fixtures-bundle - regenerating test database
* [test-pack][symfony/test-pack] +  [http-client][symfony/http-client] +
  justinrainbow/json-schema - testing
* php-unit/php-unit - to enforce a newer version as the test-pack installs 7.x,
  api-platform classes require 8.x
* zalas/phpunit-globals - to change environment variables for specific test