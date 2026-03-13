import 'dart:async';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import '../repositories/notification_repository.dart';

/// Background message handler - must be top-level function
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  // Handle background message
  print('Handling background message: ${message.messageId}');
}

class NotificationService {
  final FirebaseMessaging _firebaseMessaging;
  final FlutterLocalNotificationsPlugin _localNotifications;
  final NotificationRepository _notificationRepository;

  NotificationService({
    required FirebaseMessaging firebaseMessaging,
    required FlutterLocalNotificationsPlugin localNotifications,
    required NotificationRepository notificationRepository,
  })  : _firebaseMessaging = firebaseMessaging,
        _localNotifications = localNotifications,
        _notificationRepository = notificationRepository;

  /// Initialize notification service
  Future<void> initialize() async {
    // Request permission
    await _requestPermission();

    // Initialize local notifications
    await _initializeLocalNotifications();

    // Get FCM token and register with backend
    await _registerFcmToken();

    // Setup message handlers
    _setupMessageHandlers();

    // Listen for token refresh
    _firebaseMessaging.onTokenRefresh.listen(_onTokenRefresh);
  }

  /// Request notification permissions
  Future<void> _requestPermission() async {
    final settings = await _firebaseMessaging.requestPermission(
      alert: true,
      announcement: false,
      badge: true,
      carPlay: false,
      criticalAlert: false,
      provisional: false,
      sound: true,
    );

    print('Notification permission status: ${settings.authorizationStatus}');
  }

  /// Initialize local notifications plugin
  Future<void> _initializeLocalNotifications() async {
    const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosSettings = DarwinInitializationSettings(
      requestAlertPermission: true,
      requestBadgePermission: true,
      requestSoundPermission: true,
    );

    const initSettings = InitializationSettings(
      android: androidSettings,
      iOS: iosSettings,
    );

    await _localNotifications.initialize(
      initSettings,
      onDidReceiveNotificationResponse: _onNotificationTapped,
    );

    // Create notification channel for Android
    const androidChannel = AndroidNotificationChannel(
      'ajo_notifications',
      'Ajo Notifications',
      description: 'Notifications for Ajo app activities',
      importance: Importance.high,
      playSound: true,
    );

    await _localNotifications
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(androidChannel);
  }

  /// Register FCM token with backend
  Future<void> _registerFcmToken() async {
    try {
      final token = await _firebaseMessaging.getToken();
      if (token != null) {
        await _notificationRepository.registerFcmToken(token);
        print('FCM token registered: $token');
      }
    } catch (e) {
      print('Error registering FCM token: $e');
    }
  }

  /// Handle token refresh
  Future<void> _onTokenRefresh(String token) async {
    try {
      await _notificationRepository.registerFcmToken(token);
      print('FCM token refreshed and registered: $token');
    } catch (e) {
      print('Error registering refreshed FCM token: $e');
    }
  }

  /// Setup message handlers for different app states
  void _setupMessageHandlers() {
    // Foreground messages
    FirebaseMessaging.onMessage.listen(_handleForegroundMessage);

    // Background messages (app in background but not terminated)
    FirebaseMessaging.onMessageOpenedApp.listen(_handleBackgroundMessage);

    // Terminated state (app was closed)
    _checkInitialMessage();
  }

  /// Handle foreground messages (app is open)
  Future<void> _handleForegroundMessage(RemoteMessage message) async {
    print('Foreground message received: ${message.messageId}');

    final notification = message.notification;
    final data = message.data;

    if (notification != null) {
      // Show local notification when app is in foreground
      await _showLocalNotification(
        title: notification.title ?? 'Notification',
        body: notification.body ?? '',
        payload: data.toString(),
      );
    }
  }

  /// Handle background messages (app opened from notification)
  void _handleBackgroundMessage(RemoteMessage message) {
    print('Background message opened: ${message.messageId}');
    _handleNotificationNavigation(message.data);
  }

  /// Check for initial message (app opened from terminated state)
  Future<void> _checkInitialMessage() async {
    final initialMessage = await _firebaseMessaging.getInitialMessage();
    if (initialMessage != null) {
      print('App opened from terminated state: ${initialMessage.messageId}');
      _handleNotificationNavigation(initialMessage.data);
    }
  }

  /// Show local notification
  Future<void> _showLocalNotification({
    required String title,
    required String body,
    String? payload,
  }) async {
    const androidDetails = AndroidNotificationDetails(
      'ajo_notifications',
      'Ajo Notifications',
      channelDescription: 'Notifications for Ajo app activities',
      importance: Importance.high,
      priority: Priority.high,
      showWhen: true,
    );

    const iosDetails = DarwinNotificationDetails(
      presentAlert: true,
      presentBadge: true,
      presentSound: true,
    );

    const notificationDetails = NotificationDetails(
      android: androidDetails,
      iOS: iosDetails,
    );

    await _localNotifications.show(
      DateTime.now().millisecondsSinceEpoch ~/ 1000,
      title,
      body,
      notificationDetails,
      payload: payload,
    );
  }

  /// Handle notification tap
  void _onNotificationTapped(NotificationResponse response) {
    print('Notification tapped: ${response.payload}');
    if (response.payload != null) {
      // Parse payload and navigate
      // TODO: Implement navigation based on payload
    }
  }

  /// Handle notification navigation based on data
  void _handleNotificationNavigation(Map<String, dynamic> data) {
    // TODO: Implement navigation logic based on notification type
    // Example:
    // if (data.containsKey('group_id')) {
    //   navigatorKey.currentState?.pushNamed('/groups/${data['group_id']}');
    // }
    print('Navigation data: $data');
  }

  /// Unregister FCM token (call on logout)
  Future<void> unregisterToken() async {
    try {
      final token = await _firebaseMessaging.getToken();
      if (token != null) {
        await _notificationRepository.unregisterFcmToken(token);
        await _firebaseMessaging.deleteToken();
        print('FCM token unregistered');
      }
    } catch (e) {
      print('Error unregistering FCM token: $e');
    }
  }

  /// Get current FCM token
  Future<String?> getToken() async {
    return await _firebaseMessaging.getToken();
  }

  /// Subscribe to topic
  Future<void> subscribeToTopic(String topic) async {
    await _firebaseMessaging.subscribeToTopic(topic);
    print('Subscribed to topic: $topic');
  }

  /// Unsubscribe from topic
  Future<void> unsubscribeFromTopic(String topic) async {
    await _firebaseMessaging.unsubscribeFromTopic(topic);
    print('Unsubscribed from topic: $topic');
  }

  /// Clear all notifications
  Future<void> clearAllNotifications() async {
    await _localNotifications.cancelAll();
  }

  /// Set badge count (iOS)
  Future<void> setBadgeCount(int count) async {
    await _firebaseMessaging.setForegroundNotificationPresentationOptions(
      alert: true,
      badge: true,
      sound: true,
    );
  }
}
