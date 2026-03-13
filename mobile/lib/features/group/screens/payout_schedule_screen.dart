import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../../core/theme/app_colors.dart';
import '../../../models/group.dart';
import '../../../providers/group_provider.dart';

class PayoutScheduleScreen extends ConsumerStatefulWidget {
  final String groupId;

  const PayoutScheduleScreen({
    super.key,
    required this.groupId,
  });

  @override
  ConsumerState<PayoutScheduleScreen> createState() =>
      _PayoutScheduleScreenState();
}

class _PayoutScheduleScreenState extends ConsumerState<PayoutScheduleScreen> {
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadSchedule();
    });
  }

  void _loadSchedule() {
    ref
        .read(groupStateProvider.notifier)
        .getPayoutSchedule(int.parse(widget.groupId));
  }

  @override
  Widget build(BuildContext context) {
    final groupState = ref.watch(groupStateProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Payout Schedule'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(60),
          child: Padding(
            padding: const EdgeInsets.all(8.0),
            child: TextField(
              decoration: InputDecoration(
                hintText: 'Search by member name...',
                prefixIcon: const Icon(Icons.search),
                filled: true,
                fillColor: Colors.white,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                  borderSide: BorderSide.none,
                ),
              ),
              onChanged: (value) {
                setState(() {
                  _searchQuery = value.toLowerCase();
                });
              },
            ),
          ),
        ),
      ),
      body: _buildBody(groupState),
    );
  }

  Widget _buildBody(GroupState state) {
    if (state is GroupLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state is GroupError) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            Text(state.message),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: _loadSchedule,
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    if (state is PayoutScheduleLoaded) {
      final schedule = state.schedule;
      final filteredSchedule = _searchQuery.isEmpty
          ? schedule
          : schedule
              .where((item) =>
                  item.memberName.toLowerCase().contains(_searchQuery))
              .toList();

      if (filteredSchedule.isEmpty) {
        return const Center(
          child: Text('No schedule items found'),
        );
      }

      return RefreshIndicator(
        onRefresh: () async => _loadSchedule(),
        child: ListView.builder(
          padding: const EdgeInsets.all(16),
          itemCount: filteredSchedule.length,
          itemBuilder: (context, index) {
            final item = filteredSchedule[index];
            return _ScheduleCard(item: item);
          },
        ),
      );
    }

    return const Center(child: Text('No schedule available'));
  }
}

class _ScheduleCard extends StatelessWidget {
  final PayoutScheduleItem item;

  const _ScheduleCard({required this.item});

  @override
  Widget build(BuildContext context) {
    final isToday = _isToday(item.date);

    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      elevation: isToday ? 4 : 1,
      color: isToday ? AppColors.primary.withOpacity(0.1) : null,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 6,
                      ),
                      decoration: BoxDecoration(
                        color: isToday
                            ? AppColors.primary
                            : AppColors.primary.withOpacity(0.2),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        'Day ${item.day}',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                          color: isToday ? Colors.white : AppColors.primary,
                        ),
                      ),
                    ),
                    if (isToday) ...[
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.orange,
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: const Text(
                          'TODAY',
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            color: Colors.white,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
                _StatusBadge(status: item.status),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: AppColors.primary,
                  child: Text(
                    item.memberName.substring(0, 1).toUpperCase(),
                    style: const TextStyle(color: Colors.white),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.memberName,
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      Text(
                        'Position ${item.positionNumber}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: Colors.grey,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            const Divider(),
            const SizedBox(height: 12),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Payout Date',
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _formatDate(item.date),
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    const Text(
                      'Payout Amount',
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '₦${item.amountValue.toStringAsFixed(0)}',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: AppColors.primary,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            if (item.contributionStatus != null) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: _getContributionStatusColor(item.contributionStatus!)
                      .withOpacity(0.1),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Row(
                  children: [
                    Icon(
                      _getContributionStatusIcon(item.contributionStatus!),
                      size: 16,
                      color: _getContributionStatusColor(item.contributionStatus!),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'Contributions: ${item.contributionStatus}',
                      style: TextStyle(
                        fontSize: 12,
                        color: _getContributionStatusColor(item.contributionStatus!),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  bool _isToday(String date) {
    try {
      final scheduleDate = DateTime.parse(date);
      final today = DateTime.now();
      return scheduleDate.year == today.year &&
          scheduleDate.month == today.month &&
          scheduleDate.day == today.day;
    } catch (e) {
      return false;
    }
  }

  String _formatDate(String date) {
    try {
      final dateTime = DateTime.parse(date);
      return DateFormat('MMM dd, yyyy').format(dateTime);
    } catch (e) {
      return date;
    }
  }

  Color _getContributionStatusColor(String status) {
    switch (status.toLowerCase()) {
      case 'complete':
      case 'completed':
        return Colors.green;
      case 'pending':
        return Colors.orange;
      case 'incomplete':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  IconData _getContributionStatusIcon(String status) {
    switch (status.toLowerCase()) {
      case 'complete':
      case 'completed':
        return Icons.check_circle;
      case 'pending':
        return Icons.pending;
      case 'incomplete':
        return Icons.cancel;
      default:
        return Icons.info;
    }
  }
}

class _StatusBadge extends StatelessWidget {
  final String status;

  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    Color color;
    String label;

    switch (status.toLowerCase()) {
      case 'pending':
        color = Colors.orange;
        label = 'Pending';
        break;
      case 'successful':
      case 'completed':
        color = Colors.green;
        label = 'Completed';
        break;
      case 'failed':
        color = Colors.red;
        label = 'Failed';
        break;
      case 'processing':
        color = Colors.blue;
        label = 'Processing';
        break;
      default:
        color = Colors.grey;
        label = status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.2),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }
}
