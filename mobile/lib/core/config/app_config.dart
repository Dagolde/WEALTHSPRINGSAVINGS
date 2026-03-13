import 'package:flutter_dotenv/flutter_dotenv.dart';

enum Environment { development, staging, production }

class AppConfig {
  static late Environment environment;
  static late String apiBaseUrl;
  static late String appName;
  static late String appVersion;
  
  static Future<void> initialize() async {
    // Determine environment from .env file
    final envString = dotenv.env['ENVIRONMENT'] ?? 'development';
    environment = Environment.values.firstWhere(
      (e) => e.name == envString,
      orElse: () => Environment.development,
    );
    
    // Load configuration based on environment
    apiBaseUrl = dotenv.env['API_BASE_URL'] ?? 'http://192.168.1.106:8002/api/v1';
    appName = dotenv.env['APP_NAME'] ?? 'Ajo App';
    appVersion = dotenv.env['APP_VERSION'] ?? '1.0.0';
  }
  
  static bool get isDevelopment => environment == Environment.development;
  static bool get isStaging => environment == Environment.staging;
  static bool get isProduction => environment == Environment.production;
}
