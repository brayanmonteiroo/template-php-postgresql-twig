# Template administrativo PHP + PostgreSQL + Twig

Template com login, RBAC (roles e permissões), MVC + Services, FastRoute, Twig e AdminLTE. Ambiente de execução: **Docker** (Nginx + PHP-FPM + PostgreSQL).

## Requisitos

- Docker e Docker Compose

## Instalação e execução

```bash
cp .env.example .env
composer install
docker compose up -d
```

O `.env.example` já está configurado para o Docker (DB_HOST=postgres, etc.). A base de front (AdminLTE 4) já está em `public/assets/adminlte/`; não é preciso rodar nenhum script de cópia.

**Antes do primeiro login**, é obrigatório rodar as migrations e o seed (veja seção "Banco de dados" abaixo).

## Banco de dados

Com os containers no ar, rode as migrations e o seed (o banco já é criado pelo compose):

```bash
docker compose exec -T postgres psql -U postgres -d template_admin < database/migrations/001_create_tables.sql
docker compose exec -T postgres psql -U postgres -d template_admin < database/seeds/001_roles_permissions_admin.sql
docker compose exec app php database/seed_admin.php
```

Usuário inicial: `admin@example.com` / `admin123`

## Acesso

http://localhost:8080

## Estrutura

- `docker/` – config do servidor (Nginx, PHP-FPM)
- `public/` – ponto de entrada (`index.php`) e assets
- `src/Controllers/` – controllers
- `src/Models/` – acesso a dados
- `src/Services/` – regras de negócio (Auth, Permission, User)
- `src/Middleware/` – auth e permissão
- `src/Core/` – dispatcher (FastRoute + middlewares)
- `config/` – app e database
- `views/` – templates Twig
- `database/migrations/` e `database/seeds/` – SQL
- `tests/` – PHPUnit

## Testes

```bash
./vendor/bin/phpunit
```

## Rotas

- GET `/login` – formulário de login
- POST `/login` – autenticar
- POST `/logout` – sair
- GET `/`, `/dashboard` – dashboard (autenticado)
- GET/POST `/users` – listar/criar (permissão `user.view` / `user.create`)
- GET `/users/{id}/edit`, POST `/users/{id}` – editar (`user.edit`)
- POST `/users/{id}/delete` – excluir (`user.delete`)

## Front-end (AdminLTE 4)

A base do front está em `public/assets/adminlte/` (AdminLTE 4, Bootstrap 5, Bootstrap Icons, OverlayScrollbars, Source Sans 3). Tudo é servido localmente, sem CDN. O sistema é construído em cima dessa pasta; a pasta original do AdminLTE (`AdminLTE-4.0.0-rc4`) pode ser removida após a configuração inicial.
