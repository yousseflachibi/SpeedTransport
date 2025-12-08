# AlloKineLandingPage


docker compose exec php bash -lc "composer install --no-interaction || true && php bin/console doctrine:migrations:diff --no-interaction || true && php bin/console doctrine:migrations:migrate --no-interaction || true"
docker compose build
docker ps
docker-compose up -d
docker exec -it <container name> /bin/bash.

docker compose exec php bash -lc "composer install --no-interaction || true && php bin/console doctrine:migrations:diff --no-interaction || true && php bin/console doctrine:migrations:migrate --no-interaction || true"


Utilisateur : root
Mot de passe : ys1993YS****
DATABASE_URL="mysql://root:ys1993YS****@db:3306/allo_kine_landing_page_db?serverVersion=5.7&charset=utf8mb4"

INSERT INTO user (email, roles, password)
VALUES (
  'admin@admin.com',
  '["ROLE_ADMIN"]',
  '$2y$13$POaUP0/hrmqnfnuJeBrOke7O1Gphbt2elrJ.unVBk.ehXMbMaFw3.'
);
Email : admin@admin.com
Mot de passe : admin123