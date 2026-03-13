import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import '../core/storage/cache_manager.dart';

/// Smart cache interceptor for API requests
/// Implements cache-first strategy with background refresh
class CacheInterceptor extends Interceptor {
  final CacheManager _cacheManager;
  
  // Cache TTL configurations (in minutes)
  static const Map<String, int> cacheTTLConfig = {
    // User data - cache for 30 minutes
    '/user/profile': 30,
    '/user/kyc/status': 30,
    '/user/bank-accounts': 30,
    
    // Groups - cache for 15 minutes
    '/group': 15,
    '/group/': 15,
    
    // Wallet - cache for 5 minutes (frequently updated)
    '/wallet/balance': 5,
    '/wallet/transactions': 10,
    
    // Contributions - cache for 10 minutes
    '/contribution/history': 10,
    '/contribution/missed': 10,
    
    // Notifications - cache for 5 minutes
    '/notification': 5,
    
    // Payouts - cache for 15 minutes
    '/payout': 15,
  };

  CacheInterceptor(this._cacheManager);

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    // Only cache GET requests
    if (options.method.toUpperCase() != 'GET') {
      return handler.next(options);
    }

    // Check if caching is disabled for this request
    if (options.extra['no_cache'] == true) {
      return handler.next(options);
    }

    final cacheKey = _getCacheKey(options);
    final cachedData = _cacheManager.get(cacheKey);

    if (cachedData != null) {
      debugPrint('📦 Cache HIT: ${options.path}');
      
      // Return cached data immediately
      final response = Response(
        requestOptions: options,
        data: cachedData,
        statusCode: 200,
        extra: {'from_cache': true},
      );
      
      return handler.resolve(response);
    }

    debugPrint('🌐 Cache MISS: ${options.path} - Fetching from API');
    return handler.next(options);
  }

  @override
  void onResponse(Response response, ResponseInterceptorHandler handler) async {
    // Only cache successful GET requests
    if (response.requestOptions.method.toUpperCase() == 'GET' &&
        response.statusCode == 200 &&
        response.requestOptions.extra['no_cache'] != true) {
      
      final cacheKey = _getCacheKey(response.requestOptions);
      final ttl = _getTTL(response.requestOptions.path);
      
      await _cacheManager.save(cacheKey, response.data, ttlMinutes: ttl);
      debugPrint('💾 Cached: ${response.requestOptions.path} (TTL: ${ttl}m)');
    }

    return handler.next(response);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    // If network error, try to return cached data even if expired
    if (err.type == DioExceptionType.connectionError ||
        err.type == DioExceptionType.connectionTimeout) {
      
      final cacheKey = _getCacheKey(err.requestOptions);
      final cachedData = _cacheManager.get(cacheKey);
      
      if (cachedData != null) {
        debugPrint('⚠️ Network error - Using cached data: ${err.requestOptions.path}');
        
        final response = Response(
          requestOptions: err.requestOptions,
          data: cachedData,
          statusCode: 200,
          extra: {'from_cache': true, 'stale': true},
        );
        
        return handler.resolve(response);
      }
    }

    return handler.next(err);
  }

  /// Generate cache key from request options
  String _getCacheKey(RequestOptions options) {
    final path = options.path;
    final queryParams = options.queryParameters.entries
        .map((e) => '${e.key}=${e.value}')
        .join('&');
    
    return 'api_cache_$path${queryParams.isNotEmpty ? '?$queryParams' : ''}';
  }

  /// Get TTL for specific endpoint
  int _getTTL(String path) {
    // Find matching TTL configuration
    for (final entry in cacheTTLConfig.entries) {
      if (path.contains(entry.key)) {
        return entry.value;
      }
    }
    
    // Default TTL
    return CacheManager.defaultCacheTTL;
  }
}
