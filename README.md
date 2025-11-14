# FRSC Housing Management System - Laravel API

A comprehensive multi-tenant SaaS platform for housing cooperatives built with Laravel 11.

## Features

- **Multi-Tenancy**: Each business operates in isolated database with shared central management
- **White Labeling**: Custom branding, colors, logos, and domain names per tenant
- **Landing Page Builder**: Drag-and-drop page builder for business landing pages
- **Payment Processing**: Integrated Paystack, Remita, Stripe, and manual bank transfers
- **Member Management**: KYC verification, membership tiers, document management
- **Financial Modules**: Loans, investments, contributions, wallets, statutory charges
- **Property Management**: Listings, allocations, maintenance requests
- **Communication**: Internal mail service, notifications
- **Reports & Analytics**: Comprehensive reporting across all modules
- **Role-Based Access**: Granular permissions for super admin, admin, and users

## Technology Stack

- **Backend**: Laravel 11.x
- **Database**: PostgreSQL 15+
- **Cache**: Redis
- **Queue**: Redis
- **Storage**: AWS S3 / Local
- **Email**: SMTP / SendGrid
- **Payment**: Paystack, Remita, Stripe
- **Authentication**: Laravel Sanctum
- **Multi-Tenancy**: Stancl/Tenancy

## Installation

### Prerequisites

- PHP 8.2+
- Composer
- PostgreSQL 15+
- Redis
- Node.js & NPM (for frontend assets)

### Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd api
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database setup**
   ```bash
   # Create central database
   createdb frsc_central
   
   # Run migrations
   php artisan migrate --path=database/migrations/central
   ```

5. **Publish and configure packages**
   ```bash
   php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
   php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
   php artisan vendor:publish --provider="Stancl\Tenancy\TenancyServiceProvider"
   ```

6. **Run migrations and seeders**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

## API Documentation

### Authentication

All API endpoints require authentication except for public routes.

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

### Core Endpoints

#### Authentication
- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout

#### Admin Endpoints
- `GET /api/admin/custom-domains` - List custom domains
- `POST /api/admin/custom-domains` - Create custom domain
- `POST /api/admin/custom-domains/verify` - Verify domain
- `GET /api/admin/landing-page` - Get landing page config
- `POST /api/admin/landing-page` - Update landing page
- `POST /api/admin/landing-page/publish` - Publish landing page

#### Super Admin Endpoints
- `GET /api/super-admin/businesses` - List all businesses
- `POST /api/super-admin/businesses` - Create business
- `GET /api/super-admin/packages` - List packages
- `POST /api/super-admin/packages` - Create package

### Multi-Tenancy

The system supports multi-tenancy through subdomains and custom domains:

- **Subdomain**: `tenant-name.frsc-housing.com`
- **Custom Domain**: `tenant.com` (after verification)

### Database Architecture

#### Central Database
- `tenants` - Business/tenant information
- `packages` - Subscription packages
- `subscriptions` - Business subscriptions
- `modules` - Available modules
- `super_admins` - Platform administrators
- `custom_domain_requests` - Custom domain requests
- `platform_transactions` - Platform transactions
- `activity_logs` - System activity logs

#### Tenant Database (Per Business)
- `users` - User accounts
- `members` - Member profiles
- `properties` - Property listings
- `loans` - Loan products and applications
- `investments` - Investment plans
- `contributions` - Member contributions
- `wallets` - User wallets
- `transactions` - Payment transactions
- `mail_messages` - Internal messaging
- `documents` - Document management
- `landing_page_configs` - Landing page builder

## Development

### Running the Application

```bash
# Start the development server
php artisan serve

# Start queue workers
php artisan queue:work

# Start Redis (if not running)
redis-server
```

### Testing

```bash
# Run tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
```

### Code Quality

```bash
# Run Pint for code formatting
./vendor/bin/pint

# Run PHPStan for static analysis
./vendor/bin/phpstan analyse
```

## Deployment

### Production Checklist

- [ ] Set up environment variables
- [ ] Configure database connections
- [ ] Set up queue workers
- [ ] Configure file storage (S3/local)
- [ ] Set up SSL certificates
- [ ] Configure CORS
- [ ] Set up monitoring and logging
- [ ] Configure backup strategy
- [ ] Set up CI/CD pipeline
- [ ] Performance testing
- [ ] Security audit

### Environment Variables

```env
# Application
APP_NAME="FRSC Housing Management"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_DATABASE=frsc_central
DB_USERNAME=your-username
DB_PASSWORD=your-password

# Redis
REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-redis-password

# Payment Gateways
PAYSTACK_PUBLIC_KEY=your-paystack-public-key
PAYSTACK_SECRET_KEY=your-paystack-secret-key
REMITA_MERCHANT_ID=your-remita-merchant-id
REMITA_API_KEY=your-remita-api-key
STRIPE_KEY=your-stripe-key
STRIPE_SECRET=your-stripe-secret

# Platform Settings
PLATFORM_DOMAIN=your-platform-domain.com
DEFAULT_SUBDOMAIN_SUFFIX=.your-platform-domain.com
```

## Security

### Authentication & Authorization
- Laravel Sanctum for API token authentication
- Role-based access control (RBAC)
- Tenant isolation at database level
- Rate limiting (60 requests per minute)

### Data Protection
- All sensitive data encrypted
- HTTPS for all API communication
- Password hashing with bcrypt
- Input validation and sanitization

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## License

This project is proprietary software. All rights reserved.

## Support

For support and questions, please contact the development team.