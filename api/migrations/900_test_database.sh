#!/bin/bash
# Banco espelho para a suite de testes. `composer test` trunca tabelas entre os casos, e isso
# nao pode tocar os dados da aplicacao. Roda por ultimo, depois das migrations numeradas.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres \
     -c "CREATE DATABASE ${POSTGRES_DB}_test"

for migration in /docker-entrypoint-initdb.d/[0-9][0-9][0-9]_*.sql; do
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "${POSTGRES_DB}_test" -f "$migration"
done
