import 'package:json_annotation/json_annotation.dart';

part 'notification.g.dart';

@JsonSerializable()
class AppNotification {
  final int id;
  @JsonKey(name: 'user_id')
  final int userId;
  final String type;
  final String title;
  final String message;
  final Map<String, dynamic>? data;
  @JsonKey(name: 'is_read')
  final bool isRead;
  @JsonKey(name: 'created_at')
  final String createdAt;
  @JsonKey(name: 'read_at')
  final String? readAt;

  AppNotification({
    required this.id,
    required this.userId,
    required this.type,
    required this.title,
    required this.message,
    this.data,
    required this.isRead,
    required this.createdAt,
    this.readAt,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) =>
      _$AppNotificationFromJson(json);

  Map<String, dynamic> toJson() => _$AppNotificationToJson(this);

  // Notification type helpers
  bool get isGroupNotification => type.contains('group');
  bool get isContributionNotification => type.contains('contribution');
  bool get isPayoutNotification => type.contains('payout');
  bool get isAdminNotification => type.contains('admin') || type.contains('kyc');

  // Get icon based on notification type
  String get iconName {
    if (type.contains('group')) return 'group';
    if (type.contains('contribution')) return 'payment';
    if (type.contains('payout')) return 'account_balance_wallet';
    if (type.contains('kyc')) return 'verified_user';
    if (type.contains('withdrawal')) return 'money_off';
    return 'notifications';
  }

  // Get color based on notification type
  String get colorHex {
    if (type.contains('group')) return '#4CAF50';
    if (type.contains('contribution')) return '#2196F3';
    if (type.contains('payout')) return '#FF9800';
    if (type.contains('kyc')) return '#9C27B0';
    if (type.contains('withdrawal')) return '#F44336';
    return '#757575';
  }

  DateTime get createdAtDateTime => DateTime.parse(createdAt);
  DateTime? get readAtDateTime => readAt != null ? DateTime.parse(readAt!) : null;

  AppNotification copyWith({
    int? id,
    int? userId,
    String? type,
    String? title,
    String? message,
    Map<String, dynamic>? data,
    bool? isRead,
    String? createdAt,
    String? readAt,
  }) {
    return AppNotification(
      id: id ?? this.id,
      userId: userId ?? this.userId,
      type: type ?? this.type,
      title: title ?? this.title,
      message: message ?? this.message,
      data: data ?? this.data,
      isRead: isRead ?? this.isRead,
      createdAt: createdAt ?? this.createdAt,
      readAt: readAt ?? this.readAt,
    );
  }
}

@JsonSerializable()
class NotificationSettings {
  @JsonKey(name: 'email_enabled')
  final bool emailEnabled;
  @JsonKey(name: 'push_enabled')
  final bool pushEnabled;
  @JsonKey(name: 'sms_enabled')
  final bool smsEnabled;
  
  // Category-specific preferences
  @JsonKey(name: 'groups_enabled')
  final bool groupsEnabled;
  @JsonKey(name: 'contributions_enabled')
  final bool contributionsEnabled;
  @JsonKey(name: 'payouts_enabled')
  final bool payoutsEnabled;
  @JsonKey(name: 'admin_enabled')
  final bool adminEnabled;

  NotificationSettings({
    required this.emailEnabled,
    required this.pushEnabled,
    required this.smsEnabled,
    required this.groupsEnabled,
    required this.contributionsEnabled,
    required this.payoutsEnabled,
    required this.adminEnabled,
  });

  factory NotificationSettings.fromJson(Map<String, dynamic> json) =>
      _$NotificationSettingsFromJson(json);

  Map<String, dynamic> toJson() => _$NotificationSettingsToJson(this);

  factory NotificationSettings.defaults() {
    return NotificationSettings(
      emailEnabled: true,
      pushEnabled: true,
      smsEnabled: true,
      groupsEnabled: true,
      contributionsEnabled: true,
      payoutsEnabled: true,
      adminEnabled: true,
    );
  }

  NotificationSettings copyWith({
    bool? emailEnabled,
    bool? pushEnabled,
    bool? smsEnabled,
    bool? groupsEnabled,
    bool? contributionsEnabled,
    bool? payoutsEnabled,
    bool? adminEnabled,
  }) {
    return NotificationSettings(
      emailEnabled: emailEnabled ?? this.emailEnabled,
      pushEnabled: pushEnabled ?? this.pushEnabled,
      smsEnabled: smsEnabled ?? this.smsEnabled,
      groupsEnabled: groupsEnabled ?? this.groupsEnabled,
      contributionsEnabled: contributionsEnabled ?? this.contributionsEnabled,
      payoutsEnabled: payoutsEnabled ?? this.payoutsEnabled,
      adminEnabled: adminEnabled ?? this.adminEnabled,
    );
  }
}
