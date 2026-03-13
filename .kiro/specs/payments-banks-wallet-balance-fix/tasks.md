# Implementation Plan

## Bug 1: Missing /payments/banks Endpoint

- [x] 1. Write bug condition exploration test for missing banks endpoint
  - **Property 1: Bug Condition** - Banks Endpoint Returns 404
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the missing endpoint bug
  - **Scoped PBT Approach**: Scope the property to authenticated requests to `/api/v1/payments/banks`
  - Test that `GET /api/v1/payments/banks` returns 404 error on unfixed code
  - Test with authenticated user token
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS with 404 error (this is correct - it proves the endpoint is missing)
  - Document the 404 error to confirm root cause
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 2.1_

- [x] 2. Write preservation property tests for existing payment endpoints (BEFORE implementing fix)
  - **Property 2: Preservation** - Existing Payment Endpoints Unchanged
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for wallet funding, withdrawal, transaction history
  - Write property-based tests capturing observed behavior patterns:
    - Wallet funding updates database correctly
    - Wallet withdrawal processes correctly
    - Transaction history returns accurate records
    - Other payment endpoints (contributions, payouts) function correctly
  - Property-based testing generates many test cases for stronger guarantees
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.4, 3.5_

- [x] 3. Implement missing /payments/banks endpoint

  - [x] 3.1 Add banks route to backend
    - Add `GET /api/v1/payments/banks` route in `backend/routes/api.php`
    - Apply `auth:sanctum` middleware for authentication
    - Route to `WalletController@getBanks` method
    - _Bug_Condition: isBugCondition1(request) where request.path == '/api/v1/payments/banks' AND routeExists() == false_
    - _Expected_Behavior: response.statusCode == 200 AND response.data IS Array of Nigerian banks_
    - _Preservation: Existing wallet, payment, and API endpoints must remain unchanged_
    - _Requirements: 2.1, 2.2, 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

  - [x] 3.2 Create getBanks controller method
    - Add `getBanks()` method to `backend/app/Http/Controllers/Api/WalletController.php`
    - Return JSON array of Nigerian banks (GTBank, Access, Zenith, First Bank, UBA, etc.)
    - Each bank object includes `code` and `name` fields
    - Use `successResponse()` helper for standardized response format
    - Return 200 status code
    - _Bug_Condition: isBugCondition1(request) where controller method does not exist_
    - _Expected_Behavior: Returns array of banks with code and name fields_
    - _Preservation: Other controller methods must remain unchanged_
    - _Requirements: 2.1, 2.2_

  - [x] 3.3 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Banks Endpoint Returns Nigerian Banks
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES with 200 status and banks array (confirms bug is fixed)
    - _Requirements: 2.1, 2.2_

  - [x] 3.4 Verify preservation tests still pass
    - **Property 2: Preservation** - Existing Payment Endpoints Unchanged
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all tests still pass after fix (no regressions)

- [x] 4. Checkpoint - Ensure Bug 1 tests pass
  - Verify banks endpoint returns 200 with Nigerian banks list
  - Verify existing payment endpoints still work correctly
  - Ask user if questions arise

## Bug 2: Wallet Balance Showing ₦0.0

- [x] 5. Write bug condition exploration test for wallet balance display issue
  - **Property 1: Bug Condition** - Wallet Balance Shows ₦0.0 Despite Non-Zero Database Balance
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the stale cache bug
  - **Scoped PBT Approach**: Scope the property to users with non-zero database balances
  - Test that after wallet funding, home screen displays correct balance (not ₦0.0)
  - Test flow: Fund wallet → Navigate to home screen → Check displayed balance
  - Verify database balance is non-zero but displayed balance is ₦0.0
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS showing ₦0.0 despite non-zero database balance (this is correct - it proves the cache bug exists)
  - Document counterexamples found (e.g., "User 18 has ₦1,015,000 in database but home screen shows ₦0.0")
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 2.3_

- [x] 6. Write preservation property tests for wallet operations (BEFORE implementing fix)
  - **Property 2: Preservation** - Wallet Operations Database Updates Preserved
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for wallet operations:
    - Wallet funding updates database correctly
    - Wallet withdrawal updates database correctly
    - Transaction history retrieval returns accurate records
    - KYC and profile operations work correctly
  - Write property-based tests capturing observed behavior patterns
  - Property-based testing generates many test cases for stronger guarantees
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

- [x] 7. Fix wallet balance cache invalidation

  - [x] 7.1 Add cache invalidation to wallet operations
    - In `backend/app/Http/Controllers/Api/WalletController.php`
    - Add `Cache::forget("wallet_balance_{$userId}")` after successful `fund()` operation
    - Add `Cache::forget("wallet_balance_{$userId}")` after successful `withdraw()` operation
    - Ensure cache is cleared AFTER database update completes
    - _Bug_Condition: isBugCondition2(state) where displayedBalance == 0.0 AND databaseBalance > 0.0 due to stale cache_
    - _Expected_Behavior: displayedBalance == databaseBalance after wallet operations_
    - _Preservation: Wallet funding and withdrawal database updates must remain unchanged_
    - _Requirements: 2.3, 2.4, 3.1, 3.2_

  - [x] 7.2 Add cache bypass option to getBalance
    - In `backend/app/Http/Controllers/Api/WalletController.php`
    - Check for `no_cache` or `force_refresh` query parameter in `getBalance()` method
    - If parameter present, skip cache and fetch fresh data from database
    - Allow mobile app to force fresh data when needed
    - _Bug_Condition: isBugCondition2(state) where mobile app cannot force fresh data_
    - _Expected_Behavior: getBalance(?force_refresh=1) returns fresh database balance_
    - _Preservation: Default getBalance behavior (with cache) must remain unchanged_
    - _Requirements: 2.4_

  - [x] 7.3 Add force refresh to home screen after wallet operations
    - In `mobile/lib/features/home/screens/home_dashboard_screen.dart`
    - Detect navigation from wallet funding screen
    - Call `loadDashboardData(forceRefresh: true)` when returning from wallet screen
    - Ensure `forceRefresh: true` bypasses mobile-side cache and adds `?force_refresh=1` to API request
    - _Bug_Condition: isBugCondition2(state) where home screen uses cached data after wallet operations_
    - _Expected_Behavior: Home screen displays fresh balance after returning from wallet operations_
    - _Preservation: Home screen behavior for other navigation scenarios must remain unchanged_
    - _Requirements: 2.3, 2.4_

  - [x] 7.4 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Wallet Balance Displays Correctly After Operations
    - **IMPORTANT**: Re-run the SAME test from task 5 - do NOT write a new test
    - The test from task 5 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 5
    - **EXPECTED OUTCOME**: Test PASSES showing correct balance after wallet funding (confirms bug is fixed)
    - _Requirements: 2.3, 2.4_

  - [x] 7.5 Verify preservation tests still pass
    - **Property 2: Preservation** - Wallet Operations Database Updates Preserved
    - **IMPORTANT**: Re-run the SAME tests from task 6 - do NOT write new tests
    - Run preservation property tests from step 6
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm wallet operations still update database correctly
    - Confirm other endpoints (KYC, profile, groups) still work correctly

- [x] 8. Checkpoint - Ensure Bug 2 tests pass
  - Verify wallet balance displays correctly after funding operations
  - Verify home screen force refreshes when returning from wallet screen
  - Verify cache invalidation clears stale data
  - Verify existing wallet operations still work correctly
  - Ask user if questions arise

## Final Validation

- [x] 9. Integration testing
  - Test full flow: Login → Fund wallet → Return to home screen → Verify correct balance
  - Test full flow: Login → Open bank linking → Verify banks list loads → Select bank
  - Test multiple rapid wallet operations show correct final balance
  - Test that wallet balance updates across multiple screens
  - Verify no regressions in other features (KYC, groups, contributions)

- [x] 10. Final checkpoint
  - Ensure all exploration tests pass (bugs are fixed)
  - Ensure all preservation tests pass (no regressions)
  - Ensure all integration tests pass
  - Ask user if questions arise before marking complete
