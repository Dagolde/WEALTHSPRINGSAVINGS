import 'package:dio/dio.dart';
import '../models/contribution.dart';
import 'api_client.dart';

class PaymentService {
  final ApiClient _apiClient;

  PaymentService(this._apiClient);

  /// Initialize payment with backend (returns authorization URL)
  Future<PaymentInitializationResponse> initializePayment({
    required int groupId,
    required double amount,
    required String paymentMethod,
  }) async {
    try {
      final response = await _apiClient.dio.post(
        '/contributions/initialize-payment',
        data: {
          'group_id': groupId,
          'amount': amount,
          'payment_method': paymentMethod,
        },
      );
      return PaymentInitializationResponse.fromJson(response.data['data']);
    } catch (e) {
      throw _handleError(e);
    }
  }

  /// Verify payment after completion
  Future<Map<String, dynamic>> verifyPayment(String reference) async {
    try {
      final response = await _apiClient.dio.post(
        '/contributions/verify',
        data: {'payment_reference': reference},
      );
      return response.data['data'];
    } catch (e) {
      throw _handleError(e);
    }
  }

  Exception _handleError(dynamic error) {
    if (error is DioException) {
      if (error.response != null) {
        final message = error.response?.data['error']?['message'] ??
            error.response?.data['message'] ??
            'Payment processing failed';
        return Exception(message);
      }
      return Exception('Network error. Please check your connection.');
    }
    return Exception('An unexpected error occurred');
  }
}
