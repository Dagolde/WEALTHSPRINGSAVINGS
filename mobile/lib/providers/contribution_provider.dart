import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../core/di/injection.dart';
import '../models/contribution.dart';
import '../repositories/contribution_repository.dart';

/// Contribution state
sealed class ContributionState {
  const ContributionState();
}

class ContributionInitial extends ContributionState {
  const ContributionInitial();
}

class ContributionLoading extends ContributionState {
  const ContributionLoading();
}

class ContributionRecorded extends ContributionState {
  final Contribution contribution;
  const ContributionRecorded(this.contribution);
}

class ContributionVerified extends ContributionState {
  final Contribution contribution;
  const ContributionVerified(this.contribution);
}

class ContributionHistoryLoaded extends ContributionState {
  final List<Contribution> contributions;
  const ContributionHistoryLoaded(this.contributions);
}

class MissedContributionsLoaded extends ContributionState {
  final List<MissedContribution> missedContributions;
  const MissedContributionsLoaded(this.missedContributions);
}

class PaymentInitialized extends ContributionState {
  final PaymentInitializationResponse paymentData;
  const PaymentInitialized(this.paymentData);
}

class ContributionError extends ContributionState {
  final String message;
  const ContributionError(this.message);
}

/// Contribution state notifier
class ContributionNotifier extends StateNotifier<ContributionState> {
  final ContributionRepository _contributionRepository;

  ContributionNotifier(this._contributionRepository)
      : super(const ContributionInitial());

  Future<void> recordContribution({
    required int groupId,
    required double amount,
    required String paymentMethod,
    String? paymentReference,
  }) async {
    state = const ContributionLoading();
    try {
      final contribution = await _contributionRepository.recordContribution(
        groupId: groupId,
        amount: amount,
        paymentMethod: paymentMethod,
        paymentReference: paymentReference,
      );
      state = ContributionRecorded(contribution);
    } catch (e) {
      state = ContributionError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<void> verifyContribution(String paymentReference) async {
    state = const ContributionLoading();
    try {
      final contribution =
          await _contributionRepository.verifyContribution(paymentReference);
      state = ContributionVerified(contribution);
    } catch (e) {
      state = ContributionError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<void> getContributionHistory({int? groupId}) async {
    state = const ContributionLoading();
    try {
      final contributions =
          await _contributionRepository.getContributionHistory(groupId: groupId);
      state = ContributionHistoryLoaded(contributions);
    } catch (e) {
      state = ContributionError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<void> getGroupContributions(int groupId) async {
    state = const ContributionLoading();
    try {
      final contributions =
          await _contributionRepository.getGroupContributions(groupId);
      state = ContributionHistoryLoaded(contributions);
    } catch (e) {
      state = ContributionError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<void> getMissedContributions() async {
    state = const ContributionLoading();
    try {
      final missedContributions =
          await _contributionRepository.getMissedContributions();
      state = MissedContributionsLoaded(missedContributions);
    } catch (e) {
      state = ContributionError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<bool> checkTodayContribution(int groupId) async {
    try {
      return await _contributionRepository.checkTodayContribution(groupId);
    } catch (e) {
      return false;
    }
  }

  void reset() {
    state = const ContributionInitial();
  }
}

/// Contribution state provider
final contributionStateProvider =
    StateNotifierProvider<ContributionNotifier, ContributionState>((ref) {
  final contributionRepository = ref.watch(contributionRepositoryProvider);
  return ContributionNotifier(contributionRepository);
});
