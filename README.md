# AlloKineLandingPage


docker compose exec php bash -lc "composer install --no-interaction || true && php bin/console doctrine:migrations:diff --no-interaction || true && php bin/console doctrine:migrations:migrate --no-interaction || true"
docker compose build
docker ps
docker-compose up -d
docker exec -it <container name> /bin/bash.

docker compose exec php bash -lc "composer install --no-interaction || true && php bin/console doctrine:migrations:diff --no-interaction || true && php bin/console doctrine:migrations:migrate --no-interaction || true"
