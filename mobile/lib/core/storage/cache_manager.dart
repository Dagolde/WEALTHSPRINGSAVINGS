import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';

/// Cache manager for API responses and offline data
class CacheManager {
  final SharedPreferences _prefs;
  
  // Cache TTL (Time To Live) in minutes
  static const int defaultCacheTTL = 5;
  
  CacheManager(this._prefs);
  
  /// Save data to cache with TTL
  Future<void> save(String key, dynamic data, {int ttlMinutes = defaultCacheTTL}) async {
    final cacheData = {
      'data': data,
      'timestamp': DateTime.now().millisecondsSinceEpoch,
      'ttl': ttlMinutes,
    };
    
    await _prefs.setString(key, jsonEncode(cacheData));
  }
  
  /// Get data from cache if not expired
  dynamic get(String key) {
    final cacheString = _prefs.getString(key);
    if (cacheString == null) return null;
    
    try {
      final cacheData = jsonDecode(cacheString) as Map<String, dynamic>;
      final timestamp = cacheData['timestamp'] as int;
      final ttl = cacheData['ttl'] as int;
      final data = cacheData['data'];
      
      // Check if cache is expired
      final now = DateTime.now().millisecondsSinceEpoch;
      final expiryTime = timestamp + (ttl * 60 * 1000);
      
      if (now > expiryTime) {
        // Cache expired, remove it
        _prefs.remove(key);
        return null;
      }
      
      return data;
    } catch (e) {
      return null;
    }
  }
  
  /// Check if cache exists and is valid
  bool has(String key) {
    return get(key) != null;
  }
  
  /// Remove specific cache entry
  Future<void> remove(String key) async {
    await _prefs.remove(key);
  }
  
  /// Remove cache entries matching a pattern (e.g., all entries starting with a prefix)
  Future<void> removePattern(String pattern) async {
    final keys = _prefs.getKeys();
    for (final key in keys) {
      if (key.contains(pattern)) {
        await _prefs.remove(key);
      }
    }
  }
  
  /// Clear all cache
  Future<void> clearAll() async {
    final keys = _prefs.getKeys();
    for (final key in keys) {
      if (key.startsWith('cache_')) {
        await _prefs.remove(key);
      }
    }
  }
  
  /// Cache keys for common data
  static String walletBalanceKey(int userId) => 'cache_wallet_balance_$userId';
  static String groupListKey(int userId) => 'cache_group_list_$userId';
  static String groupDetailsKey(int groupId) => 'cache_group_details_$groupId';
  static String contributionHistoryKey(int userId) => 'cache_contribution_history_$userId';
  static String transactionHistoryKey(int userId) => 'cache_transaction_history_$userId';
  static String notificationsKey(int userId) => 'cache_notifications_$userId';
  static String bankAccountsKey(int userId) => 'cache_bank_accounts_$userId';
  static String payoutsKey(int userId) => 'cache_payouts_$userId';
  
  /// Invalidate cache for specific user data
  Future<void> invalidateUserCache(int userId) async {
    await Future.wait([
      remove(walletBalanceKey(userId)),
      remove(groupListKey(userId)),
      remove(contributionHistoryKey(userId)),
      remove(transactionHistoryKey(userId)),
      remove(notificationsKey(userId)),
      remove(bankAccountsKey(userId)),
      remove(payoutsKey(userId)),
    ]);
  }
  
  /// Invalidate cache for specific group
  Future<void> invalidateGroupCache(int groupId) async {
    await remove(groupDetailsKey(groupId));
  }
  
  /// Get cache age in minutes
  int? getCacheAge(String key) {
    final cacheString = _prefs.getString(key);
    if (cacheString == null) return null;
    
    try {
      final cacheData = jsonDecode(cacheString) as Map<String, dynamic>;
      final timestamp = cacheData['timestamp'] as int;
      final now = DateTime.now().millisecondsSinceEpoch;
      final ageMs = now - timestamp;
      return (ageMs / 60000).floor(); // Convert to minutes
    } catch (e) {
      return null;
    }
  }
}
