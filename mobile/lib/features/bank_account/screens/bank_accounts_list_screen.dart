import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../models/bank_account.dart';
import '../../../providers/bank_account_provider.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/loading_overlay.dart';
import 'bank_account_linking_screen.dart';

class BankAccountsListScreen extends ConsumerStatefulWidget {
  const BankAccountsListScreen({super.key});

  @override
  ConsumerState<BankAccountsListScreen> createState() =>
      _BankAccountsListScreenState();
}

class _BankAccountsListScreenState
    extends ConsumerState<BankAccountsListScreen> {
  @override
  void initState() {
    super.initState();
    // Load bank accounts when screen opens
    Future.microtask(
      () => ref.read(bankAccountStateProvider.notifier).listBankAccounts(),
    );
  }

  void _navigateToLinkAccount() async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => const BankAccountLinkingScreen(),
      ),
    );

    // Reload accounts if a new account was added
    if (result == true && mounted) {
      ref.read(bankAccountStateProvider.notifier).listBankAccounts();
    }
  }

  void _setPrimaryAccount(int accountId) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Set Primary Account'),
        content: const Text(
          'Do you want to set this as your primary account for receiving payouts?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () {
              Navigator.pop(context);
              ref
                  .read(bankAccountStateProvider.notifier)
                  .setPrimaryAccount(accountId);
            },
            child: const Text('Set Primary'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final bankAccountState = ref.watch(bankAccountStateProvider);

    ref.listen<BankAccountState>(bankAccountStateProvider, (previous, next) {
      if (next is BankAccountError) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.message),
            backgroundColor: Colors.red,
          ),
        );
      } else if (next is BankAccountLoading) {
        showLoadingOverlay(context, message: 'Loading...');
      } else if (previous is BankAccountLoading && next is! BankAccountLoading) {
        hideLoadingOverlay(context);
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Bank Accounts'),
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.add),
            onPressed: _navigateToLinkAccount,
          ),
        ],
      ),
      body: bankAccountState is BankAccountsLoaded
          ? _buildAccountsList(bankAccountState.accounts)
          : bankAccountState is BankAccountError
              ? _buildErrorContent(bankAccountState.message)
              : const SizedBox.shrink(),
      floatingActionButton: bankAccountState is BankAccountsLoaded &&
              bankAccountState.accounts.isNotEmpty
          ? FloatingActionButton.extended(
              onPressed: _navigateToLinkAccount,
              icon: const Icon(Icons.add),
              label: const Text('Add Account'),
              backgroundColor: AppColors.primary,
            )
          : null,
    );
  }

  Widget _buildAccountsList(List<BankAccount> accounts) {
    if (accounts.isEmpty) {
      return EmptyState(
        icon: Icons.account_balance,
        title: 'No Bank Accounts',
        message: 'Link your bank account to receive payouts',
        actionText: 'Add Bank Account',
        onAction: _navigateToLinkAccount,
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(AppSpacing.lg),
      itemCount: accounts.length,
      separatorBuilder: (context, index) =>
          const SizedBox(height: AppSpacing.md),
      itemBuilder: (context, index) {
        final account = accounts[index];
        return _buildAccountCard(account);
      },
    );
  }

  Widget _buildAccountCard(BankAccount account) {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: account.isPrimary
            ? BorderSide(color: AppColors.primary, width: 2)
            : BorderSide.none,
      ),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: AppColors.primary.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(
                    Icons.account_balance,
                    color: AppColors.primary,
                  ),
                ),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        account.bankName,
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        account.accountNumber,
                        style: TextStyle(
                          fontSize: 14,
                          color: Colors.grey.shade600,
                        ),
                      ),
                    ],
                  ),
                ),
                if (account.isPrimary)
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: AppSpacing.sm,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.primary,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Text(
                      'PRIMARY',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              account.accountName,
              style: const TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w500,
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            Row(
              children: [
                Icon(
                  account.isVerified
                      ? Icons.check_circle
                      : Icons.hourglass_empty,
                  size: 16,
                  color: account.isVerified ? Colors.green : Colors.orange,
                ),
                const SizedBox(width: 4),
                Text(
                  account.isVerified ? 'Verified' : 'Pending Verification',
                  style: TextStyle(
                    fontSize: 12,
                    color: account.isVerified ? Colors.green : Colors.orange,
                  ),
                ),
              ],
            ),
            if (!account.isPrimary) ...[
              const SizedBox(height: AppSpacing.md),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: () => _setPrimaryAccount(account.id!),
                  style: OutlinedButton.styleFrom(
                    side: BorderSide(color: AppColors.primary),
                  ),
                  child: Text(
                    'Set as Primary',
                    style: TextStyle(color: AppColors.primary),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildErrorContent(String message) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.error_outline,
              size: 64,
              color: Colors.grey.shade400,
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              'Failed to load bank accounts',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w600,
                color: Colors.grey.shade700,
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 14,
                color: Colors.grey.shade600,
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            AppButton(
              text: 'Retry',
              onPressed: () {
                ref.read(bankAccountStateProvider.notifier).listBankAccounts();
              },
            ),
          ],
        ),
      ),
    );
  }
}
