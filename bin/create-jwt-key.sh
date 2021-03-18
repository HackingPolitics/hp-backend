#!/bin/sh
set -e

# Determine the passphrase from ENV > .env.local > .env
if [ -z "$JWT_PASSPHRASE" ]; then
    JWT_PASSPHRASE=$(grep '^JWT_PASSPHRASE=' .env.local | cut -f 2 -d '=')

    if [ -z "$JWT_PASSPHRASE" ]; then
        JWT_PASSPHRASE=$(grep '^JWT_PASSPHRASE=' .env | cut -f 2 -d '=')
        echo "Using JWT passphrase from .env"
    else
        echo "Using JWT passphrase from .env.local"
    fi
else
    echo "Using JWT passphrase from environment var"
fi

# check if private key exists and is decryptable with current passphrase
if [ -f config/jwt/private.pem ] &&  echo "$JWT_PASSPHRASE" | openssl pkey -in config/jwt/private.pem -passin stdin -noout > /dev/null 2>&1; then
  echo "Private key up to date with configured passphrase, exiting ..."
  exit 0
fi

echo "Creating new public & private key ..."

mkdir -p config/jwt
echo "$JWT_PASSPHRASE" | openssl genpkey -out config/jwt/private.pem -pass stdin -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
echo "$JWT_PASSPHRASE" | openssl pkey -in config/jwt/private.pem -passin stdin -out config/jwt/public.pem -pubout
chown -R www-data:www-data config/jwt

echo "done!"