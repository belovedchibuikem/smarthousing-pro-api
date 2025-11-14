# Multi-Tenant Database Architecture

## Central Database (`frsc_housing_central`)
**Purpose**: Platform management and tenant registry

### Tables:
- `tenants` - Tenant registry (id, data)
- `packages` - Subscription plans (starter, professional, enterprise)
- `super_admins` - Platform administrators
- `modules` - Available features
- `subscriptions` - Tenant subscriptions
- `payment_gateways` - Payment providers
- `activity_logs` - Platform activity
- `platform_transactions` - Platform-level transactions

## Tenant Database (`{tenant_slug}_housing_tenant_template`)
**Purpose**: Business-specific data for each tenant

### Tables:
- `users` - Business users (admin, members, staff)
- `members` - Member profiles and KYC
- `properties` - Real estate listings
- `wallets` - User wallets and balances
- `wallet_transactions` - Wallet transactions
- `payments` - Business transactions
- `loans` - Member loans
- `investments` - Member investments
- `contributions` - Member contributions
- `roles` & `permissions` - RBAC system
- `sessions` - User sessions
- `password_reset_tokens` - Password reset
- `notifications` - User notifications
- `documents` - KYC documents
- `landing_page_configs` - Tenant landing pages

## How It Works:

1. **Tenant Creation**: 
   - New tenant registered in central database
   - New database created: `{tenant_slug}_housing_tenant_template`
   - Tenant-specific tables migrated
   - Default roles and admin user created

2. **Data Isolation**:
   - Each tenant has completely separate database
   - No data sharing between tenants
   - Central database only manages tenant registry

3. **User Authentication**:
   - Super admins authenticate against central database
   - Tenant users authenticate against their tenant database
   - Sessions stored in tenant database

4. **API Routing**:
   - Central APIs: `/api/super-admin/*`, `/api/public/*`
   - Tenant APIs: `/api/*` (resolved by tenant middleware)

## Example:
- **Central**: `frsc_housing_central`
- **FRSC Tenant**: `frsc_housing_tenant_template`
- **Another Tenant**: `acme_housing_tenant_template`