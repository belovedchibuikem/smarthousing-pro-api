-- Insert test role assignments directly into user_roles table
-- This will assign the first super admin to "Super Admin" role
-- and the second super admin to "Business Manager" role

INSERT INTO user_roles (id, user_id, role_id, user_type, created_at, updated_at) VALUES
(
    UUID(),
    'a02b10ae-7f5e-4c35-a77f-a8c5f446eca0', -- First super admin
    'a02d039f-d143-455e-97ad-6db6a9758baa', -- Super Admin role
    'App\\Models\\Central\\SuperAdmin',
    NOW(),
    NOW()
),
(
    UUID(),
    'a02cfa9b-5970-438d-8fd9-3ad1c9d50096', -- Second super admin
    'a02d0461-2d55-4f26-870e-2aaef2a19b65', -- Business Manager role
    'App\\Models\\Central\\SuperAdmin',
    NOW(),
    NOW()
);


