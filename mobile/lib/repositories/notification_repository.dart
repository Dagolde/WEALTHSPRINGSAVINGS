import '../models/notification.dart';
import '../services/api_client.dart';

class NotificationRepository {
  final ApiClient _apiClient;

  NotificationRepository(this._apiClient);

  /// Get list of notifications with pagination
  Future<List<AppNotification>> getNotifications({
    int page = 1,
    int perPage = 20,
    bool? unreadOnly,
  }) async {
    final queryParams = <String, dynamic>{
      'page': page,
      'per_page': perPage,
    };

    if (unreadOnly != null && unreadOnly) {
      queryParams['unread_only'] = true;
    }

    final response = await _apiClient.dio.get(
      '/notifications',
      queryParameters: queryParams,
    );

    // Backend returns paginated data: {data: {data: [...], current_page: 1, ...}}
    final paginatedData = response.data['data'];
    
    // Handle null response
    if (paginatedData == null) {
      return [];
    }
    
    // Handle both paginated and non-paginated responses
    final dynamic notificationsData = paginatedData is Map<String, dynamic> && paginatedData.containsKey('data')
        ? paginatedData['data']
        : paginatedData;
    
    // Handle null data
    if (notificationsData == null) {
      return [];
    }
    
    // Ensure we have a list
    final List<dynamic> notificationsJson = notificationsData is List 
        ? notificationsData 
        : [notificationsData];
    
    return notificationsJson.map((json) => AppNotification.fromJson(json as Map<String, dynamic>)).toList();
  }

  /// Mark a notification as read
  Future<void> markAsRead(int notificationId) async {
    await _apiClient.dio.post('/notifications/$notificationId/read');
  }

  /// Mark all notifications as read
  Future<void> markAllAsRead() async {
    await _apiClient.dio.post('/notifications/read-all');
  }

  /// Get unread notification count
  Future<int> getUnreadCount() async {
    final response = await _apiClient.dio.get('/notifications/unread-count');
    return response.data['data']['count'] as int;
  }

  /// Get notification settings
  Future<NotificationSettings> getSettings() async {
    final response = await _apiClient.dio.get('/notifications/settings');
    return NotificationSettings.fromJson(response.data['data']);
  }

  /// Update notification settings
  Future<NotificationSettings> updateSettings(NotificationSettings settings) async {
    final response = await _apiClient.dio.put(
      '/notifications/settings',
      data: settings.toJson(),
    );
    return NotificationSettings.fromJson(response.data['data']);
  }

  /// Register FCM device token
  Future<void> registerFcmToken(String token) async {
    await _apiClient.dio.post(
      '/notifications/fcm-token',
      data: {'token': token},
    );
  }

  /// Unregister FCM device token
  Future<void> unregisterFcmToken(String token) async {
    await _apiClient.dio.delete(
      '/notifications/fcm-token',
      data: {'token': token},
    );
  }
}
