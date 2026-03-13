import '../models/wallet.dart';
import '../services/api_client.dart';
import '../core/storage/cache_manager.dart';
import 'package:dio/dio.dart';

class WalletRepository {
  final ApiClient _apiClient;
  final CacheManager _cacheManager;

  WalletRepository(this._apiClient, this._cacheManager);

  /// Fund wallet with specified amount
  Future<Map<String, dynamic>> fundWallet({
    required double amount,
    required String paymentMethod,
  }) async {
    final response = await _apiClient.dio.post(
      '/wallet/fund',
      data: {
        'amount': amount,
        'payment_method': paymentMethod,
      },
      // Disable caching for this request
      options: Options(extra: {'no_cache': true}),
    );
    
    // Invalidate wallet cache after funding
    await _invalidateWalletCache();
    
    return response.data['data'];
  }

  /// Request withdrawal to bank account
  Future<Withdrawal> withdraw({
    required double amount,
    required int bankAccountId,
  }) async {
    final response = await _apiClient.dio.post(
      '/wallet/withdraw',
      data: {
        'amount': amount,
        'bank_account_id': bankAccountId,
      },
      // Disable caching for this request
      options: Options(extra: {'no_cache': true}),
    );
    
    // Invalidate wallet cache after withdrawal
    await _invalidateWalletCache();
    
    return Withdrawal.fromJson(response.data['data']);
  }

  /// Get current wallet balance
  Future<WalletBalance> getBalance({bool forceRefresh = false}) async {
    final queryParams = forceRefresh ? {'force_refresh': '1'} : null;
    
    final response = await _apiClient.dio.get(
      '/wallet/balance',
      queryParameters: queryParams,
      options: forceRefresh ? Options(extra: {'no_cache': true}) : null,
    );
    return WalletBalance.fromJson(response.data['data']);
  }

  /// Get wallet transaction history
  Future<List<WalletTransaction>> getTransactions({
    String? type,
    String? startDate,
    String? endDate,
    int page = 1,
    int perPage = 20,
  }) async {
    final queryParams = <String, dynamic>{
      'page': page,
      'per_page': perPage,
    };

    if (type != null) queryParams['type'] = type;
    if (startDate != null) queryParams['start_date'] = startDate;
    if (endDate != null) queryParams['end_date'] = endDate;

    final response = await _apiClient.dio.get(
      '/wallet/transactions',
      queryParameters: queryParams,
    );

    // Backend returns paginated data: {data: {data: [...], current_page: 1, ...}}
    final paginatedData = response.data['data'];
    
    // Handle null response
    if (paginatedData == null) {
      return [];
    }
    
    // Handle both paginated and non-paginated responses
    final dynamic transactionsData = paginatedData is Map<String, dynamic> && paginatedData.containsKey('data')
        ? paginatedData['data']
        : paginatedData;
    
    // Handle null data
    if (transactionsData == null) {
      return [];
    }
    
    // Ensure we have a list
    final List<dynamic> transactionsJson = transactionsData is List 
        ? transactionsData 
        : [transactionsData];
    
    return transactionsJson.map((json) => WalletTransaction.fromJson(json as Map<String, dynamic>)).toList();
  }

  /// Get single transaction details
  Future<WalletTransaction> getTransactionDetails(int transactionId) async {
    final response = await _apiClient.dio.get('/wallet/transactions/$transactionId');
    return WalletTransaction.fromJson(response.data['data']);
  }

  /// Get withdrawal history
  Future<List<Withdrawal>> getWithdrawals({
    int page = 1,
    int perPage = 20,
  }) async {
    final response = await _apiClient.dio.get(
      '/wallet/withdrawals',
      queryParameters: {
        'page': page,
        'per_page': perPage,
      },
    );

    // Backend returns paginated data: {data: {data: [...], current_page: 1, ...}}
    final paginatedData = response.data['data'];
    
    // Handle null response
    if (paginatedData == null) {
      return [];
    }
    
    // Handle both paginated and non-paginated responses
    final dynamic withdrawalsData = paginatedData is Map<String, dynamic> && paginatedData.containsKey('data')
        ? paginatedData['data']
        : paginatedData;
    
    // Handle null data
    if (withdrawalsData == null) {
      return [];
    }
    
    // Ensure we have a list
    final List<dynamic> withdrawalsJson = withdrawalsData is List 
        ? withdrawalsData 
        : [withdrawalsData];
    
    return withdrawalsJson.map((json) => Withdrawal.fromJson(json as Map<String, dynamic>)).toList();
  }
  
  /// Invalidate wallet-related cache and home screen cache
  Future<void> _invalidateWalletCache() async {
    // Clear all wallet-related cache entries
    await _cacheManager.removePattern('api_cache_/wallet');
    
    // Also clear home screen related cache to force refresh
    await _cacheManager.removePattern('api_cache_/group');
    await _cacheManager.removePattern('api_cache_/contribution');
    await _cacheManager.removePattern('api_cache_/payout');
  }
}
