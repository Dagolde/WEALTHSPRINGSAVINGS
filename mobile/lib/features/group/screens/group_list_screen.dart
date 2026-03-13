import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/router/app_router.dart';
import '../../../models/group.dart';
import '../../../providers/group_provider.dart';
import '../../../shared/widgets/empty_state.dart';

class GroupListScreen extends ConsumerStatefulWidget {
  const GroupListScreen({super.key});

  @override
  ConsumerState<GroupListScreen> createState() => _GroupListScreenState();
}

class _GroupListScreenState extends ConsumerState<GroupListScreen> {
  String? _selectedFilter;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadGroups();
    });
  }

  void _loadGroups() {
    ref.read(groupStateProvider.notifier).listGroups(status: _selectedFilter);
  }

  void _onFilterChanged(String? filter) {
    setState(() {
      _selectedFilter = filter;
    });
    _loadGroups();
  }

  @override
  Widget build(BuildContext context) {
    final groupState = ref.watch(groupStateProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('My Groups'),
        actions: [
          PopupMenuButton<String>(
            icon: const Icon(Icons.filter_list),
            onSelected: _onFilterChanged,
            itemBuilder: (context) => [
              const PopupMenuItem(
                value: null,
                child: Text('All Groups'),
              ),
              const PopupMenuItem(
                value: 'pending',
                child: Text('Pending'),
              ),
              const PopupMenuItem(
                value: 'active',
                child: Text('Active'),
              ),
              const PopupMenuItem(
                value: 'completed',
                child: Text('Completed'),
              ),
            ],
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async => _loadGroups(),
        child: _buildBody(groupState),
      ),
      floatingActionButton: Column(
        mainAxisAlignment: MainAxisAlignment.end,
        children: [
          FloatingActionButton(
            heroTag: 'join',
            onPressed: () => context.push(AppRoutes.joinGroup),
            child: const Icon(Icons.group_add),
          ),
          const SizedBox(height: 16),
          FloatingActionButton.extended(
            heroTag: 'create',
            onPressed: () => context.push(AppRoutes.createGroup),
            icon: const Icon(Icons.add),
            label: const Text('Create Group'),
          ),
        ],
      ),
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
              onPressed: _loadGroups,
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    if (state is GroupsLoaded) {
      if (state.groups.isEmpty) {
        return EmptyState(
          icon: Icons.group,
          title: 'No Groups Yet',
          message: _selectedFilter == null
              ? 'Create a new group or join an existing one'
              : 'No ${_selectedFilter} groups found',
        );
      }

      return ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: state.groups.length,
        itemBuilder: (context, index) {
          final group = state.groups[index];
          return _GroupCard(
            group: group,
            onTap: () => context.push('${AppRoutes.groups}/${group.id}'),
          );
        },
      );
    }

    return EmptyState(
      icon: Icons.group,
      title: 'No Groups Yet',
      message: 'Create a new group or join an existing one',
    );
  }
}

class _GroupCard extends StatelessWidget {
  final Group group;
  final VoidCallback onTap;

  const _GroupCard({
    required this.group,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      child: InkWell(
        onTap: onTap,
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
                    child: Text(
                      group.name,
                      style: const TextStyle(
                        fontSize: 18,
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
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
              const SizedBox(height: 12),
              Row(
                children: [
                  _InfoChip(
                    icon: Icons.people,
                    label: '${group.currentMembers}/${group.totalMembers}',
                  ),
                  const SizedBox(width: 8),
                  _InfoChip(
                    icon: Icons.attach_money,
                    label: '₦${group.contributionAmountValue.toStringAsFixed(0)}',
                  ),
                  const SizedBox(width: 8),
                  _InfoChip(
                    icon: Icons.calendar_today,
                    label: '${group.cycleDays} days',
                  ),
                ],
              ),
              if (group.isActive) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Total Pool:',
                        style: TextStyle(fontWeight: FontWeight.w500),
                      ),
                      Text(
                        '₦${group.totalPoolAmount.toStringAsFixed(0)}',
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          color: AppColors.primary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
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

class _InfoChip extends StatelessWidget {
  final IconData icon;
  final String label;

  const _InfoChip({
    required this.icon,
    required this.label,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.grey.withOpacity(0.1),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: Colors.grey),
          const SizedBox(width: 4),
          Text(
            label,
            style: const TextStyle(fontSize: 12),
          ),
        ],
      ),
    );
  }
}
