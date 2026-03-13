import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../../models/contribution.dart';
import '../../../providers/contribution_provider.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../core/theme/app_colors.dart';

class ContributionHistoryScreen extends ConsumerStatefulWidget {
  final int? groupId;

  const ContributionHistoryScreen({
    super.key,
    this.groupId,
  });

  @override
  ConsumerState<ContributionHistoryScreen> createState() =>
      _ContributionHistoryScreenState();
}

class _ContributionHistoryScreenState
    extends ConsumerState<ContributionHistoryScreen> {
  String _filterStatus = 'all';

  @override
  void initState() {
    super.initState();
    _loadContributions();
  }

  void _loadContributions() {
    if (widget.groupId != null) {
      ref
          .read(contributionStateProvider.notifier)
          .getGroupContributions(widget.groupId!);
    } else {
      ref.read(contributionStateProvider.notifier).getContributionHistory();
    }
  }

  @override
  Widget build(BuildContext context) {
    final contributionState = ref.watch(contributionStateProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.groupId != null
            ? 'Group Contributions'
            : 'Contribution History'),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        actions: [
          PopupMenuButton<String>(
            icon: const Icon(Icons.filter_list),
            onSelected: (value) {
              setState(() {
                _filterStatus = value;
              });
            },
            itemBuilder: (context) => [
              const PopupMenuItem(value: 'all', child: Text('All')),
              const PopupMenuItem(value: 'successful', child: Text('Successful')),
              const PopupMenuItem(value: 'pending', child: Text('Pending')),
              const PopupMenuItem(value: 'failed', child: Text('Failed')),
            ],
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          _loadContributions();
        },
        child: _buildBody(contributionState),
      ),
    );
  }

  Widget _buildBody(ContributionState state) {
    if (state is ContributionLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state is ContributionError) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            Text(state.message),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: _loadContributions,
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    if (state is ContributionHistoryLoaded) {
      final contributions = _filterContributions(state.contributions);

      if (contributions.isEmpty) {
        return const EmptyState(
          icon: Icons.history,
          title: 'No Contributions',
          message: 'You haven\'t made any contributions yet.',
        );
      }

      return ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: contributions.length,
        itemBuilder: (context, index) {
          final contribution = contributions[index];
          return _buildContributionCard(contribution);
        },
      );
    }

    return const EmptyState(
      icon: Icons.history,
      title: 'No Contributions',
      message: 'You haven\'t made any contributions yet.',
    );
  }

  List<Contribution> _filterContributions(List<Contribution> contributions) {
    if (_filterStatus == 'all') return contributions;
    return contributions
        .where((c) => c.paymentStatus == _filterStatus)
        .toList();
  }

  Widget _buildContributionCard(Contribution contribution) {
    final dateFormat = DateFormat('MMM dd, yyyy');

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: () => _showContributionDetails(contribution),
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (contribution.groupName != null)
                          Text(
                            contribution.groupName!,
                            style: const TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 16,
                            ),
                          ),
                        const SizedBox(height: 4),
                        Text(
                          dateFormat.format(
                              DateTime.parse(contribution.contributionDate)),
                          style: TextStyle(
                            color: Colors.grey[600],
                            fontSize: 14,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Text(
                    '₦${contribution.amountValue.toStringAsFixed(2)}',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: AppColors.primary,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  _buildStatusBadge(contribution.paymentStatus),
                  Text(
                    _getPaymentMethodLabel(contribution.paymentMethod),
                    style: TextStyle(
                      color: Colors.grey[600],
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStatusBadge(String status) {
    Color color;
    String label;

    switch (status) {
      case 'successful':
        color = Colors.green;
        label = 'Successful';
        break;
      case 'pending':
        color = Colors.orange;
        label = 'Pending';
        break;
      case 'failed':
        color = Colors.red;
        label = 'Failed';
        break;
      default:
        color = Colors.grey;
        label = status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  String _getPaymentMethodLabel(String method) {
    switch (method) {
      case 'wallet':
        return 'Wallet';
      case 'card':
        return 'Card';
      case 'bank_transfer':
        return 'Bank Transfer';
      default:
        return method;
    }
  }

  void _showContributionDetails(Contribution contribution) {
    final dateFormat = DateFormat('MMM dd, yyyy');
    final timeFormat = DateFormat('hh:mm a');

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Contribution Details',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 24),
            _buildDetailRow('Amount', '₦${contribution.amountValue.toStringAsFixed(2)}'),
            _buildDetailRow('Status', contribution.paymentStatus),
            _buildDetailRow('Payment Method', _getPaymentMethodLabel(contribution.paymentMethod)),
            _buildDetailRow('Date', dateFormat.format(DateTime.parse(contribution.contributionDate))),
            if (contribution.paidAt != null)
              _buildDetailRow('Paid At', '${dateFormat.format(DateTime.parse(contribution.paidAt!))} ${timeFormat.format(DateTime.parse(contribution.paidAt!))}'),
            _buildDetailRow('Reference', contribution.paymentReference),
            if (contribution.groupName != null)
              _buildDetailRow('Group', contribution.groupName!),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Close'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(
              label,
              style: TextStyle(
                color: Colors.grey[600],
                fontSize: 14,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
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
}
