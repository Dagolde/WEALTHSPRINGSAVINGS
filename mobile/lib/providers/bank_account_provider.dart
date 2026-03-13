import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../core/di/injection.dart';
import '../models/bank_account.dart';
import '../repositories/bank_account_repository.dart';

/// Bank account state
sealed class BankAccountState {
  const BankAccountState();
}

class BankAccountInitial extends BankAccountState {
  const BankAccountInitial();
}

class BankAccountLoading extends BankAccountState {
  const BankAccountLoading();
}

class BanksLoaded extends BankAccountState {
  final List<Bank> banks;
  const BanksLoaded(this.banks);
}

class AccountResolved extends BankAccountState {
  final AccountResolution resolution;
  const AccountResolved(this.resolution);
}

class BankAccountsLoaded extends BankAccountState {
  final List<BankAccount> accounts;
  const BankAccountsLoaded(this.accounts);
}

class BankAccountAdded extends BankAccountState {
  final BankAccount account;
  const BankAccountAdded(this.account);
}

class BankAccountError extends BankAccountState {
  final String message;
  const BankAccountError(this.message);
}

/// Bank account state notifier
class BankAccountNotifier extends StateNotifier<BankAccountState> {
  final BankAccountRepository _bankAccountRepository;

  BankAccountNotifier(this._bankAccountRepository)
      : super(const BankAccountInitial());

  Future<void> fetchBanks() async {
    state = const BankAccountLoading();
    try {
      final banks = await _bankAccountRepository.fetchBanks();
      state = BanksLoaded(banks);
    } catch (e) {
      state = BankAccountError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<void> resolveAccount({
    required String accountNumber,
    required String bankCode,
  }) async {
    state = const BankAccountLoading();
    try {
      final resolution = await _bankAccountRepository.resolveAccount(
        accountNumber: accountNumber,
        bankCode: bankCode,
      );
      state = AccountResolved(resolution);
    } catch (e) {
      state = BankAccountError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<void> addBankAccount({
    required String accountName,
    required String accountNumber,
    required String bankName,
    required String bankCode,
  }) async {
    state = const BankAccountLoading();
    try {
      final account = await _bankAccountRepository.addBankAccount(
        accountName: accountName,
        accountNumber: accountNumber,
        bankName: bankName,
        bankCode: bankCode,
      );
      state = BankAccountAdded(account);
    } catch (e) {
      state = BankAccountError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<void> listBankAccounts() async {
    state = const BankAccountLoading();
    try {
      final accounts = await _bankAccountRepository.listBankAccounts();
      state = BankAccountsLoaded(accounts);
    } catch (e) {
      state = BankAccountError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<void> setPrimaryAccount(int accountId) async {
    state = const BankAccountLoading();
    try {
      await _bankAccountRepository.setPrimaryAccount(accountId);
      // Reload accounts after setting primary
      await listBankAccounts();
    } catch (e) {
      state = BankAccountError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  void reset() {
    state = const BankAccountInitial();
  }
}

/// Bank account state provider
final bankAccountStateProvider =
    StateNotifierProvider<BankAccountNotifier, BankAccountState>((ref) {
  final bankAccountRepository = ref.watch(bankAccountRepositoryProvider);
  return BankAccountNotifier(bankAccountRepository);
});
