import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';
import '../../../core/router/app_router.dart';
import '../../../providers/home_provider.dart';
import '../../../providers/auth_provider.dart';
import '../../../services/connectivity_service.dart';
import '../../../shared/widgets/offline_indicator.dart';
import '../../../shared/widgets/error_dialog.dart';
import '../widgets/wallet_balance_card.dart';
import '../widgets/active_groups_summary.dart';
import '../widgets/upcoming_payouts_widget.dart';
import '../widgets/quick_actions_widget.dart';

class HomeDashboardScreen extends ConsumerStatefulWidget {
  const HomeDashboardScreen({super.key});

  @override
  ConsumerState<HomeDashboardScreen> createState() => _HomeDashboardScreenState();
}

class _HomeDashboardScreenState extends ConsumerState<HomeDashboardScreen> {
  @override
  void initState() {
    super.initState();
    // Load dashboard data on init with force refresh
    WidgetsBinding.instance.addPostFrameCallback((_) {
      // Always force refresh on home screen to ensure wallet balance is current
      // This is especially important after wallet funding/withdrawal operations
      _loadData(forceRefresh: true);
    });
  }

  Future<void> _loadData({bool forceRefresh = false}) async {
    await ref.read(homeProvider.notifier).loadDashboardData(forceRefresh: forceRefresh);
  }

  @override
  Widget build(BuildContext context) {
    final homeState = ref.watch(homeProvider);
    final authState = ref.watch(authStateProvider);
    final connectivityState = ref.watch(connectivityStreamProvider);
    
    final isOffline = connectivityState.when(
      data: (isConnected) => !isConnected,
      loading: () => false,
      error: (_, __) => false,
    );
    
    final userName = authState is Authenticated ? authState.user.name : 'User';

    // Show error dialog if there's an error
    if (homeState.error != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        showErrorDialog(
          context,
          homeState.error!,
          title: 'Failed to Load Data',
          onRetry: () => _loadData(forceRefresh: true),
          onDismiss: () => ref.read(homeProvider.notifier).clearError(),
        );
      });
    }

    return Scaffold(
      backgroundColor: AppColors.background,
      body: OfflineIndicatorBanner(
        isOffline: isOffline,
        child: RefreshIndicator(
          onRefresh: () => _loadData(forceRefresh: true),
          child: CustomScrollView(
            slivers: [
              SliverAppBar(
                expandedHeight: 120,
                floating: false,
                pinned: true,
                backgroundColor: AppColors.primary,
                flexibleSpace: FlexibleSpaceBar(
                  title: Column(
                    mainAxisAlignment: MainAxisAlignment.end,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Welcome back,',
                        style: AppTextStyles.caption.copyWith(
                          color: Colors.white.withOpacity(0.9),
                        ),
                      ),
                      Text(
                        userName.split(' ').first,
                        style: AppTextStyles.h2.copyWith(
                          color: Colors.white,
                        ),
                      ),
                    ],
                  ),
                  titlePadding: const EdgeInsets.only(left: 16, bottom: 16),
                ),
                actions: [
                  IconButton(
                    icon: const Icon(Icons.notifications_outlined),
                    onPressed: () => context.push(AppRoutes.notifications),
                  ),
                  IconButton(
                    icon: const Icon(Icons.person_outline),
                    onPressed: () => context.push(AppRoutes.profile),
                  ),
                ],
              ),
              SliverPadding(
                padding: const EdgeInsets.all(16),
                sliver: SliverList(
                  delegate: SliverChildListDelegate([
                    // Wallet Balance Card
                    WalletBalanceCard(
                      balance: homeState.walletBalance,
                      isLoading: homeState.isLoading,
                      onTap: () => context.push(AppRoutes.wallet),
                    ),
                    const SizedBox(height: 16),
                    
                    // Pending Contributions Alert
                    if (homeState.missedContributionsCount > 0)
                      _buildMissedContributionsAlert(context, homeState.missedContributionsCount),
                    if (homeState.missedContributionsCount > 0)
                      const SizedBox(height: 16),
                    
                    // Quick Actions
                    QuickActionsWidget(
                      onCreateGroup: () => context.push(AppRoutes.createGroup),
                      onJoinGroup: () => context.push(AppRoutes.joinGroup),
                      onMakeContribution: () => context.push(AppRoutes.groups),
                    ),
                    const SizedBox(height: 16),
                    
                    // Active Groups Summary
                    ActiveGroupsSummary(
                      groups: homeState.activeGroups,
                      isLoading: homeState.isLoading,
                      onViewAll: () => context.push(AppRoutes.groups),
                      onGroupTap: (groupId) => context.push('${AppRoutes.groups}/$groupId'),
                    ),
                    const SizedBox(height: 16),
                    
                    // Upcoming Payouts
                    UpcomingPayoutsWidget(
                      payouts: homeState.upcomingPayouts,
                      isLoading: homeState.isLoading,
                    ),
                    const SizedBox(height: 16),
                    
                    // Last Updated
                    if (homeState.lastUpdated != null)
                      Center(
                        child: Text(
                          'Last updated: ${_formatLastUpdated(homeState.lastUpdated!)}',
                          style: AppTextStyles.caption.copyWith(
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ),
                    const SizedBox(height: 32),
                  ]),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
  
  Widget _buildMissedContributionsAlert(BuildContext context, int count) {
    return Card(
      elevation: 2,
      color: AppColors.error.withOpacity(0.05),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: AppColors.error.withOpacity(0.3)),
      ),
      child: InkWell(
        onTap: () => context.push(AppRoutes.missedContributions),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: AppColors.error.withOpacity(0.1),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.warning_amber_rounded,
                  color: AppColors.error,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Pending Contributions',
                      style: AppTextStyles.bodySmall.copyWith(
                        color: AppColors.error,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'You have $count missed contribution${count > 1 ? 's' : ''}',
                      style: AppTextStyles.body.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(
                Icons.arrow_forward_ios,
                size: 16,
                color: AppColors.error,
              ),
            ],
          ),
        ),
      ),
    );
  }
  
  String _formatLastUpdated(DateTime dateTime) {
    final now = DateTime.now();
    final difference = now.difference(dateTime);
    
    if (difference.inSeconds < 60) {
      return 'Just now';
    } else if (difference.inMinutes < 60) {
      return '${difference.inMinutes}m ago';
    } else if (difference.inHours < 24) {
      return '${difference.inHours}h ago';
    } else {
      return '${difference.inDays}d ago';
    }
  }
}
