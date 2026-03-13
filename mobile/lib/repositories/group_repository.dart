import 'package:dio/dio.dart';
import '../models/group.dart';
import '../services/api_client.dart';
import '../core/storage/cache_manager.dart';

class GroupRepository {
  final ApiClient _apiClient;
  final CacheManager _cacheManager;

  GroupRepository(this._apiClient, this._cacheManager);

  Future<Group> createGroup({
    required String name,
    String? description,
    required double contributionAmount,
    required int totalMembers,
    required int cycleDays,
    required String frequency,
  }) async {
    try {
      final response = await _apiClient.dio.post(
        '/groups',
        data: {
          'name': name,
          'description': description,
          'contribution_amount': contributionAmount,
          'total_members': totalMembers,
          'cycle_days': cycleDays,
          'frequency': frequency,
        },
      );
      
      // Invalidate group list cache after creation
      await _invalidateGroupListCache();
      
      return Group.fromJson(response.data['data']);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<Group> joinGroup(String groupCode) async {
    try {
      final response = await _apiClient.dio.post(
        '/groups/join',
        data: {'group_code': groupCode},
      );
      
      // Invalidate group list cache after joining
      await _invalidateGroupListCache();
      
      return Group.fromJson(response.data['data']);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<Group> startGroup(int groupId) async {
    try {
      final response = await _apiClient.dio.post('/groups/$groupId/start');
      
      final group = Group.fromJson(response.data['data']);
      
      // Invalidate group cache after starting
      await _invalidateGroupCache(groupId);
      
      return group;
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<List<Group>> listGroups({String? status, bool forceRefresh = false}) async {
    try {
      final queryParams = status != null ? {'status': status} : null;
      final response = await _apiClient.dio.get(
        '/groups',
        queryParameters: queryParams,
        options: forceRefresh ? Options(extra: {'no_cache': true}) : null,
      );
      // Backend returns paginated data: {data: {data: [...], current_page: 1, ...}}
      final paginatedData = response.data['data'];
      
      // Handle null response
      if (paginatedData == null) {
        return [];
      }
      
      // Handle both paginated and non-paginated responses
      final dynamic groupsData = paginatedData is Map<String, dynamic> && paginatedData.containsKey('data')
          ? paginatedData['data']
          : paginatedData;
      
      // Handle null data
      if (groupsData == null) {
        return [];
      }
      
      // Ensure we have a list
      final List<dynamic> groupsJson = groupsData is List 
          ? groupsData 
          : [groupsData];
      
      return groupsJson.map((json) => Group.fromJson(json as Map<String, dynamic>)).toList();
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<Group> getGroupDetails(int groupId) async {
    try {
      final response = await _apiClient.dio.get('/groups/$groupId');
      return Group.fromJson(response.data['data']);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<List<GroupMember>> getGroupMembers(int groupId) async {
    try {
      final response = await _apiClient.dio.get('/groups/$groupId/members');
      final dynamic membersData = response.data['data'];
      
      // Handle null response
      if (membersData == null) {
        return [];
      }
      
      // Ensure we have a list
      final List<dynamic> membersJson = membersData is List 
          ? membersData 
          : [membersData];
      
      return membersJson.map((json) => GroupMember.fromJson(json as Map<String, dynamic>)).toList();
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<List<PayoutScheduleItem>> getPayoutSchedule(int groupId) async {
    try {
      final response = await _apiClient.dio.get('/groups/$groupId/schedule');
      final dynamic scheduleData = response.data['data'];
      
      // Handle null response
      if (scheduleData == null) {
        return [];
      }
      
      // Ensure we have a list
      final List<dynamic> scheduleJson = scheduleData is List 
          ? scheduleData 
          : [scheduleData];
      
      return scheduleJson
          .map((json) => PayoutScheduleItem.fromJson(json as Map<String, dynamic>))
          .toList();
    } catch (e) {
      throw _handleError(e);
    }
  }

  Exception _handleError(dynamic error) {
    if (error is DioException) {
      if (error.response != null) {
        final message = error.response?.data['error']?['message'] ??
            error.response?.data['message'] ??
            'Failed to process group request';
        return Exception(message);
      }
      return Exception('Network error. Please check your connection.');
    }
    return Exception('An unexpected error occurred');
  }
  
  /// Invalidate group list cache
  Future<void> _invalidateGroupListCache() async {
    await _cacheManager.removePattern('api_cache_/groups');
  }
  
  /// Invalidate specific group cache
  Future<void> _invalidateGroupCache(int groupId) async {
    await _cacheManager.removePattern('api_cache_/groups/$groupId');
    await _cacheManager.removePattern('api_cache_/groups?'); // Clear list with query params
  }
}
