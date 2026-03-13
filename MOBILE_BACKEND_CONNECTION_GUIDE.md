# Mobile App & Backend Connection Guide

This guide explains how to connect the Flutter mobile app to the Laravel backend API and enable admin dashboard control.

## Table of Contents
1. [Architecture Overview](#architecture-overview)
2. [Backend Setup](#backend-setup)
3. [Mobile App Configuration](#mobile-app-configuration)
4. [Admin Dashboard Setup](#admin-dashboard-setup)
5. [Testing the Connection](#testing-the-connection)
6. [Troubleshooting](#troubleshooting)

## Architecture Overview

```
┌─────────────────┐         ┌──────────────────┐         ┌─────────────────┐
│                 │         │                  │         │                 │
│  Flutter Mobile │◄───────►│  Laravel Backend │◄───────►│ Admin Dashboard │
│      App        │  HTTP   │    API (REST)    │  HTTP   │   (Web/Mobile)  │
│                 │         │                  │         │                 │
└─────────────────┘         └──────────────────┘         └─────────────────┘
        │                            │                            │
        │                            │                            │
        ▼                            ▼                            ▼
┌─────────────────┐         ┌──────────────────┐         ┌─────────────────┐
│  Local Storage  │         │   PostgreSQL DB  │         │  Admin Actions  │
│  (Hive/SQLite)  │         │      Redis       │         │  - Approve KYC  │
│  - Auth Token   │         │                  │         │  - Manage Users │
│  - Cache Data   │         │                  │         │  - Analytics    │
└─────────────────┘         └──────────────────┘         └─────────────────┘
```

## Backend Setup

### 1. Start Backend Services

#### Using Docker (Recommended)
```bash
# Navigate to project root
cd /path/to/rotational-contribution-app

# Start all services
docker-compose up -d

# Check services are running
docker-compose ps
```

#### Manual Setup
```bash
# Start PostgreSQL
# Start Redis

# Navigate to backend
cd backend

# Install dependencies
composer install

# Run migrations
php artisan migrate

# Seed database with test data
php artisan db:seed

# Start Laravel server (accessible from network)
php artisan serve --host=0.0.0.0 --port=8000
```

### 2. Configure Backend for Mobile Access

#### Update CORS Configuration
Edit `backend/config/cors.php`:
```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'], // For development only
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

#### Update .env for Network Access
Edit `backend/.env`:
```env
APP_URL=http://YOUR_IP_ADDRESS:8000
SANCTUM_STATEFUL_DOMAINS=YOUR_IP_ADDRESS:8000
SESSION_DOMAIN=.YOUR_IP_ADDRESS
```

### 3. Create Admin User

```bash
cd backend

# Run admin seeder
php artisan db:seed --class=AdminUserSeeder

# Or create manually
php artisan tinker
```

In tinker:
```php
$admin = new App\Models\User();
$admin->name = 'Admin User';
$admin->email = 'admin@ajoplatform.com';
$admin->phone = '+2348012345678';
$admin->password = Hash::make('admin123');
$admin->is_admin = true;
$admin->kyc_status = 'verified';
$admin->status = 'active';
$admin->save();
```

### 4. Find Your Computer's IP Address

#### Windows
```powershell
ipconfig
# Look for "IPv4 Address" under your active network adapter
# Example: 192.168.1.100
```

#### Linux/Mac
```bash
ifconfig
# or
ip addr show
# Look for inet address (not 127.0.0.1)
# Example: 192.168.1.100
```

### 5. Test Backend API

Open browser and test:
```
http://YOUR_IP:8000/api/v1/health
```

You should see a JSON response.

## Mobile App Configuration

### 1. Update Environment File

Edit `mobile/.env`:
```env
# Replace with YOUR computer's IP address
API_BASE_URL=http://192.168.1.100:8000/api/v1

# Other configurations
APP_NAME=Ajo Platform
APP_VERSION=1.0.0
ENVIRONMENT=development
```

**IMPORTANT:** 
- ❌ Don't use `localhost` or `127.0.0.1` (these refer to the device itself)
- ✅ Use your computer's actual IP address on the network
- ✅ Ensure mobile device is on the same network as your computer

### 2. Rebuild Mobile App

```bash
cd mobile

# Clean previous builds
flutter clean

# Get dependencies
flutter pub get

# Build and install
flutter run
# or
flutter build apk --debug
```

### 3. Configure Firewall

#### Windows Firewall
```powershell
# Allow Laravel server through firewall
New-NetFirewallRule -DisplayName "Laravel Dev Server" -Direction Inbound -LocalPort 8000 -Protocol TCP -Action Allow
```

#### Linux (UFW)
```bash
sudo ufw allow 8000/tcp
```

#### Mac
```bash
# System Preferences → Security & Privacy → Firewall → Firewall Options
# Add Laravel/PHP and allow incoming connections
```

## Admin Dashboard Setup

### Option 1: Web-Based Admin Dashboard

#### 1. Create Admin Web Interface

Create `backend/resources/views/admin/dashboard.blade.php`:
```html
<!DOCTYPE html>
<html>
<head>
    <title>Ajo Platform - Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div id="app">
        <!-- Admin dashboard will be loaded here -->
    </div>
    <script src="{{ asset('js/admin.js') }}"></script>
</body>
</html>
```

#### 2. Access Admin Dashboard

```
http://YOUR_IP:8000/admin/dashboard
```

Login with admin credentials:
- Email: `admin@ajoplatform.com`
- Password: `admin123`

### Option 2: Mobile Admin App

The mobile app includes admin features when logged in as admin:

1. **Login as Admin**
   - Use admin email and password
   - App automatically detects admin role

2. **Admin Features Available:**
   - Dashboard with statistics
   - User management (suspend/activate)
   - KYC approval/rejection
   - Withdrawal approval
   - Analytics and reports

### Admin API Endpoints

All admin endpoints require authentication and admin role:

#### Dashboard Statistics
```http
GET /api/v1/admin/dashboard/stats
Authorization: Bearer {admin_token}
```

#### User Management
```http
GET /api/v1/admin/users
GET /api/v1/admin/users/{id}
PUT /api/v1/admin/users/{id}/suspend
PUT /api/v1/admin/users/{id}/activate
```

#### KYC Management
```http
GET /api/v1/admin/kyc/pending
POST /api/v1/admin/kyc/{id}/approve
POST /api/v1/admin/kyc/{id}/reject
```

#### Withdrawal Management
```http
GET /api/v1/admin/withdrawals/pending
POST /api/v1/admin/withdrawals/{id}/approve
POST /api/v1/admin/withdrawals/{id}/reject
```

#### Analytics
```http
GET /api/v1/admin/analytics/users
GET /api/v1/admin/analytics/groups
GET /api/v1/admin/analytics/transactions
GET /api/v1/admin/analytics/revenue
```

## Testing the Connection

### 1. Test Backend Health

```bash
curl http://YOUR_IP:8000/api/v1/health
```

Expected response:
```json
{
  "status": "ok",
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### 2. Test User Registration (from mobile)

```bash
curl -X POST http://YOUR_IP:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "phone": "+2348012345678",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

### 3. Test Admin Login

```bash
curl -X POST http://YOUR_IP:8000/api/v1/auth/admin/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@ajoplatform.com",
    "password": "admin123"
  }'
```

### 4. Test Protected Endpoint

```bash
curl http://YOUR_IP:8000/api/v1/user/profile \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## API Communication Flow

### 1. User Registration Flow
```
Mobile App                    Backend API
    │                             │
    ├──POST /auth/register───────►│
    │                             ├─ Validate data
    │                             ├─ Create user
    │                             ├─ Generate token
    │◄──Response (user + token)───┤
    │                             │
    ├─ Store token locally        │
    └─ Navigate to home           │
```

### 2. Authenticated Request Flow
```
Mobile App                    Backend API
    │                             │
    ├─ Get token from storage     │
    │                             │
    ├──GET /user/profile─────────►│
    │  Header: Bearer {token}     │
    │                             ├─ Verify token
    │                             ├─ Check user status
    │                             ├─ Fetch user data
    │◄──Response (user data)──────┤
    │                             │
    └─ Display data               │
```

### 3. Admin Action Flow
```
Admin Dashboard              Backend API                Mobile App
    │                             │                         │
    ├──POST /admin/kyc/approve───►│                         │
    │  Header: Bearer {admin_token}│                        │
    │                             ├─ Verify admin role      │
    │                             ├─ Update KYC status      │
    │                             ├─ Send notification─────►│
    │◄──Response (success)─────────┤                        │
    │                             │                         │
    │                             │                    ┌────┴────┐
    │                             │                    │ User    │
    │                             │                    │ receives│
    │                             │                    │ notif   │
    │                             │                    └─────────┘
```

## Troubleshooting

### Issue 1: "Connection refused" or "Network error"

**Causes:**
- Backend not running
- Wrong IP address
- Firewall blocking connection
- Different networks

**Solutions:**
```bash
# 1. Verify backend is running
curl http://YOUR_IP:8000/api/v1/health

# 2. Check firewall
# Windows
netsh advfirewall firewall show rule name="Laravel Dev Server"

# Linux
sudo ufw status

# 3. Verify both devices on same network
# Mobile: Settings → WiFi → Check network name
# Computer: Check WiFi/Ethernet connection

# 4. Test from mobile browser
# Open: http://YOUR_IP:8000/api/v1/health
```

### Issue 2: "401 Unauthorized"

**Causes:**
- Invalid or expired token
- Token not sent in request
- User suspended

**Solutions:**
```dart
// Check token storage
final token = await TokenStorage().getToken();
print('Token: $token');

// Clear token and re-login
await TokenStorage().clearAll();

// Check user status in database
// Backend: SELECT status FROM users WHERE id = ?
```

### Issue 3: "403 Forbidden" on Admin Endpoints

**Causes:**
- User is not admin
- Admin middleware not working

**Solutions:**
```bash
# Verify user is admin
cd backend
php artisan tinker

# In tinker:
$user = User::where('email', 'admin@ajoplatform.com')->first();
echo $user->is_admin; // Should be 1 or true

# Update if needed:
$user->is_admin = true;
$user->save();
```

### Issue 4: CORS Errors

**Symptoms:**
- "Access-Control-Allow-Origin" error
- Preflight request failed

**Solutions:**
```bash
# 1. Update CORS config
# Edit backend/config/cors.php

# 2. Clear config cache
cd backend
php artisan config:clear
php artisan cache:clear

# 3. Restart server
php artisan serve --host=0.0.0.0 --port=8000
```

### Issue 5: Slow API Responses

**Solutions:**
```bash
# 1. Enable query caching
# Edit backend/.env
CACHE_DRIVER=redis

# 2. Optimize database
cd backend
php artisan optimize

# 3. Check network latency
ping YOUR_IP

# 4. Use production build
cd mobile
flutter build apk --release
```

## Security Considerations

### Development Environment
- ✅ Use HTTP for local testing
- ✅ Allow all CORS origins
- ✅ Use simple passwords
- ✅ Enable debug logging

### Production Environment
- ✅ Use HTTPS only
- ✅ Restrict CORS origins
- ✅ Use strong passwords
- ✅ Disable debug logging
- ✅ Use environment-specific configs
- ✅ Enable rate limiting
- ✅ Implement API key authentication

## Next Steps

1. ✅ Start backend services
2. ✅ Configure mobile app with correct IP
3. ✅ Build and install mobile app
4. ✅ Test user registration
5. ✅ Test admin login
6. ✅ Test admin actions
7. ✅ Monitor logs for errors

## Useful Commands

### Backend
```bash
# View logs
tail -f storage/logs/laravel.log

# Clear cache
php artisan cache:clear
php artisan config:clear

# Check routes
php artisan route:list

# Monitor requests
php artisan serve --host=0.0.0.0 --port=8000 --verbose
```

### Mobile
```bash
# View logs
flutter logs

# Hot reload
flutter run
# Press 'r' to reload

# Clear app data
adb shell pm clear com.ajoplatform.rotational_contribution

# Check network
adb shell ping YOUR_IP
```

### Database
```bash
# Access database
cd backend
php artisan tinker

# Check users
User::all();

# Check admin users
User::where('is_admin', true)->get();

# Check groups
Group::all();
```

## Support

For issues:
1. Check logs (backend and mobile)
2. Verify network connectivity
3. Test API endpoints with curl/Postman
4. Review this guide
5. Check firewall settings

---

**Happy Connecting! 🚀**
