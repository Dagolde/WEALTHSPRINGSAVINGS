import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/auth_provider.dart';
import '../../features/auth/screens/login_screen.dart';
import '../../features/auth/screens/register_screen.dart';
import '../../features/auth/screens/otp_verification_screen.dart';
import '../../features/auth/screens/profile_screen.dart';
import '../../features/home/screens/home_dashboard_screen.dart';
import '../../features/kyc/screens/kyc_submission_screen.dart';
import '../../features/kyc/screens/kyc_status_screen.dart';
import '../../features/bank_account/screens/bank_account_linking_screen.dart';
import '../../features/bank_account/screens/bank_accounts_list_screen.dart';
import '../../features/group/screens/group_list_screen.dart';
import '../../features/group/screens/group_creation_screen.dart';
import '../../features/group/screens/group_joining_screen.dart';
import '../../features/group/screens/group_details_screen.dart';
import '../../features/group/screens/payout_schedule_screen.dart';
import '../../features/contribution/screens/contribution_history_screen.dart';
import '../../features/contribution/screens/missed_contributions_screen.dart';
import '../../features/wallet/screens/wallet_dashboard_screen.dart';
import '../../features/wallet/screens/wallet_funding_screen.dart';
import '../../features/wallet/screens/withdrawal_screen.dart';
import '../../features/wallet/screens/transaction_history_screen.dart';
import '../../features/notifications/screens/notifications_list_screen.dart';
import '../../features/notifications/screens/notification_settings_screen.dart';
import '../../core/theme/app_colors.dart';

/// App routes
class AppRoutes {
  static const String login = '/login';
  static const String register = '/register';
  static const String otpVerification = '/otp-verification';
  static const String home = '/';
  static const String profile = '/profile';
  static const String kycStatus = '/kyc/status';
  static const String kycSubmission = '/kyc/submit';
  static const String bankAccounts = '/bank-accounts';
  static const String linkBankAccount = '/bank-accounts/link';
  static const String groups = '/groups';
  static const String groupDetails = '/groups/:id';
  static const String createGroup = '/groups/create';
  static const String joinGroup = '/groups/join';
  static const String wallet = '/wallet';
  static const String fundWallet = '/wallet/fund';
  static const String withdraw = '/wallet/withdraw';
  static const String transactionHistory = '/wallet/transactions';
  static const String contributions = '/contributions';
  static const String contributionHistory = '/contributions/history';
  static const String missedContributions = '/contributions/missed';
  static const String notifications = '/notifications';
  static const String notificationSettings = '/notifications/settings';
}

// Bottom navigation shell key
final _rootNavigatorKey = GlobalKey<NavigatorState>();
final _shellNavigatorKey = GlobalKey<NavigatorState>();

/// Router provider
final appRouterProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authStateProvider);
  
  return GoRouter(
    navigatorKey: _rootNavigatorKey,
    initialLocation: AppRoutes.login,
    redirect: (context, state) {
      final isAuthenticated = authState is Authenticated;
      
      final isAuthRoute = state.matchedLocation == AppRoutes.login ||
          state.matchedLocation == AppRoutes.register ||
          state.matchedLocation == AppRoutes.otpVerification;
      
      // Redirect to login if not authenticated and trying to access protected route
      if (!isAuthenticated && !isAuthRoute) {
        return AppRoutes.login;
      }
      
      // Redirect to home if authenticated and trying to access auth routes
      if (isAuthenticated && isAuthRoute) {
        return AppRoutes.home;
      }
      
      return null;
    },
    routes: [
      GoRoute(
        path: AppRoutes.login,
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: AppRoutes.register,
        builder: (context, state) => const RegisterScreen(),
      ),
      GoRoute(
        path: AppRoutes.otpVerification,
        builder: (context, state) {
          final email = state.uri.queryParameters['email'] ?? '';
          final type = state.uri.queryParameters['type'] ?? 'email';
          return OtpVerificationScreen(
            email: email,
            verificationType: type,
          );
        },
      ),
      // Bottom navigation shell
      ShellRoute(
        navigatorKey: _shellNavigatorKey,
        builder: (context, state, child) {
          return ScaffoldWithBottomNav(child: child);
        },
        routes: [
          GoRoute(
            path: AppRoutes.home,
            pageBuilder: (context, state) => NoTransitionPage(
              child: const HomeDashboardScreen(),
            ),
          ),
          GoRoute(
            path: AppRoutes.groups,
            pageBuilder: (context, state) => NoTransitionPage(
              child: const GroupListScreen(),
            ),
          ),
          GoRoute(
            path: AppRoutes.wallet,
            pageBuilder: (context, state) => NoTransitionPage(
              child: const WalletDashboardScreen(),
            ),
          ),
          GoRoute(
            path: AppRoutes.profile,
            pageBuilder: (context, state) => NoTransitionPage(
              child: const ProfileScreen(),
            ),
          ),
        ],
      ),
      // Other routes (outside bottom nav)
      GoRoute(
        path: AppRoutes.kycStatus,
        builder: (context, state) => const KycStatusScreen(),
      ),
      GoRoute(
        path: AppRoutes.kycSubmission,
        builder: (context, state) => const KycSubmissionScreen(),
      ),
      GoRoute(
        path: AppRoutes.bankAccounts,
        builder: (context, state) => const BankAccountsListScreen(),
      ),
      GoRoute(
        path: AppRoutes.linkBankAccount,
        builder: (context, state) => const BankAccountLinkingScreen(),
      ),
      GoRoute(
        path: AppRoutes.createGroup,
        builder: (context, state) => const GroupCreationScreen(),
      ),
      GoRoute(
        path: AppRoutes.joinGroup,
        builder: (context, state) => const GroupJoiningScreen(),
      ),
      GoRoute(
        path: AppRoutes.groupDetails,
        builder: (context, state) {
          final id = state.pathParameters['id']!;
          return GroupDetailsScreen(groupId: id);
        },
      ),
      GoRoute(
        path: '${AppRoutes.groupDetails}/schedule',
        builder: (context, state) {
          final id = state.pathParameters['id']!;
          return PayoutScheduleScreen(groupId: id);
        },
      ),
      GoRoute(
        path: AppRoutes.contributionHistory,
        builder: (context, state) => const ContributionHistoryScreen(),
      ),
      GoRoute(
        path: AppRoutes.missedContributions,
        builder: (context, state) => const MissedContributionsScreen(),
      ),
      GoRoute(
        path: AppRoutes.fundWallet,
        builder: (context, state) => const WalletFundingScreen(),
      ),
      GoRoute(
        path: AppRoutes.withdraw,
        builder: (context, state) => const WithdrawalScreen(),
      ),
      GoRoute(
        path: AppRoutes.transactionHistory,
        builder: (context, state) => const TransactionHistoryScreen(),
      ),
      GoRoute(
        path: AppRoutes.notifications,
        builder: (context, state) => const NotificationsListScreen(),
      ),
      GoRoute(
        path: AppRoutes.notificationSettings,
        builder: (context, state) => const NotificationSettingsScreen(),
      ),
    ],
    errorBuilder: (context, state) => Scaffold(
      body: Center(
        child: Text('Page not found: ${state.matchedLocation}'),
      ),
    ),
  );
});

/// Bottom navigation scaffold
class ScaffoldWithBottomNav extends StatefulWidget {
  final Widget child;
  
  const ScaffoldWithBottomNav({
    super.key,
    required this.child,
  });

  @override
  State<ScaffoldWithBottomNav> createState() => _ScaffoldWithBottomNavState();
}

class _ScaffoldWithBottomNavState extends State<ScaffoldWithBottomNav> {
  int _currentIndex = 0;

  void _onItemTapped(int index) {
    setState(() {
      _currentIndex = index;
    });
    
    switch (index) {
      case 0:
        context.go(AppRoutes.home);
        break;
      case 1:
        context.go(AppRoutes.groups);
        break;
      case 2:
        context.go(AppRoutes.wallet);
        break;
      case 3:
        context.go(AppRoutes.profile);
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    // Update current index based on current location
    final location = GoRouterState.of(context).matchedLocation;
    if (location == AppRoutes.home) {
      _currentIndex = 0;
    } else if (location == AppRoutes.groups) {
      _currentIndex = 1;
    } else if (location == AppRoutes.wallet) {
      _currentIndex = 2;
    } else if (location == AppRoutes.profile) {
      _currentIndex = 3;
    }

    return Scaffold(
      body: widget.child,
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: _onItemTapped,
        type: BottomNavigationBarType.fixed,
        selectedItemColor: AppColors.primary,
        unselectedItemColor: AppColors.textSecondary,
        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.home_outlined),
            activeIcon: Icon(Icons.home),
            label: 'Home',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.group_outlined),
            activeIcon: Icon(Icons.group),
            label: 'Groups',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.account_balance_wallet_outlined),
            activeIcon: Icon(Icons.account_balance_wallet),
            label: 'Wallet',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),
            activeIcon: Icon(Icons.person),
            label: 'Profile',
          ),
        ],
      ),
    );
  }
}
