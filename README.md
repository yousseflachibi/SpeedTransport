# AlloKineLandingPage


docker compose exec php bash -lc "composer install --no-interaction || true && php bin/console doctrine:migrations:diff --no-interaction || true && php bin/console doctrine:migrations:migrate --no-interaction || true"
docker compose build
docker ps
docker-compose up -d
docker exec -it <container name> /bin/bash.

docker compose exec php bash -lc "composer install --no-interaction || true && php bin/console doctrine:migrations:diff --no-interaction || true && php bin/console doctrine:migrations:migrate --no-interaction || true"

Page de login : https://legendary-orbit-vpj797xr4qgf6q46-80.app.github.dev/login
Page d'accueil : https://legendary-orbit-vpj797xr4qgf6q46-80.app.github.dev/
Espace admin : https://legendary-orbit-vpj797xr4qgf6q46-80.app.github.dev/admin
