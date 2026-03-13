# KYC Status Loading Fix

## Problem
Mobile app showing "Failed to load KYC status - An unexpected error occurred" when trying to view KYC status.

## Root Cause
**JSON Serialization Mismatch**: The backend returns `kyc_status` but the mobile model was expecting `status` without the `@JsonKey` annotation to map the field name.

### Backend Response Format:
```json
{
  "success": true,
  "message": "KYC status retrieved successfully",
  "data": {
    "kyc_status": "pending",
    "kyc_document_url": "kyc_documents/user_1_1234567890.jpg",
    "kyc_rejection_reason": null,
    "submitted_at": "2024-01-15T10:30:00Z"
  }
}
```

### Mobile Model (Before Fix):
```dart
class KycStatus {
  final String status;  // ❌ No @JsonKey annotation
  // ...
}
```

### Mobile Model (After Fix):
```dart
class KycStatus {
  @JsonKey(name: 'kyc_status')  // ✅ Maps to backend field
  final String status;
  // ...
}
```

## Solution Implemented

### 1. Fixed KYC Model (`mobile/lib/models/kyc.dart`)

**Changes:**
- Added `@JsonKey(name: 'kyc_status')` to map `status` field
- Added `@JsonKey(name: 'kyc_rejection_reason')` to map `rejectionReason` field
- Added `@JsonKey(name: 'kyc_document_url')` to map `documentUrl` field

**Updated Model:**
```dart
@JsonSerializable()
class KycStatus {
  @JsonKey(name: 'kyc_status')
  final String status;
  
  @JsonKey(name: 'kyc_rejection_reason')
  final String? rejectionReason;
  
  @JsonKey(name: 'submitted_at')
  final String? submittedAt;
  
  @JsonKey(name: 'verified_at')
  final String? verifiedAt;
  
  @JsonKey(name: 'kyc_document_url')
  final String? documentUrl;
  
  @JsonKey(name: 'document_type')
  final String? documentType;

  // ... rest of the model
}
```

### 2. Enhanced Error Logging (`mobile/lib/repositories/kyc_repository.dart`)

**Added:**
- Debug logging for API responses
- Null checks for response data
- Detailed error messages

**Updated Code:**
```dart
Future<KycStatus> getKycStatus() async {
  try {
    final response = await _apiClient.dio.get('/user/kyc/status');
    
    // Log the response for debugging
    debugPrint('KYC Status Response: ${response.data}');
    
    // Check if response has data
    if (response.data == null) {
      throw Exception('No data received from server');
    }
    
    // Check if data field exists
    if (response.data['data'] == null) {
      throw Exception('Invalid response format: missing data field');
    }
    
    return KycStatus.fromJson(response.data['data']);
  } catch (e) {
    debugPrint('KYC Status Error: $e');
    throw _handleError(e);
  }
}
```

### 3. Created Model Regeneration Script (`mobile/regenerate-models.ps1`)

**Purpose:** Regenerate JSON serialization code after model changes

**Usage:**
```powershell
./mobile/regenerate-models.ps1
```

**What it does:**
- Runs `flutter pub run build_runner build --delete-conflicting-outputs`
- Generates `.g.dart` files for all models
- Ensures JSON serialization works correctly

## Steps to Fix

### Step 1: Regenerate JSON Serialization Code
```powershell
cd mobile
flutter pub run build_runner build --delete-conflicting-outputs
```

This will generate `kyc.g.dart` with the correct field mappings.

### Step 2: Rebuild the App
```powershell
flutter run
```

### Step 3: Test KYC Status Loading
1. Login to the app
2. Navigate to Profile screen
3. Tap "Check KYC Status" or "Complete KYC Verification"
4. KYC status should load successfully

## Testing the Fix

### Test Case 1: User with No KYC Submission
**Expected:**
- Status: "pending"
- No document URL
- No submission date
- Shows "Complete KYC Verification" button

### Test Case 2: User with Pending KYC
**Expected:**
- Status: "pending"
- Document URL present
- Submission date shown
- Timeline shows "Under Review"

### Test Case 3: User with Verified KYC
**Expected:**
- Status: "verified"
- Green checkmark icon
- "Verified" title
- Timeline shows all steps completed

### Test Case 4: User with Rejected KYC
**Expected:**
- Status: "rejected"
- Red X icon
- Rejection reason displayed
- "Resubmit Document" button shown

## Debugging Tips

### Check Flutter Logs:
```bash
flutter logs
```

Look for:
```
KYC Status Response: {success: true, message: ..., data: {...}}
```

### Check for Errors:
```
KYC Status Error: Exception: ...
```

### Common Errors and Solutions:

#### Error: "type 'Null' is not a subtype of type 'String'"
**Cause:** Missing `@JsonKey` annotation or wrong field name
**Solution:** Ensure all field names match backend response

#### Error: "Invalid response format: missing data field"
**Cause:** Backend not returning data in expected format
**Solution:** Check backend endpoint is working: `curl http://localhost:8002/api/v1/user/kyc/status -H "Authorization: Bearer <token>"`

#### Error: "No data received from server"
**Cause:** Backend returned null response
**Solution:** Check backend logs, ensure user is authenticated

## Backend Endpoint Details

### Endpoint: GET /api/v1/user/kyc/status

**Headers:**
```
Authorization: Bearer <jwt_token>
Accept: application/json
```

**Response:**
```json
{
  "success": true,
  "message": "KYC status retrieved successfully",
  "data": {
    "kyc_status": "pending|verified|rejected",
    "kyc_document_url": "kyc_documents/user_1_1234567890.jpg",
    "kyc_rejection_reason": "Document is blurry",
    "submitted_at": "2024-01-15T10:30:00Z"
  }
}
```

## Files Modified

### Modified Files:
- `mobile/lib/models/kyc.dart` - Fixed field mappings
- `mobile/lib/repositories/kyc_repository.dart` - Added error logging

### New Files:
- `mobile/regenerate-models.ps1` - Model regeneration script
- `KYC_STATUS_LOADING_FIX.md` - This documentation

## Prevention

To prevent similar issues in the future:

### 1. Always Use @JsonKey for Backend Fields
```dart
@JsonSerializable()
class MyModel {
  @JsonKey(name: 'backend_field_name')
  final String myField;
}
```

### 2. Test JSON Serialization
```dart
// Test deserialization
final json = {'backend_field_name': 'value'};
final model = MyModel.fromJson(json);
assert(model.myField == 'value');

// Test serialization
final serialized = model.toJson();
assert(serialized['backend_field_name'] == 'value');
```

### 3. Add Debug Logging
```dart
debugPrint('API Response: ${response.data}');
```

### 4. Regenerate After Model Changes
```bash
flutter pub run build_runner build --delete-conflicting-outputs
```

## Conclusion

The KYC status loading issue was caused by a JSON field name mismatch between the backend response (`kyc_status`) and the mobile model (`status`). Adding the `@JsonKey` annotation fixed the deserialization, and regenerating the `.g.dart` files ensured the fix was applied.

The app should now successfully load and display KYC status for all users.
