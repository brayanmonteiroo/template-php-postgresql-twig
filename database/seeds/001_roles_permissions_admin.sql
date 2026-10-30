-- Roles
INSERT INTO roles (name, slug) VALUES
    ('Administrador', 'admin'),
    ('Editor', 'editor'),
    ('Visualizador', 'viewer')
ON CONFLICT (slug) DO NOTHING;

-- Permissions (ordem lógica alinhada à sidebar: Painel → Usuários → Perfis)
INSERT INTO permissions (name, slug) VALUES
    ('Visualizar painel', 'dashboard.view'),
    ('Visualizar usuários', 'user.view'),
    ('Criar usuários', 'user.create'),
    ('Editar usuários', 'user.edit'),
    ('Excluir usuários', 'user.delete'),
    ('Visualizar perfis', 'role.view'),
    ('Criar perfis', 'role.create'),
    ('Editar perfis', 'role.edit'),
    ('Excluir perfis', 'role.delete'),
    ('Visualizar permissões', 'permission.view'),
    ('Gerenciar permissões', 'permission.manage')
ON CONFLICT (slug) DO NOTHING;

-- Atualiza nomes em bases já seedadas (papéis → perfis)
UPDATE permissions SET name = 'Visualizar painel' WHERE slug = 'dashboard.view';
UPDATE permissions SET name = 'Visualizar perfis' WHERE slug = 'role.view';
UPDATE permissions SET name = 'Criar perfis' WHERE slug = 'role.create';
UPDATE permissions SET name = 'Editar perfis' WHERE slug = 'role.edit';
UPDATE permissions SET name = 'Excluir perfis' WHERE slug = 'role.delete';

-- Admin role: all permissions (incl. role.* and permission.*)
INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.slug = 'admin'
ON CONFLICT DO NOTHING;

-- Editor: view, create, edit (no delete)
INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'editor' AND p.slug IN ('user.view', 'user.create', 'user.edit', 'dashboard.view')
ON CONFLICT DO NOTHING;

-- Viewer: view only
INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'viewer' AND p.slug IN ('user.view', 'dashboard.view')
ON CONFLICT DO NOTHING;

-- Admin user and user_role are created by: php database/seed_admin.php
