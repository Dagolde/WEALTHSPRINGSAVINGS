import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/notification.dart';
import '../repositories/notification_repository.dart';
import '../core/di/injection.dart';

// Notification list state
sealed class NotificationListState {}

class NotificationListInitial extends NotificationListState {}

class NotificationListLoading extends NotificationListState {}

class NotificationListLoaded extends NotificationListState {
  final List<AppNotification> notifications;
  final bool hasMore;
  NotificationListLoaded(this.notifications, {this.hasMore = true});
}

class NotificationListError extends NotificationListState {
  final String message;
  NotificationListError(this.message);
}

// Notification settings state
sealed class NotificationSettingsState {}

class NotificationSettingsInitial extends NotificationSettingsState {}

class NotificationSettingsLoading extends NotificationSettingsState {}

class NotificationSettingsLoaded extends NotificationSettingsState {
  final NotificationSettings settings;
  NotificationSettingsLoaded(this.settings);
}

class NotificationSettingsError extends NotificationSettingsState {
  final String message;
  NotificationSettingsError(this.message);
}

// Notification settings update state
sealed class NotificationSettingsUpdateState {}

class NotificationSettingsUpdateInitial extends NotificationSettingsUpdateState {}

class NotificationSettingsUpdateLoading extends NotificationSettingsUpdateState {}

class NotificationSettingsUpdateSuccess extends NotificationSettingsUpdateState {
  final NotificationSettings settings;
  NotificationSettingsUpdateSuccess(this.settings);
}

class NotificationSettingsUpdateError extends NotificationSettingsUpdateState {
  final String message;
  NotificationSettingsUpdateError(this.message);
}

// Notification List Provider
class NotificationListNotifier extends StateNotifier<NotificationListState> {
  final NotificationRepository _repository;
  int _currentPage = 1;
  bool _unreadOnly = false;

  NotificationListNotifier(this._repository) : super(NotificationListInitial());

  Future<void> loadNotifications({
    bool refresh = false,
    bool unreadOnly = false,
  }) async {
    if (refresh) {
      _currentPage = 1;
      _unreadOnly = unreadOnly;
      state = NotificationListLoading();
    } else if (state is NotificationListLoading) {
      return; // Prevent duplicate loading
    }

    try {
      final notifications = await _repository.getNotifications(
        page: _currentPage,
        unreadOnly: _unreadOnly,
      );

      if (state is NotificationListLoaded && !refresh) {
        final currentNotifications = (state as NotificationListLoaded).notifications;
        state = NotificationListLoaded(
          [...currentNotifications, ...notifications],
          hasMore: notifications.isNotEmpty,
        );
      } else {
        state = NotificationListLoaded(
          notifications,
          hasMore: notifications.isNotEmpty,
        );
      }
    } catch (e) {
      state = NotificationListError(e.toString());
    }
  }

  Future<void> loadMore() async {
    if (state is NotificationListLoaded) {
      final currentState = state as NotificationListLoaded;
      if (currentState.hasMore) {
        _currentPage++;
        await loadNotifications();
      }
    }
  }

  Future<void> markAsRead(int notificationId) async {
    try {
      await _repository.markAsRead(notificationId);
      
      // Update local state
      if (state is NotificationListLoaded) {
        final currentState = state as NotificationListLoaded;
        final updatedNotifications = currentState.notifications.map((notification) {
          if (notification.id == notificationId) {
            return notification.copyWith(
              isRead: true,
              readAt: DateTime.now().toIso8601String(),
            );
          }
          return notification;
        }).toList();
        
        state = NotificationListLoaded(
          updatedNotifications,
          hasMore: currentState.hasMore,
        );
      }
    } catch (e) {
      // Silently fail or show error
      rethrow;
    }
  }

  Future<void> markAllAsRead() async {
    try {
      await _repository.markAllAsRead();
      
      // Update local state
      if (state is NotificationListLoaded) {
        final currentState = state as NotificationListLoaded;
        final updatedNotifications = currentState.notifications.map((notification) {
          return notification.copyWith(
            isRead: true,
            readAt: DateTime.now().toIso8601String(),
          );
        }).toList();
        
        state = NotificationListLoaded(
          updatedNotifications,
          hasMore: currentState.hasMore,
        );
      }
    } catch (e) {
      rethrow;
    }
  }

  void reset() {
    _currentPage = 1;
    _unreadOnly = false;
    state = NotificationListInitial();
  }
}

// Notification Settings Provider
class NotificationSettingsNotifier extends StateNotifier<NotificationSettingsState> {
  final NotificationRepository _repository;

  NotificationSettingsNotifier(this._repository) : super(NotificationSettingsInitial());

  Future<void> loadSettings() async {
    state = NotificationSettingsLoading();
    try {
      final settings = await _repository.getSettings();
      state = NotificationSettingsLoaded(settings);
    } catch (e) {
      state = NotificationSettingsError(e.toString());
    }
  }

  Future<void> refreshSettings() async {
    try {
      final settings = await _repository.getSettings();
      state = NotificationSettingsLoaded(settings);
    } catch (e) {
      if (state is! NotificationSettingsLoaded) {
        state = NotificationSettingsError(e.toString());
      }
    }
  }
}

// Notification Settings Update Provider
class NotificationSettingsUpdateNotifier extends StateNotifier<NotificationSettingsUpdateState> {
  final NotificationRepository _repository;

  NotificationSettingsUpdateNotifier(this._repository)
      : super(NotificationSettingsUpdateInitial());

  Future<void> updateSettings(NotificationSettings settings) async {
    state = NotificationSettingsUpdateLoading();
    try {
      final updatedSettings = await _repository.updateSettings(settings);
      state = NotificationSettingsUpdateSuccess(updatedSettings);
    } catch (e) {
      state = NotificationSettingsUpdateError(e.toString());
    }
  }

  void reset() {
    state = NotificationSettingsUpdateInitial();
  }
}

// Unread count provider
class UnreadCountNotifier extends StateNotifier<int> {
  final NotificationRepository _repository;

  UnreadCountNotifier(this._repository) : super(0);

  Future<void> loadUnreadCount() async {
    try {
      final count = await _repository.getUnreadCount();
      state = count;
    } catch (e) {
      // Keep current state on error
    }
  }

  void decrementCount() {
    if (state > 0) {
      state = state - 1;
    }
  }

  void resetCount() {
    state = 0;
  }
}

// Provider instances
final notificationListProvider =
    StateNotifierProvider<NotificationListNotifier, NotificationListState>((ref) {
  final repository = ref.watch(notificationRepositoryProvider);
  return NotificationListNotifier(repository);
});

final notificationSettingsProvider =
    StateNotifierProvider<NotificationSettingsNotifier, NotificationSettingsState>((ref) {
  final repository = ref.watch(notificationRepositoryProvider);
  return NotificationSettingsNotifier(repository);
});

final notificationSettingsUpdateProvider =
    StateNotifierProvider<NotificationSettingsUpdateNotifier, NotificationSettingsUpdateState>(
        (ref) {
  final repository = ref.watch(notificationRepositoryProvider);
  return NotificationSettingsUpdateNotifier(repository);
});

final unreadCountProvider = StateNotifierProvider<UnreadCountNotifier, int>((ref) {
  final repository = ref.watch(notificationRepositoryProvider);
  return UnreadCountNotifier(repository);
});

// Auto-refresh unread count every 60 seconds
final unreadCountAutoRefreshProvider = StreamProvider<void>((ref) {
  return Stream.periodic(const Duration(seconds: 60), (_) {
    ref.read(unreadCountProvider.notifier).loadUnreadCount();
  });
});
