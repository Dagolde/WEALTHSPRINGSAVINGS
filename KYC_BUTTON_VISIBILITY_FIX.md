# KYC Button Visibility Fix

## Problem
The KYC button was not appearing in the Profile screen for new users who haven't submitted KYC documents yet.

## Root Cause
1. New users are created with `kyc_status = 'pending'` by default (even before submitting any document)
2. The button text logic showed "Check KYC Status" for all pending users
3. There was no way to differentiate between:
   - "Pending submission" (no document submitted yet)
   - "Pending review" (document submitted and under review)

## Solution

### 1. Added `kyc_document_url` field to User model
**File**: `mobile/lib/models/user.dart`
- Added `kycDocumentUrl` field to track if user has submitted a document
- Added `hasSubmittedKyc` getter to check if document exists

### 2. Updated Profile Screen Logic
**File**: `mobile/lib/features/auth/screens/profile_screen.dart`
- Created `_getKycButtonText()` method with smart logic:
  - If rejected → "Resubmit KYC Document"
  - If pending AND has document → "Check KYC Status"
  - Otherwise → "Complete KYC Verification"
- Created `_handleKycNavigation()` method:
  - If has submitted document → Navigate to `/kyc/status`
  - Otherwise → Navigate to `/kyc/submit`

### 3. Updated Backend API Responses
**File**: `backend/app/Http/Controllers/Api/AuthController.php`
- Added `kyc_document_url` to registration response
- Added `kyc_document_url` to login response

## Button Behavior Matrix

| KYC Status | Has Document | Button Text | Navigation |
|------------|--------------|-------------|------------|
| pending | No | "Complete KYC Verification" | `/kyc/submit` |
| pending | Yes | "Check KYC Status" | `/kyc/status` |
| rejected | Yes | "Resubmit KYC Document" | `/kyc/status` |
| verified | N/A | Button hidden | N/A |

## Files Modified

1. `mobile/lib/models/user.dart`
   - Added `kycDocumentUrl` field
   - Added `hasSubmittedKyc` getter

2. `mobile/lib/features/auth/screens/profile_screen.dart`
   - Added `_getKycButtonText()` method
   - Added `_handleKycNavigation()` method
   - Updated button rendering logic

3. `backend/app/Http/Controllers/Api/AuthController.php`
   - Added `kyc_document_url` to registration response
   - Added `kyc_document_url` to login response

## Testing

### Test 1: New User (No KYC Submitted)
1. Register a new user
2. Login and go to Profile
3. **Expected**: Button shows "Complete KYC Verification"
4. Tap button
5. **Expected**: Navigate to KYC Submission screen

### Test 2: User with Pending KYC
1. Submit a KYC document
2. Go to Profile
3. **Expected**: Button shows "Check KYC Status"
4. Tap button
5. **Expected**: Navigate to KYC Status screen showing "Pending"

### Test 3: User with Rejected KYC
1. Admin rejects KYC
2. User goes to Profile
3. **Expected**: Button shows "Resubmit KYC Document"
4. Tap button
5. **Expected**: Navigate to KYC Status screen showing rejection reason

## Status
✅ **FIXED** - KYC button now appears correctly for all users

---
**Last Updated**: 2024-03-12
**Issue**: No KYC button visible in profile screen
**Resolution**: Added document URL tracking and smart button logic
