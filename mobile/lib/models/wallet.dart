import 'package:json_annotation/json_annotation.dart';

part 'wallet.g.dart';

@JsonSerializable()
class WalletTransaction {
  final int id;
  @JsonKey(name: 'user_id')
  final int userId;
  final String type; // 'credit' or 'debit'
  final String amount;
  @JsonKey(name: 'balance_before')
  final String balanceBefore;
  @JsonKey(name: 'balance_after')
  final String balanceAfter;
  final String purpose;
  final String reference;
  final Map<String, dynamic>? metadata;
  final String status;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  WalletTransaction({
    required this.id,
    required this.userId,
    required this.type,
    required this.amount,
    required this.balanceBefore,
    required this.balanceAfter,
    required this.purpose,
    required this.reference,
    this.metadata,
    required this.status,
    this.createdAt,
  });

  factory WalletTransaction.fromJson(Map<String, dynamic> json) {
    // Handle both string and number types from backend
    final amountValue = json['amount'];
    final balanceBeforeValue = json['balance_before'];
    final balanceAfterValue = json['balance_after'];
    
    // Safely handle potentially null values
    final id = json['id'];
    final userId = json['user_id'];
    
    return WalletTransaction(
      id: id is int ? id : (id is String ? int.tryParse(id) ?? 0 : 0),
      userId: userId is int ? userId : (userId is String ? int.tryParse(userId) ?? 0 : 0),
      type: json['type'] as String? ?? 'unknown',
      amount: amountValue is num ? amountValue.toString() : (amountValue as String? ?? '0'),
      balanceBefore: balanceBeforeValue is num ? balanceBeforeValue.toString() : (balanceBeforeValue as String? ?? '0'),
      balanceAfter: balanceAfterValue is num ? balanceAfterValue.toString() : (balanceAfterValue as String? ?? '0'),
      purpose: json['purpose'] as String? ?? 'Unknown',
      reference: json['reference'] as String? ?? '',
      metadata: json['metadata'] as Map<String, dynamic>?,
      status: json['status'] as String? ?? 'unknown',
      createdAt: json['created_at'] as String?,
    );
  }

  Map<String, dynamic> toJson() => _$WalletTransactionToJson(this);

  bool get isCredit => type == 'credit';
  bool get isDebit => type == 'debit';
  bool get isSuccessful => status == 'successful';
  bool get isPending => status == 'pending';
  bool get isFailed => status == 'failed';

  double get amountValue => double.tryParse(amount) ?? 0.0;
  double get balanceBeforeValue => double.tryParse(balanceBefore) ?? 0.0;
  double get balanceAfterValue => double.tryParse(balanceAfter) ?? 0.0;

  WalletTransaction copyWith({
    int? id,
    int? userId,
    String? type,
    String? amount,
    String? balanceBefore,
    String? balanceAfter,
    String? purpose,
    String? reference,
    Map<String, dynamic>? metadata,
    String? status,
    String? createdAt,
  }) {
    return WalletTransaction(
      id: id ?? this.id,
      userId: userId ?? this.userId,
      type: type ?? this.type,
      amount: amount ?? this.amount,
      balanceBefore: balanceBefore ?? this.balanceBefore,
      balanceAfter: balanceAfter ?? this.balanceAfter,
      purpose: purpose ?? this.purpose,
      reference: reference ?? this.reference,
      metadata: metadata ?? this.metadata,
      status: status ?? this.status,
      createdAt: createdAt ?? this.createdAt,
    );
  }
}

@JsonSerializable()
class Withdrawal {
  final int id;
  @JsonKey(name: 'user_id')
  final int userId;
  @JsonKey(name: 'bank_account_id')
  final int bankAccountId;
  final String amount;
  final String status;
  @JsonKey(name: 'admin_approval_status')
  final String adminApprovalStatus;
  @JsonKey(name: 'approved_by')
  final int? approvedBy;
  @JsonKey(name: 'approved_at')
  final String? approvedAt;
  @JsonKey(name: 'rejection_reason')
  final String? rejectionReason;
  @JsonKey(name: 'payment_reference')
  final String? paymentReference;
  @JsonKey(name: 'processed_at')
  final String? processedAt;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  // Optional enriched data
  @JsonKey(name: 'bank_account')
  final Map<String, dynamic>? bankAccount;

  Withdrawal({
    required this.id,
    required this.userId,
    required this.bankAccountId,
    required this.amount,
    required this.status,
    required this.adminApprovalStatus,
    this.approvedBy,
    this.approvedAt,
    this.rejectionReason,
    this.paymentReference,
    this.processedAt,
    this.createdAt,
    this.updatedAt,
    this.bankAccount,
  });

  factory Withdrawal.fromJson(Map<String, dynamic> json) {
    // Handle both string and number types from backend
    final amountValue = json['amount'];
    
    // Safely handle potentially null values
    final id = json['id'];
    final userId = json['user_id'];
    final bankAccountId = json['bank_account_id'];
    
    return Withdrawal(
      id: id is int ? id : (id is String ? int.tryParse(id) ?? 0 : 0),
      userId: userId is int ? userId : (userId is String ? int.tryParse(userId) ?? 0 : 0),
      bankAccountId: bankAccountId is int ? bankAccountId : (bankAccountId is String ? int.tryParse(bankAccountId) ?? 0 : 0),
      amount: amountValue is num ? amountValue.toString() : (amountValue as String? ?? '0'),
      status: json['status'] as String? ?? 'unknown',
      adminApprovalStatus: json['admin_approval_status'] as String? ?? 'pending',
      approvedBy: json['approved_by'] as int?,
      approvedAt: json['approved_at'] as String?,
      rejectionReason: json['rejection_reason'] as String?,
      paymentReference: json['payment_reference'] as String?,
      processedAt: json['processed_at'] as String?,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
      bankAccount: json['bank_account'] as Map<String, dynamic>?,
    );
  }

  Map<String, dynamic> toJson() => _$WithdrawalToJson(this);

  bool get isPending => status == 'pending';
  bool get isApproved => status == 'approved';
  bool get isProcessing => status == 'processing';
  bool get isSuccessful => status == 'successful';
  bool get isRejected => status == 'rejected';
  bool get isFailed => status == 'failed';

  bool get awaitingApproval => adminApprovalStatus == 'pending';

  double get amountValue => double.tryParse(amount) ?? 0.0;

  Withdrawal copyWith({
    int? id,
    int? userId,
    int? bankAccountId,
    String? amount,
    String? status,
    String? adminApprovalStatus,
    int? approvedBy,
    String? approvedAt,
    String? rejectionReason,
    String? paymentReference,
    String? processedAt,
    String? createdAt,
    String? updatedAt,
    Map<String, dynamic>? bankAccount,
  }) {
    return Withdrawal(
      id: id ?? this.id,
      userId: userId ?? this.userId,
      bankAccountId: bankAccountId ?? this.bankAccountId,
      amount: amount ?? this.amount,
      status: status ?? this.status,
      adminApprovalStatus: adminApprovalStatus ?? this.adminApprovalStatus,
      approvedBy: approvedBy ?? this.approvedBy,
      approvedAt: approvedAt ?? this.approvedAt,
      rejectionReason: rejectionReason ?? this.rejectionReason,
      paymentReference: paymentReference ?? this.paymentReference,
      processedAt: processedAt ?? this.processedAt,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      bankAccount: bankAccount ?? this.bankAccount,
    );
  }
}

@JsonSerializable()
class WalletBalance {
  final String balance;
  @JsonKey(name: 'user_id')
  final int? userId;

  WalletBalance({
    required this.balance,
    this.userId,
  });

  factory WalletBalance.fromJson(Map<String, dynamic> json) {
    // Handle both string and number types from backend
    final balanceValue = json['balance'];
    final balanceStr = balanceValue is num 
        ? balanceValue.toString() 
        : balanceValue as String;
    
    return WalletBalance(
      balance: balanceStr,
      userId: json['user_id'] as int?,
    );
  }

  Map<String, dynamic> toJson() => _$WalletBalanceToJson(this);

  double get balanceValue => double.tryParse(balance) ?? 0.0;
}
