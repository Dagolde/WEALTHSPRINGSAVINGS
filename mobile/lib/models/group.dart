import 'package:json_annotation/json_annotation.dart';

part 'group.g.dart';

@JsonSerializable()
class Group {
  final int id;
  final String name;
  final String? description;
  @JsonKey(name: 'group_code')
  final String groupCode;
  @JsonKey(name: 'contribution_amount')
  final String contributionAmount;
  @JsonKey(name: 'total_members')
  final int totalMembers;
  @JsonKey(name: 'current_members')
  final int currentMembers;
  @JsonKey(name: 'cycle_days')
  final int cycleDays;
  final String frequency;
  @JsonKey(name: 'start_date')
  final String? startDate;
  @JsonKey(name: 'end_date')
  final String? endDate;
  final String status;
  @JsonKey(name: 'created_by')
  final int createdBy;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Group({
    required this.id,
    required this.name,
    this.description,
    required this.groupCode,
    required this.contributionAmount,
    required this.totalMembers,
    required this.currentMembers,
    required this.cycleDays,
    required this.frequency,
    this.startDate,
    this.endDate,
    required this.status,
    required this.createdBy,
    this.createdAt,
    this.updatedAt,
  });

  factory Group.fromJson(Map<String, dynamic> json) {
    // Handle both string and number types from backend
    final contributionAmountValue = json['contribution_amount'];
    
    return Group(
      id: json['id'] as int,
      name: json['name'] as String,
      description: json['description'] as String?,
      groupCode: json['group_code'] as String,
      contributionAmount: contributionAmountValue is num 
          ? contributionAmountValue.toString() 
          : contributionAmountValue as String,
      totalMembers: json['total_members'] as int,
      currentMembers: json['current_members'] as int,
      cycleDays: json['cycle_days'] as int,
      frequency: json['frequency'] as String,
      startDate: json['start_date'] as String?,
      endDate: json['end_date'] as String?,
      status: json['status'] as String,
      createdBy: json['created_by'] as int,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }
  Map<String, dynamic> toJson() => _$GroupToJson(this);

  bool get isPending => status == 'pending';
  bool get isActive => status == 'active';
  bool get isCompleted => status == 'completed';
  bool get isCancelled => status == 'cancelled';
  bool get isFull => currentMembers >= totalMembers;
  
  double get contributionAmountValue => double.tryParse(contributionAmount) ?? 0.0;
  double get totalPoolAmount => contributionAmountValue * totalMembers;

  Group copyWith({
    int? id,
    String? name,
    String? description,
    String? groupCode,
    String? contributionAmount,
    int? totalMembers,
    int? currentMembers,
    int? cycleDays,
    String? frequency,
    String? startDate,
    String? endDate,
    String? status,
    int? createdBy,
    String? createdAt,
    String? updatedAt,
  }) {
    return Group(
      id: id ?? this.id,
      name: name ?? this.name,
      description: description ?? this.description,
      groupCode: groupCode ?? this.groupCode,
      contributionAmount: contributionAmount ?? this.contributionAmount,
      totalMembers: totalMembers ?? this.totalMembers,
      currentMembers: currentMembers ?? this.currentMembers,
      cycleDays: cycleDays ?? this.cycleDays,
      frequency: frequency ?? this.frequency,
      startDate: startDate ?? this.startDate,
      endDate: endDate ?? this.endDate,
      status: status ?? this.status,
      createdBy: createdBy ?? this.createdBy,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }
}

@JsonSerializable()
class GroupMember {
  final int id;
  @JsonKey(name: 'group_id')
  final int groupId;
  @JsonKey(name: 'user_id')
  final int userId;
  @JsonKey(name: 'user_name')
  final String? userName;
  @JsonKey(name: 'user_email')
  final String? userEmail;
  @JsonKey(name: 'position_number')
  final int? positionNumber;
  @JsonKey(name: 'payout_day')
  final int? payoutDay;
  @JsonKey(name: 'has_received_payout')
  final bool hasReceivedPayout;
  @JsonKey(name: 'payout_received_at')
  final String? payoutReceivedAt;
  @JsonKey(name: 'joined_at')
  final String? joinedAt;
  final String status;

  GroupMember({
    required this.id,
    required this.groupId,
    required this.userId,
    this.userName,
    this.userEmail,
    this.positionNumber,
    this.payoutDay,
    this.hasReceivedPayout = false,
    this.payoutReceivedAt,
    this.joinedAt,
    required this.status,
  });

  factory GroupMember.fromJson(Map<String, dynamic> json) =>
      _$GroupMemberFromJson(json);

  Map<String, dynamic> toJson() => _$GroupMemberToJson(this);

  bool get isActive => status == 'active';
  bool get hasPosition => positionNumber != null;

  GroupMember copyWith({
    int? id,
    int? groupId,
    int? userId,
    String? userName,
    String? userEmail,
    int? positionNumber,
    int? payoutDay,
    bool? hasReceivedPayout,
    String? payoutReceivedAt,
    String? joinedAt,
    String? status,
  }) {
    return GroupMember(
      id: id ?? this.id,
      groupId: groupId ?? this.groupId,
      userId: userId ?? this.userId,
      userName: userName ?? this.userName,
      userEmail: userEmail ?? this.userEmail,
      positionNumber: positionNumber ?? this.positionNumber,
      payoutDay: payoutDay ?? this.payoutDay,
      hasReceivedPayout: hasReceivedPayout ?? this.hasReceivedPayout,
      payoutReceivedAt: payoutReceivedAt ?? this.payoutReceivedAt,
      joinedAt: joinedAt ?? this.joinedAt,
      status: status ?? this.status,
    );
  }
}

@JsonSerializable()
class PayoutScheduleItem {
  final int day;
  final String date;
  @JsonKey(name: 'member_id')
  final int memberId;
  @JsonKey(name: 'member_name')
  final String memberName;
  @JsonKey(name: 'position_number')
  final int positionNumber;
  final String amount;
  final String status;
  @JsonKey(name: 'contribution_status')
  final String? contributionStatus;

  PayoutScheduleItem({
    required this.day,
    required this.date,
    required this.memberId,
    required this.memberName,
    required this.positionNumber,
    required this.amount,
    required this.status,
    this.contributionStatus,
  });

  factory PayoutScheduleItem.fromJson(Map<String, dynamic> json) {
    // Handle both string and number types from backend
    final amountValue = json['amount'];
    
    return PayoutScheduleItem(
      day: json['day'] as int,
      date: json['date'] as String,
      memberId: json['member_id'] as int,
      memberName: json['member_name'] as String,
      positionNumber: json['position_number'] as int,
      amount: amountValue is num ? amountValue.toString() : amountValue as String,
      status: json['status'] as String,
      contributionStatus: json['contribution_status'] as String?,
    );
  }

  Map<String, dynamic> toJson() => _$PayoutScheduleItemToJson(this);

  bool get isPending => status == 'pending';
  bool get isCompleted => status == 'successful';
  bool get isFailed => status == 'failed';
  
  double get amountValue => double.tryParse(amount) ?? 0.0;
}
