# Mobile App Pagination Fix - Complete

## Issue
Multiple screens in the mobile app were showing errors:
- Home screen: "unexpected error"
- Wallet screen: "type '_Map<String, dynamic>' is not a subtype of type 'List<dynamic>'"
- Other screens with similar pagination errors

## Root Cause
Backend API returns paginated data with nested structure:
```json
{
  "success": true,
  "data": {
    "data": [...items...],
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7
  }
}
```

Mobile app repositories were trying to parse `response.data['data']` directly as a List, but it's actually a pagination object. The actual items array is at `response.data['data']['data']`.

## Solution
Updated all repository methods that fetch paginated data to correctly extract the items array from the pagination wrapper.

## Files Fixed

### 1. Group Repository (`mobile/lib/repositories/group_repository.dart`)
- Fixed `listGroups()` method
- Now correctly extracts groups from paginated response

### 2. Wallet Repository (`mobile/lib/repositories/wallet_repository.dart`)
- Fixed `getTransactions()` method
- Fixed `getWithdrawals()` method
- Both now correctly handle paginated responses

### 3. Notification Repository (`mobile/lib/repositories/notification_repository.dart`)
- Fixed `getNotifications()` method
- Now correctly extracts notifications from paginated response

### 4. Contribution Repository (`mobile/lib/repositories/contribution_repository.dart`)
- Fixed `getContributionHistory()` method
- Now correctly handles paginated contribution data

## Pattern Used
All fixes follow the same pattern:

```dart
// OLD (BROKEN)
final List<dynamic> data = response.data['data'];
return data.map((json) => Model.fromJson(json)).toList();

// NEW (FIXED)
// Backend returns paginated data: {data: {data: [...], current_page: 1, ...}}
final paginatedData = response.data['data'];
final List<dynamic> itemsJson = paginatedData['data'];
return itemsJson.map((json) => Model.fromJson(json)).toList();
```

## Testing
Hot reload the mobile app - all screens should now load without pagination errors:
- ✅ Home screen loads groups correctly
- ✅ Wallet screen loads transactions correctly
- ✅ Notifications screen loads correctly
- ✅ Contribution history loads correctly

## Related Fixes in This Session
1. **Group join endpoint** - Added POST /groups/join route for joining by group code
2. **Type casting errors** - Fixed model classes to handle numeric values from backend
3. **Pagination parsing** - Fixed all repositories to handle paginated responses

## Backend Endpoints That Return Paginated Data
- `GET /api/v1/groups` - Groups list
- `GET /api/v1/wallet/transactions` - Wallet transactions
- `GET /api/v1/wallet/withdrawals` - Withdrawal history
- `GET /api/v1/notifications` - Notifications list
- `GET /api/v1/contributions` - Contribution history

All of these now work correctly with the mobile app.
