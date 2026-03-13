import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../providers/wallet_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/empty_state.dart';
import 'wallet_funding_screen.dart';
import 'withdrawal_screen.dart';
import 'transaction_history_screen.dart';

class WalletDashboardScreen extends ConsumerStatefulWidget {
  const WalletDashboardScreen({super.key});

  @override
  ConsumerState<WalletDashboardScreen> createState() => _WalletDashboardScreenState();
}

class _WalletDashboardScreenState extends ConsumerState<WalletDashboardScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(walletProvider.notifier).loadBalance();
      ref.read(transactionHistoryProvider.notifier).loadTransactions(refresh: true);
    });
  }

  Future<void> _refreshData() async {
    await ref.read(walletProvider.notifier).refreshBalance();
    await ref.read(transactionHistoryProvider.notifier).loadTransactions(refresh: true);
  }

  @override
  Widget build(BuildContext context) {
    final walletState = ref.watch(walletProvider);
    final transactionState = ref.watch(transactionHistoryProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('My Wallet'),
        elevation: 0,
      ),
      body: RefreshIndicator(
        onRefresh: _refreshData,
        child: CustomScrollView(
          slivers: [
            // Wallet Balance Card
            SliverToBoxAdapter(
              child: Container(
                margin: const EdgeInsets.all(16),
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [AppColors.primary, AppColors.primary.withOpacity(0.7)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.primary.withOpacity(0.3),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Wallet Balance',
                      style: TextStyle(
                        color: Colors.white70,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 8),
                    _buildBalanceDisplay(walletState),
                    const SizedBox(height: 24),
                    Row(
                      children: [
                        Expanded(
                          child: _buildActionButton(
                            icon: Icons.add_circle_outline,
                            label: 'Fund Wallet',
                            onTap: () => _navigateToFunding(),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: _buildActionButton(
                            icon: Icons.arrow_circle_up_outlined,
                            label: 'Withdraw',
                            onTap: () => _navigateToWithdrawal(),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),

            // Recent Transactions Section
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Recent Transactions',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    TextButton(
                      onPressed: () => _navigateToTransactionHistory(),
                      child: const Text('View All'),
                    ),
                  ],
                ),
              ),
            ),

            // Transaction List
            _buildTransactionList(transactionState),
          ],
        ),
      ),
    );
  }

  Widget _buildBalanceDisplay(WalletState state) {
    if (state is WalletLoading) {
      return const Center(
        child: CircularProgressIndicator(color: Colors.white),
      );
    } else if (state is WalletLoaded) {
      return Text(
        '₦${state.balance.balanceValue.toStringAsFixed(2)}',
        style: const TextStyle(
          color: Colors.white,
          fontSize: 32,
          fontWeight: FontWeight.bold,
        ),
      );
    } else if (state is WalletError) {
      return Text(
        'Error loading balance',
        style: TextStyle(
          color: Colors.red[100],
          fontSize: 16,
        ),
      );
    }
    return const Text(
      '₦0.00',
      style: TextStyle(
        color: Colors.white,
        fontSize: 32,
        fontWeight: FontWeight.bold,
      ),
    );
  }

  Widget _buildActionButton({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.2),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: Colors.white, size: 20),
            const SizedBox(width: 8),
            Text(
              label,
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTransactionList(TransactionHistoryState state) {
    if (state is TransactionHistoryLoading) {
      return const SliverFillRemaining(
        child: Center(child: CircularProgressIndicator()),
      );
    } else if (state is TransactionHistoryLoaded) {
      if (state.transactions.isEmpty) {
        return SliverFillRemaining(
          child: EmptyState(
            icon: Icons.receipt_long_outlined,
            title: 'No Transactions',
            message: 'Your transaction history will appear here',
          ),
        );
      }

      final recentTransactions = state.transactions.take(5).toList();
      return SliverList(
        delegate: SliverChildBuilderDelegate(
          (context, index) {
            final transaction = recentTransactions[index];
            return _buildTransactionItem(transaction);
          },
          childCount: recentTransactions.length,
        ),
      );
    } else if (state is TransactionHistoryError) {
      return SliverFillRemaining(
        child: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const SizedBox(height: 16),
              Text(state.message),
              const SizedBox(height: 16),
              AppButton(
                text: 'Retry',
                onPressed: () {
                  ref.read(transactionHistoryProvider.notifier).loadTransactions(refresh: true);
                },
              ),
            ],
          ),
        ),
      );
    }

    return const SliverFillRemaining(
      child: Center(child: Text('Pull to refresh')),
    );
  }

  Widget _buildTransactionItem(transaction) {
    final isCredit = transaction.isCredit;
    final icon = isCredit ? Icons.arrow_downward : Icons.arrow_upward;
    final color = isCredit ? Colors.green : Colors.red;

    return ListTile(
      leading: CircleAvatar(
        backgroundColor: color.withOpacity(0.1),
        child: Icon(icon, color: color, size: 20),
      ),
      title: Text(
        transaction.purpose,
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
      subtitle: Text(
        transaction.createdAt ?? '',
        style: const TextStyle(fontSize: 12),
      ),
      trailing: Text(
        '${isCredit ? '+' : '-'}₦${transaction.amountValue.toStringAsFixed(2)}',
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.bold,
          fontSize: 16,
        ),
      ),
    );
  }

  void _navigateToFunding() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => const WalletFundingScreen()),
    ).then((_) => _refreshData());
  }

  void _navigateToWithdrawal() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => const WithdrawalScreen()),
    ).then((_) => _refreshData());
  }

  void _navigateToTransactionHistory() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => const TransactionHistoryScreen()),
    );
  }
}
