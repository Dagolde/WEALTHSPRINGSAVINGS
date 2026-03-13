# Mobile-Backend Connection Quick Test Guide

## Quick Start (5 Minutes)

### Step 1: Start Backend Services
```powershell
# Windows PowerShell
.\start-full-stack.ps1

# Or Linux/Mac
./start-full-stack.sh
```

### Step 2: Run Connection Test
```powershell
# Windows PowerShell
.\test-mobile-connection.ps1

# Or Linux/Mac
chmod +x test-mobile-connection.sh
./test-mobile-connection.sh
```

### Step 3: Test from Mobile Device Browser

1. Find your computer's IP address (the test script will show it)
2. On your mobile device, open browser
3. Navigate to: `http://YOUR_IP:8000/api/v1/health`
4. You should see: `{"status":"ok","timestamp":"..."}`

### Step 4: Build and Install Mobile App

```bash
cd mobile
flutter clean
flutter pub get
flutter run
```

## Troubleshooting Checklist

### ❌ "Connection refused" or "Network error"

**Check:**
- [ ] Backend services running? → Run `docker-compose ps`
- [ ] Laravel server running? → Check port 8000
- [ ] Same WiFi network? → Check both devices
- [ ] Firewall blocking? → Run test script to add rule
- [ ] Correct IP in mobile/.env? → Should match your computer's IP

**Quick Fix:**
```powershell
# Restart everything
docker-compose down
.\start-full-stack.ps1
```

### ❌ "401 Unauthorized"

**Check:**
- [ ] Token expired? → Re-login in app
- [ ] Token stored correctly? → Clear app data and re-login

**Quick Fix:**
```dart
// In mobile app, clear storage and re-login
await TokenStorage().clearAll();
```

### ❌ "403 Forbidden" (Admin endpoints)

**Check:**
- [ ] User is admin? → Check database
- [ ] Admin middleware working? → Check backend logs

**Quick Fix:**
```bash
cd backend
php artisan tinker
# In tinker:
$user = User::where('email', 'admin@ajoplatform.com')->first();
$user->is_admin = true;
$user->save();
```

### ❌ API responds in browser but not in app

**Check:**
- [ ] mobile/.env has correct IP? → Should be computer's IP, not localhost
- [ ] App rebuilt after .env change? → Run `flutter clean && flutter pub get`
- [ ] CORS configured? → Check backend/config/cors.php

**Quick Fix:**
```bash
cd mobile
flutter clean
flutter pub get
flutter run
```

## Test Endpoints

### Health Check
```bash
curl http://YOUR_IP:8000/api/v1/health
```
Expected: `{"status":"ok",...}`

### User Registration
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
Expected: `{"data":{"user":{...},"token":"..."}}`

### Admin Login
```bash
curl -X POST http://YOUR_IP:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@ajoplatform.com",
    "password": "admin123"
  }'
```
Expected: `{"data":{"user":{...},"token":"..."}}`

### Protected Endpoint (requires token)
```bash
curl http://YOUR_IP:8000/api/v1/user/profile \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```
Expected: `{"data":{"id":1,"name":"..."}}`

## Common IP Addresses

### Find Your IP

**Windows:**
```powershell
ipconfig
# Look for "IPv4 Address" under your WiFi/Ethernet adapter
```

**Linux/Mac:**
```bash
ifconfig
# or
ip addr show
# Look for inet address (not 127.0.0.1)
```

### Typical IP Ranges
- Home WiFi: `192.168.0.x` or `192.168.1.x`
- Office: `10.0.0.x` or `172.16.0.x`
- ❌ Never use: `127.0.0.1` or `localhost` (these refer to the device itself)

## Mobile App Testing Checklist

### Before Building
- [ ] mobile/.env has correct IP address
- [ ] Backend services are running
- [ ] API responds in browser from mobile device
- [ ] Firewall allows port 8000

### Build Process
```bash
cd mobile
flutter clean                    # Clean previous builds
flutter pub get                  # Get dependencies
flutter run                      # Run on connected device
# or
flutter build apk --debug        # Build APK for installation
```

### After Installation
- [ ] App opens without crashing
- [ ] Registration works
- [ ] Login works
- [ ] Can see dashboard
- [ ] Can create/join groups
- [ ] Can make contributions

## Admin Testing Checklist

### Login as Admin
1. Open mobile app
2. Login with:
   - Email: `admin@ajoplatform.com`
   - Password: `admin123`
3. App should detect admin role automatically

### Test Admin Features
- [ ] Dashboard shows statistics
- [ ] Can view all users
- [ ] Can suspend/activate users
- [ ] Can approve/reject KYC
- [ ] Can approve/reject withdrawals
- [ ] Can view analytics

## Performance Checks

### API Response Time
```bash
# Should be < 500ms
time curl http://YOUR_IP:8000/api/v1/health
```

### Database Connection
```bash
cd backend
php artisan tinker
# In tinker:
DB::connection()->getPdo();  # Should not throw error
```

### Redis Connection
```bash
docker exec -it redis redis-cli ping
# Should return: PONG
```

## Logs and Debugging

### Backend Logs
```bash
# Laravel logs
tail -f backend/storage/logs/laravel.log

# Docker logs
docker-compose logs -f

# Specific service logs
docker-compose logs -f postgres
docker-compose logs -f redis
```

### Mobile App Logs
```bash
# Flutter logs
flutter logs

# Or while running
flutter run --verbose
```

### Network Debugging
```bash
# Test connectivity from mobile device
# Install Termux on Android, then:
ping YOUR_IP
curl http://YOUR_IP:8000/api/v1/health
```

## Success Indicators

✅ **Backend Ready:**
- Docker containers running
- Laravel server on port 8000
- API health endpoint responds
- Database migrations complete
- Admin user seeded

✅ **Mobile Ready:**
- mobile/.env configured with correct IP
- Flutter dependencies installed
- App builds without errors
- Can install on device

✅ **Connection Working:**
- Mobile browser can access API
- Mobile app can register users
- Mobile app can login
- Mobile app shows dashboard
- Admin features work

## Quick Commands Reference

```bash
# Start everything
.\start-full-stack.ps1              # Windows
./start-full-stack.sh               # Linux/Mac

# Test connection
.\test-mobile-connection.ps1        # Windows
./test-mobile-connection.sh         # Linux/Mac

# Stop everything
docker-compose down

# View logs
docker-compose logs -f

# Rebuild mobile app
cd mobile && flutter clean && flutter pub get && flutter run

# Check database
cd backend && php artisan tinker

# Run tests
cd backend && php artisan test
```

## Support Resources

- **Connection Guide:** MOBILE_BACKEND_CONNECTION_GUIDE.md
- **Android Build Guide:** mobile/ANDROID_BUILD_GUIDE.md
- **API Documentation:** backend/routes/api.php
- **Backend Setup:** backend/README.md
- **Mobile Setup:** mobile/README.md

## Emergency Reset

If everything is broken:

```bash
# 1. Stop all services
docker-compose down -v

# 2. Clean mobile app
cd mobile
flutter clean
rm -rf .dart_tool
rm -rf build

# 3. Restart from scratch
cd ..
.\start-full-stack.ps1

# 4. Rebuild mobile
cd mobile
flutter pub get
flutter run
```

---

**Need Help?**
1. Run the test script: `.\test-mobile-connection.ps1`
2. Check logs: `docker-compose logs -f`
3. Review connection guide: `MOBILE_BACKEND_CONNECTION_GUIDE.md`
4. Test API in browser first before testing in app
