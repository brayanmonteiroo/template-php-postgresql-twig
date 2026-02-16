# Arquitetura do projeto

Este documento descreve a arquitetura do template administrativo PHP + PostgreSQL + Twig: camadas, fluxo de requisição, segurança e deploy.

## Visão geral

A aplicação segue um **MVC enxuto** com front controller, roteamento por FastRoute, camada de **Services** para regras de negócio e **Models** para acesso a dados. Autenticação e autorização (RBAC) são feitas por middlewares aplicados às rotas. A view usa Twig e o layout é baseado no AdminLTE 4.

- **Stack:** PHP 8.4+, PostgreSQL, Twig 3, FastRoute, Phinx (migrations).
- **Padrões:** Front Controller, Dependency Injection (container simples), Repository-style nos Models.

---

## Fluxo da requisição

```
Cliente (navegador)
       |
       v
  Nginx (document root: public/)
       |
       v
  public/index.php  (front controller)
       |
       +-- Carrega .env, config, PDO, Twig
       +-- Inicializa sessão e CSRF
       +-- Monta container (models, services, middlewares)
       |
       v
  Dispatcher (Core\Dispatcher)
       +-- FastRoute: método + URI -> handler
       +-- Handler no formato "Controller@método" ou "auth:Controller@método:permission.slug"
       |
       +-- [Se auth:] AuthMiddleware (redireciona para /login se não autenticado)
       +-- [Se permission:] PermissionMiddleware (403 se sem permissão)
       |
       v
  Controller (ex.: UserController@index)
       +-- Usa Services e Models do container
       +-- Renderiza view Twig ou redireciona (redirect())
       |
       v
  Resposta HTTP (HTML ou redirect)
```

Todas as requisições passam por `public/index.php`. Não há arquivos PHP públicos além desse e dos assets estáticos em `public/assets/`.

---

## Estrutura de diretórios

```
project/
├── config/                 # Configuração (app, database)
├── database/
│   ├── migrations/         # Phinx: schema do banco
│   └── seeds/             # SQL e script PHP de dados iniciais
├── public/                 # Document root
│   ├── index.php          # Front controller (único ponto de entrada)
│   └── assets/            # CSS, JS, imagens (AdminLTE)
├── src/
│   ├── Core/              # Dispatcher (roteamento + middlewares)
│   ├── Controllers/       # Controllers (orquestração)
│   ├── Middleware/        # Auth e Permission
│   ├── Models/            # Acesso a dados (PDO)
│   ├── Services/          # Regras de negócio (Auth, User, Role, Permission)
│   └── Helpers/           # functions.php (redirect, CSRF, flash)
├── views/                  # Templates Twig
│   ├── auth/              # Login
│   ├── partials/           # Header, sidebar, alerts, footer
│   └── ...                 # Por recurso (users, roles, permissions, etc.)
├── routes.php             # Definição das rotas (FastRoute)
├── phinx.php              # Config do Phinx (envs development/production)
├── nginx.template.conf    # Nginx para deploy (Coolify/Nixpacks)
└── docker/                # Docker local (Nginx, PHP-FPM)
```

---

## Camadas

### 1. Front controller (`public/index.php`)

- Carrega autoload, `.env` (Dotenv), `config/app.php` e `config/database.php`.
- Cria a conexão PDO (PostgreSQL).
- Inicializa a sessão (parâmetros seguros: httponly, samesite, secure quando HTTPS).
- Instancia Twig (loader em `views/`, cache em `storage/cache/twig` quando não debug).
- Instancia Models (User, Role, Permission) e Services (AuthService, PermissionService, UserService, RoleService).
- Gera ou reutiliza o token CSRF em sessão.
- Expõe no Twig: `user`, `permissions`, `csrf_token`, `flash_messages` e a função `build_query`.
- Monta o **container** (array) com config, PDO, Twig, services, models e middlewares.
- Cria o `Dispatcher` com as rotas (`routes.php`) e o container, e chama `$dispatcher->handle($method, $uri)`.

O container é um array associativo; não há container PSR. Os Controllers recebem esse array no construtor e usam as chaves necessárias (twig, authService, userService, etc.).

### 2. Roteamento (`routes.php` + `Core\Dispatcher`)

- **FastRoute** resolve `(método, URI)` para um handler em string.
- Formato do handler:
  - `Controller@método` – rota pública (ex.: `AuthController@showLogin`).
  - `auth:Controller@método` – exige usuário autenticado (AuthMiddleware).
  - `auth:Controller@método:permission.slug` – exige autenticação e a permissão indicada (PermissionMiddleware).

Exemplo: `auth:UserController@index:user.view` → só segue se estiver logado e com permissão `user.view`.

O **Dispatcher**:
1. Chama `$fastRoute->dispatch($method, $uri)`.
2. Se NOT_FOUND → 404; se METHOD_NOT_ALLOWED → 405.
3. Se FOUND, interpreta o handler (prefixo `auth:`, sufixo `:permission`), aplica Auth e depois Permission se definidos.
4. Instancia o controller (`App\Controllers\<Controller>`) com o container e chama o método com os argumentos da rota (ex.: `{id}`).

### 3. Middlewares

- **AuthMiddleware:** verifica se `authService->check()` (sessão com usuário). Se não, redireciona para `/login` e interrompe.
- **PermissionMiddleware:** verifica se `permissionService->hasPermission($permission)`. Se não, responde 403 e interrompe.

Ambos recebem o container (e o nome da permissão no caso do Permission). Não há pipeline genérico; o Dispatcher aplica apenas esses dois, conforme anotação na rota.

### 4. Controllers

- Vivem em `App\Controllers\*`.
- Recebem o container no construtor.
- Responsabilidade: ler entrada (GET/POST), chamar Services (e eventualmente Models), definir flash, renderizar view ou redirecionar.
- Não contêm regras de negócio pesadas; essas ficam nos Services.
- Exemplos: `AuthController` (login/logout), `UserController`, `RoleController`, `PermissionController`, `ProfileController`, `DashboardController`.

### 5. Services

- Vivem em `App\Services\*`.
- Encapsulam regras de negócio e orquestram Models quando necessário.
  - **AuthService:** login (verificação de senha, sessão), logout, usuário atual (`user()`, `check()`).
  - **PermissionService:** permissões do usuário logado (`getUserPermissions()`, `hasPermission()`), baseado em User + roles + permissions.
  - **UserService:** CRUD de usuários, atribuição de roles, validações.
  - **RoleService:** CRUD de papéis e vínculo com permissions.

Os Controllers usam os Services; os Services usam os Models. Não há injeção automática; as dependências são passadas no construtor e registradas no container em `index.php`.

### 6. Models

- Vivem em `App\Models\*`.
- Recebem apenas PDO no construtor.
- Abstraem o acesso ao banco: queries preparadas, fetch em array associativo.
- Não expõem entidades “ricas”; retornam arrays. Ex.: `User::findByEmail`, `User::listPaginated`, `Role::all`, `Permission::all`, etc.

Padrão próximo a Repository (um “repositório” por entidade principal), sem ORM.

### 7. Views (Twig)

- Todas em `views/`, extensão `.twig`.
- Layout principal: `layout.twig` (AdminLTE 4), com blocos `title`, `page_title`, `breadcrumb`, `content`.
- Partials: `partials/header.twig`, `sidebar.twig`, `alerts.twig`, `footer.twig`.
- Variáveis globais (definidas no front controller): `user`, `permissions`, `csrf_token`, `flash_messages`.
- Função Twig: `build_query` (para montar query strings em links de listagem).
- Escape automático (autoescape html). Formulários protegidos com `csrf_token` em hidden.

---

## Banco de dados

- **SGBD:** PostgreSQL.
- **Acesso:** PDO (config em `config/database.php`; credenciais via `.env`: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD).
- **Migrations:** Phinx em `database/migrations/`. Ambiente definido em `phinx.php` (development/production) usando as mesmas variáveis de ambiente.

### Schema principal (RBAC)

- **users** – id, name, email, password_hash, status, created_at, updated_at.
- **roles** – id, name, slug (ex.: admin, editor, viewer).
- **permissions** – id, name, slug (ex.: user.view, user.create, role.edit).
- **user_role** – user_id, role_id (N:N usuário–papel).
- **role_permission** – role_id, permission_id (N:N papel–permissão).

Um usuário tem uma ou mais roles; cada role tem várias permissions. O PermissionService resolve as permissões do usuário logado via joins (user → user_role → role → role_permission → permission).

### Seeds

- `database/seeds/001_roles_permissions_admin.sql` – insere roles, permissions e associações iniciais (incluindo admin).
- `database/seed_admin.php` – script PHP que cria o usuário admin (email/senha padrão) usando o mesmo PDO/config da aplicação. Deve ser executado após o seed SQL (ver README e “Deploy na VPS”).

---

## Segurança

- **Sessão:** cookie com httponly, samesite=Lax, secure em HTTPS; regeneração de ID após login.
- **CSRF:** token em sessão; todo POST (login, logout, formulários) deve enviar `csrf_token` (campo ou header). Validação em `validate_csrf()` (helpers); falha → 403.
- **Senha:** armazenada com `password_hash()` (bcrypt); verificação com `password_verify()` no AuthService.
- **Autorização:** após autenticação, cada rota protegida pode exigir uma permissão (slug). PermissionMiddleware consulta PermissionService e retorna 403 se o usuário não tiver a permissão.
- **Saída:** Twig com autoescape; não há `{!! ... !!}` com conteúdo arbitrário do usuário.

---

## Front-end e assets

- **Layout e componentes:** AdminLTE 4 (Bootstrap 5, Bootstrap Icons, OverlayScrollbars, Source Sans 3). Arquivos em `public/assets/adminlte/`.
- **Páginas:** Twig que estendem `layout.twig` e preenchem os blocos. Listagens com tabelas, filtros e paginação; formulários com botões de ação.
- **Comportamento:** Bootstrap e AdminLTE JS; na tela de login, um pequeno script inline para “Credenciais de demonstração” (mostrar/ocultar senha). Sem framework JS pesado; interações simples.

---

## Configuração e ambiente

- **Variáveis de ambiente (`.env`):**
  - App: APP_ENV, APP_DEBUG, APP_URL, SESSION_LIFETIME.
  - Banco: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD.
- **Config:** `config/app.php` (env, debug, url, session_lifetime). `config/database.php` monta o DSN e retorna o PDO.
- **Ambientes:** development (Docker local, DB_NAME pode ser template_admin) e production (Coolify, DB_NAME frequentemente postgres). Phinx e aplicação usam o mesmo .env do ambiente.

---

## Deploy

### Local (Docker)

- `docker-compose.yml` sobe serviços: app (PHP-FPM), nginx, postgres.
- Document root do Nginx: `public/` (mapeado no volume). Config do Nginx em `docker/nginx/app.conf`.
- Aplicação acessível em http://localhost:8080. Migrations e seeds conforme README (exec no container app/postgres).

### VPS (Coolify / Nixpacks)

- Build via Nixpacks (provider PHP): detecta `composer.json` e/ou `index.php`, instala dependências, usa `nginx.template.conf` da raiz do projeto se existir.
- O `nginx.template.conf` na raiz define document root `/app/public`, `try_files` para o front controller e usa `${PORT}` e `$!{nginx}` para o script de start do Nixpacks.
- Container único: Nginx + PHP-FPM; o proxy do Coolify encaminha para a PORT do container.
- Migrations: executadas no pós-deploy (comando configurado no Coolify). Seed SQL e `php database/seed_admin.php` devem ser rodados manualmente (ver README, seção “Deploy na VPS (Coolify)”).

O arquivo `docker/nginx/app.conf` é apenas para o ambiente Docker local; na VPS vale o `nginx.template.conf` da raiz.

---

## Resumo dos componentes por camada

| Camada           | Responsabilidade                                   |
|------------------|----------------------------------------------------|
| Front controller | Bootstrap, container, dispatcher                   |
| Dispatcher       | Rota → Auth → Permission → Controller              |
| Controller       | Entrada, chamada a Service/Model, view ou redirect |
| Service          | Regras de negócio, orquestração de Models          |
| Model            | Acesso a dados (PDO), retorno em array             |
| Middleware       | Auth (sessão), Permission (RBAC)                   |
| View (Twig)      | Renderização HTML com layout e partials            |

Este documento reflete o estado do projeto e deve ser atualizado quando a arquitetura for alterada.
