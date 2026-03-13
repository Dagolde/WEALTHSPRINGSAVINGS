import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import '../../services/api_client.dart';
import '../../services/token_storage.dart';
import '../../services/payment_service.dart';
import '../../services/notification_service.dart';
import '../../services/connectivity_service.dart';
import '../../repositories/auth_repository.dart';
import '../../repositories/kyc_repository.dart';
import '../../repositories/bank_account_repository.dart';
import '../../repositories/group_repository.dart';
import '../../repositories/contribution_repository.dart';
import '../../repositories/wallet_repository.dart';
import '../../repositories/notification_repository.dart';
import '../storage/cache_manager.dart';
import '../storage/offline_sync.dart';
import '../state/state_persistence.dart';

// Storage providers
final secureStorageProvider = Provider<FlutterSecureStorage>((ref) {
  return const FlutterSecureStorage(
    aOptions: AndroidOptions(
      encryptedSharedPreferences: true,
    ),
  );
});

final sharedPreferencesProvider = Provider<SharedPreferences>((ref) {
  throw UnimplementedError('SharedPreferences must be initialized in main()');
});

final tokenStorageProvider = Provider<TokenStorage>((ref) {
  final secureStorage = ref.watch(secureStorageProvider);
  return TokenStorage(secureStorage);
});

// Cache and offline sync providers
final cacheManagerProvider = Provider<CacheManager>((ref) {
  final prefs = ref.watch(sharedPreferencesProvider);
  return CacheManager(prefs);
});

final offlineSyncProvider = Provider<OfflineSync>((ref) {
  final prefs = ref.watch(sharedPreferencesProvider);
  return OfflineSync(prefs);
});

final statePersistenceProvider = Provider<StatePersistence>((ref) {
  final prefs = ref.watch(sharedPreferencesProvider);
  return StatePersistence(prefs);
});

// API client provider
final apiClientProvider = Provider<ApiClient>((ref) {
  final tokenStorage = ref.watch(tokenStorageProvider);
  final cacheManager = ref.watch(cacheManagerProvider);
  return ApiClient(tokenStorage, cacheManager);
});

// Payment service provider
final paymentServiceProvider = Provider<PaymentService>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return PaymentService(apiClient);
});

// Repository providers
final authRepositoryProvider = Provider<AuthRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final tokenStorage = ref.watch(tokenStorageProvider);
  return AuthRepository(apiClient, tokenStorage);
});

final kycRepositoryProvider = Provider<KycRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return KycRepository(apiClient);
});

final bankAccountRepositoryProvider = Provider<BankAccountRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return BankAccountRepository(apiClient);
});

final groupRepositoryProvider = Provider<GroupRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final cacheManager = ref.watch(cacheManagerProvider);
  return GroupRepository(apiClient, cacheManager);
});

final contributionRepositoryProvider = Provider<ContributionRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final cacheManager = ref.watch(cacheManagerProvider);
  return ContributionRepository(apiClient, cacheManager);
});

final walletRepositoryProvider = Provider<WalletRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final cacheManager = ref.watch(cacheManagerProvider);
  return WalletRepository(apiClient, cacheManager);
});

final notificationRepositoryProvider = Provider<NotificationRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return NotificationRepository(apiClient);
});

// Firebase and notification service providers
final firebaseMessagingProvider = Provider<FirebaseMessaging>((ref) {
  return FirebaseMessaging.instance;
});

final flutterLocalNotificationsProvider = Provider<FlutterLocalNotificationsPlugin>((ref) {
  return FlutterLocalNotificationsPlugin();
});

final notificationServiceProvider = Provider<NotificationService>((ref) {
  final firebaseMessaging = ref.watch(firebaseMessagingProvider);
  final localNotifications = ref.watch(flutterLocalNotificationsProvider);
  final notificationRepository = ref.watch(notificationRepositoryProvider);
  
  return NotificationService(
    firebaseMessaging: firebaseMessaging,
    localNotifications: localNotifications,
    notificationRepository: notificationRepository,
  );
});

// Connectivity provider
final connectivityProvider = Provider<Connectivity>((ref) {
  return Connectivity();
});

final connectivityServiceProvider = Provider<ConnectivityService>((ref) {
  final connectivity = ref.watch(connectivityProvider);
  return ConnectivityService(connectivity);
});
