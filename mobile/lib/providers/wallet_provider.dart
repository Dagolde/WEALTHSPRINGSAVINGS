import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/wallet.dart';
import '../repositories/wallet_repository.dart';
import '../core/di/injection.dart';

// Wallet state classes
sealed class WalletState {}

class WalletInitial extends WalletState {}

class WalletLoading extends WalletState {}

class WalletLoaded extends WalletState {
  final WalletBalance balance;
  WalletLoaded(this.balance);
}

class WalletError extends WalletState {
  final String message;
  WalletError(this.message);
}

// Transaction history state
sealed class TransactionHistoryState {}

class TransactionHistoryInitial extends TransactionHistoryState {}

class TransactionHistoryLoading extends TransactionHistoryState {}

class TransactionHistoryLoaded extends TransactionHistoryState {
  final List<WalletTransaction> transactions;
  final bool hasMore;
  TransactionHistoryLoaded(this.transactions, {this.hasMore = true});
}

class TransactionHistoryError extends TransactionHistoryState {
  final String message;
  TransactionHistoryError(this.message);
}

// Withdrawal state
sealed class WithdrawalState {}

class WithdrawalInitial extends WithdrawalState {}

class WithdrawalLoading extends WithdrawalState {}

class WithdrawalSuccess extends WithdrawalState {
  final Withdrawal withdrawal;
  WithdrawalSuccess(this.withdrawal);
}

class WithdrawalError extends WithdrawalState {
  final String message;
  WithdrawalError(this.message);
}

// Wallet Provider
class WalletNotifier extends StateNotifier<WalletState> {
  final WalletRepository _repository;

  WalletNotifier(this._repository) : super(WalletInitial());

  Future<void> loadBalance() async {
    state = WalletLoading();
    try {
      final balance = await _repository.getBalance();
      state = WalletLoaded(balance);
    } catch (e) {
      state = WalletError(e.toString());
    }
  }

  Future<void> refreshBalance({bool forceRefresh = false}) async {
    try {
      final balance = await _repository.getBalance(forceRefresh: forceRefresh);
      state = WalletLoaded(balance);
    } catch (e) {
      // Keep current state on refresh error
      if (state is! WalletLoaded) {
        state = WalletError(e.toString());
      }
    }
  }

  Future<Map<String, dynamic>> fundWallet({
    required double amount,
    required String paymentMethod,
  }) async {
    try {
      final result = await _repository.fundWallet(
        amount: amount,
        paymentMethod: paymentMethod,
      );
      // Force refresh balance after funding (bypass cache)
      await refreshBalance(forceRefresh: true);
      return result;
    } catch (e) {
      rethrow;
    }
  }
}

// Transaction History Provider
class TransactionHistoryNotifier extends StateNotifier<TransactionHistoryState> {
  final WalletRepository _repository;
  int _currentPage = 1;
  String? _filterType;
  String? _filterStartDate;
  String? _filterEndDate;

  TransactionHistoryNotifier(this._repository) : super(TransactionHistoryInitial());

  Future<void> loadTransactions({
    String? type,
    String? startDate,
    String? endDate,
    bool refresh = false,
  }) async {
    if (refresh) {
      _currentPage = 1;
      _filterType = type;
      _filterStartDate = startDate;
      _filterEndDate = endDate;
      state = TransactionHistoryLoading();
    } else if (state is TransactionHistoryLoading) {
      return; // Prevent duplicate loading
    }

    try {
      final transactions = await _repository.getTransactions(
        type: type ?? _filterType,
        startDate: startDate ?? _filterStartDate,
        endDate: endDate ?? _filterEndDate,
        page: _currentPage,
      );

      if (state is TransactionHistoryLoaded && !refresh) {
        final currentTransactions = (state as TransactionHistoryLoaded).transactions;
        state = TransactionHistoryLoaded(
          [...currentTransactions, ...transactions],
          hasMore: transactions.isNotEmpty,
        );
      } else {
        state = TransactionHistoryLoaded(
          transactions,
          hasMore: transactions.isNotEmpty,
        );
      }
    } catch (e) {
      state = TransactionHistoryError(e.toString());
    }
  }

  Future<void> loadMore() async {
    if (state is TransactionHistoryLoaded) {
      final currentState = state as TransactionHistoryLoaded;
      if (currentState.hasMore) {
        _currentPage++;
        await loadTransactions();
      }
    }
  }

  void reset() {
    _currentPage = 1;
    _filterType = null;
    _filterStartDate = null;
    _filterEndDate = null;
    state = TransactionHistoryInitial();
  }
}

// Withdrawal Provider
class WithdrawalNotifier extends StateNotifier<WithdrawalState> {
  final WalletRepository _repository;

  WithdrawalNotifier(this._repository) : super(WithdrawalInitial());

  Future<void> requestWithdrawal({
    required double amount,
    required int bankAccountId,
  }) async {
    state = WithdrawalLoading();
    try {
      final withdrawal = await _repository.withdraw(
        amount: amount,
        bankAccountId: bankAccountId,
      );
      state = WithdrawalSuccess(withdrawal);
    } catch (e) {
      state = WithdrawalError(e.toString());
    }
  }

  void reset() {
    state = WithdrawalInitial();
  }
}

// Provider instances
final walletProvider = StateNotifierProvider<WalletNotifier, WalletState>((ref) {
  final repository = ref.watch(walletRepositoryProvider);
  return WalletNotifier(repository);
});

final transactionHistoryProvider =
    StateNotifierProvider<TransactionHistoryNotifier, TransactionHistoryState>((ref) {
  final repository = ref.watch(walletRepositoryProvider);
  return TransactionHistoryNotifier(repository);
});

final withdrawalProvider = StateNotifierProvider<WithdrawalNotifier, WithdrawalState>((ref) {
  final repository = ref.watch(walletRepositoryProvider);
  return WithdrawalNotifier(repository);
});

// Auto-refresh wallet balance every 30 seconds when screen is active
final walletAutoRefreshProvider = StreamProvider<void>((ref) {
  return Stream.periodic(const Duration(seconds: 30), (_) {
    ref.read(walletProvider.notifier).refreshBalance();
  });
});
