# Rotational Contribution App - Backend API

Laravel backend API for the Rotational Contribution App (Ajo Platform), a digital rotational savings system.

## Requirements

- PHP 8.2+
- Composer
- PostgreSQL 13+
- Redis 6+

## Installation

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```

3. Copy the appropriate environment file:
   ```bash
   # For development
   cp .env.development .env
   
   # For staging
   cp .env.staging .env
   
   # For production
   cp .env.production .env
   ```

4. Generate application key:
   ```bash
   php artisan key:generate
   ```

5. Configure your database and Redis settings in `.env`

6. Run migrations:
   ```bash
   php artisan migrate
   ```

7. Publish Sanctum configuration (if not already done):
   ```bash
   php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
   ```

## Development

### Running the Development Server

```bash
php artisan serve
```

The API will be available at `http://localhost:8000`

### Code Quality Tools

#### PHP CS Fixer
Format code according to PSR-12 standards:
```bash
vendor/bin/php-cs-fixer fix
```

Check code without fixing:
```bash
vendor/bin/php-cs-fixer fix --dry-run --diff
```

#### Psalm
Run static analysis:
```bash
vendor/bin/psalm
```

Run with error baseline:
```bash
vendor/bin/psalm --set-baseline=psalm-baseline.xml
```

### Running Tests

```bash
php artisan test
```

Run with coverage:
```bash
php artisan test --coverage
```

### Queue Workers

Start the queue worker:
```bash
php artisan queue:work redis --queue=high,default,low
```

For development with auto-reload:
```bash
php artisan queue:listen redis --queue=high,default,low
```

## Environment Files

- `.env.development` - Local development environment
- `.env.staging` - Staging environment
- `.env.production` - Production environment

## Core Dependencies

- **Laravel 12+** - PHP framework
- **Laravel Sanctum** - API authentication
- **PHP CS Fixer** - Code style enforcement
- **Psalm** - Static analysis

## Project Structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Middleware/
│   ├── Models/
│   ├── Services/
│   └── Events/
├── config/
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── routes/
│   ├── api.php
│   └── web.php
├── tests/
│   ├── Feature/
│   └── Unit/
└── storage/
```

## API Documentation

API documentation will be available at `/api/documentation` once configured.

## License

Proprietary - All rights reserved
