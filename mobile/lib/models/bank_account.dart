import 'package:json_annotation/json_annotation.dart';

part 'bank_account.g.dart';

@JsonSerializable()
class BankAccount {
  final int? id;
  @JsonKey(name: 'user_id')
  final int? userId;
  @JsonKey(name: 'account_name')
  final String accountName;
  @JsonKey(name: 'account_number')
  final String accountNumber;
  @JsonKey(name: 'bank_name')
  final String bankName;
  @JsonKey(name: 'bank_code')
  final String bankCode;
  @JsonKey(name: 'is_verified')
  final bool isVerified;
  @JsonKey(name: 'is_primary')
  final bool isPrimary;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  BankAccount({
    this.id,
    this.userId,
    required this.accountName,
    required this.accountNumber,
    required this.bankName,
    required this.bankCode,
    this.isVerified = false,
    this.isPrimary = false,
    this.createdAt,
    this.updatedAt,
  });

  factory BankAccount.fromJson(Map<String, dynamic> json) =>
      _$BankAccountFromJson(json);

  Map<String, dynamic> toJson() => _$BankAccountToJson(this);

  BankAccount copyWith({
    int? id,
    int? userId,
    String? accountName,
    String? accountNumber,
    String? bankName,
    String? bankCode,
    bool? isVerified,
    bool? isPrimary,
    String? createdAt,
    String? updatedAt,
  }) {
    return BankAccount(
      id: id ?? this.id,
      userId: userId ?? this.userId,
      accountName: accountName ?? this.accountName,
      accountNumber: accountNumber ?? this.accountNumber,
      bankName: bankName ?? this.bankName,
      bankCode: bankCode ?? this.bankCode,
      isVerified: isVerified ?? this.isVerified,
      isPrimary: isPrimary ?? this.isPrimary,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }
}

@JsonSerializable()
class Bank {
  final String name;
  final String code;
  final String? slug;

  Bank({
    required this.name,
    required this.code,
    this.slug,
  });

  factory Bank.fromJson(Map<String, dynamic> json) => _$BankFromJson(json);

  Map<String, dynamic> toJson() => _$BankToJson(this);
}

@JsonSerializable()
class AccountResolution {
  @JsonKey(name: 'account_number')
  final String accountNumber;
  @JsonKey(name: 'account_name')
  final String accountName;
  @JsonKey(name: 'bank_code')
  final String? bankCode;

  AccountResolution({
    required this.accountNumber,
    required this.accountName,
    this.bankCode,
  });

  factory AccountResolution.fromJson(Map<String, dynamic> json) =>
      _$AccountResolutionFromJson(json);

  Map<String, dynamic> toJson() => _$AccountResolutionToJson(this);
}
