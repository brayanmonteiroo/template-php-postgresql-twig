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

Migrations são gerenciadas pelo **Phinx**. O banco já é criado pelo Docker Compose.

Com os containers no ar, rode as migrations e o seed:

```bash
docker compose exec app vendor/bin/phinx migrate -e development
docker compose exec -T postgres psql -U postgres -d template_admin < database/seeds/001_roles_permissions_admin.sql
docker compose exec app php database/seed_admin.php
```

Usuário inicial: `admin@example.com` / `admin123`

**Comandos Phinx úteis** (rodar com `docker compose exec app vendor/bin/phinx ...` ou, fora do Docker, `vendor/bin/phinx ...` com `.env` configurado):

- `phinx migrate -e development` – aplica migrations pendentes
- `phinx rollback -e development` – desfaz a última migration
- `phinx status -e development` – lista migrations e status
- `phinx create NomeDaMigration` – gera nova migration em `database/migrations/`

Se o projeto já estava instalado antes de incluir as telas de Papéis e Permissões, rode novamente o seed para criar as permissões `role.*` e `permission.*` e atribuí-las ao admin:

```bash
docker compose exec -T postgres psql -U postgres -d template_admin < database/seeds/001_roles_permissions_admin.sql
```

## Acesso

http://localhost:8080

## Estrutura

- `docker/` – config do servidor (Nginx, PHP-FPM)
- `public/` – ponto de entrada (`index.php`) e assets
- `src/Controllers/` – controllers
- `src/Models/` – acesso a dados
- `src/Services/` – regras de negócio (Auth, Permission, User, Role)
- `src/Middleware/` – auth e permissão
- `src/Core/` – dispatcher (FastRoute + middlewares)
- `config/` – app e database
- `views/` – templates Twig
- `database/migrations/` – migrations Phinx (PHP); `database/seeds/` – SQL de dados iniciais
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
- GET `/profile`, POST `/profile` – editar próprio perfil (nome, email, senha)
- GET/POST `/users` – listar (com paginação, busca e ordenação) / criar (permissão `user.view` / `user.create`)
- GET `/users/{id}/edit`, POST `/users/{id}` – editar (`user.edit`)
- POST `/users/{id}/delete` – excluir (`user.delete`); não é possível excluir a si mesmo nem o último admin
- GET/POST `/roles` – listar/criar papéis (`role.view` / `role.create`)
- GET `/roles/{id}/edit`, POST `/roles/{id}`, POST `/roles/{id}/delete` – editar/excluir papéis (`role.edit` / `role.delete`)
- GET `/permissions`, POST `/permissions` – visualizar e gerenciar permissões por papel (`permission.view` / `permission.manage`)

## Front-end (AdminLTE 4)

A base do front está em `public/assets/adminlte/` (AdminLTE 4, Bootstrap 5, Bootstrap Icons, OverlayScrollbars, Source Sans 3). Tudo é servido localmente, sem CDN. O sistema é construído em cima dessa pasta; a pasta original do AdminLTE (`AdminLTE-4.0.0-rc4`) pode ser removida após a configuração inicial.

## Deploy na VPS (Coolify)

As migrations são executadas automaticamente no pós-deploy (comando configurado no Coolify). Após o primeiro deploy, é preciso rodar o seed de roles/permissões e o script do usuário admin **uma vez**:

1. **Seed SQL (roles e permissões)** – O arquivo `database/seeds/001_roles_permissions_admin.sql` existe apenas no container da **aplicação** (não no container do PostgreSQL). Na VPS, pelo terminal do **host** (SSH), use o nome completo dos containers (`docker ps --format '{{.Names}}'`) e o nome do banco definido em **DB_NAME** no Coolify (geralmente `postgres`):

   ```bash
   docker exec <CONTAINER_APP> cat /app/database/seeds/001_roles_permissions_admin.sql | docker exec -i <CONTAINER_POSTGRES> psql -U postgres -d <DB_NAME>
   ```

   Exemplo (substitua pelos nomes reais que aparecem no `docker ps`):

   ```bash
   docker exec a7c08owow0h0k830o0c8s874-111111111 cat /app/database/seeds/001_roles_permissions_admin.sql | docker exec -i r0wswgw0ss4okk8oggw0csks psql -U postgres -d postgres
   ```
   Os valores acima não são reais, apenas exemplos.

2. **Usuário admin** – No Coolify, abra o **Terminal** da aplicação (conectado ao container da app, onde existe `/app`). Rode:

   ```bash
   php database/seed_admin.php
   ```

   Usuário inicial: `admin@example.com` / `admin123`

---

**Observação:** O arquivo `nginx.template.conf` na raiz do projeto é usado apenas em **hospedagem** (Coolify/Nixpacks, VPS etc.). O Nixpacks o utiliza no deploy para configurar o Nginx com document root em `public` e fallback para o front controller. Em desenvolvimento local, o Nginx usado é o de `docker/nginx/app.conf`.
