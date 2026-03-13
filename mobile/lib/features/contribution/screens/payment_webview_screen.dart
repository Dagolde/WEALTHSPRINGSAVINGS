import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../../../models/group.dart';
import '../../../core/di/injection.dart';
import '../../../core/theme/app_colors.dart';

class PaymentWebViewScreen extends ConsumerStatefulWidget {
  final Group group;
  final String paymentMethod;

  const PaymentWebViewScreen({
    super.key,
    required this.group,
    required this.paymentMethod,
  });

  @override
  ConsumerState<PaymentWebViewScreen> createState() =>
      _PaymentWebViewScreenState();
}

class _PaymentWebViewScreenState extends ConsumerState<PaymentWebViewScreen> {
  late WebViewController _controller;
  bool _isLoading = true;
  String? _paymentUrl;
  String? _paymentReference;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _initializePayment();
  }

  Future<void> _initializePayment() async {
    try {
      final paymentService = ref.read(paymentServiceProvider);
      final response = await paymentService.initializePayment(
        groupId: widget.group.id,
        amount: widget.group.contributionAmountValue,
        paymentMethod: widget.paymentMethod,
      );

      setState(() {
        _paymentUrl = response.authorizationUrl;
        _paymentReference = response.reference;
        _isLoading = false;
      });

      _setupWebView();
    } catch (e) {
      setState(() {
        _errorMessage = e.toString().replaceAll('Exception: ', '');
        _isLoading = false;
      });
    }
  }

  void _setupWebView() {
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (String url) {
            _checkPaymentStatus(url);
          },
          onPageFinished: (String url) {
            _checkPaymentStatus(url);
          },
          onWebResourceError: (WebResourceError error) {
            debugPrint('WebView error: ${error.description}');
          },
        ),
      )
      ..loadRequest(Uri.parse(_paymentUrl!));
  }

  void _checkPaymentStatus(String url) {
    // Check if payment was successful or cancelled
    if (url.contains('success') || url.contains('callback')) {
      _verifyPayment();
    } else if (url.contains('cancel') || url.contains('close')) {
      Navigator.pop(context, false);
    }
  }

  Future<void> _verifyPayment() async {
    if (_paymentReference == null) return;

    try {
      final paymentService = ref.read(paymentServiceProvider);
      await paymentService.verifyPayment(_paymentReference!);
      
      // Payment verified successfully
      if (mounted) {
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (mounted) {
        _showErrorDialog(e.toString().replaceAll('Exception: ', ''));
      }
    }
  }

  void _showErrorDialog(String message) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Payment Error'),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(context); // Close dialog
              Navigator.pop(context, false); // Close webview
            },
            child: const Text('OK'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Complete Payment'),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => Navigator.pop(context, false),
        ),
      ),
      body: _isLoading
          ? const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  CircularProgressIndicator(),
                  SizedBox(height: 16),
                  Text('Initializing payment...'),
                ],
              ),
            )
          : _errorMessage != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(
                          Icons.error_outline,
                          size: 64,
                          color: Colors.red,
                        ),
                        const SizedBox(height: 16),
                        Text(
                          _errorMessage!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(fontSize: 16),
                        ),
                        const SizedBox(height: 24),
                        ElevatedButton(
                          onPressed: () => Navigator.pop(context, false),
                          child: const Text('Go Back'),
                        ),
                      ],
                    ),
                  ),
                )
              : WebViewWidget(controller: _controller),
    );
  }
}
