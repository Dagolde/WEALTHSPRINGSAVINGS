import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../models/group.dart';
import '../../../providers/contribution_provider.dart';
import '../../../providers/auth_provider.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../core/theme/app_colors.dart';
import 'payment_webview_screen.dart';

class ContributionScreen extends ConsumerStatefulWidget {
  final Group group;

  const ContributionScreen({
    super.key,
    required this.group,
  });

  @override
  ConsumerState<ContributionScreen> createState() => _ContributionScreenState();
}

class _ContributionScreenState extends ConsumerState<ContributionScreen> {
  String _selectedPaymentMethod = 'wallet';
  bool _isProcessing = false;

  @override
  Widget build(BuildContext context) {
    final contributionState = ref.watch(contributionStateProvider);
    final authState = ref.watch(authStateProvider);

    // Get wallet balance from auth state
    double walletBalance = 0.0;
    if (authState is Authenticated) {
      walletBalance = authState.user.walletBalanceAmount;
    }

    // Listen to contribution state changes
    ref.listen<ContributionState>(contributionStateProvider, (previous, next) {
      if (next is ContributionRecorded) {
        if (next.contribution.isSuccessful) {
          _showSuccessDialog();
        } else if (next.contribution.isPending && next.contribution.isCardPayment) {
          // For card payments, we need to redirect to payment gateway
          // This would be handled by the payment initialization response
        }
      } else if (next is ContributionError) {
        _showErrorDialog(next.message);
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Make Contribution'),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Group details card
            Card(
              elevation: 2,
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      widget.group.name,
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Contribution Amount',
                      style: TextStyle(
                        color: Colors.grey[600],
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '₦${widget.group.contributionAmountValue.toStringAsFixed(2)}',
                      style: const TextStyle(
                        fontSize: 32,
                        fontWeight: FontWeight.bold,
                        color: AppColors.primary,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),

            // Payment method selection
            const Text(
              'Select Payment Method',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 12),

            // Wallet payment option
            _buildPaymentMethodTile(
              value: 'wallet',
              title: 'Wallet',
              subtitle: 'Balance: ₦${walletBalance.toStringAsFixed(2)}',
              icon: Icons.account_balance_wallet,
              enabled: walletBalance >= widget.group.contributionAmountValue,
            ),
            const SizedBox(height: 8),

            // Card payment option
            _buildPaymentMethodTile(
              value: 'card',
              title: 'Card Payment',
              subtitle: 'Pay with debit/credit card',
              icon: Icons.credit_card,
              enabled: true,
            ),
            const SizedBox(height: 8),

            // Bank transfer option
            _buildPaymentMethodTile(
              value: 'bank_transfer',
              title: 'Bank Transfer',
              subtitle: 'Pay via bank transfer',
              icon: Icons.account_balance,
              enabled: true,
            ),

            const SizedBox(height: 32),

            // Pay now button
            SizedBox(
              width: double.infinity,
              child: AppButton(
                text: 'Pay Now',
                onPressed: _isProcessing ? null : _handlePayment,
                isLoading: _isProcessing || contributionState is ContributionLoading,
              ),
            ),

            // Insufficient balance warning
            if (_selectedPaymentMethod == 'wallet' &&
                walletBalance < widget.group.contributionAmountValue)
              Padding(
                padding: const EdgeInsets.only(top: 16.0),
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.orange[50],
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.orange),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.warning, color: Colors.orange),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'Insufficient wallet balance. Please fund your wallet or use another payment method.',
                          style: TextStyle(color: Colors.orange[900]),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildPaymentMethodTile({
    required String value,
    required String title,
    required String subtitle,
    required IconData icon,
    required bool enabled,
  }) {
    return Card(
      elevation: _selectedPaymentMethod == value ? 2 : 0,
      color: _selectedPaymentMethod == value
          ? AppColors.primary.withOpacity(0.1)
          : null,
      child: RadioListTile<String>(
        value: value,
        groupValue: _selectedPaymentMethod,
        onChanged: enabled
            ? (val) {
                setState(() {
                  _selectedPaymentMethod = val!;
                });
              }
            : null,
        title: Row(
          children: [
            Icon(icon, color: enabled ? AppColors.primary : Colors.grey),
            const SizedBox(width: 12),
            Text(
              title,
              style: TextStyle(
                fontWeight: FontWeight.w600,
                color: enabled ? Colors.black : Colors.grey,
              ),
            ),
          ],
        ),
        subtitle: Padding(
          padding: const EdgeInsets.only(left: 44.0),
          child: Text(
            subtitle,
            style: TextStyle(
              color: enabled ? Colors.grey[600] : Colors.grey,
            ),
          ),
        ),
        activeColor: AppColors.primary,
      ),
    );
  }

  Future<void> _handlePayment() async {
    setState(() {
      _isProcessing = true;
    });

    try {
      if (_selectedPaymentMethod == 'wallet') {
        // Direct wallet payment
        await ref.read(contributionStateProvider.notifier).recordContribution(
              groupId: widget.group.id,
              amount: widget.group.contributionAmountValue,
              paymentMethod: _selectedPaymentMethod,
            );
      } else {
        // Card or bank transfer - need to initialize payment gateway
        await _initializePaymentGateway();
      }
    } finally {
      setState(() {
        _isProcessing = false;
      });
    }
  }

  Future<void> _initializePaymentGateway() async {
    // Navigate to payment webview screen
    final result = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (context) => PaymentWebViewScreen(
          group: widget.group,
          paymentMethod: _selectedPaymentMethod,
        ),
      ),
    );

    if (result == true) {
      _showSuccessDialog();
    }
  }

  void _showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.check_circle, color: Colors.green, size: 32),
            SizedBox(width: 12),
            Text('Success'),
          ],
        ),
        content: const Text(
          'Your contribution has been recorded successfully!',
        ),
        actions: [
          TextButton(
            onPressed: () {
              context.pop(); // Close dialog
              context.pop(); // Go back to previous screen
            },
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  void _showErrorDialog(String message) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.error, color: Colors.red, size: 32),
            SizedBox(width: 12),
            Text('Error'),
          ],
        ),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => context.pop(),
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }
}
