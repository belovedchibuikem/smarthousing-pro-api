-- Fix the permissions system by creating tables and seeding data
-- Run this SQL script directly in your database

-- 1. Create permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    description TEXT NULL,
    `group` VARCHAR(100) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_permissions_slug (slug),
    INDEX idx_permissions_group (`group`),
    INDEX idx_permissions_is_active (is_active)
);

-- 2. Create role_permissions pivot table
CREATE TABLE IF NOT EXISTS role_permissions (
    id CHAR(36) PRIMARY KEY,
    role_id CHAR(36) NOT NULL,
    permission_id CHAR(36) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    UNIQUE KEY unique_role_permission (role_id, permission_id),
    INDEX idx_role_permissions_role_id (role_id),
    INDEX idx_role_permissions_permission_id (permission_id),
    
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- 3. Insert permissions
INSERT INTO permissions (id, name, slug, description, `group`, is_active, created_at, updated_at) VALUES
(UUID(), 'View Businesses', 'businesses.view', 'View all businesses', 'businesses', TRUE, NOW(), NOW()),
(UUID(), 'Create Businesses', 'businesses.create', 'Create new businesses', 'businesses', TRUE, NOW(), NOW()),
(UUID(), 'Edit Businesses', 'businesses.edit', 'Edit existing businesses', 'businesses', TRUE, NOW(), NOW()),
(UUID(), 'Delete Businesses', 'businesses.delete', 'Delete businesses', 'businesses', TRUE, NOW(), NOW()),
(UUID(), 'Suspend Businesses', 'businesses.suspend', 'Suspend businesses', 'businesses', TRUE, NOW(), NOW()),
(UUID(), 'Activate Businesses', 'businesses.activate', 'Activate businesses', 'businesses', TRUE, NOW(), NOW()),
(UUID(), 'View Super Admins', 'super_admins.view', 'View super admin users', 'users', TRUE, NOW(), NOW()),
(UUID(), 'Create Super Admins', 'super_admins.create', 'Create super admin users', 'users', TRUE, NOW(), NOW()),
(UUID(), 'Edit Super Admins', 'super_admins.edit', 'Edit super admin users', 'users', TRUE, NOW(), NOW()),
(UUID(), 'Delete Super Admins', 'super_admins.delete', 'Delete super admin users', 'users', TRUE, NOW(), NOW()),
(UUID(), 'View Roles', 'roles.view', 'View roles and permissions', 'roles', TRUE, NOW(), NOW()),
(UUID(), 'Create Roles', 'roles.create', 'Create new roles', 'roles', TRUE, NOW(), NOW()),
(UUID(), 'Edit Roles', 'roles.edit', 'Edit existing roles', 'roles', TRUE, NOW(), NOW()),
(UUID(), 'Delete Roles', 'roles.delete', 'Delete roles', 'roles', TRUE, NOW(), NOW()),
(UUID(), 'View Packages', 'packages.view', 'View subscription packages', 'packages', TRUE, NOW(), NOW()),
(UUID(), 'Create Packages', 'packages.create', 'Create new packages', 'packages', TRUE, NOW(), NOW()),
(UUID(), 'Edit Packages', 'packages.edit', 'Edit existing packages', 'packages', TRUE, NOW(), NOW()),
(UUID(), 'Delete Packages', 'packages.delete', 'Delete packages', 'packages', TRUE, NOW(), NOW()),
(UUID(), 'View Modules', 'modules.view', 'View system modules', 'modules', TRUE, NOW(), NOW()),
(UUID(), 'Create Modules', 'modules.create', 'Create new modules', 'modules', TRUE, NOW(), NOW()),
(UUID(), 'Edit Modules', 'modules.edit', 'Edit existing modules', 'modules', TRUE, NOW(), NOW()),
(UUID(), 'Delete Modules', 'modules.delete', 'Delete modules', 'modules', TRUE, NOW(), NOW()),
(UUID(), 'View Analytics', 'analytics.view', 'View system analytics', 'analytics', TRUE, NOW(), NOW()),
(UUID(), 'View Reports', 'reports.view', 'View system reports', 'reports', TRUE, NOW(), NOW()),
(UUID(), 'View Settings', 'settings.view', 'View system settings', 'settings', TRUE, NOW(), NOW()),
(UUID(), 'Edit Settings', 'settings.edit', 'Edit system settings', 'settings', TRUE, NOW(), NOW());

-- 4. Assign permissions to roles
-- Get role IDs
SET @super_admin_role_id = (SELECT id FROM roles WHERE name = 'Super Admin' LIMIT 1);
SET @business_manager_role_id = (SELECT id FROM roles WHERE name = 'Business Manager' LIMIT 1);
SET @support_agent_role_id = (SELECT id FROM roles WHERE name = 'Support Agent' LIMIT 1);

-- Assign all permissions to Super Admin role
INSERT INTO role_permissions (id, role_id, permission_id, created_at, updated_at)
SELECT UUID(), @super_admin_role_id, id, NOW(), NOW()
FROM permissions
WHERE @super_admin_role_id IS NOT NULL;

-- Assign business and user management permissions to Business Manager role
INSERT INTO role_permissions (id, role_id, permission_id, created_at, updated_at)
SELECT UUID(), @business_manager_role_id, id, NOW(), NOW()
FROM permissions
WHERE `group` IN ('businesses', 'users', 'analytics', 'reports')
AND @business_manager_role_id IS NOT NULL;

-- Assign limited permissions to Support Agent role
INSERT INTO role_permissions (id, role_id, permission_id, created_at, updated_at)
SELECT UUID(), @support_agent_role_id, id, NOW(), NOW()
FROM permissions
WHERE slug IN ('businesses.view', 'super_admins.view', 'analytics.view', 'reports.view')
AND @support_agent_role_id IS NOT NULL;

-- 5. Assign roles to existing super admins
-- Get super admin IDs
SET @first_admin_id = (SELECT id FROM super_admins LIMIT 1);
SET @second_admin_id = (SELECT id FROM super_admins LIMIT 1 OFFSET 1);

-- Assign Super Admin role to first admin
INSERT INTO user_roles (id, user_id, role_id, user_type, created_at, updated_at)
SELECT UUID(), @first_admin_id, @super_admin_role_id, 'App\\Models\\Central\\SuperAdmin', NOW(), NOW()
WHERE @first_admin_id IS NOT NULL AND @super_admin_role_id IS NOT NULL;

-- Assign Business Manager role to second admin
INSERT INTO user_roles (id, user_id, role_id, user_type, created_at, updated_at)
SELECT UUID(), @second_admin_id, @business_manager_role_id, 'App\\Models\\Central\\SuperAdmin', NOW(), NOW()
WHERE @second_admin_id IS NOT NULL AND @business_manager_role_id IS NOT NULL;

-- 6. Verify the setup
SELECT 'Setup completed successfully!' as status;

SELECT 
    r.name as role_name,
    COUNT(DISTINCT ur.user_id) as user_count,
    COUNT(DISTINCT rp.permission_id) as permission_count
FROM roles r
LEFT JOIN user_roles ur ON r.id = ur.role_id
LEFT JOIN role_permissions rp ON r.id = rp.role_id
GROUP BY r.id, r.name
ORDER BY r.name;


