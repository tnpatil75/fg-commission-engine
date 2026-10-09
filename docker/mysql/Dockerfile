# MySQL 8.4 LTS with an init script that creates the separate test database and test user.
FROM mysql:8.4

COPY initdb/ /docker-entrypoint-initdb.d/
# Non-executable *.sh files are sourced by the MySQL entrypoint, which makes docker_process_sql available.
RUN sed -i 's/\r$//' /docker-entrypoint-initdb.d/*.sh && chmod 0644 /docker-entrypoint-initdb.d/*.sh
