#!/usr/bin/env bash
# Roda a suíte duas vezes: SQLite (rápido) e Postgres descartável (o banco de
# produção — o índice único e o tipo das colunas valem lá). Precisa só de Docker.
set -euo pipefail
cd "$(dirname "$0")/.."
U="$(id -u):$(id -g)"

# A sail-8.4 tem pdo_pgsql e o composer.
docker run --rm -u "$U" -e COMPOSER_HOME=/tmp/c -v "$PWD":/app -w /app --entrypoint sh "${IMAGEM_PHP:-sail-8.4/app:latest}" \
  -c '[ -d vendor ] || composer install -q --no-interaction; vendor/bin/phpunit'

docker network create visitas-teste >/dev/null
trap 'docker rm -f visitas-pg >/dev/null 2>&1; docker network rm visitas-teste >/dev/null 2>&1' EXIT
docker run -d --rm --name visitas-pg --network visitas-teste \
  -e POSTGRES_PASSWORD=teste -e POSTGRES_DB=visitas postgres:16-alpine >/dev/null
until docker exec visitas-pg pg_isready -U postgres -q; do sleep 1; done

# Imagem com pdo_pgsql e PHP 8.4 (o de produção).
docker run --rm --network visitas-teste -u "$U" -e DB_HOST=visitas-pg -e DB_PASSWORD=teste \
  -v "$PWD":/app -w /app --entrypoint php "${IMAGEM_PHP:-sail-8.4/app:latest}" \
  vendor/bin/phpunit -c tests/phpunit.pgsql.xml
