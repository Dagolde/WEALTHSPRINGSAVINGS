import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/router/app_router.dart';
import '../../../models/group.dart';
import '../../../providers/group_provider.dart';
import '../../../providers/auth_provider.dart';
import '../../../shared/widgets/app_button.dart';
import '../../contribution/screens/contribution_screen.dart';

class GroupDetailsScreen extends ConsumerStatefulWidget {
  final String groupId;

  const GroupDetailsScreen({
    super.key,
    required this.groupId,
  });

  @override
  ConsumerState<GroupDetailsScreen> createState() =>
      _GroupDetailsScreenState();
}

class _GroupDetailsScreenState extends ConsumerState<GroupDetailsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadGroupDetails();
    });
  }

  void _loadGroupDetails() {
    ref
        .read(groupStateProvider.notifier)
        .getGroupDetails(int.parse(widget.groupId));
  }

  void _startGroup(int groupId) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Start Group'),
        content: const Text(
          'Are you sure you want to start this group? Positions will be assigned randomly to all members.',
        ),
        actions: [
          TextButton(
            onPressed: () => context.pop(),
            child: const Text('Cancel'),
          ),
          AppButton(
            text: 'Start',
            onPressed: () {
              context.pop();
              ref.read(groupStateProvider.notifier).startGroup(groupId);
            },
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    ref.listen<GroupState>(groupStateProvider, (previous, next) {
      if (next is GroupStarted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Group started successfully!'),
            backgroundColor: Colors.green,
          ),
        );
        _loadGroupDetails();
      } else if (next is GroupError) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.message),
            backgroundColor: Colors.red,
          ),
        );
      }
    });

    final groupState = ref.watch(groupStateProvider);
    final authState = ref.watch(authStateProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Group Details'),
      ),
      body: _buildBody(groupState, authState),
    );
  }

  Widget _buildBody(GroupState groupState, AuthState authState) {
    if (groupState is GroupLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (groupState is GroupError) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            Text(groupState.message),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: _loadGroupDetails,
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    if (groupState is GroupDetailsLoaded) {
      final group = groupState.group;
      final members = groupState.members;
      final currentUserId =
          authState is Authenticated ? authState.user.id : null;
      final isCreator = currentUserId == group.createdBy;

      return RefreshIndicator(
        onRefresh: () async => _loadGroupDetails(),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _GroupInfoCard(group: group),
              const SizedBox(height: 16),
              _MembersSection(members: members),
              const SizedBox(height: 16),
              if (group.isActive) ...[
                AppButton(
                  text: 'View Payout Schedule',
                  onPressed: () =>
                      context.push('${AppRoutes.groups}/${group.id}/schedule'),
                ),
                const SizedBox(height: 12),
                AppButton(
                  text: 'Make Contribution',
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => ContributionScreen(group: group),
                      ),
                    );
                  },
                  type: ButtonType.secondary,
                ),
              ],
              if (group.isPending && isCreator && group.isFull) ...[
                AppButton(
                  text: 'Start Group',
                  onPressed: () => _startGroup(group.id),
                ),
              ],
            ],
          ),
        ),
      );
    }

    return const Center(child: Text('No data available'));
  }
}

class _GroupInfoCard extends StatelessWidget {
  final Group group;

  const _GroupInfoCard({required this.group});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    group.name,
                    style: const TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                _StatusBadge(status: group.status),
              ],
            ),
            if (group.description != null) ...[
              const SizedBox(height: 8),
              Text(
                group.description!,
                style: const TextStyle(
                  fontSize: 14,
                  color: Colors.grey,
                ),
              ),
            ],
            const SizedBox(height: 16),
            const Divider(),
            const SizedBox(height: 16),
            _InfoRow(
              label: 'Group Code',
              value: group.groupCode,
              trailing: IconButton(
                icon: const Icon(Icons.copy, size: 20),
                onPressed: () {
                  Clipboard.setData(ClipboardData(text: group.groupCode));
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Group code copied!')),
                  );
                },
              ),
            ),
            const SizedBox(height: 12),
            _InfoRow(
              label: 'Contribution Amount',
              value: '₦${group.contributionAmountValue.toStringAsFixed(0)}',
            ),
            const SizedBox(height: 12),
            _InfoRow(
              label: 'Total Pool',
              value: '₦${group.totalPoolAmount.toStringAsFixed(0)}',
            ),
            const SizedBox(height: 12),
            _InfoRow(
              label: 'Members',
              value: '${group.currentMembers}/${group.totalMembers}',
            ),
            const SizedBox(height: 12),
            _InfoRow(
              label: 'Cycle Duration',
              value: '${group.cycleDays} days',
            ),
            const SizedBox(height: 12),
            _InfoRow(
              label: 'Frequency',
              value: group.frequency.toUpperCase(),
            ),
            if (group.startDate != null) ...[
              const SizedBox(height: 12),
              _InfoRow(
                label: 'Start Date',
                value: group.startDate!,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;
  final Widget? trailing;

  const _InfoRow({
    required this.label,
    required this.value,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: const TextStyle(
            fontSize: 14,
            color: Colors.grey,
          ),
        ),
        Row(
          children: [
            Text(
              value,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
              ),
            ),
            if (trailing != null) trailing!,
          ],
        ),
      ],
    );
  }
}

class _MembersSection extends StatelessWidget {
  final List<GroupMember> members;

  const _MembersSection({required this.members});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Members',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 16),
            ...members.map((member) => _MemberTile(member: member)),
          ],
        ),
      ),
    );
  }
}

class _MemberTile extends StatelessWidget {
  final GroupMember member;

  const _MemberTile({required this.member});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          CircleAvatar(
            backgroundColor: AppColors.primary,
            child: Text(
              member.userName?.substring(0, 1).toUpperCase() ?? 'U',
              style: const TextStyle(color: Colors.white),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  member.userName ?? 'Unknown',
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                if (member.userEmail != null)
                  Text(
                    member.userEmail!,
                    style: const TextStyle(
                      fontSize: 12,
                      color: Colors.grey,
                    ),
                  ),
              ],
            ),
          ),
          if (member.hasPosition)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: AppColors.primary.withOpacity(0.2),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                'Position ${member.positionNumber}',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.bold,
                  color: AppColors.primary,
                ),
              ),
            ),
          if (member.hasReceivedPayout)
            const Icon(
              Icons.check_circle,
              color: Colors.green,
              size: 20,
            ),
        ],
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String status;

  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    Color color;
    String label;

    switch (status) {
      case 'pending':
        color = Colors.orange;
        label = 'Pending';
        break;
      case 'active':
        color = Colors.green;
        label = 'Active';
        break;
      case 'completed':
        color = Colors.blue;
        label = 'Completed';
        break;
      case 'cancelled':
        color = Colors.red;
        label = 'Cancelled';
        break;
      default:
        color = Colors.grey;
        label = status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
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
