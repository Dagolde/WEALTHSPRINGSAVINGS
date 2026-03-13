# Payments Banks and Wallet Balance Bugfix Design

## Overview

This design addresses two critical bugs affecting the mobile app's payment and wallet functionality:

1. **Missing `/payments/banks` endpoint**: The mobile app cannot fetch Nigerian banks for bank account linking because the backend route doesn't exist, resulting in 404 errors.

2. **Wallet balance showing ₦0.0**: The home screen displays ₦0.0 despite the database containing correct balances (e.g., ₦1,015,000 for User 18). Investigation reveals the backend returns correct data, but the mobile app may be using stale cached data or the balance is not being properly refreshed after wallet operations.

These bugs prevent users from linking bank accounts and viewing their correct wallet balance, significantly impacting core financial functionality.

## Glossary

- **Bug_Condition_1 (C1)**: The condition that triggers Bug 1 - when the mobile app calls `/api/v1/payments/banks` and receives a 404 error
- **Bug_Condition_2 (C2)**: The condition that triggers Bug 2 - when the home screen displays ₦0.0 despite the database containing a non-zero balance
- **Property_1 (P1)**: The desired behavior for Bug 1 - the endpoint should return a 200 status with a JSON array of Nigerian banks
- **Property_2 (P2)**: The desired behavior for Bug 2 - the home screen should display the correct wallet balance from the database
- **Preservation**: Existing wallet, payment, and API functionality that must remain unchanged by these fixes
- **WalletController**: The Laravel controller in `backend/app/Http/Controllers/Api/WalletController.php` that handles wallet operations
- **WalletRepository**: The Dart repository in `mobile/lib/repositories/wallet_repository.dart` that fetches wallet data from the API
- **HomeProvider**: The Riverpod state manager in `mobile/lib/providers/home_provider.dart` that manages home screen data including wallet balance
- **Cache Invalidation**: The process of clearing stale cached data to force fresh API requests

## Bug Details

### Bug Condition 1: Missing Banks Endpoint

The bug manifests when the mobile app attempts to fetch the list of Nigerian banks for bank account linking. The app calls `GET /api/v1/payments/banks`, but this route does not exist in `backend/routes/api.php`, resulting in a 404 error.

**Formal Specification:**
```
FUNCTION isBugCondition1(request)
  INPUT: request of type HttpRequest
  OUTPUT: boolean
  
  RETURN request.method == 'GET'
         AND request.path == '/api/v1/payments/banks'
         AND routeExists('/api/v1/payments/banks') == false
         AND response.statusCode == 404
END FUNCTION
```

### Bug Condition 2: Wallet Balance Display Issue

The bug manifests when the home screen displays ₦0.0 despite the database containing the correct balance. The `WalletController::getBalance()` method returns correct data from the database, but the mobile app's `HomeProvider` may be displaying stale cached data or the balance is not being refreshed after wallet funding operations.

**Formal Specification:**
```
FUNCTION isBugCondition2(state)
  INPUT: state of type HomeState
  OUTPUT: boolean
  
  RETURN state.walletBalance == 0.0
         AND databaseBalance(state.userId) > 0.0
         AND apiResponse.balance > 0.0
         AND displayedBalance == 0.0
END FUNCTION
```

### Examples

**Bug 1 Examples:**
- User opens bank account linking screen → App calls `GET /api/v1/payments/banks` → Receives 404 error → No banks displayed
- User attempts to add bank account → Cannot proceed because bank list is empty → User blocked from linking account

**Bug 2 Examples:**
- User 18 funds wallet with ₦1,015,000 → Database shows ₦1,015,000 → Home screen shows ₦0.0 → User sees incorrect balance
- User completes wallet funding → Returns to home screen → Balance still shows ₦0.0 → User thinks funding failed
- User refreshes home screen → Balance remains ₦0.0 → Cached data not invalidated → Stale data persists

**Edge Cases:**
- User with genuinely ₦0.0 balance should see ₦0.0 (not a bug)
- User who just registered and never funded wallet should see ₦0.0 (not a bug)
- Multiple rapid wallet operations should show correct final balance

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Existing wallet funding operations must continue to update the database correctly
- Wallet withdrawal operations must continue to process correctly
- Transaction history retrieval must continue to return accurate records
- Other payment endpoints (contributions, payouts) must continue to function
- KYC submission and profile picture upload must continue to work
- Bank account operations (if any exist) must continue without disruption
- API authentication and authorization must remain unchanged
- Cache behavior for other endpoints must remain unchanged

**Scope:**
All inputs that do NOT involve the `/payments/banks` endpoint or wallet balance display should be completely unaffected by these fixes. This includes:
- Wallet funding API endpoint (must continue updating database)
- Wallet withdrawal API endpoint
- Transaction history API endpoint
- Group management endpoints
- Contribution recording endpoints
- KYC and profile endpoints
- Admin endpoints

## Hypothesized Root Cause

### Bug 1: Missing Banks Endpoint

Based on the bug description and code analysis, the root cause is clear:

1. **Missing Route Definition**: The route `GET /api/v1/payments/banks` is not defined in `backend/routes/api.php`
   - The mobile app expects this endpoint to exist
   - The backend has no controller method to handle this request
   - Laravel returns a 404 error for undefined routes

2. **Missing Controller Method**: No controller method exists to return Nigerian banks data
   - Need to create a method that returns a list of Nigerian banks
   - Banks data should include bank code and bank name
   - Data can be hardcoded or stored in database/config

### Bug 2: Wallet Balance Display Issue

Based on code analysis, the most likely root causes are:

1. **Stale Cache After Wallet Funding**: The `WalletController::getBalance()` uses Laravel cache with 5-minute TTL
   - After wallet funding, the cache is not invalidated
   - The mobile app's `_invalidateWalletCache()` only clears mobile-side cache
   - Backend cache still returns old balance (₦0.0) for up to 5 minutes
   - Home screen fetches from backend cache, gets stale ₦0.0

2. **Cache Key Mismatch**: The backend uses cache key `wallet_balance_{user_id}`
   - If cache invalidation doesn't match this exact key pattern, stale data persists
   - Mobile app clears pattern `api_cache_/wallet` but backend cache uses different key

3. **Force Refresh Not Used**: The `HomeProvider::loadDashboardData()` accepts `forceRefresh` parameter
   - When returning from wallet funding, the home screen may not pass `forceRefresh: true`
   - Without force refresh, mobile app uses its own cached response
   - Even if backend returns correct data, mobile app shows cached ₦0.0

4. **Race Condition**: Wallet funding updates database, but home screen loads before cache expires
   - User funds wallet → Database updated → Returns to home screen
   - Home screen loads immediately → Fetches from cache → Gets old ₦0.0
   - Cache hasn't expired yet (5-minute TTL)

## Correctness Properties

Property 1: Bug Condition 1 - Banks Endpoint Returns Nigerian Banks

_For any_ authenticated request to `GET /api/v1/payments/banks`, the backend SHALL return a 200 status code with a JSON array containing Nigerian banks, where each bank object includes a bank code and bank name.

**Validates: Requirements 2.1, 2.2**

Property 2: Bug Condition 2 - Wallet Balance Displays Correctly

_For any_ user whose database wallet balance is non-zero, the home screen SHALL display the correct wallet balance from the database, not a stale cached value of ₦0.0.

**Validates: Requirements 2.3, 2.4**

Property 3: Preservation - Existing Wallet Operations

_For any_ wallet operation (funding, withdrawal, transaction history) that is NOT the balance display or banks endpoint, the system SHALL produce exactly the same behavior as before the fix, preserving all existing wallet functionality.

**Validates: Requirements 3.1, 3.2, 3.4, 3.5**

Property 4: Preservation - Other API Endpoints

_For any_ API endpoint that is NOT `/payments/banks` or `/wallet/balance`, the system SHALL produce exactly the same behavior as before the fix, preserving all existing API functionality including KYC, profile, groups, contributions, and admin endpoints.

**Validates: Requirements 3.3, 3.6**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

### Bug 1: Missing Banks Endpoint

**File**: `backend/routes/api.php`

**Changes**:
1. **Add Banks Route**: Add a new route for fetching Nigerian banks
   - Route: `GET /api/v1/payments/banks`
   - Controller: Create new method or add to existing controller
   - Middleware: Require authentication (`auth:sanctum`)
   - Placement: Add to protected routes section

**File**: `backend/app/Http/Controllers/Api/WalletController.php` (or create new PaymentController)

**Function**: Create `getBanks()` method

**Specific Changes**:
1. **Create getBanks Method**: Return list of Nigerian banks
   - Return JSON array of banks with code and name
   - Use hardcoded list or database/config
   - Include major Nigerian banks (GTBank, Access, Zenith, First Bank, UBA, etc.)
   - Return 200 status with standardized response format

2. **Response Format**: Match existing API response structure
   - Use `successResponse()` helper method
   - Return data in `data` key
   - Include status and message

### Bug 2: Wallet Balance Display Issue

**File**: `backend/app/Http/Controllers/Api/WalletController.php`

**Function**: `getBalance()`

**Specific Changes**:
1. **Invalidate Cache After Wallet Operations**: In `fund()` and `withdraw()` methods
   - Clear cache key `wallet_balance_{user_id}` after successful operations
   - Use `Cache::forget()` to remove stale balance
   - Ensure cache is cleared AFTER database update completes

2. **Add Cache Bypass Option**: Support `no_cache` query parameter
   - Check for `?no_cache=1` or `?force_refresh=1` in request
   - Skip cache and fetch fresh data from database
   - Allow mobile app to force fresh data when needed

**File**: `mobile/lib/repositories/wallet_repository.dart`

**Function**: `fundWallet()`

**Specific Changes**:
1. **Ensure Cache Invalidation**: Verify `_invalidateWalletCache()` is called
   - Already implemented, but verify it's working correctly
   - May need to add delay or force refresh flag

**File**: `mobile/lib/features/home/screens/home_dashboard_screen.dart`

**Specific Changes**:
1. **Force Refresh After Navigation**: When returning from wallet funding
   - Use `forceRefresh: true` when calling `loadDashboardData()`
   - Detect navigation from wallet screen using route observer
   - Automatically refresh home screen data when user returns

2. **Alternative**: Add Pull-to-Refresh
   - Allow user to manually refresh home screen
   - Call `loadDashboardData(forceRefresh: true)` on pull
   - Provide visual feedback during refresh

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bugs on unfixed code, then verify the fixes work correctly and preserve existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the bugs BEFORE implementing the fixes. Confirm or refute the root cause analysis. If we refute, we will need to re-hypothesize.

**Test Plan**: Write tests that call the missing endpoint and verify wallet balance display. Run these tests on the UNFIXED code to observe failures and understand the root causes.

**Test Cases**:
1. **Missing Banks Endpoint Test**: Call `GET /api/v1/payments/banks` (will fail with 404 on unfixed code)
2. **Wallet Balance After Funding Test**: Fund wallet, immediately check balance on home screen (will show ₦0.0 on unfixed code)
3. **Wallet Balance Cache Test**: Fund wallet, wait 1 second, check balance (will show ₦0.0 on unfixed code due to cache)
4. **Force Refresh Test**: Fund wallet, force refresh home screen (may still show ₦0.0 if backend cache not cleared)

**Expected Counterexamples**:
- Banks endpoint returns 404 error
- Home screen shows ₦0.0 after wallet funding despite database showing correct balance
- Backend cache returns stale ₦0.0 for up to 5 minutes
- Mobile app cache shows stale ₦0.0 even with force refresh if backend cache not cleared

### Fix Checking

**Goal**: Verify that for all inputs where the bug conditions hold, the fixed functions produce the expected behavior.

**Pseudocode for Bug 1:**
```
FOR ALL authenticated_request WHERE request.path == '/api/v1/payments/banks' DO
  response := getBanks_fixed(authenticated_request)
  ASSERT response.statusCode == 200
  ASSERT response.data IS Array
  ASSERT response.data.length > 0
  ASSERT ALL bank IN response.data HAVE bank.code AND bank.name
END FOR
```

**Pseudocode for Bug 2:**
```
FOR ALL user WHERE databaseBalance(user) > 0.0 DO
  fundWallet(user, amount)
  navigateToHomeScreen()
  displayedBalance := getDisplayedBalance()
  ASSERT displayedBalance == databaseBalance(user)
  ASSERT displayedBalance > 0.0
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug conditions do NOT hold, the fixed functions produce the same results as the original functions.

**Pseudocode:**
```
FOR ALL api_request WHERE api_request.path NOT IN ['/payments/banks', '/wallet/balance'] DO
  ASSERT handleRequest_original(api_request) == handleRequest_fixed(api_request)
END FOR

FOR ALL wallet_operation WHERE wallet_operation NOT IN ['getBalance'] DO
  ASSERT walletOperation_original(wallet_operation) == walletOperation_fixed(wallet_operation)
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across the input domain
- It catches edge cases that manual unit tests might miss
- It provides strong guarantees that behavior is unchanged for all non-buggy inputs

**Test Plan**: Observe behavior on UNFIXED code first for other endpoints and operations, then write property-based tests capturing that behavior.

**Test Cases**:
1. **Wallet Funding Preservation**: Observe that wallet funding updates database correctly on unfixed code, verify this continues after fix
2. **Wallet Withdrawal Preservation**: Observe that wallet withdrawal works correctly on unfixed code, verify this continues after fix
3. **Transaction History Preservation**: Observe that transaction history returns correct data on unfixed code, verify this continues after fix
4. **Other Endpoints Preservation**: Observe that KYC, profile, groups, contributions work correctly on unfixed code, verify they continue after fix

### Unit Tests

- Test banks endpoint returns correct Nigerian banks list
- Test banks endpoint requires authentication
- Test wallet balance endpoint clears cache after funding
- Test wallet balance endpoint supports force refresh parameter
- Test home screen force refreshes after returning from wallet funding
- Test cache invalidation clears correct cache keys

### Property-Based Tests

- Generate random wallet funding amounts and verify balance displays correctly after each operation
- Generate random sequences of wallet operations and verify final balance is always correct
- Generate random API requests and verify non-affected endpoints continue working
- Test that cache invalidation works across many concurrent wallet operations

### Integration Tests

- Test full flow: Login → Fund wallet → Return to home screen → Verify correct balance displayed
- Test full flow: Login → Open bank linking → Verify banks list loads → Select bank → Link account
- Test that multiple rapid wallet operations show correct final balance
- Test that wallet balance updates in real-time across multiple screens
