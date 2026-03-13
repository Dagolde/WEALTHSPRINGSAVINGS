import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../models/bank_account.dart';
import '../../../providers/bank_account_provider.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/app_text_field.dart';
import '../../../shared/widgets/loading_overlay.dart';

class BankAccountLinkingScreen extends ConsumerStatefulWidget {
  const BankAccountLinkingScreen({super.key});

  @override
  ConsumerState<BankAccountLinkingScreen> createState() =>
      _BankAccountLinkingScreenState();
}

class _BankAccountLinkingScreenState
    extends ConsumerState<BankAccountLinkingScreen> {
  final _formKey = GlobalKey<FormState>();
  final _accountNumberController = TextEditingController();

  Bank? _selectedBank;
  AccountResolution? _resolvedAccount;
  bool _isAccountResolved = false;

  @override
  void initState() {
    super.initState();
    // Load banks when screen opens
    Future.microtask(
      () => ref.read(bankAccountStateProvider.notifier).fetchBanks(),
    );
  }

  @override
  void dispose() {
    _accountNumberController.dispose();
    super.dispose();
  }

  void _resolveAccount() {
    if (_selectedBank == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please select a bank'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    if (_accountNumberController.text.length != 10) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Account number must be 10 digits'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    ref.read(bankAccountStateProvider.notifier).resolveAccount(
          accountNumber: _accountNumberController.text,
          bankCode: _selectedBank!.code,
        );
  }

  void _addBankAccount() {
    if (_resolvedAccount == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please verify account first'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    ref.read(bankAccountStateProvider.notifier).addBankAccount(
          accountName: _resolvedAccount!.accountName,
          accountNumber: _accountNumberController.text,
          bankName: _selectedBank!.name,
          bankCode: _selectedBank!.code,
        );
  }

  @override
  Widget build(BuildContext context) {
    final bankAccountState = ref.watch(bankAccountStateProvider);

    ref.listen<BankAccountState>(bankAccountStateProvider, (previous, next) {
      if (next is AccountResolved) {
        setState(() {
          _resolvedAccount = next.resolution;
          _isAccountResolved = true;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Account verified successfully'),
            backgroundColor: Colors.green,
          ),
        );
      } else if (next is BankAccountAdded) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Bank account added successfully'),
            backgroundColor: Colors.green,
          ),
        );
        Navigator.pop(context, true);
      } else if (next is BankAccountError) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.message),
            backgroundColor: Colors.red,
          ),
        );
      } else if (next is BankAccountLoading) {
        showLoadingOverlay(context, message: 'Processing...');
      } else if (previous is BankAccountLoading && next is! BankAccountLoading) {
        hideLoadingOverlay(context);
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Link Bank Account'),
        elevation: 0,
      ),
      body: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Info card
                Container(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        Icons.info_outline,
                        color: AppColors.primary,
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: Text(
                          'Link your bank account to receive payouts',
                          style: TextStyle(
                            color: AppColors.primary,
                            fontSize: 14,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.xl),

                // Bank selector
                const Text(
                  'Select Bank',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: AppSpacing.md),
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.grey.shade300),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: bankAccountState is BanksLoaded
                      ? DropdownButtonHideUnderline(
                          child: DropdownButton<Bank>(
                            value: _selectedBank,
                            hint: const Text('Choose your bank'),
                            isExpanded: true,
                            items: bankAccountState.banks.map((bank) {
                              return DropdownMenuItem(
                                value: bank,
                                child: Text(bank.name),
                              );
                            }).toList(),
                            onChanged: (value) {
                              setState(() {
                                _selectedBank = value;
                                _isAccountResolved = false;
                                _resolvedAccount = null;
                              });
                            },
                          ),
                        )
                      : const Padding(
                          padding: EdgeInsets.all(AppSpacing.md),
                          child: Text('Loading banks...'),
                        ),
                ),
                const SizedBox(height: AppSpacing.lg),

                // Account number
                AppTextField(
                  label: 'Account Number',
                  hint: 'Enter 10-digit account number',
                  controller: _accountNumberController,
                  keyboardType: TextInputType.number,
                  inputFormatters: [
                    LengthLimitingTextInputFormatter(10),
                  ],
                  onChanged: (value) {
                    if (_isAccountResolved) {
                      setState(() {
                        _isAccountResolved = false;
                        _resolvedAccount = null;
                      });
                    }
                  },
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter account number';
                    }
                    if (value.length != 10) {
                      return 'Account number must be 10 digits';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: AppSpacing.lg),

                // Verify button
                if (!_isAccountResolved)
                  AppButton(
                    text: 'Verify Account',
                    onPressed: _selectedBank != null &&
                            _accountNumberController.text.length == 10
                        ? _resolveAccount
                        : null,
                  ),

                // Resolved account info
                if (_isAccountResolved && _resolvedAccount != null) ...[
                  Container(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    decoration: BoxDecoration(
                      color: Colors.green.shade50,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.green.shade200),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              Icons.check_circle,
                              color: Colors.green.shade700,
                            ),
                            const SizedBox(width: AppSpacing.sm),
                            Text(
                              'Account Verified',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                                color: Colors.green.shade700,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: AppSpacing.md),
                        _buildInfoRow(
                          'Account Name',
                          _resolvedAccount!.accountName,
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        _buildInfoRow(
                          'Account Number',
                          _resolvedAccount!.accountNumber,
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        _buildInfoRow(
                          'Bank',
                          _selectedBank!.name,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  AppButton(
                    text: 'Add Bank Account',
                    onPressed: _addBankAccount,
                  ),
                ],
              ],
            ),
          ),
        ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: 14,
            color: Colors.grey.shade700,
          ),
        ),
        Flexible(
          child: Text(
            value,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
            textAlign: TextAlign.right,
          ),
        ),
      ],
    );
  }
}
