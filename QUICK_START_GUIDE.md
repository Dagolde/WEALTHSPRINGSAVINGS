# Quick Start Guide - Ajo Platform

## For Users (Mobile App)

### 1. Register
1. Open mobile app
2. Tap "Create Account"
3. Fill in: Name, Email, Phone, Password
4. Submit → Auto-login to dashboard

### 2. Access Dashboard
- View wallet balance
- See active groups
- Make contributions
- Manage profile

---

## For Admins (Web Dashboard)

### 1. Access Dashboard
```powershell
.\open-admin-dashboard.ps1
```
Or open: `admin-dashboard/index.html`

### 2. Login
- **Email**: `admin@ajo.test`
- **Password**: `password`

### 3. Manage Platform
- View statistics
- Manage users
- Approve KYC
- Monitor groups

---

## Setup Commands

### Start Backend
```powershell
docker-compose up -d
```

### Configure Mobile Connection
```powershell
.\allow-mobile-connection.ps1
```

### Create Admin User
```powershell
docker exec ajo_laravel php artisan db:seed --class=AdminUserSeeder
```

### Test Everything Works
```powershell
.\test-user-registration.ps1
```

---

## Key URLs

- **Backend API**: `http://localhost:8002/api/v1`
- **Mobile API**: `http://192.168.1.106:8002/api/v1`
- **Admin Dashboard**: `admin-dashboard/index.html`

---

## Troubleshooting

### Mobile App Can't Connect
1. Check Docker: `docker-compose ps`
2. Configure firewall: `.\allow-mobile-connection.ps1`
3. Verify IP in `mobile/.env`

### Admin Can't Login
1. Create admin: `docker exec ajo_laravel php artisan db:seed --class=AdminUserSeeder`
2. Use credentials: `admin@ajo.test` / `password`

### Backend Not Running
```powershell
docker-compose up -d
docker-compose ps
```

---

## What Works

✅ User registration via mobile app
✅ User login and dashboard access
✅ Admin web dashboard
✅ All API endpoints functional
✅ Database migrations complete
✅ Docker services running

---

## Next Steps

1. **For Development**:
   - Add more test users
   - Create sample groups
   - Test contribution flows

2. **For Production**:
   - Change admin password
   - Configure real payment gateway
   - Set up SSL/HTTPS
   - Configure production environment

---

## Support Files

- `USER_REGISTRATION_GUIDE.md` - Detailed user registration guide
- `MOBILE_LOCAL_CONNECTION_SETUP.md` - Mobile connection setup
- `admin-dashboard/README.md` - Admin dashboard documentation
- `MOBILE_CONNECTION_TROUBLESHOOTING.md` - Connection troubleshooting
