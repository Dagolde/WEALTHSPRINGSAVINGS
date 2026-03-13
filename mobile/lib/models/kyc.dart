import 'package:json_annotation/json_annotation.dart';

part 'kyc.g.dart';

@JsonSerializable()
class KycDocument {
  final int? id;
  @JsonKey(name: 'document_type')
  final String documentType;
  @JsonKey(name: 'document_url')
  final String? documentUrl;
  final String status;
  @JsonKey(name: 'rejection_reason')
  final String? rejectionReason;
  @JsonKey(name: 'submitted_at')
  final String? submittedAt;
  @JsonKey(name: 'verified_at')
  final String? verifiedAt;

  KycDocument({
    this.id,
    required this.documentType,
    this.documentUrl,
    required this.status,
    this.rejectionReason,
    this.submittedAt,
    this.verifiedAt,
  });

  factory KycDocument.fromJson(Map<String, dynamic> json) =>
      _$KycDocumentFromJson(json);

  Map<String, dynamic> toJson() => _$KycDocumentToJson(this);

  bool get isPending => status == 'pending';
  bool get isVerified => status == 'verified';
  bool get isRejected => status == 'rejected';
}

@JsonSerializable()
class KycStatus {
  @JsonKey(name: 'kyc_status')
  final String status;
  @JsonKey(name: 'kyc_rejection_reason')
  final String? rejectionReason;
  @JsonKey(name: 'submitted_at')
  final String? submittedAt;
  @JsonKey(name: 'verified_at')
  final String? verifiedAt;
  @JsonKey(name: 'kyc_document_url')
  final String? documentUrl;
  @JsonKey(name: 'document_type')
  final String? documentType;

  KycStatus({
    required this.status,
    this.rejectionReason,
    this.submittedAt,
    this.verifiedAt,
    this.documentUrl,
    this.documentType,
  });

  factory KycStatus.fromJson(Map<String, dynamic> json) =>
      _$KycStatusFromJson(json);

  Map<String, dynamic> toJson() => _$KycStatusToJson(this);

  bool get isPending => status == 'pending';
  bool get isVerified => status == 'verified';
  bool get isRejected => status == 'rejected';
}
