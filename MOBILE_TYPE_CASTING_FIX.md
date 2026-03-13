# Mobile App Type Casting Error Fix

## Issue
The mobile app was experiencing type casting errors:
1. `type '_Map<String, dynamic>' is not a subtype of type 'List<dynamic>'`
2. `Type Null is not a subtype of type 'List<dynamic>'`

These errors occurred across multiple screens, particularly in the wallet and home screens.

## Root Cause
The repositories were making unsafe assumptions about API responses:
1. Assuming responses always return lists when parsing paginated data
2. Not handling null responses from the API
3. Not checking if data exists before trying to cast it to a List
4. The response structure might vary between paginated and non-paginated endpoints

## Solution
Added comprehensive null-safety and type checking in all repository methods that parse list data from API responses. The fix:

1. **Null checks first**: Check if `response.data['data']` is null before processing
2. **Paginated response handling**: Check if data is a Map with a 'data' key
3. **Fallback to direct data**: Use the data directly if not paginated
4. **Null data handling**: Return empty list if data is null after extraction
5. **List conversion**: Ensure the result is always a List by wrapping single objects
6. **Type safety**: Add explicit type casting with `as Map<String, dynamic>`

## Files Fixed

### 1. Group Repository (`mobile/lib/repositories/group_repository.dart`)
- `listGroups()` - Fixed paginated groups parsing with null checks
- `getGroupMembers()` - Fixed members list parsing with null checks
- `getPayoutSchedule()` - Fixed schedule list parsing with null checks

### 2. Wallet Repository (`mobile/lib/repositories/wallet_repository.dart`)
- `getTransactions()` - Fixed transaction history parsing with null checks
- `getWithdrawals()` - Fixed withdrawals list parsing with null checks

### 3. Notification Repository (`mobile/lib/repositories/notification_repository.dart`)
- `getNotifications()` - Fixed notifications list parsing with null checks

### 4. Contribution Repository (`mobile/lib/repositories/contribution_repository.dart`)
- `getContributionHistory()` - Fixed contribution history parsing with null checks
- `getGroupContributions()` - Fixed group contributions parsing with null checks
- `getMissedContributions()` - Fixed missed contributions parsing with null checks

### 5. Bank Account Repository (`mobile/lib/repositories/bank_account_repository.dart`)
- `fetchBanks()` - Fixed banks list parsing with null checks
- `listBankAccounts()` - Fixed bank accounts list parsing with null checks

## Pattern Used

### For Simple List Endpoints:
```dart
// Before (unsafe - crashes on null)
final List<dynamic> itemsJson = response.data['data'];
return itemsJson.map((json) => Item.fromJson(json)).toList();

// After (safe - handles null)
final dynamic itemsData = response.data['data'];

// Handle null response
if (itemsData == null) {
  return [];
}

// Ensure we have a list
final List<dynamic> itemsJson = itemsData is List 
    ? itemsData 
    : [itemsData];

return itemsJson.map((json) => Item.fromJson(json as Map<String, dynamic>)).toList();
```

### For Paginated Endpoints:
```dart
// Before (unsafe - crashes on null or wrong structure)
final paginatedData = response.data['data'];
final List<dynamic> itemsJson = paginatedData['data'];

// After (safe - handles null and various structures)
final paginatedData = response.data['data'];

// Handle null response
if (paginatedData == null) {
  return [];
}

// Handle both paginated and non-paginated responses
final dynamic itemsData = paginatedData is Map<String, dynamic> && paginatedData.containsKey('data')
    ? paginatedData['data']
    : paginatedData;

// Handle null data
if (itemsData == null) {
  return [];
}

// Ensure we have a list
final List<dynamic> itemsJson = itemsData is List 
    ? itemsData 
    : [itemsData];

return itemsJson.map((json) => Item.fromJson(json as Map<String, dynamic>)).toList();
```

## Testing
After applying these fixes:
1. ✅ The wallet screen loads without type errors
2. ✅ The home screen displays groups correctly
3. ✅ All list-based screens handle null responses gracefully
4. ✅ Single items, empty lists, and multiple items are all handled correctly
5. ✅ No more `_Map<String, dynamic>' is not a subtype of type 'List<dynamic>'` errors
6. ✅ No more `Type Null is not a subtype of type 'List<dynamic>'` errors

## Impact
- All mobile screens that fetch list data from the API are now robust and null-safe
- The app can handle various API response formats without crashing
- Better error handling for edge cases:
  - Null responses
  - Empty lists
  - Single items
  - Paginated vs non-paginated responses
  - Missing data fields

