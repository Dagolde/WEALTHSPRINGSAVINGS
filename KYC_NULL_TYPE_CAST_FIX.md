# KYC Null Type Cast Error - FIXED

## Problem
```
KYC Status Error: type 'Null' is not a subtype of type 'String' in type cast
```

## Root Cause Analysis

### Backend Response (Actual):
```json
{
  "success": true,
  "message": "KYC status retrieved successfully",
  "data": {
    "kyc_status": "verified",
    "kyc_document_url": "kyc_documents/user_480_1773338852.jpg",
    "kyc_rejection_reason": null,
    "submitted_at": "2026-03-12T18:33:36+00:00"
    // ❌ MISSING: verified_at field
    // ❌ MISSING: document_type field
  }
}
```

### Mobile Model (Expected):
```dart
class KycStatus {
  final String? verifiedAt;  // Expects this field to exist (even if null)
  final String? documentType; // Expects this field to exist (even if null)
}
```

### The Issue:
When `json_serializable` tries to deserialize the response, it expects all fields to be present in the JSON, even if they're nullable. When a field is completely missing (not even `null`), it causes a type cast error.

## Solution Implemented

### 1. Backend Fix - Add Missing Fields (`backend/app/Http/Controllers/Api/UserController.php`)

**Before:**
```php
return $this->successResponse([
    'kyc_status' => $user->kyc_status,
    'kyc_document_url' => $user->kyc_document_url,
    'kyc_rejection_reason' => $user->kyc_rejection_reason,
    'submitted_at' => $user->kyc_document_url ? $user->updated_at->toIso8601String() : null,
    // Missing verified_at
], 'KYC status retrieved successfully');
```

**After:**
```php
return $this->successResponse([
    'kyc_status' => $user->kyc_status,
    'kyc_document_url' => $user->kyc_document_url,
    'kyc_rejection_reason' => $user->kyc_rejection_reason,
    'submitted_at' => $user->kyc_document_url ? $user->updated_at->toIso8601String() : null,
    'verified_at' => $user->kyc_status === 'verified' ? $user->updated_at->toIso8601String() : null, // ✅ Added
], 'KYC status retrieved successfully');
```

### 2. Mobile Fix - Defensive Deserialization (`mobile/lib/repositories/kyc_repository.dart`)

**Added defensive code to handle missing fields:**
```dart
Future<KycStatus> getKycStatus() async {
  try {
    final response = await _apiClient.dio.get('/user/kyc/status');
    
    // Get the data object
    final data = response.data['data'] as Map<String, dynamic>;
    
    // Ensure verified_at field exists (add if missing)
    if (!data.containsKey('verified_at')) {
      data['verified_at'] = null;
    }
    
    // Ensure document_type field exists (add if missing)
    if (!data.containsKey('document_type')) {
      data['document_type'] = null;
    }
    
    return KycStatus.fromJson(data);
  } catch (e) {
    debugPrint('KYC Status Error: $e');
    throw _handleError(e);
  }
}
```

## Why This Happens

### JSON Serialization Behavior:
```dart
// This works:
{"field": null}  // Field exists with null value ✅

// This fails:
{}  // Field doesn't exist at all ❌
```

When using `@JsonSerializable()`, the generated code expects all fields to be present in the JSON, even nullable ones. If a field is completely missing, it tries to cast `null` to the field type, causing the error.

## Testing the Fix

### Test Case 1: Verified KYC
**Backend Response:**
```json
{
  "kyc_status": "verified",
  "kyc_document_url": "kyc_documents/user_480_1773338852.jpg",
  "kyc_rejection_reason": null,
  "submitted_at": "2026-03-12T18:33:36+00:00",
  "verified_at": "2026-03-12T18:33:36+00:00"  // ✅ Now included
}
```

**Expected Result:**
- Status loads successfully
- Shows "Verified" with green checkmark
- Timeline shows all steps completed
- Verified date displayed

### Test Case 2: Pending KYC
**Backend Response:**
```json
{
  "kyc_status": "pending",
  "kyc_document_url": "kyc_documents/user_480_1773338852.jpg",
  "kyc_rejection_reason": null,
  "submitted_at": "2026-03-12T18:33:36+00:00",
  "verified_at": null  // ✅ Explicitly null
}
```

**Expected Result:**
- Status loads successfully
- Shows "Verification Pending"
- Timeline shows "Under Review"

### Test Case 3: Rejected KYC
**Backend Response:**
```json
{
  "kyc_status": "rejected",
  "kyc_document_url": "kyc_documents/user_480_1773338852.jpg",
  "kyc_rejection_reason": "Document is blurry",
  "submitted_at": "2026-03-12T18:33:36+00:00",
  "verified_at": null  // ✅ Explicitly null
}
```

**Expected Result:**
- Status loads successfully
- Shows "Verification Rejected"
- Displays rejection reason
- Shows "Resubmit Document" button

## How to Test

### 1. Restart Backend (to apply PHP changes):
```bash
docker-compose restart laravel
```

### 2. Rebuild Mobile App:
```bash
cd mobile
flutter run
```

### 3. Test in App:
1. Login to the app
2. Navigate to Profile screen
3. Tap "Check KYC Status"
4. Verify status loads without errors

### 4. Check Logs:
```bash
flutter logs
```

Look for:
```
I/flutter: KYC Status Response: {success: true, message: ..., data: {...}}
```

Should NOT see:
```
I/flutter: KYC Status Error: type 'Null' is not a subtype of type 'String'
```

## Prevention Tips

### 1. Always Include All Fields in Backend Response
Even if a field is null, include it explicitly:
```php
// ❌ Bad
return ['field1' => $value];

// ✅ Good
return [
    'field1' => $value,
    'field2' => null,  // Explicitly include null fields
];
```

### 2. Use Defensive Deserialization in Mobile
Add default values for missing fields:
```dart
final data = response.data['data'] as Map<String, dynamic>;

// Add missing fields with null values
data.putIfAbsent('optional_field', () => null);

return MyModel.fromJson(data);
```

### 3. Test with Different Data States
Test your API with:
- New users (minimal data)
- Active users (full data)
- Edge cases (rejected, suspended, etc.)

### 4. Use API Documentation
Document all fields in your API responses, including nullable ones:
```php
/**
 * @return array{
 *   kyc_status: string,
 *   kyc_document_url: string|null,
 *   kyc_rejection_reason: string|null,
 *   submitted_at: string|null,
 *   verified_at: string|null  // Document this!
 * }
 */
```

## Files Modified

### Backend:
- `backend/app/Http/Controllers/Api/UserController.php` - Added `verified_at` field to response

### Mobile:
- `mobile/lib/repositories/kyc_repository.dart` - Added defensive field checking

### Documentation:
- `KYC_NULL_TYPE_CAST_FIX.md` - This file

## Related Issues

This same pattern can occur with other models. Check these files if you see similar errors:

- `mobile/lib/models/user.dart`
- `mobile/lib/models/group.dart`
- `mobile/lib/models/wallet.dart`
- `mobile/lib/models/contribution.dart`

## Conclusion

The KYC status loading error was caused by the backend not including the `verified_at` field in the response. The fix includes:

1. **Backend**: Added `verified_at` field to the response (set to `updated_at` for verified users, `null` otherwise)
2. **Mobile**: Added defensive code to handle missing fields by adding them with `null` values before deserialization

The app should now successfully load KYC status for all users without type cast errors.
