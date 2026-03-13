import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import '../models/kyc.dart';
import '../services/api_client.dart';

class KycRepository {
  final ApiClient _apiClient;

  KycRepository(this._apiClient);

  Future<KycDocument> submitKyc({
    required String documentType,
    required File documentFile,
  }) async {
    try {
      // Create multipart form data
      final formData = FormData.fromMap({
        'document_type': documentType,
        'document': await MultipartFile.fromFile(
          documentFile.path,
          filename: documentFile.path.split('/').last,
        ),
      });

      final response = await _apiClient.dio.post(
        '/user/kyc/submit',
        data: formData,
      );

      // Log the response for debugging
      debugPrint('KYC Submit Response: ${response.data}');

      // Transform backend response to match KycDocument model
      final data = response.data['data'] as Map<String, dynamic>;
      final transformedData = {
        'document_type': documentType, // Use the submitted document type
        'status': data['kyc_status'], // Map kyc_status to status
        'document_url': data['kyc_document_url'], // Map kyc_document_url to document_url
        'submitted_at': data['submitted_at'],
        'verified_at': data['verified_at'],
        'rejection_reason': data['kyc_rejection_reason'],
      };

      return KycDocument.fromJson(transformedData);
    } catch (e) {
      debugPrint('KYC Submit Error: $e');
      throw _handleError(e);
    }
  }

  Future<KycStatus> getKycStatus() async {
    try {
      final response = await _apiClient.dio.get('/user/kyc/status');
      
      // Log the response for debugging
      debugPrint('KYC Status Response: ${response.data}');
      
      // Check if response has data
      if (response.data == null) {
        throw Exception('No data received from server');
      }
      
      // Check if data field exists
      if (response.data['data'] == null) {
        throw Exception('Invalid response format: missing data field');
      }
      
      // Get the data object
      final data = response.data['data'] as Map<String, dynamic>;
      
      // Ensure verified_at field exists (add if missing)
      if (!data.containsKey('verified_at')) {
        data['verified_at'] = null;
      }
      
      // Ensure document_type field exists (add if missing)
      if (!data.containsKey('document_type')) {
        data['document_type'] = null;
      }
      
      return KycStatus.fromJson(data);
    } catch (e) {
      debugPrint('KYC Status Error: $e');
      throw _handleError(e);
    }
  }

  Exception _handleError(dynamic error) {
    if (error is DioException) {
      if (error.response != null) {
        final message = error.response?.data['error']?['message'] ??
            error.response?.data['message'] ??
            'Failed to process KYC request';
        return Exception(message);
      }
      return Exception('Network error. Please check your connection.');
    }
    return Exception('An unexpected error occurred');
  }
}
