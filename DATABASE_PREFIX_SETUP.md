# Database Prefix Configuration Guide

## Overview
When deploying to a live server that automatically prefixes all databases (e.g., `smarthou1_`), you need to configure the application to handle this prefix correctly.

## Problem
Your live server automatically attaches `smarthou1_` prefix to all databases. So when you create a tenant database named `tenant-uuid_smart_housing`, the actual database on the server becomes `smarthou1_tenant-uuid_smart_housing`.

## Solution

### Step 1: Set Environment Variable
Add the following to your `.env` file on the live server:

```env
TENANCY_DB_PREFIX=smarthou1_
```

**Important:** Make sure there's no space after the prefix. The prefix should end with an underscore if your server uses one.

### Step 2: Verify Tenancy Configuration
The `config/tenancy.php` file already has the prefix configuration:

```php
'database' => [
    'prefix' => env('TENANCY_DB_PREFIX', ''),
    'suffix' => env('TENANCY_DB_SUFFIX', '_smart_housing'),
],
```

This means:
- The tenancy package will automatically use the prefix when creating/managing tenant databases
- Database names will be: `{PREFIX}{tenant_id}{SUFFIX}`
- Example: `smarthou1_550e8400-e29b-41d4-a716-446655440000_smart_housing`

### Step 3: How It Works

#### Automatic Database Creation
The tenancy package (using `HasDatabase` trait) automatically:
1. Reads `TENANCY_DB_PREFIX` from environment
2. Combines: `prefix + tenant_id + suffix`
3. Creates database with the full name including prefix

#### Manual Database Creation
The `TenantDatabaseService` has been updated to:
1. Read the prefix from environment/config
2. Automatically prepend prefix when creating databases
3. Handle prefix when checking database existence
4. Use prefixed names when setting up connections

### Step 4: Testing

After setting `TENANCY_DB_PREFIX=smarthou1_` in your `.env`:

1. **Create a new tenant** - The database should be created as `smarthou1_{tenant_id}_smart_housing`
2. **Check database exists** - The system will look for the prefixed name
3. **Connect to tenant database** - Connections will use the prefixed name automatically

### Step 5: Existing Tenants

If you have existing tenants created before adding the prefix:

**Option A: Recreate Databases (Recommended for Development)**
- Delete existing tenant databases
- Re-run onboarding for those tenants
- New databases will be created with the prefix

**Option B: Migrate Existing Databases (Production)**
- Manually rename existing databases to include the prefix
- Or create a migration script to handle this

### Important Notes

1. **Central Database**: The central database (`smart_housing_central`) should also have the prefix. Make sure your `DB_DATABASE` in `.env` includes the prefix:
   ```env
   DB_DATABASE=smarthou1_smart_housing_central
   ```

2. **Database User Permissions**: Ensure your database user has permissions to create databases with the prefix pattern.

3. **Backup Scripts**: Update any backup/restore scripts to include the prefix.

4. **Monitoring**: Check your database monitoring tools to ensure they account for the prefix.

## Code Changes Made

### 1. TenantDatabaseService.php
- Added `getDatabasePrefix()` method
- Added `getFullDatabaseName()` method  
- Updated `createDatabaseFromSql()` to use prefix
- Updated `databaseExists()` to handle prefix
- Updated `createDatabaseConnection()` to handle prefix

### 2. BusinessOnboardingController.php
- Updated to use tenant ID instead of slug for database name
- Database connection setup now handles prefix automatically

## Environment Variables Summary

```env
# Database Prefix (required for live server)
TENANCY_DB_PREFIX=smarthou1_

# Central Database (should include prefix if server requires it)
DB_DATABASE=smarthou1_smart_housing_central

# Tenant Database Suffix (already configured)
TENANCY_DB_SUFFIX=_smart_housing
```

## Troubleshooting

### Database Not Found Errors
- Verify `TENANCY_DB_PREFIX` is set correctly in `.env`
- Check that the prefix matches exactly what your server uses (including underscores)
- Clear config cache: `php artisan config:clear`

### Connection Errors
- Ensure database user has permissions for prefixed databases
- Check that the database name format matches: `{prefix}{tenant_id}{suffix}`

### Migration Issues
- Run `php artisan config:cache` after changing `.env`
- Restart your application server/queue workers

## Support

If you encounter issues:
1. Check application logs: `storage/logs/laravel.log`
2. Verify database exists: `SHOW DATABASES LIKE 'smarthou1_%';`
3. Test database connection manually using the prefixed name

