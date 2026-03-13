import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../providers/wallet_provider.dart';
import '../../../core/theme/app_colors.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/app_text_field.dart';

class WalletFundingScreen extends ConsumerStatefulWidget {
  const WalletFundingScreen({super.key});

  @override
  ConsumerState<WalletFundingScreen> createState() => _WalletFundingScreenState();
}

class _WalletFundingScreenState extends ConsumerState<WalletFundingScreen> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  String _selectedPaymentMethod = 'card';
  bool _isLoading = false;

  final List<Map<String, dynamic>> _paymentMethods = [
    {
      'value': 'card',
      'label': 'Debit/Credit Card',
      'icon': Icons.credit_card,
      'description': 'Pay with your card via Paystack',
    },
    {
      'value': 'bank_transfer',
      'label': 'Bank Transfer',
      'icon': Icons.account_balance,
      'description': 'Transfer from your bank account',
    },
  ];

  @override
  void dispose() {
    _amountController.dispose();
    super.dispose();
  }

  Future<void> _handleFundWallet() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isLoading = true);

    try {
      final amount = double.parse(_amountController.text);
      final result = await ref.read(walletProvider.notifier).fundWallet(
            amount: amount,
            paymentMethod: _selectedPaymentMethod,
          );

      if (!mounted) return;

      if (_selectedPaymentMethod == 'card') {
        // Navigate to payment webview - for now, show success message
        // In production, integrate with payment gateway webview
        _showSuccessDialog();
      } else {
        // Show bank transfer instructions
        _showBankTransferInstructions(result);
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

  void _showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        icon: const Icon(Icons.check_circle, color: Colors.green, size: 64),
        title: const Text('Funding Successful'),
        content: const Text('Your wallet has been funded successfully!'),
        actions: [
          AppButton(
            text: 'Done',
            onPressed: () {
              Navigator.of(context).pop(); // Close dialog
              context.pop(); // Return to previous screen using go_router
            },
          ),
        ],
      ),
    );
  }

  void _showBankTransferInstructions(Map<String, dynamic> result) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Bank Transfer Instructions'),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text('Transfer to the account below:'),
              const SizedBox(height: 16),
              _buildInfoRow('Bank Name', result['bank_name'] ?? 'N/A'),
              _buildInfoRow('Account Number', result['account_number'] ?? 'N/A'),
              _buildInfoRow('Account Name', result['account_name'] ?? 'N/A'),
              _buildInfoRow('Amount', '₦${_amountController.text}'),
              _buildInfoRow('Reference', result['reference'] ?? 'N/A'),
              const SizedBox(height: 16),
              const Text(
                'Your wallet will be credited automatically once payment is confirmed.',
                style: TextStyle(fontSize: 12, color: Colors.grey),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(context); // Close dialog
              Navigator.pop(context); // Return to wallet dashboard
            },
            child: const Text('Done'),
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
          Text(value),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Fund Wallet'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Amount Input
              AppTextField(
                controller: _amountController,
                label: 'Amount',
                hint: 'Enter amount to fund',
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
                  if (amount < 100) {
                    return 'Minimum funding amount is ₦100';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 24),

              // Payment Method Selection
              const Text(
                'Payment Method',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 12),
              ..._paymentMethods.map((method) => _buildPaymentMethodCard(method)),
              const SizedBox(height: 24),

              // Funding Fee Info
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
                        'No additional fees for wallet funding',
                        style: TextStyle(fontSize: 12),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 32),

              // Fund Button
              AppButton(
                text: 'Fund Wallet',
                onPressed: _isLoading ? null : _handleFundWallet,
                isLoading: _isLoading,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildPaymentMethodCard(Map<String, dynamic> method) {
    final isSelected = _selectedPaymentMethod == method['value'];

    return GestureDetector(
      onTap: () {
        setState(() {
          _selectedPaymentMethod = method['value'];
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
              method['icon'],
              color: isSelected ? AppColors.primary : Colors.grey,
              size: 32,
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    method['label'],
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: isSelected ? AppColors.primary : Colors.black,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    method['description'],
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
  }
}
