import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../providers/wallet_provider.dart';
import '../../../providers/bank_account_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/router/app_router.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/app_text_field.dart';
import '../../../models/bank_account.dart';

class WithdrawalScreen extends ConsumerStatefulWidget {
  const WithdrawalScreen({super.key});

  @override
  ConsumerState<WithdrawalScreen> createState() => _WithdrawalScreenState();
}

class _WithdrawalScreenState extends ConsumerState<WithdrawalScreen> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  BankAccount? _selectedBankAccount;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(bankAccountStateProvider.notifier).listBankAccounts();
      ref.read(walletProvider.notifier).loadBalance();
    });
  }

  @override
  void dispose() {
    _amountController.dispose();
    super.dispose();
  }

  Future<void> _handleWithdrawal() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedBankAccount == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please select a bank account'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final amount = double.parse(_amountController.text);
    final walletState = ref.read(walletProvider);
    
    if (walletState is WalletLoaded) {
      if (amount > walletState.balance.balanceValue) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Insufficient wallet balance'),
            backgroundColor: Colors.red,
          ),
        );
        return;
      }
    }

    // Show confirmation dialog
    final confirmed = await _showConfirmationDialog(amount);
    if (!confirmed) return;

    setState(() => _isLoading = true);

    try {
      await ref.read(withdrawalProvider.notifier).requestWithdrawal(
            amount: amount,
            bankAccountId: _selectedBankAccount!.id!,
          );

      if (!mounted) return;

      final withdrawalState = ref.read(withdrawalProvider);
      if (withdrawalState is WithdrawalSuccess) {
        _showSuccessDialog();
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error: ${e.toString()}'),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<bool> _showConfirmationDialog(double amount) async {
    return await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: const Text('Confirm Withdrawal'),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('You are about to withdraw:'),
                const SizedBox(height: 16),
                _buildInfoRow('Amount', '₦${amount.toStringAsFixed(2)}'),
                _buildInfoRow('Bank', _selectedBankAccount!.bankName),
                _buildInfoRow('Account', _selectedBankAccount!.accountNumber),
                _buildInfoRow('Account Name', _selectedBankAccount!.accountName),
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.orange.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Text(
                    'Withdrawal requests require admin approval and may take 1-2 business days to process.',
                    style: TextStyle(fontSize: 12),
                  ),
                ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Cancel'),
              ),
              AppButton(
                text: 'Confirm',
                onPressed: () => Navigator.pop(context, true),
              ),
            ],
          ),
        ) ??
        false;
  }

  void _showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        icon: const Icon(Icons.check_circle, color: Colors.green, size: 64),
        title: const Text('Withdrawal Requested'),
        content: const Text(
          'Your withdrawal request has been submitted successfully. You will be notified once it is approved and processed.',
        ),
        actions: [
          AppButton(
            text: 'Done',
            onPressed: () {
              Navigator.pop(context); // Close dialog
              Navigator.pop(context); // Return to wallet dashboard
            },
          ),
        ],
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final walletState = ref.watch(walletProvider);
    final bankAccountState = ref.watch(bankAccountStateProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Withdraw Funds'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Wallet Balance Display
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppColors.primary.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Available Balance',
                      style: TextStyle(fontSize: 14, color: Colors.grey),
                    ),
                    const SizedBox(height: 4),
                    if (walletState is WalletLoaded)
                      Text(
                        '₦${walletState.balance.balanceValue.toStringAsFixed(2)}',
                        style: TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.bold,
                          color: AppColors.primary,
                        ),
                      )
                    else if (walletState is WalletLoading)
                      const CircularProgressIndicator()
                    else
                      const Text('₦0.00'),
                  ],
                ),
              ),
              const SizedBox(height: 24),

              // Amount Input
              AppTextField(
                controller: _amountController,
                label: 'Withdrawal Amount',
                hint: 'Enter amount to withdraw',
                keyboardType: TextInputType.number,
                prefixIcon: const Icon(Icons.money),
                inputFormatters: [
                  FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}')),
                ],
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Please enter amount';
                  }
                  final amount = double.tryParse(value);
                  if (amount == null || amount <= 0) {
                    return 'Please enter a valid amount';
                  }
                  if (amount < 1000) {
                    return 'Minimum withdrawal amount is ₦1,000';
                  }
                  if (walletState is WalletLoaded) {
                    if (amount > walletState.balance.balanceValue) {
                      return 'Insufficient balance';
                    }
                  }
                  return null;
                },
              ),
              const SizedBox(height: 24),

              // Bank Account Selection
              const Text(
                'Select Bank Account',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 12),
              _buildBankAccountSelection(bankAccountState),
              const SizedBox(height: 24),

              // Withdrawal Fee Info
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.blue.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  children: const [
                    Icon(Icons.info_outline, color: AppColors.primary, size: 20),
                    SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'No withdrawal fees. Processing time: 1-2 business days',
                        style: TextStyle(fontSize: 12),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 32),

              // Withdraw Button
              AppButton(
                text: 'Request Withdrawal',
                onPressed: _isLoading ? null : _handleWithdrawal,
                isLoading: _isLoading,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBankAccountSelection(BankAccountState state) {
    if (state is BankAccountLoading) {
      return const Center(child: CircularProgressIndicator());
    } else if (state is BankAccountsLoaded) {
      if (state.accounts.isEmpty) {
        return Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            border: Border.all(color: Colors.grey.shade300),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Column(
            children: [
              const Text('No bank accounts linked'),
              const SizedBox(height: 8),
              TextButton(
                onPressed: () {
                  // Navigate to bank account linking screen
                  context.push(AppRoutes.linkBankAccount);
                },
                child: const Text('Link Bank Account'),
              ),
            ],
          ),
        );
      }

      return Column(
        children: state.accounts.map((account) {
          final isSelected = _selectedBankAccount?.id == account.id;
          return GestureDetector(
            onTap: () {
              setState(() {
                _selectedBankAccount = account;
              });
            },
            child: Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                border: Border.all(
                  color: isSelected ? AppColors.primary : Colors.grey.shade300,
                  width: isSelected ? 2 : 1,
                ),
                borderRadius: BorderRadius.circular(12),
                color: isSelected ? AppColors.primary.withOpacity(0.05) : Colors.white,
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.account_balance,
                    color: isSelected ? AppColors.primary : Colors.grey,
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          account.bankName,
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: isSelected ? AppColors.primary : Colors.black,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${account.accountNumber} - ${account.accountName}',
                          style: TextStyle(
                            fontSize: 12,
                            color: Colors.grey.shade600,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (isSelected)
                    Icon(Icons.check_circle, color: AppColors.primary),
                ],
              ),
            ),
          );
        }).toList(),
      );
    } else if (state is BankAccountError) {
      return Center(
        child: Column(
          children: [
            Text(state.message),
            TextButton(
              onPressed: () {
                ref.read(bankAccountStateProvider.notifier).listBankAccounts();
              },
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    return const SizedBox.shrink();
  }
}
