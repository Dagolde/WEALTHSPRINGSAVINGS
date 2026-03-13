# Admin Profile & User Creation Features

## ✅ NEW FEATURES ADDED

Your super admin dashboard now has:

1. **Admin Profile Management** - Manage your own admin account
2. **User Creation** - Create new users with role assignment
3. **User Editing** - Edit existing user details and roles
4. **Role Assignment** - Assign admin or user roles to any account

---

## 1. Admin Profile Management

### Access Your Profile
Click **"My Profile"** in the sidebar navigation.

### What You Can Do

#### View Profile Information
- Admin ID
- Role (admin)
- Status (active)
- Member since date

#### Update Personal Information
- **Full Name** - Change your display name
- **Email** - Update your email address
- **Phone Number** - Update your phone number

#### Change Password
- Enter current password
- Set new password (minimum 8 characters)
- Confirm new password
- Secure password update

### How to Update Profile

1. Navigate to **My Profile**
2. Edit the fields you want to change
3. Click **"Update Profile"**
4. Your changes are saved immediately
5. Your name in the header updates automatically

### How to Change Password

1. Navigate to **My Profile**
2. Scroll to **"Change Password"** section
3. Enter your current password
4. Enter new password (min 8 characters)
5. Confirm new password
6. Click **"Change Password"**
7. You'll receive a success notification

---

## 2. User Creation (Super Admin Power!)

### Create New Users
Click **"Create User"** button in the Users section.

### User Creation Form

#### Required Fields

1. **Full Name**
   - User's complete name
   - Example: "John Doe"

2. **Email**
   - Must be unique
   - Example: "john@example.com"

3. **Phone Number**
   - Must be unique
   - Format: +234...
   - Example: "+2348012345678"

4. **Password**
   - Minimum 8 characters
   - User will use this to login

5. **Role** ⭐ IMPORTANT
   - **User** - Regular user (default)
     - Can use mobile app
     - Access to user features only
     - Cannot access admin dashboard
   
   - **Admin** - Super Admin
     - Full platform access
     - Can access admin dashboard
     - Can manage all users
     - Can approve KYC, withdrawals, etc.

6. **Status**
   - **Active** - User can login and use platform
   - **Inactive** - User cannot login

7. **KYC Status**
   - **Pending** - KYC not verified
   - **Verified** - KYC approved
   - **Rejected** - KYC rejected

### How to Create a User

1. Go to **Users** section
2. Click **"Create User"** button
3. Fill in all required fields
4. Select appropriate **Role** (User or Admin)
5. Set **Status** (Active/Inactive)
6. Set **KYC Status** if needed
7. Click **"Create User"**
8. User is created immediately
9. User can now login with the credentials

### Example: Creating an Admin User

```
Name: Jane Smith
Email: jane@ajo.test
Phone: +2348023456789
Password: SecurePass123
Role: Admin ← Makes them super admin!
Status: Active
KYC Status: Verified
```

Jane can now login to the admin dashboard with full access!

### Example: Creating a Regular User

```
Name: Mike Johnson
Email: mike@example.com
Phone: +2348034567890
Password: UserPass123
Role: User ← Regular user
Status: Active
KYC Status: Pending
```

Mike can login to the mobile app as a regular user.

---

## 3. User Editing

### Edit Existing Users
Click **"Edit"** button next to any user in the Users list.

### What You Can Edit

1. **Full Name** - Change user's name
2. **Email** - Update email address
3. **Phone Number** - Update phone number
4. **Role** - Change between User and Admin
5. **Status** - Change Active/Suspended/Inactive
6. **KYC Status** - Change Pending/Verified/Rejected

### How to Edit a User

1. Go to **Users** section
2. Find the user you want to edit
3. Click **"Edit"** button
4. Modify the fields you want to change
5. Click **"Save Changes"**
6. Changes are applied immediately

### Promote User to Admin

1. Find the user in Users list
2. Click **"Edit"**
3. Change **Role** from "User" to "Admin"
4. Click **"Save Changes"**
5. User now has admin access!

### Demote Admin to User

1. Find the admin in Users list
2. Click **"Edit"**
3. Change **Role** from "Admin" to "User"
4. Click **"Save Changes"**
5. User loses admin access

---

## 4. Role Management

### Understanding Roles

#### User Role (Regular User)
- **Access**: Mobile app only
- **Permissions**:
  - Register and login
  - Complete KYC
  - Join/create groups
  - Make contributions
  - Request withdrawals
  - View wallet and transactions
  - Manage profile
- **Cannot Access**: Admin dashboard

#### Admin Role (Super Admin)
- **Access**: Admin dashboard + Mobile app
- **Permissions**:
  - Everything a User can do, PLUS:
  - View all users
  - Create new users
  - Edit user details
  - Suspend/activate users
  - Approve/reject KYC
  - Approve/reject withdrawals
  - View all groups
  - View all transactions
  - Access analytics
  - Export data
  - Manage system settings

### Role Assignment Best Practices

1. **Default to User Role**
   - Most accounts should be regular users
   - Only create admins when necessary

2. **Admin Role Security**
   - Only assign to trusted individuals
   - Use strong passwords for admin accounts
   - Monitor admin activity

3. **Role Changes**
   - Can promote users to admin anytime
   - Can demote admins to users anytime
   - Changes take effect immediately

---

## 5. Complete Workflows

### Workflow 1: Create a New Admin

1. Click **"Create User"** in Users section
2. Fill in personal details
3. Set **Role** to **"Admin"**
4. Set **Status** to **"Active"**
5. Set **KYC Status** to **"Verified"** (optional)
6. Click **"Create User"**
7. New admin can now login to admin dashboard

### Workflow 2: Create a Regular User

1. Click **"Create User"** in Users section
2. Fill in personal details
3. Set **Role** to **"User"**
4. Set **Status** to **"Active"**
5. Set **KYC Status** to **"Pending"**
6. Click **"Create User"**
7. User can now login to mobile app

### Workflow 3: Promote User to Admin

1. Go to **Users** section
2. Find the user
3. Click **"Edit"**
4. Change **Role** to **"Admin"**
5. Click **"Save Changes"**
6. User now has admin access

### Workflow 4: Update Your Own Profile

1. Click **"My Profile"** in sidebar
2. Update your name, email, or phone
3. Click **"Update Profile"**
4. Your profile is updated

### Workflow 5: Change Your Password

1. Click **"My Profile"** in sidebar
2. Scroll to "Change Password"
3. Enter current password
4. Enter new password twice
5. Click **"Change Password"**
6. Password is updated

---

## 6. API Endpoints Used

### Profile Management
- `GET /user/profile` - Get admin profile
- `PUT /user/profile` - Update profile
- `PUT /user/change-password` - Change password

### User Management
- `POST /auth/register` - Create new user
- `GET /admin/users/{id}` - Get user details
- `PUT /admin/users/{id}` - Update user details

---

## 7. Security Features

### Password Requirements
- Minimum 8 characters
- Required for all new users
- Can be changed by admin or user

### Role-Based Access
- Users cannot access admin dashboard
- Admins have full platform access
- Roles can be changed anytime

### Audit Trail
- All user creations logged
- All role changes tracked
- All profile updates recorded

---

## 8. Quick Reference

### Create Admin User
```
Role: Admin
Status: Active
KYC Status: Verified (optional)
```

### Create Regular User
```
Role: User
Status: Active
KYC Status: Pending
```

### Promote to Admin
```
Edit User → Role: Admin → Save
```

### Update Your Profile
```
My Profile → Edit Fields → Update Profile
```

### Change Your Password
```
My Profile → Change Password → Enter Details → Change Password
```

---

## Summary

You now have COMPLETE control over:

✅ **Your own admin profile** - Update name, email, phone, password
✅ **User creation** - Create new users with any role
✅ **Role assignment** - Make anyone an admin or regular user
✅ **User editing** - Change any user's details or role
✅ **Full user management** - Create, edit, suspend, activate, delete

This gives you TOTAL control over user management and admin access!
