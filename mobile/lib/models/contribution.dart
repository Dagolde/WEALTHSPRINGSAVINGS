import 'package:json_annotation/json_annotation.dart';

part 'contribution.g.dart';

@JsonSerializable()
class Contribution {
  final int id;
  @JsonKey(name: 'group_id')
  final int groupId;
  @JsonKey(name: 'user_id')
  final int userId;
  final String amount;
  @JsonKey(name: 'payment_method')
  final String paymentMethod;
  @JsonKey(name: 'payment_reference')
  final String paymentReference;
  @JsonKey(name: 'payment_status')
  final String paymentStatus;
  @JsonKey(name: 'contribution_date')
  final String contributionDate;
  @JsonKey(name: 'paid_at')
  final String? paidAt;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;
  
  // Optional fields for enriched data
  @JsonKey(name: 'group_name')
  final String? groupName;
  @JsonKey(name: 'user_name')
  final String? userName;

  Contribution({
    required this.id,
    required this.groupId,
    required this.userId,
    required this.amount,
    required this.paymentMethod,
    required this.paymentReference,
    required this.paymentStatus,
    required this.contributionDate,
    this.paidAt,
    this.createdAt,
    this.updatedAt,
    this.groupName,
    this.userName,
  });

  factory Contribution.fromJson(Map<String, dynamic> json) {
    // Handle both string and number types from backend
    final amountValue = json['amount'];
    
    return Contribution(
      id: json['id'] as int,
      groupId: json['group_id'] as int,
      userId: json['user_id'] as int,
      amount: amountValue is num ? amountValue.toString() : amountValue as String,
      paymentMethod: json['payment_method'] as String,
      paymentReference: json['payment_reference'] as String,
      paymentStatus: json['payment_status'] as String,
      contributionDate: json['contribution_date'] as String,
      paidAt: json['paid_at'] as String?,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
      groupName: json['group_name'] as String?,
      userName: json['user_name'] as String?,
    );
  }

  Map<String, dynamic> toJson() => _$ContributionToJson(this);

  bool get isPending => paymentStatus == 'pending';
  bool get isSuccessful => paymentStatus == 'successful';
  bool get isFailed => paymentStatus == 'failed';
  
  bool get isWalletPayment => paymentMethod == 'wallet';
  bool get isCardPayment => paymentMethod == 'card';
  bool get isBankTransfer => paymentMethod == 'bank_transfer';
  
  double get amountValue => double.tryParse(amount) ?? 0.0;

  Contribution copyWith({
    int? id,
    int? groupId,
    int? userId,
    String? amount,
    String? paymentMethod,
    String? paymentReference,
    String? paymentStatus,
    String? contributionDate,
    String? paidAt,
    String? createdAt,
    String? updatedAt,
    String? groupName,
    String? userName,
  }) {
    return Contribution(
      id: id ?? this.id,
      groupId: groupId ?? this.groupId,
      userId: userId ?? this.userId,
      amount: amount ?? this.amount,
      paymentMethod: paymentMethod ?? this.paymentMethod,
      paymentReference: paymentReference ?? this.paymentReference,
      paymentStatus: paymentStatus ?? this.paymentStatus,
      contributionDate: contributionDate ?? this.contributionDate,
      paidAt: paidAt ?? this.paidAt,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      groupName: groupName ?? this.groupName,
      userName: userName ?? this.userName,
    );
  }
}

@JsonSerializable()
class MissedContribution {
  @JsonKey(name: 'group_id')
  final int groupId;
  @JsonKey(name: 'group_name')
  final String groupName;
  @JsonKey(name: 'contribution_amount')
  final String contributionAmount;
  @JsonKey(name: 'missed_date')
  final String missedDate;
  @JsonKey(name: 'days_missed')
  final int daysMissed;

  MissedContribution({
    required this.groupId,
    required this.groupName,
    required this.contributionAmount,
    required this.missedDate,
    required this.daysMissed,
  });

  factory MissedContribution.fromJson(Map<String, dynamic> json) {
    // Handle both string and number types from backend
    final contributionAmountValue = json['contribution_amount'];
    
    return MissedContribution(
      groupId: json['group_id'] as int,
      groupName: json['group_name'] as String,
      contributionAmount: contributionAmountValue is num 
          ? contributionAmountValue.toString() 
          : contributionAmountValue as String,
      missedDate: json['missed_date'] as String,
      daysMissed: json['days_missed'] as int,
    );
  }

  Map<String, dynamic> toJson() => _$MissedContributionToJson(this);

  double get amountValue => double.tryParse(contributionAmount) ?? 0.0;
}

@JsonSerializable()
class PaymentInitializationResponse {
  @JsonKey(name: 'authorization_url')
  final String authorizationUrl;
  @JsonKey(name: 'access_code')
  final String accessCode;
  final String reference;

  PaymentInitializationResponse({
    required this.authorizationUrl,
    required this.accessCode,
    required this.reference,
  });

  factory PaymentInitializationResponse.fromJson(Map<String, dynamic> json) =>
      _$PaymentInitializationResponseFromJson(json);

  Map<String, dynamic> toJson() => _$PaymentInitializationResponseToJson(this);
}
