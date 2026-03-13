import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../../providers/notification_provider.dart';
import '../../../models/notification.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/router/app_router.dart';

class NotificationsListScreen extends ConsumerStatefulWidget {
  const NotificationsListScreen({super.key});

  @override
  ConsumerState<NotificationsListScreen> createState() => _NotificationsListScreenState();
}

class _NotificationsListScreenState extends ConsumerState<NotificationsListScreen> {
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    
    // Load notifications on init
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(notificationListProvider.notifier).loadNotifications(refresh: true);
      ref.read(unreadCountProvider.notifier).loadUnreadCount();
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent * 0.9) {
      ref.read(notificationListProvider.notifier).loadMore();
    }
  }

  Future<void> _onRefresh() async {
    await ref.read(notificationListProvider.notifier).loadNotifications(refresh: true);
    await ref.read(unreadCountProvider.notifier).loadUnreadCount();
  }

  Future<void> _markAllAsRead() async {
    try {
      await ref.read(notificationListProvider.notifier).markAllAsRead();
      ref.read(unreadCountProvider.notifier).resetCount();
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('All notifications marked as read')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: ${e.toString()}')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final notificationState = ref.watch(notificationListProvider);
    final unreadCount = ref.watch(unreadCountProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          if (unreadCount > 0)
            TextButton.icon(
              onPressed: _markAllAsRead,
              icon: const Icon(Icons.done_all, size: 18),
              label: const Text('Mark all read'),
              style: TextButton.styleFrom(
                foregroundColor: Colors.white,
              ),
            ),
          IconButton(
            icon: const Icon(Icons.settings),
            onPressed: () {
              context.push(AppRoutes.notificationSettings);
            },
          ),
        ],
      ),
      body: _buildBody(notificationState),
    );
  }

  Widget _buildBody(NotificationListState state) {
    return switch (state) {
      NotificationListInitial() => const Center(child: CircularProgressIndicator()),
      NotificationListLoading() => const Center(child: CircularProgressIndicator()),
      NotificationListError(:final message) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 64, color: Colors.red),
              const SizedBox(height: 16),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: _onRefresh,
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      NotificationListLoaded(:final notifications, :final hasMore) =>
        notifications.isEmpty
            ? const EmptyState(
                icon: Icons.notifications_none,
                title: 'No notifications',
                message: 'You don\'t have any notifications yet',
              )
            : RefreshIndicator(
                onRefresh: _onRefresh,
                child: _buildNotificationsList(notifications, hasMore),
              ),
    };
  }

  Widget _buildNotificationsList(List<AppNotification> notifications, bool hasMore) {
    // Group notifications by date
    final groupedNotifications = _groupNotificationsByDate(notifications);

    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.symmetric(vertical: 8),
      itemCount: groupedNotifications.length + (hasMore ? 1 : 0),
      itemBuilder: (context, index) {
        if (index >= groupedNotifications.length) {
          return const Center(
            child: Padding(
              padding: EdgeInsets.all(16.0),
              child: CircularProgressIndicator(),
            ),
          );
        }

        final group = groupedNotifications[index];
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Text(
                group['label'] as String,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.bold,
                  color: Colors.grey,
                ),
              ),
            ),
            ...((group['notifications'] as List<AppNotification>).map(
              (notification) => _buildNotificationItem(notification),
            )),
          ],
        );
      },
    );
  }

  List<Map<String, dynamic>> _groupNotificationsByDate(List<AppNotification> notifications) {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final yesterday = today.subtract(const Duration(days: 1));
    final thisWeek = today.subtract(const Duration(days: 7));

    final todayNotifications = <AppNotification>[];
    final yesterdayNotifications = <AppNotification>[];
    final thisWeekNotifications = <AppNotification>[];
    final olderNotifications = <AppNotification>[];

    for (final notification in notifications) {
      final date = notification.createdAtDateTime;
      final dateOnly = DateTime(date.year, date.month, date.day);

      if (dateOnly == today) {
        todayNotifications.add(notification);
      } else if (dateOnly == yesterday) {
        yesterdayNotifications.add(notification);
      } else if (dateOnly.isAfter(thisWeek)) {
        thisWeekNotifications.add(notification);
      } else {
        olderNotifications.add(notification);
      }
    }

    final groups = <Map<String, dynamic>>[];
    if (todayNotifications.isNotEmpty) {
      groups.add({'label': 'Today', 'notifications': todayNotifications});
    }
    if (yesterdayNotifications.isNotEmpty) {
      groups.add({'label': 'Yesterday', 'notifications': yesterdayNotifications});
    }
    if (thisWeekNotifications.isNotEmpty) {
      groups.add({'label': 'This Week', 'notifications': thisWeekNotifications});
    }
    if (olderNotifications.isNotEmpty) {
      groups.add({'label': 'Older', 'notifications': olderNotifications});
    }

    return groups;
  }

  Widget _buildNotificationItem(AppNotification notification) {
    final iconColor = _parseColor(notification.colorHex);
    
    return InkWell(
      onTap: () => _onNotificationTap(notification),
      child: Container(
        color: notification.isRead ? null : AppColors.primary.withOpacity(0.05),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Icon
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: iconColor.withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: Icon(
                _getIconData(notification.iconName),
                color: iconColor,
                size: 20,
              ),
            ),
            const SizedBox(width: 12),
            
            // Content
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          notification.title,
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: notification.isRead ? FontWeight.normal : FontWeight.bold,
                          ),
                        ),
                      ),
                      if (!notification.isRead)
                        Container(
                          width: 8,
                          height: 8,
                          decoration: const BoxDecoration(
                            color: AppColors.primary,
                            shape: BoxShape.circle,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    notification.message,
                    style: TextStyle(
                      fontSize: 14,
                      color: Colors.grey[600],
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    _formatTime(notification.createdAtDateTime),
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.grey[500],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _onNotificationTap(AppNotification notification) async {
    // Mark as read if unread
    if (!notification.isRead) {
      try {
        await ref.read(notificationListProvider.notifier).markAsRead(notification.id);
        ref.read(unreadCountProvider.notifier).decrementCount();
      } catch (e) {
        // Silently fail
      }
    }

    // Handle navigation based on notification type and data
    if (notification.data != null) {
      _handleNotificationNavigation(notification);
    }
  }

  void _handleNotificationNavigation(AppNotification notification) {
    // TODO: Implement navigation based on notification type
    // Example:
    // final data = notification.data!;
    // if (notification.type.contains('group') && data.containsKey('group_id')) {
    //   context.push('/groups/${data['group_id']}');
    // }
  }

  Color _parseColor(String hexColor) {
    try {
      return Color(int.parse(hexColor.replaceFirst('#', '0xFF')));
    } catch (e) {
      return Colors.grey;
    }
  }

  IconData _getIconData(String iconName) {
    switch (iconName) {
      case 'group':
        return Icons.group;
      case 'payment':
        return Icons.payment;
      case 'account_balance_wallet':
        return Icons.account_balance_wallet;
      case 'verified_user':
        return Icons.verified_user;
      case 'money_off':
        return Icons.money_off;
      default:
        return Icons.notifications;
    }
  }

  String _formatTime(DateTime dateTime) {
    final now = DateTime.now();
    final difference = now.difference(dateTime);

    if (difference.inMinutes < 1) {
      return 'Just now';
    } else if (difference.inMinutes < 60) {
      return '${difference.inMinutes}m ago';
    } else if (difference.inHours < 24) {
      return '${difference.inHours}h ago';
    } else {
      return DateFormat('MMM d, h:mm a').format(dateTime);
    }
  }
}
