# Admin Authentication and Authorization

This document describes the admin authentication and authorization system implemented for the Rotational Contribution App.

## Overview

The admin authentication system provides secure access control for administrative functions, including user management, KYC approval, group oversight, and withdrawal approval.

## Components

### 1. Admin Role System

Users have a `role` field that can be either `'user'` or `'admin'`. The role is stored in the `users` table and determines access to admin-only endpoints.

**Database Schema:**
```sql
ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user';
```

### 2. Admin Authentication Guard

Admin users authenticate using a separate login endpoint that verifies both credentials and admin role.

**Endpoint:** `POST /api/v1/auth/admin/login`

**Request:**
```json
{
  "email": "admin@ajo.test",
  "password": "password"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Admin login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@ajo.test",
      "role": "admin"
    },
    "token": "1|abcdef123456..."
  }
}
```

**Security Features:**
- Validates user credentials
- Verifies admin role
- Checks account status (must be active)
- Rate limited (5 attempts per 15 minutes)
- Returns JWT token with admin abilities

### 3. Admin Middleware

The `EnsureUserIsAdmin` middleware protects admin routes by verifying:
1. User is authenticated
2. User has `role = 'admin'`

**Middleware Alias:** `admin`

**Usage:**
```php
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Admin routes
});
```

**Error Response (403 Forbidden):**
```json
{
  "success": false,
  "error": {
    "code": "FORBIDDEN",
    "message": "Access denied. Admin privileges required."
  }
}
```

### 4. Admin Seeder

The `AdminUserSeeder` creates an initial admin user for system setup.

**Run Seeder:**
```bash
php artisan db:seed --class=AdminUserSeeder
```

**Default Admin Credentials:**
- Email: `admin@ajo.test`
- Password: `password`
- Role: `admin`
- Status: `active`
- KYC Status: `verified`

**Note:** Change the default password in production!

## Admin Endpoints

All admin endpoints require authentication and admin role.

### Dashboard Statistics

**GET** `/api/v1/admin/dashboard/stats`

Returns comprehensive statistics about users, groups, transactions, and system health.

### User Management

- **GET** `/api/v1/admin/users` - List all users with filters
- **GET** `/api/v1/admin/users/{id}` - Get user details
- **PUT** `/api/v1/admin/users/{id}/suspend` - Suspend a user
- **PUT** `/api/v1/admin/users/{id}/activate` - Activate a user

### KYC Management

- **POST** `/api/v1/admin/kyc/{id}/approve` - Approve KYC submission
- **POST** `/api/v1/admin/kyc/{id}/reject` - Reject KYC submission

### Group Management

- **GET** `/api/v1/admin/groups` - List all groups
- **GET** `/api/v1/admin/groups/{id}` - Get group details

### Withdrawal Management

- **GET** `/api/v1/admin/withdrawals/pending` - List pending withdrawals
- **POST** `/api/v1/admin/withdrawals/{id}/approve` - Approve withdrawal
- **POST** `/api/v1/admin/withdrawals/{id}/reject` - Reject withdrawal

## Audit Logging

All admin actions are logged in the `audit_logs` table for accountability and compliance.

**Logged Information:**
- Admin user ID
- Action performed
- Entity type and ID
- Old and new values
- IP address
- User agent
- Timestamp

**Example Audit Log Entry:**
```json
{
  "user_id": 1,
  "action": "user_suspended",
  "entity_type": "User",
  "entity_id": 42,
  "old_values": {"status": "active"},
  "new_values": {"status": "suspended", "reason": "Suspicious activity"},
  "ip_address": "192.168.1.1",
  "user_agent": "Mozilla/5.0...",
  "created_at": "2024-03-15 10:30:00"
}
```

## Security Considerations

### 1. Admin Protection

- Admins cannot suspend other admin users
- Admin role can only be assigned via database seeder or direct database access
- No API endpoint exists to promote users to admin (prevents privilege escalation)

### 2. Rate Limiting

Admin login endpoint is rate limited to prevent brute force attacks:
- 5 attempts per 15 minutes per IP address

### 3. Token Security

- Admin tokens are issued with specific abilities
- Tokens should be stored securely on the client side
- Tokens should be transmitted only over HTTPS

### 4. Password Security

- Passwords are hashed using bcrypt
- Default admin password should be changed immediately in production
- Consider implementing 2FA for admin accounts in production

## Testing

Comprehensive test suites are provided:

### Test Files

1. `tests/Feature/AdminAuthenticationTest.php` - Admin login tests
2. `tests/Feature/AdminMiddlewareTest.php` - Middleware authorization tests
3. `tests/Feature/AdminEndpointsTest.php` - Admin endpoint functionality tests

### Run Tests

```bash
# Run all admin tests
php artisan test --filter Admin

# Run specific test file
php artisan test tests/Feature/AdminAuthenticationTest.php
```

## Production Deployment Checklist

- [ ] Change default admin password
- [ ] Verify admin seeder has run
- [ ] Confirm HTTPS is enforced for all admin endpoints
- [ ] Review and adjust rate limiting settings
- [ ] Set up monitoring for admin actions
- [ ] Configure audit log retention policy
- [ ] Consider implementing 2FA for admin accounts
- [ ] Restrict admin access to specific IP ranges (optional)
- [ ] Set up alerts for suspicious admin activity

## Example Usage

### 1. Admin Login

```bash
curl -X POST http://localhost/api/v1/auth/admin/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@ajo.test",
    "password": "password"
  }'
```

### 2. Access Admin Dashboard

```bash
curl -X GET http://localhost/api/v1/admin/dashboard/stats \
  -H "Authorization: Bearer {admin_token}"
```

### 3. Suspend User

```bash
curl -X PUT http://localhost/api/v1/admin/users/42/suspend \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "reason": "Suspicious activity detected"
  }'
```

### 4. Approve KYC

```bash
curl -X POST http://localhost/api/v1/admin/kyc/42/approve \
  -H "Authorization: Bearer {admin_token}"
```

## Troubleshooting

### Issue: "Access denied. Admin privileges required."

**Cause:** User is not an admin or not authenticated.

**Solution:**
1. Verify user has `role = 'admin'` in database
2. Ensure using admin login endpoint
3. Check token is valid and included in Authorization header

### Issue: Admin seeder fails

**Cause:** Database connection issue or user already exists.

**Solution:**
1. Check database connection
2. If admin exists, seeder will update existing user
3. Verify migrations have run

### Issue: Cannot suspend admin user

**Cause:** System prevents admins from suspending other admins.

**Solution:** This is by design for security. To suspend an admin, change their role to 'user' first via database.

## Future Enhancements

Potential improvements for the admin system:

1. **Role-Based Permissions:** Implement granular permissions (e.g., `can_approve_kyc`, `can_suspend_users`)
2. **Two-Factor Authentication:** Add 2FA requirement for admin login
3. **Admin Activity Dashboard:** Real-time monitoring of admin actions
4. **IP Whitelisting:** Restrict admin access to specific IP ranges
5. **Session Management:** Track and manage active admin sessions
6. **Bulk Operations:** Support bulk user/KYC operations
7. **Advanced Filtering:** More sophisticated filtering and search capabilities
8. **Export Functionality:** Export user/group/transaction data for analysis
