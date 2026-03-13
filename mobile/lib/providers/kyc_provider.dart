import 'dart:io';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../core/di/injection.dart';
import '../models/kyc.dart';
import '../repositories/kyc_repository.dart';
import '../services/image_compression_service.dart';

/// KYC state
sealed class KycState {
  const KycState();
}

class KycInitial extends KycState {
  const KycInitial();
}

class KycLoading extends KycState {
  const KycLoading();
}

class KycStatusLoaded extends KycState {
  final KycStatus status;
  const KycStatusLoaded(this.status);
}

class KycSubmitted extends KycState {
  final KycDocument document;
  const KycSubmitted(this.document);
}

class KycError extends KycState {
  final String message;
  const KycError(this.message);
}

/// KYC state notifier
class KycNotifier extends StateNotifier<KycState> {
  final KycRepository _kycRepository;

  KycNotifier(this._kycRepository) : super(const KycInitial());

  Future<void> submitKyc({
    required String documentType,
    required File documentFile,
  }) async {
    state = const KycLoading();
    try {
      // Compress image before upload for faster upload speed
      final compressedFile = await ImageCompressionService.compressKycDocument(documentFile);
      
      final document = await _kycRepository.submitKyc(
        documentType: documentType,
        documentFile: compressedFile ?? documentFile,
      );
      state = KycSubmitted(document);
    } catch (e) {
      state = KycError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  Future<void> getKycStatus() async {
    state = const KycLoading();
    try {
      final status = await _kycRepository.getKycStatus();
      state = KycStatusLoaded(status);
    } catch (e) {
      state = KycError(e.toString().replaceAll('Exception: ', ''));
    }
  }

  void reset() {
    state = const KycInitial();
  }
}

/// KYC state provider
final kycStateProvider = StateNotifierProvider<KycNotifier, KycState>((ref) {
  final kycRepository = ref.watch(kycRepositoryProvider);
  return KycNotifier(kycRepository);
});
