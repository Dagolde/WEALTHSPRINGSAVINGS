# Project Structure

This document outlines the directory structure and organization of the Rotational Contribution App backend.

## Directory Layout

```
backend/
├── app/
│   ├── Console/              # Artisan commands
│   ├── Exceptions/           # Exception handlers
│   ├── Http/
│   │   ├── Controllers/      # API controllers
│   │   │   ├── Api/
│   │   │   │   ├── V1/       # Version 1 API controllers
│   │   │   │   │   ├── Auth/
│   │   │   │   │   ├── User/
│   │   │   │   │   ├── Group/
│   │   │   │   │   ├── Contribution/
│   │   │   │   │   ├── Payout/
│   │   │   │   │   ├── Wallet/
│   │   │   │   │   └── Admin/
│   │   ├── Middleware/       # HTTP middleware
│   │   └── Requests/         # Form request validation
│   ├── Models/               # Eloquent models
│   │   ├── User.php
│   │   ├── BankAccount.php
│   │   ├── Group.php
│   │   ├── GroupMember.php
│   │   ├── Contribution.php
│   │   ├── Payout.php
│   │   ├── WalletTransaction.php
│   │   ├── Withdrawal.php
│   │   ├── Notification.php
│   │   └── AuditLog.php
│   ├── Services/             # Business logic services
│   │   ├── Interfaces/       # Service interfaces
│   │   │   ├── UserServiceInterface.php
│   │   │   ├── GroupServiceInterface.php
│   │   │   ├── ContributionServiceInterface.php
│   │   │   ├── PayoutServiceInterface.php
│   │   │   ├── WalletServiceInterface.php
│   │   │   ├── PaymentGatewayInterface.php
│   │   │   └── NotificationServiceInterface.php
│   │   ├── UserService.php
│   │   ├── GroupService.php
│   │   ├── ContributionService.php
│   │   ├── PayoutService.php
│   │   ├── WalletService.php
│   │   ├── PaymentGatewayService.php
│   │   └── NotificationService.php
│   ├── Events/               # Event classes
│   ├── Listeners/            # Event listeners
│   ├── Jobs/                 # Queue jobs
│   ├── Notifications/        # Notification classes
│   └── Providers/            # Service providers
├── bootstrap/
│   └── app.php               # Application bootstrap
├── config/                   # Configuration files
│   ├── app.php
│   ├── database.php
│   ├── sanctum.php
│   ├── queue.php
│   └── services.php
├── database/
│   ├── factories/            # Model factories
│   ├── migrations/           # Database migrations
│   └── seeders/              # Database seeders
├── public/                   # Public assets
├── resources/
│   └── views/                # Blade templates (if needed)
├── routes/
│   ├── api.php               # API routes
│   ├── web.php               # Web routes
│   └── console.php           # Console routes
├── storage/
│   ├── app/                  # Application storage
│   ├── framework/            # Framework storage
│   └── logs/                 # Application logs
├── tests/
│   ├── Feature/              # Feature tests
│   │   ├── Auth/
│   │   ├── User/
│   │   ├── Group/
│   │   ├── Contribution/
│   │   ├── Payout/
│   │   └── Wallet/
│   └── Unit/                 # Unit tests
│       └── Services/
├── .env.development          # Development environment
├── .env.staging              # Staging environment
├── .env.production           # Production environment
├── .php-cs-fixer.php         # PHP CS Fixer configuration
├── psalm.xml                 # Psalm configuration
├── composer.json             # PHP dependencies
└── README.md                 # Project documentation
```

## Key Directories

### app/Http/Controllers/Api/V1/
Contains all API controllers organized by feature:
- **Auth/** - Authentication endpoints (register, login, logout)
- **User/** - User management (profile, KYC, bank accounts)
- **Group/** - Group management (create, join, start)
- **Contribution/** - Contribution tracking and payment
- **Payout/** - Payout processing and history
- **Wallet/** - Wallet operations (fund, withdraw, balance)
- **Admin/** - Admin dashboard and management

### app/Services/
Business logic layer implementing service interfaces:
- Handles complex operations and business rules
- Interacts with models and external services
- Provides reusable functionality across controllers

### app/Models/
Eloquent ORM models representing database tables:
- Defines relationships between entities
- Implements model scopes and accessors
- Handles model events

### database/migrations/
Database schema definitions:
- Sequential migration files
- Creates tables, indexes, and constraints
- Supports rollback operations

### tests/
Automated tests:
- **Feature/** - End-to-end API tests
- **Unit/** - Service and model unit tests
- Property-based tests for correctness properties

## Naming Conventions

### Controllers
- Singular resource name: `UserController`, `GroupController`
- RESTful method names: `index`, `store`, `show`, `update`, `destroy`

### Models
- Singular, PascalCase: `User`, `GroupMember`, `WalletTransaction`

### Services
- Descriptive name + Service: `UserService`, `PaymentGatewayService`

### Migrations
- Timestamp + descriptive name: `2024_01_01_000000_create_users_table`

### Routes
- Plural resource names: `/api/v1/users`, `/api/v1/groups`
- Nested resources: `/api/v1/groups/{id}/members`

## Configuration Files

### .env files
- `.env.development` - Local development
- `.env.staging` - Staging environment
- `.env.production` - Production environment

### Code Quality
- `.php-cs-fixer.php` - Code style rules (PSR-12)
- `psalm.xml` - Static analysis configuration

## Service Layer Pattern

The application uses a service layer pattern to separate business logic from controllers:

1. **Controller** receives HTTP request
2. **Request** validates input data
3. **Service** processes business logic
4. **Model** interacts with database
5. **Controller** returns HTTP response

This pattern ensures:
- Testable business logic
- Reusable code across controllers
- Clear separation of concerns
- Easier maintenance and refactoring
