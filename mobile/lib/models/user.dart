import 'package:json_annotation/json_annotation.dart';

part 'user.g.dart';

@JsonSerializable()
class User {
  final int id;
  final String name;
  final String email;
  final String phone;
  @JsonKey(name: 'kyc_status')
  final String kycStatus;
  @JsonKey(name: 'kyc_document_url')
  final String? kycDocumentUrl;
  @JsonKey(name: 'profile_picture_url')
  final String? profilePictureUrl;
  @JsonKey(name: 'wallet_balance')
  final String walletBalance;
  final String status;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  User({
    required this.id,
    required this.name,
    required this.email,
    required this.phone,
    required this.kycStatus,
    this.kycDocumentUrl,
    this.profilePictureUrl,
    required this.walletBalance,
    required this.status,
    this.createdAt,
  });

  factory User.fromJson(Map<String, dynamic> json) => _$UserFromJson(json);
  Map<String, dynamic> toJson() => _$UserToJson(this);

  bool get isKycVerified => kycStatus == 'verified';
  bool get hasSubmittedKyc => kycDocumentUrl != null && kycDocumentUrl!.isNotEmpty;
  bool get isActive => status == 'active';
  bool get isSuspended => status == 'suspended';
  
  double get walletBalanceAmount => double.tryParse(walletBalance) ?? 0.0;
}
