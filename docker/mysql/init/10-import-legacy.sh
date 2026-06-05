#!/bin/sh
set -eu

legacy_dump="/legacy-source/gouden_draak_create_script.sql"

if [ ! -f "$legacy_dump" ]; then
    echo "Legacy dump not found at $legacy_dump; skipping legacy database import."
    exit 0
fi

sed \
    -e 's/CREATE DATABASE  IF NOT EXISTS `gouden_draak`/CREATE DATABASE IF NOT EXISTS `gouden_draak_legacy`/I' \
    -e 's/USE `gouden_draak`/USE `gouden_draak_legacy`/I' \
    "$legacy_dump" \
    | mysql -uroot -p"$MYSQL_ROOT_PASSWORD"
