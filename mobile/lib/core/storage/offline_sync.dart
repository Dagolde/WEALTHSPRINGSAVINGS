import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';

/// Manages offline data synchronization
class OfflineSync {
  final SharedPreferences _prefs;
  
  static const String _pendingActionsKey = 'offline_pending_actions';
  
  OfflineSync(this._prefs);
  
  /// Add an action to the pending queue
  Future<void> queueAction(OfflineAction action) async {
    final actions = await getPendingActions();
    actions.add(action);
    await _savePendingActions(actions);
  }
  
  /// Get all pending actions
  Future<List<OfflineAction>> getPendingActions() async {
    final actionsString = _prefs.getString(_pendingActionsKey);
    if (actionsString == null) return [];
    
    try {
      final actionsList = jsonDecode(actionsString) as List;
      return actionsList
          .map((json) => OfflineAction.fromJson(json as Map<String, dynamic>))
          .toList();
    } catch (e) {
      return [];
    }
  }
  
  /// Remove an action from the queue
  Future<void> removeAction(String actionId) async {
    final actions = await getPendingActions();
    actions.removeWhere((action) => action.id == actionId);
    await _savePendingActions(actions);
  }
  
  /// Clear all pending actions
  Future<void> clearAll() async {
    await _prefs.remove(_pendingActionsKey);
  }
  
  /// Save pending actions to storage
  Future<void> _savePendingActions(List<OfflineAction> actions) async {
    final actionsJson = actions.map((action) => action.toJson()).toList();
    await _prefs.setString(_pendingActionsKey, jsonEncode(actionsJson));
  }
}

/// Represents an action to be synced when online
class OfflineAction {
  final String id;
  final String type; // 'contribution', 'wallet_fund', 'withdrawal', etc.
  final Map<String, dynamic> data;
  final DateTime timestamp;
  
  OfflineAction({
    required this.id,
    required this.type,
    required this.data,
    required this.timestamp,
  });
  
  factory OfflineAction.fromJson(Map<String, dynamic> json) {
    return OfflineAction(
      id: json['id'] as String,
      type: json['type'] as String,
      data: json['data'] as Map<String, dynamic>,
      timestamp: DateTime.parse(json['timestamp'] as String),
    );
  }
  
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'type': type,
      'data': data,
      'timestamp': timestamp.toIso8601String(),
    };
  }
}
