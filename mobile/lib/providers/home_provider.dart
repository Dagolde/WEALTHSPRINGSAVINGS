import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/group.dart';
import '../models/wallet.dart';
import '../repositories/wallet_repository.dart';
import '../repositories/group_repository.dart';
import '../repositories/contribution_repository.dart';
import '../core/di/injection.dart';
import '../features/home/widgets/upcoming_payouts_widget.dart';

class HomeState {
  final double walletBalance;
  final List<Group> activeGroups;
  final List<PayoutInfo> upcomingPayouts;
  final int missedContributionsCount;
  final bool isLoading;
  final String? error;
  final DateTime? lastUpdated;
  
  HomeState({
    this.walletBalance = 0.0,
    this.activeGroups = const [],
    this.upcomingPayouts = const [],
    this.missedContributionsCount = 0,
    this.isLoading = false,
    this.error,
    this.lastUpdated,
  });
  
  HomeState copyWith({
    double? walletBalance,
    List<Group>? activeGroups,
    List<PayoutInfo>? upcomingPayouts,
    int? missedContributionsCount,
    bool? isLoading,
    String? error,
    DateTime? lastUpdated,
  }) {
    return HomeState(
      walletBalance: walletBalance ?? this.walletBalance,
      activeGroups: activeGroups ?? this.activeGroups,
      upcomingPayouts: upcomingPayouts ?? this.upcomingPayouts,
      missedContributionsCount: missedContributionsCount ?? this.missedContributionsCount,
      isLoading: isLoading ?? this.isLoading,
      error: error,
      lastUpdated: lastUpdated ?? this.lastUpdated,
    );
  }
}

class HomeNotifier extends StateNotifier<HomeState> {
  final WalletRepository _walletRepository;
  final GroupRepository _groupRepository;
  final ContributionRepository _contributionRepository;
  
  HomeNotifier(
    this._walletRepository,
    this._groupRepository,
    this._contributionRepository,
  ) : super(HomeState());
  
  Future<void> loadDashboardData({bool forceRefresh = false}) async {
    state = state.copyWith(isLoading: true, error: null);
    
    try {
      // Load all data in parallel with force refresh option
      final results = await Future.wait([
        _walletRepository.getBalance(forceRefresh: forceRefresh),
        _groupRepository.listGroups(forceRefresh: forceRefresh),
        _contributionRepository.getMissedContributions(forceRefresh: forceRefresh),
      ]);
      
      final walletBalance = results[0] as WalletBalance;
      final groups = results[1] as List<Group>;
      final missedContributions = results[2] as List;
      
      // Filter active groups
      final activeGroups = groups.where((g) => g.status == 'active').toList();
      
      // Get upcoming payouts (mock data for now - would come from API)
      final upcomingPayouts = await _getUpcomingPayouts(activeGroups);
      
      state = state.copyWith(
        walletBalance: walletBalance.balanceValue,
        activeGroups: activeGroups,
        upcomingPayouts: upcomingPayouts,
        missedContributionsCount: missedContributions.length,
        isLoading: false,
        lastUpdated: DateTime.now(),
      );
    } catch (e) {
      // Extract meaningful error message
      String errorMessage = 'An unexpected error occurred';
      if (e is Exception) {
        errorMessage = e.toString().replaceAll('Exception: ', '');
      } else {
        errorMessage = e.toString();
      }
      
      state = state.copyWith(
        isLoading: false,
        error: errorMessage,
      );
    }
  }
  
  Future<List<PayoutInfo>> _getUpcomingPayouts(List<Group> groups) async {
    // This would typically come from the API
    // For now, return empty list
    // TODO: Implement payout schedule API endpoint
    return [];
  }
  
  void clearError() {
    state = state.copyWith(error: null);
  }
}

final homeProvider = StateNotifierProvider<HomeNotifier, HomeState>((ref) {
  final walletRepository = ref.watch(walletRepositoryProvider);
  final groupRepository = ref.watch(groupRepositoryProvider);
  final contributionRepository = ref.watch(contributionRepositoryProvider);
  
  return HomeNotifier(
    walletRepository,
    groupRepository,
    contributionRepository,
  );
});
