#!/bin/bash
# Sourced (not executed) by the MySQL entrypoint once, when the data volume is first initialised.
# Creates a separate database and user for automated tests. The test user has no access to the demo database.

docker_process_sql --database=mysql <<-EOSQL
	CREATE DATABASE IF NOT EXISTS \`${MYSQL_TEST_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
	CREATE USER IF NOT EXISTS '${MYSQL_TEST_USER}'@'%' IDENTIFIED BY '${MYSQL_TEST_PASSWORD}';
	GRANT ALL PRIVILEGES ON \`${MYSQL_TEST_DATABASE}\`.* TO '${MYSQL_TEST_USER}'@'%';
	FLUSH PRIVILEGES;
EOSQL
