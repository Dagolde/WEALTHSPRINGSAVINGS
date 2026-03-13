import 'package:dio/dio.dart';
import '../models/bank_account.dart';
import '../services/api_client.dart';

class BankAccountRepository {
  final ApiClient _apiClient;

  BankAccountRepository(this._apiClient);

  Future<List<Bank>> fetchBanks() async {
    try {
      final response = await _apiClient.dio.get('/payments/banks');
      final dynamic banksData = response.data['data'];
      
      // Handle null response
      if (banksData == null) {
        return [];
      }
      
      // Ensure we have a list
      final List<dynamic> banksJson = banksData is List 
          ? banksData 
          : [banksData];
      
      return banksJson.map((json) => Bank.fromJson(json as Map<String, dynamic>)).toList();
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<AccountResolution> resolveAccount({
    required String accountNumber,
    required String bankCode,
  }) async {
    try {
      final response = await _apiClient.dio.post(
        '/payments/resolve-account',
        data: {
          'account_number': accountNumber,
          'bank_code': bankCode,
        },
      );
      return AccountResolution.fromJson(response.data['data']);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<BankAccount> addBankAccount({
    required String accountName,
    required String accountNumber,
    required String bankName,
    required String bankCode,
  }) async {
    try {
      final response = await _apiClient.dio.post(
        '/user/bank-account',
        data: {
          'account_name': accountName,
          'account_number': accountNumber,
          'bank_name': bankName,
          'bank_code': bankCode,
        },
      );
      return BankAccount.fromJson(response.data['data']);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<List<BankAccount>> listBankAccounts() async {
    try {
      final response = await _apiClient.dio.get('/user/bank-accounts');
      final dynamic accountsData = response.data['data'];
      
      // Handle null response
      if (accountsData == null) {
        return [];
      }
      
      // Ensure we have a list
      final List<dynamic> accountsJson = accountsData is List 
          ? accountsData 
          : [accountsData];
      
      return accountsJson.map((json) => BankAccount.fromJson(json as Map<String, dynamic>)).toList();
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<BankAccount> setPrimaryAccount(int accountId) async {
    try {
      final response = await _apiClient.dio.put(
        '/user/bank-account/$accountId/set-primary',
      );
      return BankAccount.fromJson(response.data['data']);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Exception _handleError(dynamic error) {
    if (error is DioException) {
      if (error.response != null) {
        final message = error.response?.data['error']?['message'] ??
            error.response?.data['message'] ??
            'Failed to process bank account request';
        return Exception(message);
      }
      return Exception('Network error. Please check your connection.');
    }
    return Exception('An unexpected error occurred');
  }
}
