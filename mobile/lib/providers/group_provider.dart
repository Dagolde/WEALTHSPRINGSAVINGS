import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../core/di/injection.dart';
import '../models/group.dart';
import '../repositories/group_repository.dart';

/// Group state
sealed class GroupState {
  const GroupState();
}

class GroupInitial extends GroupState {
  const GroupInitial();
}

class GroupLoading extends GroupState {
  const GroupLoading();
}

class GroupsLoaded extends GroupState {
  final List<Group> groups;
  const GroupsLoaded(this.groups);
}

class GroupDetailsLoaded extends GroupState {
  final Group group;
  final List<GroupMember> members;
  const GroupDetailsLoaded(this.group, this.members);
}

class GroupCreated extends GroupState {
  final Group group;
  const GroupCreated(this.group);
}

class GroupJoined extends GroupState {
  final Group group;
  const GroupJoined(this.group);
}

class GroupStarted extends GroupState {
  final Group group;
  const GroupStarted(this.group);
}

class PayoutScheduleLoaded extends GroupState {
  final List<PayoutScheduleItem> schedule;
  const PayoutScheduleLoaded(this.schedule);
}

class GroupError extends GroupState {
  final String message;
  const GroupError(this.message);
}

/// Group state notifier
class GroupNotifier extends StateNotifier<GroupState> {
  final GroupRepository _groupRepository;

  GroupNotifier(this._groupRepository) : super(const GroupInitial());

  Future<void> createGroup({
    required String name,
    String? description,
    required double contributionAmount,
    required int totalMembers,
    required int cycleDays,
    required String frequency,
  }) async {
    state = const GroupLoading();
    try {
      final group = await _groupRepository.createGroup(
        name: name,
        description: description,
        contributionAmount: contributionAmount,
        totalMembers: totalMembers,
        cycleDays: cycleDays,
        frequency: frequency,
      );
      state = GroupCreated(group);
    } catch (e) {
      state = GroupError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<void> joinGroup(String groupCode) async {
    state = const GroupLoading();
    try {
      final group = await _groupRepository.joinGroup(groupCode);
      state = GroupJoined(group);
    } catch (e) {
      state = GroupError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<void> startGroup(int groupId) async {
    state = const GroupLoading();
    try {
      final group = await _groupRepository.startGroup(groupId);
      state = GroupStarted(group);
    } catch (e) {
      state = GroupError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<void> listGroups({String? status}) async {
    state = const GroupLoading();
    try {
      final groups = await _groupRepository.listGroups(status: status);
      state = GroupsLoaded(groups);
    } catch (e) {
      state = GroupError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<void> getGroupDetails(int groupId) async {
    state = const GroupLoading();
    try {
      final group = await _groupRepository.getGroupDetails(groupId);
      final members = await _groupRepository.getGroupMembers(groupId);
      state = GroupDetailsLoaded(group, members);
    } catch (e) {
      state = GroupError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<void> getPayoutSchedule(int groupId) async {
    state = const GroupLoading();
    try {
      final schedule = await _groupRepository.getPayoutSchedule(groupId);
      state = PayoutScheduleLoaded(schedule);
    } catch (e) {
      state = GroupError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  void reset() {
    state = const GroupInitial();
  }
}

/// Group state provider
final groupStateProvider =
    StateNotifierProvider<GroupNotifier, GroupState>((ref) {
  final groupRepository = ref.watch(groupRepositoryProvider);
  return GroupNotifier(groupRepository);
});
