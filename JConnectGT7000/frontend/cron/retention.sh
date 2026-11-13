#!/bin/sh
# Daily retention. From the PHP container:
# php /var/www/frontend/index.php retention purge
cd "$(dirname "$0")/.."
php index.php retention purge
