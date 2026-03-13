# Wallet Navigation and Null Cast Error Fix

## Issues Fixed

### 1. Navigator.onGenerateRoute Error
**Error**: `Navigator.onGenerateRoute was null, but the route named "/bank-account-linking" was referenced`

**Root Cause**: 
- The withdrawal screen was using the old `Navigator.pushNamed()` API
- The app uses `go_router` for navigation, which doesn't support `pushNamed` without an `onGenerateRoute` handler
- The route path was also incorrect (`/bank-account-linking` instead of `/bank-accounts/link`)

**Solution**:
- Added `go_router` and `app_router` imports to `withdrawal_screen.dart`
- Changed from `Navigator.pushNamed(context, '/bank-account-linking')` to `context.push(AppRoutes.linkBankAccount)`
- This uses the correct route path defined in the router: `/bank-accounts/link`

### 2. Null Type Cast Error in Wallet Models
**Error**: `type 'Null' is not a subtype of type 'int' in type cast`

**Root Cause**:
- The `WalletTransaction.fromJson()` and `Withdrawal.fromJson()` methods were using direct type casts (`as int`, `as String`)
- When the backend returned `null` for required fields, the cast would fail
- This could happen with malformed API responses or during development

**Solution**:
- Added null-safe parsing for all required `int` fields (id, userId, bankAccountId)
- Added null-safe parsing for all required `String` fields with default values
- Used type checking and fallback values:
  ```dart
  id: id is int ? id : (id is String ? int.tryParse(id) ?? 0 : 0)
  ```
- Provided sensible defaults for all fields to prevent crashes

## Files Modified

### 1. mobile/lib/features/wallet/screens/withdrawal_screen.dart
- Added imports:
  - `import 'package:go_router/go_router.dart';`
  - `import '../../../core/router/app_router.dart';`
- Changed navigation from `Navigator.pushNamed()` to `context.push(AppRoutes.linkBankAccount)`

### 2. mobile/lib/models/wallet.dart
- Enhanced `WalletTransaction.fromJson()` with null-safe parsing
- Enhanced `Withdrawal.fromJson()` with null-safe parsing
- Added type checking and fallback values for all required fields
- Provided default values: 0 for ints, '0' for amounts, 'unknown' for status, etc.

## Testing

### Navigation Fix:
1. Open wallet screen
2. Tap "Withdraw"
3. If no bank accounts are linked, tap "Link Bank Account"
4. Should navigate to bank account linking screen without error

### Null Safety Fix:
1. The app should no longer crash when receiving null values from the API
2. Wallet transactions and withdrawals will display with default values if data is missing
3. Check wallet dashboard and transaction history screens

## Benefits

1. **Consistent Navigation**: All navigation now uses `go_router` consistently
2. **Crash Prevention**: Null values from API won't crash the app
3. **Better Error Handling**: Graceful degradation with default values
4. **Maintainability**: Easier to debug and maintain with proper null safety

## Related Routes

All bank account routes in the app:
- `/bank-accounts` - List of bank accounts
- `/bank-accounts/link` - Link new bank account

Use `AppRoutes.bankAccounts` and `AppRoutes.linkBankAccount` constants for navigation.
