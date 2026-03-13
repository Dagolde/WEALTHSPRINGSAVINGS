# User Registration & Dashboard Access Guide

## ✅ CONFIRMED: Users CAN Register and Access Dashboard!

The mobile app is fully functional for user registration and dashboard access.

---

## How It Works

### 1. User Registration Flow

Users can register via the mobile app with these steps:

1. **Open Mobile App** → Tap "Create Account" on login screen
2. **Fill Registration Form**:
   - Full Name
   - Email Address
   - Phone Number (+234...)
   - Password (min 8 characters)
   - Confirm Password
3. **Submit** → Account created automatically
4. **Auto-Login** → Redirected to home dashboard

### 2. What Users Get Access To

After registration, users can access:

- **Home Dashboard**: Wallet balance, active groups, quick actions
- **Wallet Management**: Fund wallet, view transactions, withdraw funds
- **Group Management**: Create groups, join groups, view payout schedules
- **Contributions**: Make contributions, view history, track missed payments
- **KYC Submission**: Upload documents for verification
- **Bank Accounts**: Link bank accounts for payouts
- **Notifications**: View and manage notifications
- **Profile**: Update personal information

---

## Mobile App Setup (For Users)

### Prerequisites

1. **Backend Running**: Docker containers must be running
   ```powershell
   docker-compose ps
   ```

2. **Network Connection**: Mobile device and computer on same WiFi

3. **Firewall Configuration**: Port 8002 must be open
   ```powershell
   .\allow-mobile-connection.ps1
   ```

### Mobile App Configuration

The app is already configured to connect to your local backend:

**File**: `mobile/.env`
```env
API_BASE_URL=http://192.168.1.106:8002/api/v1
```

### Testing Registration

Run this script to verify registration works:
```powershell
.\test-user-registration.ps1
```

---

## API Endpoints Used

### Authentication
- `POST /api/v1/auth/register` - User registration
- `POST /api/v1/auth/login` - User login
- `POST /api/v1/auth/logout` - User logout
- `POST /api/v1/auth/verify-otp` - OTP verification

### User Dashboard
- `GET /api/v1/user/profile` - Get user profile
- `PUT /api/v1/user/profile` - Update profile
- `GET /api/v1/user/dashboard` - Dashboard statistics

### Wallet
- `GET /api/v1/wallet` - Get wallet balance
- `POST /api/v1/wallet/fund` - Fund wallet
- `POST /api/v1/wallet/withdraw` - Withdraw funds
- `GET /api/v1/wallet/transactions` - Transaction history

### Groups
- `GET /api/v1/groups` - List user's groups
- `POST /api/v1/groups` - Create new group
- `POST /api/v1/groups/{id}/join` - Join a group
- `GET /api/v1/groups/{id}` - Get group details

### Contributions
- `POST /api/v1/contributions` - Make contribution
- `GET /api/v1/contributions` - View contribution history
- `GET /api/v1/contributions/missed` - View missed contributions

### KYC
- `POST /api/v1/kyc/submit` - Submit KYC documents
- `GET /api/v1/kyc/status` - Check KYC status

### Bank Accounts
- `GET /api/v1/bank-accounts` - List bank accounts
- `POST /api/v1/bank-accounts` - Add bank account
- `DELETE /api/v1/bank-accounts/{id}` - Remove bank account

---

## User Roles

### Regular User (Default)
- Can register via mobile app
- Access to all user features
- Cannot access admin dashboard
- Role: `user`

### Admin User
- Cannot register via mobile app
- Created via seeder or database
- Access to admin web dashboard
- Role: `admin`
- Credentials: `admin@ajo.test` / `password`

---

## Testing User Flow

### 1. Register New User

**Via Mobile App**:
1. Open app on your phone
2. Tap "Create Account"
3. Fill in details
4. Submit

**Via API (for testing)**:
```powershell
$userData = @{
    name = "John Doe"
    email = "john@example.com"
    phone = "+2348012345678"
    password = "password123"
}

Invoke-RestMethod -Uri "http://localhost:8002/api/v1/auth/register" `
    -Method Post `
    -ContentType "application/json" `
    -Body ($userData | ConvertTo-Json)
```

### 2. Login

**Via Mobile App**:
1. Enter email and password
2. Tap "Login"
3. Redirected to home dashboard

**Via API**:
```powershell
$loginData = @{
    email = "john@example.com"
    password = "password123"
}

$response = Invoke-RestMethod -Uri "http://localhost:8002/api/v1/auth/login" `
    -Method Post `
    -ContentType "application/json" `
    -Body ($loginData | ConvertTo-Json)

$token = $response.data.token
```

### 3. Access Dashboard

**Via Mobile App**:
- Automatically shown after login
- Displays wallet balance, groups, quick actions

**Via API**:
```powershell
$headers = @{
    "Authorization" = "Bearer $token"
    "Accept" = "application/json"
}

Invoke-RestMethod -Uri "http://localhost:8002/api/v1/user/profile" `
    -Method Get `
    -Headers $headers
```

---

## Troubleshooting

### Mobile App Can't Connect

1. **Check Backend**:
   ```powershell
   docker-compose ps
   ```
   All containers should be "Up"

2. **Check Firewall**:
   ```powershell
   .\allow-mobile-connection.ps1
   ```
   Run as Administrator

3. **Verify IP Address**:
   ```powershell
   ipconfig
   ```
   Update `mobile/.env` with correct IP

4. **Test Connection**:
   ```powershell
   .\test-mobile-backend-connection.ps1
   ```

### Registration Fails

1. **Check Validation Errors**:
   - Email must be unique
   - Phone must be unique
   - Password min 8 characters

2. **Check Database**:
   ```powershell
   docker exec ajo_laravel php artisan tinker --execute="echo User::count();"
   ```

3. **View Logs**:
   ```powershell
   docker logs ajo_laravel
   ```

### Can't Access Dashboard

1. **Verify Token**:
   - Token should be saved after login
   - Check mobile app storage

2. **Check Auth Middleware**:
   - Ensure token is sent in Authorization header
   - Format: `Bearer {token}`

3. **Test API Directly**:
   ```powershell
   .\test-user-registration.ps1
   ```

---

## Quick Start Commands

### Start Backend
```powershell
docker-compose up -d
```

### Configure Firewall
```powershell
.\allow-mobile-connection.ps1
```

### Test Registration
```powershell
.\test-user-registration.ps1
```

### Open Admin Dashboard
```powershell
.\open-admin-dashboard.ps1
```

### View Users
```powershell
docker exec ajo_laravel php artisan tinker --execute="User::all(['id', 'name', 'email', 'role'])->toArray()"
```

---

## Summary

✅ **Users CAN register via mobile app**
✅ **Users CAN login with credentials**
✅ **Users CAN access their dashboard**
✅ **All user features are functional**

The mobile app is fully set up and ready for user registration and dashboard access!
