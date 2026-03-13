# Rotational Contribution App

A comprehensive mobile and web application for managing rotational contribution groups (Ajo/Esusu) with integrated payment processing, KYC verification, and admin management.

## 🚀 Features

### Mobile App (Flutter)
- User authentication and profile management
- Wallet management (funding, withdrawal, balance tracking)
- Group creation and management
- Contribution recording and tracking
- KYC submission and verification
- Bank account linking
- Real-time notifications
- Offline support with data synchronization

### Admin Dashboard (Web)
- User management and KYC approval
- Group monitoring and management
- Transaction oversight
- Analytics and reporting
- Mobile app control (force updates, maintenance mode)
- System settings and configuration

### Backend (Laravel)
- RESTful API with Laravel 10
- PostgreSQL database
- Redis caching and queue management
- Paystack payment integration
- Automated payout system
- Fraud detection
- Email notifications
- Comprehensive testing (Unit, Feature, Property-based)

### Microservices (Python/FastAPI)
- Payment processing service
- Notification service
- Fraud detection service
- Scheduler service for automated tasks

## 📋 Prerequisites

- PHP 8.2+
- MySQL 8.0+ (or PostgreSQL 14+)
- Redis
- Composer
- Node.js & npm
- Flutter SDK
- Docker & Docker Compose (for local development)

## 🛠️ Technology Stack

- **Backend**: Laravel 10, PHP 8.2
- **Database**: MySQL 8.0+ (or PostgreSQL 14+)
- **Cache/Queue**: Redis
- **Mobile**: Flutter 3.x
- **Admin Dashboard**: HTML, CSS, JavaScript (Vanilla)
- **Microservices**: Python 3.11, FastAPI
- **Payment Gateway**: Paystack
- **Deployment**: CloudPanel, Nginx, Supervisor

## 📦 Project Structure

```
.
├── backend/                 # Laravel backend API
│   ├── app/                # Application code
│   ├── database/           # Migrations, seeders, factories
│   ├── routes/             # API routes
│   ├── tests/              # Unit, Feature, Property tests
│   └── .env.cloudpanel     # Production environment template
├── mobile/                  # Flutter mobile app
│   ├── lib/                # Dart source code
│   ├── android/            # Android configuration
│   └── ios/                # iOS configuration
├── admin-dashboard/         # Web admin dashboard
│   ├── index.html          # Main dashboard
│   ├── app.js              # Dashboard logic
│   └── styles.css          # Dashboard styles
├── microservices/           # Python microservices
│   ├── app/                # FastAPI services
│   └── tests/              # Service tests
├── .github/                 # GitHub Actions workflows
│   └── workflows/          # CI/CD pipelines
└── docker-compose.yml       # Local development setup
```

## 🚀 Quick Start

### Local Development

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/rotational-contribution-app.git
   cd rotational-contribution-app
   ```

2. **Start Docker services**
   ```bash
   docker-compose up -d
   ```

3. **Setup backend**
   ```bash
   cd backend
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate
   php artisan db:seed --class=AdminUserSeeder
   ```

4. **Setup mobile app**
   ```bash
   cd mobile
   flutter pub get
   flutter run
   ```

5. **Access services**
   - Backend API: http://localhost:8000
   - Admin Dashboard: http://localhost:8000/admin
   - PostgreSQL: localhost:5432
   - Redis: localhost:6379

### Production Deployment

See deployment guides based on your database choice:
- **MySQL (Recommended)**: [CLOUDPANEL_MYSQL_DEPLOYMENT.md](CLOUDPANEL_MYSQL_DEPLOYMENT.md) - Uses CloudPanel's default MySQL
- **PostgreSQL (Advanced)**: [CLOUDPANEL_DEPLOYMENT_GUIDE.md](CLOUDPANEL_DEPLOYMENT_GUIDE.md) - Requires PostgreSQL installation

Quick deployment steps:
1. Set up CloudPanel server with MySQL (default) or PostgreSQL
2. Configure GitHub repository and secrets
3. Push to main branch (auto-deploys via GitHub Actions)
4. Or use manual deployment script: `.\deploy-from-local.ps1`

## 📚 Documentation

- [CloudPanel MySQL Deployment](CLOUDPANEL_MYSQL_DEPLOYMENT.md) - **Recommended**: Uses CloudPanel's default MySQL
- [CloudPanel Deployment Guide](CLOUDPANEL_DEPLOYMENT_GUIDE.md) - General guide (MySQL or PostgreSQL)
- [CloudPanel Quick Start](CLOUDPANEL_QUICK_START.md) - Quick setup checklist
- [PostgreSQL Setup](CLOUDPANEL_POSTGRESQL_SETUP.md) - PostgreSQL-specific configuration
- [GitHub Deployment Setup](GITHUB_DEPLOYMENT_SETUP.md) - CI/CD configuration
- [Deployment Verification](CLOUDPANEL_DEPLOYMENT_VERIFICATION.md) - Testing and verification

## 🔧 Configuration

### Backend Environment Variables

Key environment variables (see `.env.cloudpanel` for full list):

```env
APP_URL=https://yourdomain.com
DB_CONNECTION=mysql
DB_DATABASE=rotational_app
DB_USERNAME=rotational_user
DB_PASSWORD=your_password

PAYSTACK_PUBLIC_KEY=pk_live_...
PAYSTACK_SECRET_KEY=sk_live_...

ADMIN_EMAIL=admin@yourdomain.com
ADMIN_PASSWORD=secure_password
```

### Mobile App Configuration

Edit `mobile/lib/core/config/app_config.dart`:

```dart
class AppConfig {
  static const String apiBaseUrl = 'https://yourdomain.com/api/v1';
  static const String appName = 'Rotational Contribution';
  static const String appVersion = '1.0.0';
}
```

## 🧪 Testing

### Backend Tests

```bash
# Run all tests
docker exec rotational_laravel php artisan test

# Run specific test suite
docker exec rotational_laravel php artisan test --testsuite=Feature

# Run with coverage
docker exec rotational_laravel php artisan test --coverage
```

### Property-Based Tests

```bash
# Run property tests
docker exec rotational_laravel php artisan test --filter=Property
```

## 🔄 Deployment Workflow

### Automatic Deployment (GitHub Actions)

1. Make changes locally
2. Commit and push to GitHub
   ```bash
   git add .
   git commit -m "Your changes"
   git push origin main
   ```
3. GitHub Actions automatically deploys to production

### Manual Deployment

```powershell
.\deploy-from-local.ps1 -ServerHost "your-server-ip" -ServerUser "your-user" -DeployPath "/path/to/site"
```

## 📊 Features Overview

### User Features
- ✅ User registration and authentication
- ✅ KYC verification with document upload
- ✅ Wallet funding via Paystack
- ✅ Wallet withdrawal to bank account
- ✅ Create and join contribution groups
- ✅ Make contributions
- ✅ Track contribution history
- ✅ Receive automated payouts
- ✅ Real-time notifications
- ✅ Profile management

### Admin Features
- ✅ User management
- ✅ KYC approval/rejection
- ✅ Group monitoring
- ✅ Transaction management
- ✅ Analytics dashboard
- ✅ Mobile app control
- ✅ System settings
- ✅ Audit logs

### Technical Features
- ✅ RESTful API with Laravel Sanctum authentication
- ✅ PostgreSQL database with optimized indexes
- ✅ Redis caching for performance
- ✅ Queue-based job processing
- ✅ Automated scheduler for recurring tasks
- ✅ Property-based testing for correctness
- ✅ Fraud detection integration
- ✅ Email notifications
- ✅ File upload with validation
- ✅ Rate limiting and security headers

## 🔒 Security

- HTTPS enforced in production
- Laravel Sanctum for API authentication
- CSRF protection
- SQL injection prevention (Eloquent ORM)
- XSS protection
- Rate limiting on API endpoints
- Secure password hashing (bcrypt)
- KYC document encryption
- Environment variable protection

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is proprietary software. All rights reserved.

## 👥 Team

- Backend Development: Laravel API, Database, Microservices
- Mobile Development: Flutter mobile application
- Frontend Development: Admin dashboard
- DevOps: Deployment and infrastructure

## 📞 Support

For support and questions:
- Email: support@yourdomain.com
- Documentation: See docs folder
- Issues: GitHub Issues

## 🎉 Acknowledgments

- Laravel Framework
- Flutter Framework
- Paystack Payment Gateway
- CloudPanel Hosting Platform
- PostgreSQL Database
- Redis Cache

---

**Version**: 1.0.0  
**Last Updated**: March 13, 2026  
**Status**: ✅ Production Ready
