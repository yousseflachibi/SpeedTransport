# AlloKine Landing Page - Copilot Instructions

## Architecture Overview

This is a Symfony 5 monorepo running in Docker containers for a physiotherapy center management landing page. The application has two main areas:
- **Public landing page** (`/`) - displays services, contact form, and newsletter subscription
- **Admin panel** (`/admin`) - CRUD operations for managing services, centers, and zones (requires authentication)

### Key Architectural Decisions
- **Dockerized multi-service setup**: PHP-FPM 8.0, Nginx, MySQL 5.7, and phpMyAdmin in separate containers
- **Symfony app in monorepo**: Located at `apps/my-symfony-app/` (not project root)
- **Annotation-based routing**: Uses `@Route` annotations via SensioFrameworkExtraBundle (Symfony 5 pattern)
- **Doctrine ORM migrations**: All schema changes tracked in `apps/my-symfony-app/migrations/`

## Development Workflow

### Essential Commands (run from project root)
```bash
# Build and start containers
docker compose build && docker compose up -d

# Install dependencies and run migrations (critical after code changes)
docker compose exec php bash -lc "composer install --no-interaction && php bin/console doctrine:migrations:migrate --no-interaction"

# Generate new migration after entity changes
docker compose exec php bash -lc "php bin/console doctrine:migrations:diff"

# Access PHP container
docker exec -it <container_name> /bin/bash

# View running containers
docker ps
```

### Database Access
- **MySQL**: `localhost:3387` (host port mapping), root/ys1993YS****, database `allo_kine_landing_page_db`
- **phpMyAdmin**: `http://localhost:6081` (container: interface-public-pma)
- **DATABASE_URL** in `.env`: `mysql://root:ys1993YS****@db:3306/allo_kine_landing_page_db?serverVersion=5.7&charset=utf8mb4`
- **Container network**: Services communicate via `my_app` bridge network (PHP connects to `db:3306` internally)

### Admin User
```sql
-- Pre-configured admin account
Email: admin@admin.com
Password: admin123
Role: ROLE_ADMIN
```

## Code Conventions

### Entity Patterns
- Entities in `src/Entity/` use Doctrine annotations (`@ORM\Entity`, `@ORM\Table`)
- Always specify table name explicitly: `@ORM\Table(name="centre_kine")`
- Domain-specific naming: `CentreKine`, `ServiceKine`, `ZoneKine`, `CategorieServiceKine`
- Use getters/setters convention (example: `$entity->getNom()`, `$entity->setNom()`)

### Controller Conventions
- Controllers extend `AbstractController` from Symfony
- Use annotation routing: `@Route("/admin/centre/{id}", name="admin_centre_get", methods={"GET"})`
- Inconsistent naming: `landingPageController` (incorrect casing) vs `AdminController` (correct) - **prefer PascalCase**
- Entity Manager access: `$this->getDoctrine()->getManager()` (Symfony 5 pattern)
- Forms created with `$this->createForm(FormType::class, $entity)`
- JSON responses: Use `new JsonResponse(['key' => 'value'])` for API endpoints
- File uploads: Store in `public/uploads/`, use `move()` method on `UploadedFile` objects

### Routing & URL Patterns
- Public routes: `/`, `/processcontactusform`, `/processsubform`
- Admin routes: `/admin`, `/admin/partial/*`, `/admin/{resource}/{action}`
- Partial rendering for AJAX: Routes like `admin_partial_services_kine` return template fragments
- Success redirects pass query params: `?successMSG=...&successAction=...`

### Form Handling Pattern
```php
$entity = new Entity();
$form = $this->createForm(EntityType::class, $entity);
$form->handleRequest($request);
if ($form->isSubmitted() && $form->isValid()) {
    $entityManager = $this->getDoctrine()->getManager();
    $entity->setDateAction(new DateTime()); // Common pattern
    $entityManager->persist($entity);
    $entityManager->flush();
    return new RedirectResponse($this->generateUrl('route_name', ['params']));
}
```

### Security Configuration
- Form-based authentication configured in `config/packages/security.yaml`
- Login path: `/login`, logout: `/logout`
- Access control: `/admin` requires `ROLE_ADMIN`, `/profile` requires `ROLE_USER`
- User entity implements `UserInterface` with email-based authentication
- Password encoding: `algorithm: auto` (bcrypt in Symfony 5)

## Testing & Debugging

- PHPUnit configured via `phpunit.xml.dist`
- Test files in `tests/` directory
- Web Profiler Bundle available in dev environment
- Check container logs: `docker compose logs php` or `docker compose logs nginx`

## File Organization

- **Controllers**: `src/Controller/` - Handle HTTP requests and responses
- **Entities**: `src/Entity/` - Doctrine ORM models
- **Forms**: `src/Form/` - Form type definitions
- **Templates**: `templates/` - Twig views organized by controller (admin/, landingpage/, security/)
- **Public assets**: `public/css/`, `public/js/`, `public/images/` - Static frontend assets
- **Config**: `config/packages/` - Service configuration per bundle

## Common Gotchas

1. **Always work inside the PHP container** for Symfony console commands (composer, doctrine, etc.)
2. **Migrations must be run after entity changes** - schema won't auto-update
3. **Path context**: Symfony app is in `apps/my-symfony-app/`, not root - adjust paths for docker exec
4. **Annotation routing**: Routes defined in docblocks, not separate YAML files
5. **Legacy Symfony 5 syntax**: Uses older patterns (e.g., `getDoctrine()` instead of dependency injection)
6. **Zone.Identifier files**: Artifacts from Windows file transfers - can be ignored/deleted
7. **Nginx routing**: All requests proxy to `/index.php` via FastCGI on `php:9000` (see `docker/nginx/default.conf`)
8. **Container user permissions**: PHP container runs as `LOCAL_USER` (from env) - ensure file permissions match

## When Adding Features

- Create entities with proper annotations and table names
- Generate migration: `doctrine:migrations:diff`
- Create corresponding Form types in `src/Form/`
- Add controller actions with `@Route` annotations
- Create/update Twig templates in appropriate subdirectory
- For admin features: follow partial rendering pattern for AJAX sections
- File uploads: Save to `public/uploads/` and store relative path in entity

## Quick Troubleshooting

```bash
# Container not starting
docker compose logs php --tail=200
docker compose logs nginx --tail=100

# Database connection issues
docker compose ps  # Check all services running
docker compose exec php bash -lc "php -m | grep -E 'PDO|mysql'"

# Clear Symfony cache
docker compose exec php bash -lc "php bin/console cache:clear"

# Rebuild containers from scratch
docker compose down && docker compose build --no-cache && docker compose up -d
```
