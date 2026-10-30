# Template administrativo PHP + PostgreSQL + Twig

Template com login, RBAC (roles e permissões), MVC + Services, FastRoute, Twig e UI **Bootstrap 6** (shell “Precision Console”, light/dark). Ambiente de execução: **Docker** (Apache + PHP 8.5 + PostgreSQL), alinhado à [guia oficial Docker para PHP](https://docs.docker.com/guides/php/).

## Requisitos

- Docker e Docker Compose

## Versões pinadas (segurança)

| Componente | Imagem / requisito |
|------------|--------------------|
| PHP + Apache | `php:8.5.12-apache` (patch para CVE-2026-109618 / CVE-2026-109619) |
| PostgreSQL | `postgres:18.6-alpine` |
| Composer (build) | `composer:2` |
| App | `php >= 8.5` no `composer.json` |

Após novos advisories do PHP, atualize a tag no `Dockerfile`, reconstrua a imagem e faça redeploy na VPS. Evite tags flutuantes (`8.5-apache`, `latest`).

## Instalação e execução (local)

```bash
cp db/password.example.txt db/password.txt
# Edite db/password.txt com uma senha forte (uma linha, sem linhas extras).

docker compose -f compose.yaml -f compose.dev.yaml up --build -d
```

O Composer roda **dentro do build** da imagem (não é necessário `composer install` no host).

Acesso: http://localhost:8080

Para sincronizar alterações de código automaticamente (Compose Watch):

```bash
docker compose -f compose.yaml -f compose.dev.yaml watch
```

A variável **APP_KEY** no `.env` (se usar) está reservada para uso futuro; pode ficar vazia. Em Docker, as variáveis de ambiente vêm do Compose; a senha do banco vem do secret `db/password.txt`.

**Antes do primeiro login**, rode as migrations e o seed (seção abaixo).

## Banco de dados

Migrations com **Phinx**. O banco `template_admin` é criado pelo serviço Postgres do Compose.

Com os containers no ar:

```bash
docker compose -f compose.yaml -f compose.dev.yaml exec server vendor/bin/phinx migrate -e development
docker compose -f compose.yaml -f compose.dev.yaml exec -T db psql -U postgres -d template_admin < database/seeds/001_roles_permissions_admin.sql
docker compose -f compose.yaml -f compose.dev.yaml exec server php database/seed_admin.php
```

Usuário inicial: `admin@example.com` / `admin123`

**Comandos Phinx úteis:**

```bash
docker compose -f compose.yaml -f compose.dev.yaml exec server vendor/bin/phinx migrate -e development
docker compose -f compose.yaml -f compose.dev.yaml exec server vendor/bin/phinx rollback -e development
docker compose -f compose.yaml -f compose.dev.yaml exec server vendor/bin/phinx status -e development
docker compose -f compose.yaml -f compose.dev.yaml exec server vendor/bin/phinx create NomeDaMigration
```

## Produção (VPS)

```bash
cp db/password.example.txt db/password.txt
# Defina uma senha forte em db/password.txt

export APP_URL=https://seu-dominio.com
docker compose -f compose.yaml -f compose.prod.yaml up --build -d
```

- Imagem alvo: estágio `final` (dependências sem `--dev`, `php.ini-production`, usuário `www-data`).
- Postgres **não** publica porta no host (só rede interna do Compose).
- TLS fica no reverse proxy da VPS (Caddy, Traefik, Nginx do host, etc.).

Após o primeiro deploy, rode migrate + seeds **uma vez** (ajuste o ambiente Phinx se usar `production`):

```bash
docker compose -f compose.yaml -f compose.prod.yaml exec server vendor/bin/phinx migrate -e production
docker compose -f compose.yaml -f compose.prod.yaml exec -T db psql -U postgres -d template_admin < database/seeds/001_roles_permissions_admin.sql
docker compose -f compose.yaml -f compose.prod.yaml exec server php database/seed_admin.php
```

## Estrutura

- `Dockerfile` – multi-stage (prod-deps, dev-deps, base, development, test, final)
- `compose.yaml` / `compose.dev.yaml` / `compose.prod.yaml` – stack Docker
- `docker/apache/` – VirtualHost com document root em `public/`
- `db/password.example.txt` – modelo do secret do Postgres (copie para `db/password.txt`)
- `public/` – ponto de entrada (`index.php`) e assets
- `src/` – Controllers, Models, Services, Middleware, Core
- `views/` – templates Twig
- `config/` – app e database
- `database/migrations/` – Phinx; `database/seeds/` – SQL / script admin
- `tests/` – PHPUnit

## Rotas

- GET `/login` – formulário de login
- POST `/login` – autenticar
- POST `/logout` – sair
- GET `/`, `/dashboard` – dashboard (autenticado)
- GET `/profile`, POST `/profile` – editar próprio perfil (nome, email, senha)
- GET/POST `/users` – listar / criar (`user.view` / `user.create`)
- GET `/users/{id}/edit`, POST `/users/{id}` – editar (`user.edit`)
- POST `/users/{id}/delete` – excluir (`user.delete`)
- GET/POST `/perfis` – listar/criar perfis RBAC (`role.view` / `role.create`); permissões se editam no formulário do perfil
- GET `/perfis/{id}/edit`, POST `/perfis/{id}`, POST `/perfis/{id}/delete` – editar/excluir (`role.edit` / `role.delete`)
- Aliases legados: `/roles`, `/permissions` → mesmo fluxo de perfis

## Front-end (Bootstrap 6)

UI própria (**Precision Console**) sobre **Bootstrap `6.0.0-alpha.1`**, Bootstrap Icons e tipografia IBM Plex (Sans/Mono), tudo vendido em `public/assets/` (sem CDN):

| Pasta | Conteúdo |
|-------|----------|
| `public/assets/bootstrap/` | CSS/JS do Bootstrap 6 (JS ESM — `type="module"`) |
| `public/assets/bootstrap-icons/` | Ícones |
| `public/assets/app/` | `app.css` (tokens, shell, login) + `app.js` (tema + nav ativa) |
| `public/assets/brand/` | Logo e favicons |

Light/dark via `data-bs-theme` + toggle persistido. Sidebar à esquerda; drawer no mobile.

Skills oficiais do Bootstrap (AI): `.agents/skills/bootstrap-*` (também em `.cursor/skills/` via symlink). Ver [Using Bootstrap with AI](https://getbootstrap.com/docs/6.0/getting-started/ai/).

## Testes

Os testes usam **PHPUnit** (`tests/Unit`, `tests/Feature`). Não dependem de banco nem de `.env`.

**Com Docker (desenvolvimento):**

```bash
docker compose -f compose.yaml -f compose.dev.yaml run --build --rm server ./vendor/bin/phpunit
```

**No build da imagem (estágio `test`, como na guia Docker):**

```bash
docker build -t template-admin-test --progress=plain --no-cache --target test .
```

**Suítes / arquivos:**

```bash
docker compose -f compose.yaml -f compose.dev.yaml run --rm server ./vendor/bin/phpunit --testsuite Unit
docker compose -f compose.yaml -f compose.dev.yaml run --rm server ./vendor/bin/phpunit --testsuite Feature
docker compose -f compose.yaml -f compose.dev.yaml run --rm server ./vendor/bin/phpunit tests/Unit/Services/UserServiceTest.php
```

Configuração em `phpunit.xml`.
