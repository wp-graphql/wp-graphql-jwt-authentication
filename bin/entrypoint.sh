#!/bin/bash

# Run WordPress docker entrypoint to copy WP files and create wp-config.
# shellcheck disable=SC1091
. docker-entrypoint.sh 'apache2'

set +u

# Create a basic MySQL client config that forces no SSL
echo "[client]
ssl=false
[mysql]
ssl=false
[mysqldump]
ssl=false" > /tmp/.my.cnf

# Wait for MySQL
wait-for-it -s -t 300 "${DB_HOST}:3306" -- echo "Database is ready..."

# Config WordPress
if [ -f "${WP_ROOT_FOLDER}/wp-config.php" ]; then
	echo "Deleting old wp-config.php"
	rm -f "${WP_ROOT_FOLDER}/wp-config.php"
fi

echo "Creating wp-config.php..."
wp config create \
	--path="${WP_ROOT_FOLDER}" \
	--dbname="${DB_NAME}" \
	--dbuser="${DB_USER}" \
	--dbpass="${DB_PASSWORD}" \
	--dbhost="${DB_HOST}" \
	--dbprefix="${WP_TABLE_PREFIX}" \
	--skip-check \
	--quiet \
	--allow-root

# Install WP if not yet installed
if ! $(wp core is-installed --allow-root); then
	echo "Installing WordPress..."
	wp core install \
		--path="${WP_ROOT_FOLDER}" \
		--url="${WP_URL}" \
		--title='JWT Auth Tests' \
		--admin_user="${ADMIN_USERNAME}" \
		--admin_password="${ADMIN_PASSWORD}" \
		--admin_email="${ADMIN_EMAIL}" \
		--allow-root
fi

# Install and activate plugins
wp plugin install wp-graphql --allow-root
wp plugin activate wp-graphql --allow-root
wp plugin activate wp-graphql-jwt-authentication --allow-root

# Configure JWT
echo "Adding WPGraphQL-JWT-Authentication secret..."
wp config set GRAPHQL_JWT_AUTH_SECRET_KEY 'test-token-that-is-long-enough-for-hs256' --allow-root
wp config set GRAPHQL_DEBUG true --raw --allow-root

echo "Setting pretty permalinks..."
wp rewrite structure '/%year%/%monthnum%/%postname%/' --allow-root
wp rewrite flush --allow-root

# Export database for Codeception
echo "Dumping database..."
MYSQL_PWD="${DB_PASSWORD}" mysqldump \
	--defaults-extra-file=/tmp/.my.cnf \
	--user="${DB_USER}" \
	--host="${DB_HOST}" \
	--port=3306 \
	--single-transaction \
	--routines \
	--triggers \
	"${DB_NAME}" > "${PROJECT_DIR}/tests/_data/dump.sql"

# Install composer dependencies
cd "${PROJECT_DIR}"
composer install --prefer-dist --no-interaction

# Start Apache
service apache2 start

echo "Running WordPress version: $(wp core version --allow-root) at $(wp option get home --allow-root)"

exec "$@"
