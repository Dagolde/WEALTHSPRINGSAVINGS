# Mobile App Type Casting Fix

## Issue

Error: `type 'double' is not a subtype of type 'string' in type cast`

This occurred on the home screen when loading wallet balance and other data.

## Root Cause

The backend API returns numeric values (like `balance: 0`) but the Flutter models expected strings (like `balance: "0"`).

## Solution

Updated all model classes to handle both string and numeric types from the backend by implementing custom `fromJson` methods.

## Files Fixed

### 1. `mobile/lib/models/wallet.dart`
- `WalletBalance.balance` - handles both string and number
- `WalletTransaction.amount`, `balanceBefore`, `balanceAfter` - handles both types
- `Withdrawal.amount` - handles both types

### 2. `mobile/lib/models/group.dart`
- `Group.contributionAmount` - handles both types
- `PayoutScheduleItem.amount` - handles both types

### 3. `mobile/lib/models/contribution.dart`
- `Contribution.amount` - handles both types
- `MissedContribution.contributionAmount` - handles both types

## How It Works

Before:
```dart
factory WalletBalance.fromJson(Map<String, dynamic> json) =>
    _$WalletBalanceFromJson(json);
```

After:
```dart
factory WalletBalance.fromJson(Map<String, dynamic> json) {
  // Handle both string and number types from backend
  final balanceValue = json['balance'];
  final balanceStr = balanceValue is num 
      ? balanceValue.toString() 
      : balanceValue as String;
  
  return WalletBalance(
    balance: balanceStr,
    userId: json['user_id'] as int?,
  );
}
```

## Testing

After this fix, the app should:
- ✅ Load wallet balance correctly (even when 0)
- ✅ Display groups with contribution amounts
- ✅ Show contributions and missed contributions
- ✅ Handle all numeric fields from the API

## No Rebuild Required

Since we only changed the model parsing logic (not configuration), you don't need to rebuild the app. Just hot reload:

```bash
# In the running app, press 'r' for hot reload
# Or press 'R' for hot restart
```

If hot reload doesn't work:
```bash
flutter run
```

## Prevention

This fix makes the app more robust by accepting both formats:
- Backend sends `0` → App converts to `"0"`
- Backend sends `"0"` → App uses as-is
- Backend sends `100.50` → App converts to `"100.5"`
- Backend sends `"100.50"` → App uses as-is

## Related Issues

This same pattern was applied to all monetary and numeric fields that were defined as strings in the models but might come as numbers from the API.

## Next Steps

1. Hot reload the app (press 'r' in terminal)
2. Navigate to home screen
3. Verify wallet balance displays correctly
4. Test all features

The app should now work perfectly! 🎉
