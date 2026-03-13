import 'dart:convert';
import 'package:dio/dio.dart';
import '../models/user.dart';
import '../models/auth_response.dart';
import '../models/api_response.dart';
import '../services/api_client.dart';
import '../services/token_storage.dart';

class AuthRepository {
  final ApiClient _apiClient;
  final TokenStorage _tokenStorage;

  AuthRepository(this._apiClient, this._tokenStorage);

  Future<AuthResponse> register({
    required String name,
    required String email,
    required String phone,
    required String password,
  }) async {
    try {
      final response = await _apiClient.dio.post(
        '/auth/register',
        data: {
          'name': name,
          'email': email,
          'phone': phone,
          'password': password,
        },
      );

      final apiResponse = ApiResponse<Map<String, dynamic>>.fromJson(
        response.data,
        (json) => json as Map<String, dynamic>,
      );

      if (!apiResponse.success || apiResponse.data == null) {
        throw Exception(apiResponse.message ?? 'Registration failed');
      }

      final authResponse = AuthResponse.fromJson(apiResponse.data!);
      
      // Store token and user data
      await _tokenStorage.saveToken(authResponse.token);
      await _tokenStorage.saveUserData(jsonEncode(authResponse.user.toJson()));

      return authResponse;
    } on DioException catch (e) {
      if (e.response?.data != null) {
        final errorData = e.response!.data;
        if (errorData['errors'] != null) {
          final errors = errorData['errors'] as Map<String, dynamic>;
          final firstError = errors.values.first;
          throw Exception(firstError is List ? firstError.first : firstError);
        }
        throw Exception(errorData['message'] ?? 'Registration failed');
      }
      throw Exception('Network error: ${e.message}');
    } catch (e) {
      throw Exception('Registration failed: $e');
    }
  }

  Future<AuthResponse> login({
    required String email,
    required String password,
  }) async {
    try {
      final response = await _apiClient.dio.post(
        '/auth/login',
        data: {
          'email': email,
          'password': password,
        },
      );

      final apiResponse = ApiResponse<Map<String, dynamic>>.fromJson(
        response.data,
        (json) => json as Map<String, dynamic>,
      );

      if (!apiResponse.success || apiResponse.data == null) {
        throw Exception(apiResponse.message ?? 'Login failed');
      }

      final authResponse = AuthResponse.fromJson(apiResponse.data!);
      
      // Store token and user data
      await _tokenStorage.saveToken(authResponse.token);
      await _tokenStorage.saveUserData(jsonEncode(authResponse.user.toJson()));

      return authResponse;
    } on DioException catch (e) {
      if (e.response?.data != null) {
        final errorData = e.response!.data;
        throw Exception(errorData['message'] ?? 'Login failed');
      }
      throw Exception('Network error: ${e.message}');
    } catch (e) {
      throw Exception('Login failed: $e');
    }
  }

  Future<void> logout() async {
    try {
      // Clear stored token and user data
      await _tokenStorage.clearAll();
    } catch (e) {
      throw Exception('Logout failed: $e');
    }
  }

  Future<User?> getCurrentUser() async {
    try {
      final userData = await _tokenStorage.getUserData();
      if (userData == null) return null;
      
      final userJson = jsonDecode(userData) as Map<String, dynamic>;
      return User.fromJson(userJson);
    } catch (e) {
      return null;
    }
  }

  Future<bool> isAuthenticated() async {
    final token = await _tokenStorage.getToken();
    return token != null;
  }

  Future<User> updateProfile({
    required String name,
    required String phone,
  }) async {
    try {
      final response = await _apiClient.dio.put(
        '/user/profile',
        data: {
          'name': name,
          'phone': phone,
        },
      );

      final apiResponse = ApiResponse<Map<String, dynamic>>.fromJson(
        response.data,
        (json) => json as Map<String, dynamic>,
      );

      if (!apiResponse.success || apiResponse.data == null) {
        throw Exception(apiResponse.message ?? 'Profile update failed');
      }

      final updatedUser = User.fromJson(apiResponse.data!);
      
      // Update stored user data
      await _tokenStorage.saveUserData(jsonEncode(updatedUser.toJson()));

      return updatedUser;
    } on DioException catch (e) {
      if (e.response?.data != null) {
        final errorData = e.response!.data;
        if (errorData['errors'] != null) {
          final errors = errorData['errors'] as Map<String, dynamic>;
          final firstError = errors.values.first;
          throw Exception(firstError is List ? firstError.first : firstError);
        }
        throw Exception(errorData['message'] ?? 'Profile update failed');
      }
      throw Exception('Network error: ${e.message}');
    } catch (e) {
      throw Exception('Profile update failed: $e');
    }
  }

  Future<String> uploadProfilePicture(String filePath) async {
    try {
      final formData = FormData.fromMap({
        'picture': await MultipartFile.fromFile(
          filePath,
          filename: filePath.split('/').last,
        ),
      });

      // Use extended timeouts for file upload
      final response = await _apiClient.dio.post(
        '/user/profile/picture',
        data: formData,
        options: Options(
          sendTimeout: const Duration(minutes: 5), // Extended timeout for uploads
          receiveTimeout: const Duration(minutes: 2), // Extended timeout for response
        ),
      );

      final apiResponse = ApiResponse<Map<String, dynamic>>.fromJson(
        response.data,
        (json) => json as Map<String, dynamic>,
      );

      if (!apiResponse.success || apiResponse.data == null) {
        throw Exception(apiResponse.message ?? 'Upload failed');
      }

      final profilePictureUrl = apiResponse.data!['profile_picture_url'] as String;
      
      // Update stored user data with new profile picture URL
      final userData = await _tokenStorage.getUserData();
      if (userData != null) {
        final userJson = jsonDecode(userData) as Map<String, dynamic>;
        userJson['profile_picture_url'] = profilePictureUrl;
        await _tokenStorage.saveUserData(jsonEncode(userJson));
      }

      return profilePictureUrl;
    } on DioException catch (e) {
      if (e.response?.data != null) {
        final errorData = e.response!.data;
        throw Exception(errorData['message'] ?? 'Upload failed');
      }
      throw Exception('Network error: ${e.message}');
    } catch (e) {
      throw Exception('Upload failed: $e');
    }
  }
}
