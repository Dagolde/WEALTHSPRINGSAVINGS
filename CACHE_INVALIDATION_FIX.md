# Cache Invalidation Fix - Home Screen Refresh Issue

## Problem
After wallet funding, the home screen was showing stale cached data even when refreshing. The wallet balance displayed was outdated (e.g., showing ₦1,005,000 instead of ₦1,015,000 after funding ₦10,000).

## Root Cause
1. Cache was being invalidated after wallet operations, but only wallet-specific cache entries were cleared
2. Home screen also loads groups and contributions data, which weren't being invalidated
3. The refresh mechanism wasn't forcing a bypass of the cache, so it would still return cached data

## Solution Implemented

### 1. Enhanced Cache Invalidation (wallet_repository.dart)
- Updated `_invalidateWalletCache()` to clear ALL home screen related cache:
  - Wallet cache: `api_cache_/wallet`
  - Groups cache: `api_cache_/group`
  - Contributions cache: `api_cache_/contribution`
  - Payouts cache: `api_cache_/payout`

### 2. Force Refresh Support
Added `forceRefresh` parameter to bypass cache when needed:

**Repositories:**
- `WalletRepository.getBalance(forceRefresh: bool)`
- `GroupRepository.listGroups(forceRefresh: bool)`
- `ContributionRepository.getMissedContributions(forceRefresh: bool)`

**Providers:**
- `WalletNotifier.refreshBalance(forceRefresh: bool)`
- `HomeNotifier.loadDashboardData(forceRefresh: bool)`

### 3. Wallet Funding Flow
- After successful wallet funding, the wallet provider now calls `refreshBalance(forceRefresh: true)`
- This bypasses the cache by adding `Options(extra: {'no_cache': true})` to the API request
- Ensures fresh data is fetched from the backend

### 4. Home Screen Pull-to-Refresh
- Updated `RefreshIndicator` to call `_loadData(forceRefresh: true)`
- When user pulls to refresh, it now bypasses cache and fetches fresh data
- Error retry also uses force refresh

## Files Modified
1. `mobile/lib/repositories/wallet_repository.dart`
   - Enhanced cache invalidation
   - Added force refresh support
   - Added `no_cache` option to POST requests

2. `mobile/lib/providers/wallet_provider.dart`
   - Added force refresh parameter to `refreshBalance()`
   - Updated `fundWallet()` to use force refresh

3. `mobile/lib/providers/home_provider.dart`
   - Added force refresh parameter to `loadDashboardData()`
   - Passes force refresh to all repository calls

4. `mobile/lib/repositories/group_repository.dart`
   - Added force refresh support to `listGroups()`

5. `mobile/lib/repositories/contribution_repository.dart`
   - Added force refresh support to `getMissedContributions()`

6. `mobile/lib/features/home/screens/home_dashboard_screen.dart`
   - Updated pull-to-refresh to use force refresh
   - Updated error retry to use force refresh

## How It Works Now

### Wallet Funding Flow:
1. User funds wallet → API call to `/wallet/fund`
2. Cache is invalidated (wallet, groups, contributions, payouts)
3. Wallet balance is refreshed with `forceRefresh: true` (bypasses cache)
4. User returns to home screen
5. User pulls to refresh → `forceRefresh: true` is used
6. Fresh data is fetched from backend, bypassing cache
7. New wallet balance is displayed correctly

### Cache Strategy:
- **Normal load**: Uses cache if available (faster, offline support)
- **After wallet operations**: Force refresh to ensure accuracy
- **Pull-to-refresh**: Force refresh to get latest data
- **Error retry**: Force refresh to avoid stale data

## Testing
To verify the fix:
1. Fund wallet with any amount
2. Return to home screen
3. Pull down to refresh
4. Verify wallet balance shows the updated amount
5. Check logs for "🌐 Cache MISS" instead of "📦 Cache HIT"

## Expected Behavior
- After wallet funding, the home screen should show the updated balance
- Pull-to-refresh should always fetch fresh data from the backend
- Cache is still used for normal navigation (performance benefit)
- Force refresh is only used when data accuracy is critical
