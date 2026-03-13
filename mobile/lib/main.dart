import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'core/config/app_config.dart';
import 'core/router/app_router.dart';
import 'core/theme/app_theme.dart';
import 'core/di/injection.dart';

// Background message handler - must be top-level function
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
    print('Handling background message: ${message.messageId}');
  } catch (e) {
    print('Error in background message handler: $e');
  }
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  try {
    // Load environment variables
    await dotenv.load(fileName: '.env');
  } catch (e) {
    print('Warning: Could not load .env file: $e');
  }
  
  // Initialize Firebase (optional - app works without it)
  try {
    await Firebase.initializeApp();
    // Set background message handler only if Firebase initialized
    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
    print('Firebase initialized successfully');
  } catch (e) {
    print('Warning: Firebase not configured. Push notifications will be disabled.');
    print('Error: $e');
  }
  
  // Initialize app configuration
  await AppConfig.initialize();
  
  // Initialize SharedPreferences
  final sharedPreferences = await SharedPreferences.getInstance();
  
  runApp(
    ProviderScope(
      overrides: [
        sharedPreferencesProvider.overrideWithValue(sharedPreferences),
      ],
      child: const AjoApp(),
    ),
  );
}

class AjoApp extends ConsumerStatefulWidget {
  const AjoApp({super.key});

  @override
  ConsumerState<AjoApp> createState() => _AjoAppState();
}

class _AjoAppState extends ConsumerState<AjoApp> {
  @override
  void initState() {
    super.initState();
    
    // Initialize notification service after first frame
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _initializeNotifications();
    });
  }

  Future<void> _initializeNotifications() async {
    try {
      final notificationService = ref.read(notificationServiceProvider);
      await notificationService.initialize();
    } catch (e) {
      print('Error initializing notifications: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final router = ref.watch(appRouterProvider);
    
    return MaterialApp.router(
      title: 'Ajo App',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      darkTheme: AppTheme.darkTheme,
      themeMode: ThemeMode.system,
      routerConfig: router,
    );
  }
}
