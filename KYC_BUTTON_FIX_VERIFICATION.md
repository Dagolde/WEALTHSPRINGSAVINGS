# KYC Button Fix - Verification Guide

## What Was Fixed

The KYC button in the Profile screen was showing a "coming soon" snackbar instead of navigating to the appropriate KYC screen.

### Changes Made
**File**: `mobile/lib/features/auth/screens/profile_screen.dart`

**Before**:
```dart
onPressed: () {
  ScaffoldMessenger.of(context).showSnackBar(
    const SnackBar(content: Text('KYC verification coming soon')),
  );
}
```

**After**:
```dart
onPressed: () {
  if (user.kycStatus == 'pending' || user.kycStatus == 'rejected') {
    context.push('/kyc/status');
  } else {
    context.push('/kyc/submit');
  }
}
```

## Button Behavior by KYC Status

| KYC Status | Button Text | Navigation Target | Purpose |
|------------|-------------|-------------------|---------|
| `not_submitted` | "Complete KYC Verification" | `/kyc/submit` | Submit new KYC document |
| `pending` | "Check KYC Status" | `/kyc/status` | View verification progress |
| `rejected` | "Resubmit KYC Document" | `/kyc/status` | View rejection reason and resubmit |
| `verified` | Button hidden | N/A | KYC already verified |

## Testing Steps

### Test 1: New User (No KYC Submitted)
1. Login with a user who hasn't submitted KYC
2. Navigate to Profile screen
3. Verify button shows "Complete KYC Verification"
4. Tap the button
5. **Expected**: Navigate to KYC Submission screen
6. **Verify**: Can select document type and upload image

### Test 2: Pending KYC
1. Login with a user who has pending KYC
2. Navigate to Profile screen
3. Verify button shows "Check KYC Status"
4. Tap the button
5. **Expected**: Navigate to KYC Status screen
6. **Verify**: Shows "Verification Pending" with timeline

### Test 3: Rejected KYC
1. Login with a user whose KYC was rejected
2. Navigate to Profile screen
3. Verify button shows "Resubmit KYC Document"
4. Tap the button
5. **Expected**: Navigate to KYC Status screen
6. **Verify**: Shows rejection reason and "Resubmit Document" button

### Test 4: Verified KYC
1. Login with a user whose KYC is verified
2. Navigate to Profile screen
3. **Expected**: KYC button is hidden
4. **Verify**: Only "Logout" button is visible

## End-to-End KYC Flow Test

### Step 1: Submit KYC
1. Open mobile app
2. Login as new user
3. Go to Profile → Tap "Complete KYC Verification"
4. Select "National ID" as document type
5. Take a photo or select from gallery
6. Tap "Submit for Verification"
7. **Expected**: Success message and return to profile
8. **Verify**: Button now shows "Check KYC Status"

### Step 2: Admin Review
1. Open admin dashboard in browser
2. Login as admin
3. Navigate to "KYC Verification" section
4. **Verify**: See the submitted KYC in pending list
5. Click "View" to see the document
6. Click "Reject" and enter reason: "Document unclear"
7. **Expected**: Success message and KYC removed from pending list

### Step 3: Check Rejection in Mobile
1. Return to mobile app
2. Go to Profile → Tap "Resubmit KYC Document"
3. **Expected**: Navigate to KYC Status screen
4. **Verify**: Shows "Verification Rejected" with rejection reason
5. Tap "Resubmit Document"
6. **Expected**: Navigate to KYC Submission screen
7. Upload a new document
8. **Expected**: Status changes back to "Pending"

### Step 4: Admin Approval
1. Return to admin dashboard
2. Navigate to "KYC Verification" section
3. **Verify**: See the resubmitted KYC
4. Click "Approve"
5. **Expected**: Success message and KYC removed from pending list

### Step 5: Verify Approval in Mobile
1. Return to mobile app
2. Go to Profile
3. **Expected**: KYC button is now hidden
4. **Verify**: KYC Status shows "Verified ✓"

## Quick Verification Commands

### Check Backend is Running
```bash
# Windows PowerShell
cd backend
php artisan serve
```

### Check Mobile App Connection
```bash
# Check if mobile can reach backend
# In mobile app, try to login - if successful, connection works
```

### Test API Endpoints Directly
```bash
# Get KYC Status
curl -X GET http://localhost:8000/api/v1/user/kyc/status \
  -H "Authorization: Bearer YOUR_TOKEN"

# Submit KYC (requires multipart form data)
# Use Postman or similar tool for file upload testing
```

## Common Issues & Solutions

### Issue 1: Button still shows "coming soon"
**Cause**: Old code still in place
**Solution**: Verify the profile_screen.dart file has the updated code

### Issue 2: Navigation doesn't work
**Cause**: Routes not registered in app_router.dart
**Solution**: Verify `/kyc/status` and `/kyc/submit` routes exist in router

### Issue 3: KYC status not updating
**Cause**: User model not refreshed
**Solution**: Logout and login again to refresh user data

### Issue 4: Document upload fails
**Cause**: File too large or wrong format
**Solution**: Use image <5MB in jpg, jpeg, png, or pdf format

## Files Modified

1. `mobile/lib/features/auth/screens/profile_screen.dart`
   - Updated KYC button onPressed handler
   - Added conditional navigation based on KYC status
   - Updated button text based on status

## Related Documentation

- Full KYC system documentation: `KYC_SYSTEM_SYNC_COMPLETE.md`
- Mobile type casting fixes: `MOBILE_TYPE_CASTING_FIX.md`
- API routes: `backend/routes/api.php`

## Status

✅ **FIXED** - KYC button now properly navigates to appropriate screens based on user's KYC status

## Next Steps

1. Test the button navigation in the mobile app
2. Verify end-to-end KYC flow works correctly
3. Test admin approval/rejection workflow
4. Ensure document upload works properly
5. Test resubmission flow for rejected KYC

---

**Last Updated**: 2024-03-12
**Issue**: KYC button not working in profile screen
**Resolution**: Updated button to navigate to correct KYC screens based on status
