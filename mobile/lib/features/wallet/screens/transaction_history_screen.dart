import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../providers/wallet_provider.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../models/wallet.dart';

class TransactionHistoryScreen extends ConsumerStatefulWidget {
  const TransactionHistoryScreen({super.key});

  @override
  ConsumerState<TransactionHistoryScreen> createState() => _TransactionHistoryScreenState();
}

class _TransactionHistoryScreenState extends ConsumerState<TransactionHistoryScreen> {
  final _scrollController = ScrollController();
  String? _filterType;
  String? _searchQuery;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(transactionHistoryProvider.notifier).loadTransactions(refresh: true);
    });
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent * 0.9) {
      ref.read(transactionHistoryProvider.notifier).loadMore();
    }
  }

  Future<void> _refreshTransactions() async {
    await ref.read(transactionHistoryProvider.notifier).loadTransactions(
          type: _filterType,
          refresh: true,
        );
  }

  void _showFilterDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Filter Transactions'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              title: const Text('All Transactions'),
              leading: Radio<String?>(
                value: null,
                groupValue: _filterType,
                onChanged: (value) {
                  setState(() => _filterType = value);
                  Navigator.pop(context);
                  _refreshTransactions();
                },
              ),
            ),
            ListTile(
              title: const Text('Credits Only'),
              leading: Radio<String?>(
                value: 'credit',
                groupValue: _filterType,
                onChanged: (value) {
                  setState(() => _filterType = value);
                  Navigator.pop(context);
                  _refreshTransactions();
                },
              ),
            ),
            ListTile(
              title: const Text('Debits Only'),
              leading: Radio<String?>(
                value: 'debit',
                groupValue: _filterType,
                onChanged: (value) {
                  setState(() => _filterType = value);
                  Navigator.pop(context);
                  _refreshTransactions();
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showTransactionDetails(WalletTransaction transaction) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => DraggableScrollableSheet(
        initialChildSize: 0.6,
        minChildSize: 0.4,
        maxChildSize: 0.9,
        expand: false,
        builder: (context, scrollController) => SingleChildScrollView(
          controller: scrollController,
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.grey.shade300,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 24),
              Center(
                child: Icon(
                  transaction.isCredit ? Icons.arrow_downward : Icons.arrow_upward,
                  size: 64,
                  color: transaction.isCredit ? Colors.green : Colors.red,
                ),
              ),
              const SizedBox(height: 16),
              Center(
                child: Text(
                  '${transaction.isCredit ? '+' : '-'}₦${transaction.amountValue.toStringAsFixed(2)}',
                  style: TextStyle(
                    fontSize: 32,
                    fontWeight: FontWeight.bold,
                    color: transaction.isCredit ? Colors.green : Colors.red,
                  ),
                ),
              ),
              const SizedBox(height: 32),
              _buildDetailRow('Type', transaction.isCredit ? 'Credit' : 'Debit'),
              _buildDetailRow('Purpose', transaction.purpose),
              _buildDetailRow('Reference', transaction.reference),
              _buildDetailRow('Status', transaction.status.toUpperCase()),
              _buildDetailRow('Balance Before', '₦${transaction.balanceBeforeValue.toStringAsFixed(2)}'),
              _buildDetailRow('Balance After', '₦${transaction.balanceAfterValue.toStringAsFixed(2)}'),
              _buildDetailRow('Date', transaction.createdAt ?? 'N/A'),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: const TextStyle(
              color: Colors.grey,
              fontSize: 14,
            ),
          ),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(
                fontWeight: FontWeight.w600,
                fontSize: 14,
              ),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(transactionHistoryProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Transaction History'),
        actions: [
          IconButton(
            icon: const Icon(Icons.filter_list),
            onPressed: _showFilterDialog,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _refreshTransactions,
        child: _buildBody(state),
      ),
    );
  }

  Widget _buildBody(TransactionHistoryState state) {
    if (state is TransactionHistoryLoading && state is! TransactionHistoryLoaded) {
      return const Center(child: CircularProgressIndicator());
    } else if (state is TransactionHistoryLoaded) {
      if (state.transactions.isEmpty) {
        return EmptyState(
          icon: Icons.receipt_long_outlined,
          title: 'No Transactions',
          message: 'Your transaction history will appear here',
        );
      }

      final filteredTransactions = _searchQuery == null || _searchQuery!.isEmpty
          ? state.transactions
          : state.transactions.where((t) {
              return t.purpose.toLowerCase().contains(_searchQuery!.toLowerCase()) ||
                  t.reference.toLowerCase().contains(_searchQuery!.toLowerCase());
            }).toList();

      return Column(
        children: [
          // Search Bar
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              decoration: InputDecoration(
                hintText: 'Search transactions...',
                prefixIcon: const Icon(Icons.search),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                filled: true,
                fillColor: Colors.grey.shade100,
              ),
              onChanged: (value) {
                setState(() {
                  _searchQuery = value;
                });
              },
            ),
          ),

          // Transaction List
          Expanded(
            child: ListView.builder(
              controller: _scrollController,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: filteredTransactions.length + (state.hasMore ? 1 : 0),
              itemBuilder: (context, index) {
                if (index == filteredTransactions.length) {
                  return const Center(
                    child: Padding(
                      padding: EdgeInsets.all(16),
                      child: CircularProgressIndicator(),
                    ),
                  );
                }

                final transaction = filteredTransactions[index];
                return _buildTransactionCard(transaction);
              },
            ),
          ),
        ],
      );
    } else if (state is TransactionHistoryError) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 48, color: Colors.red),
            const SizedBox(height: 16),
            Text(state.message),
            const SizedBox(height: 16),
            AppButton(
              text: 'Retry',
              onPressed: _refreshTransactions,
            ),
          ],
        ),
      );
    }

    return const Center(child: Text('Pull to refresh'));
  }

  Widget _buildTransactionCard(WalletTransaction transaction) {
    final isCredit = transaction.isCredit;
    final icon = isCredit ? Icons.arrow_downward : Icons.arrow_upward;
    final color = isCredit ? Colors.green : Colors.red;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        onTap: () => _showTransactionDetails(transaction),
        leading: CircleAvatar(
          backgroundColor: color.withOpacity(0.1),
          child: Icon(icon, color: color, size: 20),
        ),
        title: Text(
          transaction.purpose,
          style: const TextStyle(fontWeight: FontWeight.w600),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 4),
            Text(
              transaction.createdAt ?? '',
              style: const TextStyle(fontSize: 12),
            ),
            const SizedBox(height: 2),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(
                color: _getStatusColor(transaction.status).withOpacity(0.1),
                borderRadius: BorderRadius.circular(4),
              ),
              child: Text(
                transaction.status.toUpperCase(),
                style: TextStyle(
                  fontSize: 10,
                  color: _getStatusColor(transaction.status),
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ],
        ),
        trailing: Text(
          '${isCredit ? '+' : '-'}₦${transaction.amountValue.toStringAsFixed(2)}',
          style: TextStyle(
            color: color,
            fontWeight: FontWeight.bold,
            fontSize: 16,
          ),
        ),
      ),
    );
  }

  Color _getStatusColor(String status) {
    switch (status.toLowerCase()) {
      case 'successful':
        return Colors.green;
      case 'pending':
        return Colors.orange;
      case 'failed':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }
}
