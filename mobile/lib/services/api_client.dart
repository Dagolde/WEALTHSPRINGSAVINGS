import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import '../core/config/app_config.dart';
import '../core/storage/cache_manager.dart';
import 'token_storage.dart';
import 'cache_interceptor.dart';

class ApiClient {
  late final Dio _dio;
  final TokenStorage _tokenStorage;
  final CacheManager _cacheManager;

  ApiClient(this._tokenStorage, this._cacheManager) {
    _dio = Dio(
      BaseOptions(
        baseUrl: AppConfig.apiBaseUrl,
        connectTimeout: const Duration(seconds: 60), // Increased for slow networks and file uploads
        receiveTimeout: const Duration(seconds: 60), // Increased for slow networks and file uploads
        sendTimeout: const Duration(minutes: 5), // Increased for large file uploads
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Accept-Encoding': 'gzip, deflate', // Enable compression
        },
      ),
    );

    // Add retry interceptor (before cache)
    _dio.interceptors.add(_retryInterceptor());
    
    // Add cache interceptor (before auth interceptor)
    _dio.interceptors.add(CacheInterceptor(_cacheManager));
    
    // Add authentication interceptor
    _dio.interceptors.add(_authInterceptor());
    
    // Add error handling interceptor
    _dio.interceptors.add(_errorInterceptor());
    
    // Add logging interceptor (only in debug mode)
    if (kDebugMode) {
      _dio.interceptors.add(_loggingInterceptor());
    }
  }

  Dio get dio => _dio;

  /// Retry interceptor - retries failed requests
  InterceptorsWrapper _retryInterceptor() {
    return InterceptorsWrapper(
      onError: (error, handler) async {
        // Only retry on network errors, not on 4xx/5xx responses
        if (error.type == DioExceptionType.connectionTimeout ||
            error.type == DioExceptionType.receiveTimeout ||
            error.type == DioExceptionType.connectionError) {
          
          // Get retry count from request options
          final retryCount = error.requestOptions.extra['retry_count'] ?? 0;
          const maxRetries = 3;
          
          if (retryCount < maxRetries) {
            debugPrint('🔄 Retrying request (${retryCount + 1}/$maxRetries): ${error.requestOptions.path}');
            
            // Wait before retrying (exponential backoff)
            await Future.delayed(Duration(seconds: (retryCount + 1) * 2));
            
            // Update retry count
            error.requestOptions.extra['retry_count'] = retryCount + 1;
            
            // Retry the request
            try {
              final response = await _dio.fetch(error.requestOptions);
              return handler.resolve(response);
            } catch (e) {
              // If retry fails, continue with error handling
              return handler.next(error);
            }
          }
        }
        
        return handler.next(error);
      },
    );
  }

  /// Authentication interceptor - injects JWT token
  InterceptorsWrapper _authInterceptor() {
    return InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _tokenStorage.getToken();
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        return handler.next(options);
      },
      onError: (error, handler) async {
        // Handle 401 Unauthorized - token expired
        if (error.response?.statusCode == 401) {
          // Clear token and trigger re-login
          await _tokenStorage.clearAll();
        }
        return handler.next(error);
      },
    );
  }

  /// Error handling interceptor - standardizes error responses
  InterceptorsWrapper _errorInterceptor() {
    return InterceptorsWrapper(
      onError: (error, handler) {
        String errorMessage = 'An error occurred';
        
        if (error.type == DioExceptionType.connectionTimeout ||
            error.type == DioExceptionType.receiveTimeout) {
          errorMessage = 'Connection timeout. Please check your internet connection and try again.';
        } else if (error.type == DioExceptionType.connectionError) {
          errorMessage = 'Cannot connect to server. Please check:\n'
              '1. Your internet connection\n'
              '2. Backend server is running\n'
              '3. Firewall is not blocking the connection';
        } else if (error.type == DioExceptionType.sendTimeout) {
          errorMessage = 'Request timeout. The server is taking too long to respond.';
        } else if (error.response != null) {
          final statusCode = error.response!.statusCode;
          final data = error.response!.data;
          
          if (statusCode == 400) {
            errorMessage = data['message'] ?? 'Bad request';
          } else if (statusCode == 401) {
            errorMessage = 'Unauthorized. Please login again.';
          } else if (statusCode == 403) {
            errorMessage = 'Access forbidden';
          } else if (statusCode == 404) {
            errorMessage = 'Resource not found';
          } else if (statusCode == 422) {
            // Validation errors
            if (data['errors'] != null) {
              final errors = data['errors'] as Map<String, dynamic>;
              final firstError = errors.values.first;
              errorMessage = firstError is List ? firstError.first : firstError.toString();
            } else {
              errorMessage = data['message'] ?? 'Validation failed';
            }
          } else if (statusCode == 429) {
            errorMessage = 'Too many requests. Please slow down and try again later.';
          } else if (statusCode! >= 500) {
            errorMessage = 'Server error. Please try again later.';
          } else {
            errorMessage = data['message'] ?? 'An error occurred';
          }
        }
        
        // Create a new DioException with standardized message
        final newError = DioException(
          requestOptions: error.requestOptions,
          response: error.response,
          type: error.type,
          error: errorMessage,
          message: errorMessage,
        );
        
        return handler.next(newError);
      },
    );
  }

  /// Logging interceptor - logs requests and responses in debug mode
  InterceptorsWrapper _loggingInterceptor() {
    return InterceptorsWrapper(
      onRequest: (options, handler) {
        debugPrint('┌─────────────────────────────────────────────────');
        debugPrint('│ REQUEST: ${options.method} ${options.path}');
        debugPrint('│ Headers: ${options.headers}');
        if (options.data != null) {
          debugPrint('│ Body: ${options.data}');
        }
        debugPrint('└─────────────────────────────────────────────────');
        return handler.next(options);
      },
      onResponse: (response, handler) {
        debugPrint('┌─────────────────────────────────────────────────');
        debugPrint('│ RESPONSE: ${response.statusCode} ${response.requestOptions.path}');
        debugPrint('│ Data: ${response.data}');
        debugPrint('└─────────────────────────────────────────────────');
        return handler.next(response);
      },
      onError: (error, handler) {
        debugPrint('┌─────────────────────────────────────────────────');
        debugPrint('│ ERROR: ${error.requestOptions.method} ${error.requestOptions.path}');
        debugPrint('│ Status: ${error.response?.statusCode}');
        debugPrint('│ Message: ${error.message}');
        debugPrint('└─────────────────────────────────────────────────');
        return handler.next(error);
      },
    );
  }
}
