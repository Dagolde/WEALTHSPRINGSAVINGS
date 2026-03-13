import 'package:dio/dio.dart';
import '../models/contribution.dart';
import '../services/api_client.dart';
import '../core/storage/cache_manager.dart';

class ContributionRepository {
  final ApiClient _apiClient;
  final CacheManager _cacheManager;

  ContributionRepository(this._apiClient, this._cacheManager);

  /// Record a contribution for a group
  Future<Contribution> recordContribution({
    required int groupId,
    required double amount,
    required String paymentMethod,
    String? paymentReference,
  }) async {
    try {
      final response = await _apiClient.dio.post(
        '/contributions',
        data: {
          'group_id': groupId,
          'amount': amount,
          'payment_method': paymentMethod,
          if (paymentReference != null) 'payment_reference': paymentReference,
        },
      );
      
      // Invalidate contribution and wallet cache after recording
      await _invalidateContributionCache(groupId);
      
      return Contribution.fromJson(response.data['data']);
    } catch (e) {
      throw _handleError(e);
    }
  }

  /// Verify a contribution payment
  Future<Contribution> verifyContribution(String paymentReference) async {
    try {
      final response = await _apiClient.dio.post(
        '/contributions/verify',
        data: {'payment_reference': paymentReference},
      );
      
      final contribution = Contribution.fromJson(response.data['data']);
      
      // Invalidate contribution cache after verification
      await _invalidateContributionCache(contribution.groupId);
      
      return contribution;
    } catch (e) {
      throw _handleError(e);
    }
  }

  /// Get user's contribution history
  Future<List<Contribution>> getContributionHistory({
    int? groupId,
    int? page,
    int? perPage,
  }) async {
    try {
      final queryParams = <String, dynamic>{};
      if (groupId != null) queryParams['group_id'] = groupId;
      if (page != null) queryParams['page'] = page;
      if (perPage != null) queryParams['per_page'] = perPage;

      final response = await _apiClient.dio.get(
        '/contributions',
        queryParameters: queryParams.isNotEmpty ? queryParams : null,
      );
      
      // Backend returns paginated data: {data: {data: [...], current_page: 1, ...}}
      final paginatedData = response.data['data'];
      
      // Handle null response
      if (paginatedData == null) {
        return [];
      }
      
      // Handle both paginated and non-paginated responses
      final dynamic contributionsData = paginatedData is Map<String, dynamic> && paginatedData.containsKey('data')
          ? paginatedData['data']
          : paginatedData;
      
      // Handle null data
      if (contributionsData == null) {
        return [];
      }
      
      // Ensure we have a list
      final List<dynamic> contributionsJson = contributionsData is List 
          ? contributionsData 
          : [contributionsData];
      
      return contributionsJson
          .map((json) => Contribution.fromJson(json as Map<String, dynamic>))
          .toList();
    } catch (e) {
      throw _handleError(e);
    }
  }

  /// Get contributions for a specific group
  Future<List<Contribution>> getGroupContributions(int groupId) async {
    try {
      final response = await _apiClient.dio.get('/groups/$groupId/contributions');
      final dynamic contributionsData = response.data['data'];
      
      // Handle null response
      if (contributionsData == null) {
        return [];
      }
      
      // Ensure we have a list
      final List<dynamic> contributionsJson = contributionsData is List 
          ? contributionsData 
          : [contributionsData];
      
      return contributionsJson
          .map((json) => Contribution.fromJson(json as Map<String, dynamic>))
          .toList();
    } catch (e) {
      throw _handleError(e);
    }
  }

  /// Get missed contributions
  Future<List<MissedContribution>> getMissedContributions({bool forceRefresh = false}) async {
    try {
      final response = await _apiClient.dio.get(
        '/contributions/missed',
        options: forceRefresh ? Options(extra: {'no_cache': true}) : null,
      );
      final dynamic missedData = response.data['data'];
      
      // Handle null response
      if (missedData == null) {
        return [];
      }
      
      // Ensure we have a list
      final List<dynamic> missedJson = missedData is List 
          ? missedData 
          : [missedData];
      
      return missedJson
          .map((json) => MissedContribution.fromJson(json as Map<String, dynamic>))
          .toList();
    } catch (e) {
      throw _handleError(e);
    }
  }

  /// Check if user has contributed today for a specific group
  Future<bool> checkTodayContribution(int groupId) async {
    try {
      final response = await _apiClient.dio.get(
        '/contributions/check-today',
        queryParameters: {'group_id': groupId},
      );
      return response.data['data']['has_contributed'] ?? false;
    } catch (e) {
      // If endpoint doesn't exist, fall back to checking history
      return false;
    }
  }

  Exception _handleError(dynamic error) {
    if (error is DioException) {
      if (error.response != null) {
        final message = error.response?.data['error']?['message'] ??
            error.response?.data['message'] ??
            'Failed to process contribution request';
        return Exception(message);
      }
      return Exception('Network error. Please check your connection.');
    }
    return Exception('An unexpected error occurred');
  }
  
  /// Invalidate contribution-related cache
  Future<void> _invalidateContributionCache(int groupId) async {
    // Clear all contribution-related cache entries
    await _cacheManager.removePattern('api_cache_/contributions');
    await _cacheManager.removePattern('api_cache_/groups/$groupId/contributions');
    await _cacheManager.removePattern('api_cache_/wallet'); // Wallet balance changes after contribution
  }
}
