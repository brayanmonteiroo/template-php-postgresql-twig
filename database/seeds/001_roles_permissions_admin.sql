-- Roles
INSERT INTO roles (name, slug) VALUES
    ('Administrador', 'admin'),
    ('Editor', 'editor'),
    ('Visualizador', 'viewer')
ON CONFLICT (slug) DO NOTHING;

-- Permissions
INSERT INTO permissions (name, slug) VALUES
    ('Visualizar usuários', 'user.view'),
    ('Criar usuários', 'user.create'),
    ('Editar usuários', 'user.edit'),
    ('Excluir usuários', 'user.delete'),
    ('Visualizar dashboard', 'dashboard.view')
ON CONFLICT (slug) DO NOTHING;

-- Admin role: all permissions
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
