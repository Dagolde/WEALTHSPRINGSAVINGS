import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../../models/contribution.dart';
import '../../../models/group.dart';
import '../../../providers/contribution_provider.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../core/theme/app_colors.dart';
import 'contribution_screen.dart';

class MissedContributionsScreen extends ConsumerStatefulWidget {
  const MissedContributionsScreen({super.key});

  @override
  ConsumerState<MissedContributionsScreen> createState() =>
      _MissedContributionsScreenState();
}

class _MissedContributionsScreenState
    extends ConsumerState<MissedContributionsScreen> {
  @override
  void initState() {
    super.initState();
    _loadMissedContributions();
  }

  void _loadMissedContributions() {
    ref.read(contributionStateProvider.notifier).getMissedContributions();
  }

  @override
  Widget build(BuildContext context) {
    final contributionState = ref.watch(contributionStateProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Missed Contributions'),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          _loadMissedContributions();
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
              onPressed: _loadMissedContributions,
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    if (state is MissedContributionsLoaded) {
      if (state.missedContributions.isEmpty) {
        return const EmptyState(
          icon: Icons.check_circle_outline,
          title: 'All Caught Up!',
          message: 'You have no missed contributions.',
        );
      }

      final totalMissed = state.missedContributions.fold<double>(
        0,
        (sum, item) => sum + item.amountValue,
      );

      return Column(
        children: [
          // Total missed amount card
          Container(
            width: double.infinity,
            margin: const EdgeInsets.all(16),
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Colors.red[50],
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: Colors.red),
            ),
            child: Column(
              children: [
                const Text(
                  'Total Missed Amount',
                  style: TextStyle(
                    fontSize: 14,
                    color: Colors.red,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  '₦${totalMissed.toStringAsFixed(2)}',
                  style: const TextStyle(
                    fontSize: 32,
                    fontWeight: FontWeight.bold,
                    color: Colors.red,
                  ),
                ),
              ],
            ),
          ),

          // Missed contributions list
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: state.missedContributions.length,
              itemBuilder: (context, index) {
                final missed = state.missedContributions[index];
                return _buildMissedContributionCard(missed);
              },
            ),
          ),
        ],
      );
    }

    return const EmptyState(
      icon: Icons.check_circle_outline,
      title: 'All Caught Up!',
      message: 'You have no missed contributions.',
    );
  }

  Widget _buildMissedContributionCard(MissedContribution missed) {
    final dateFormat = DateFormat('MMM dd, yyyy');

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
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
                      Text(
                        missed.groupName,
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Missed on ${dateFormat.format(DateTime.parse(missed.missedDate))}',
                        style: TextStyle(
                          color: Colors.grey[600],
                          fontSize: 14,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${missed.daysMissed} day${missed.daysMissed > 1 ? 's' : ''} ago',
                        style: const TextStyle(
                          color: Colors.red,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                Text(
                  '₦${missed.amountValue.toStringAsFixed(2)}',
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Colors.red,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: AppButton(
                text: 'Pay Now',
                onPressed: () => _handlePayNow(missed),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _handlePayNow(MissedContribution missed) {
    // Create a temporary Group object for the contribution screen
    final group = Group(
      id: missed.groupId,
      name: missed.groupName,
      groupCode: '',
      contributionAmount: missed.contributionAmount,
      totalMembers: 0,
      currentMembers: 0,
      cycleDays: 0,
      frequency: 'daily',
      status: 'active',
      createdBy: 0,
    );

    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => ContributionScreen(group: group),
      ),
    ).then((_) {
      // Reload missed contributions after payment
      _loadMissedContributions();
    });
  }
}
