import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../core/di/injection.dart';
import '../models/user.dart';
import '../repositories/auth_repository.dart';

/// Auth state
sealed class AuthState {
  const AuthState();
}

class AuthInitial extends AuthState {
  const AuthInitial();
}

class AuthLoading extends AuthState {
  const AuthLoading();
}

class Authenticated extends AuthState {
  final User user;
  const Authenticated(this.user);
}

class Unauthenticated extends AuthState {
  const Unauthenticated();
}

class AuthError extends AuthState {
  final String message;
  const AuthError(this.message);
}

/// Auth state notifier
class AuthNotifier extends StateNotifier<AuthState> {
  final AuthRepository _authRepository;
  
  AuthNotifier(this._authRepository) : super(const AuthInitial()) {
    _checkAuthStatus();
  }
  
  Future<void> _checkAuthStatus() async {
    final isAuthenticated = await _authRepository.isAuthenticated();
    if (isAuthenticated) {
      final user = await _authRepository.getCurrentUser();
      if (user != null) {
        state = Authenticated(user);
      } else {
        state = const Unauthenticated();
      }
    } else {
      state = const Unauthenticated();
    }
  }
  
  Future<void> login(String email, String password) async {
    state = const AuthLoading();
    try {
      final authResponse = await _authRepository.login(
        email: email,
        password: password,
      );
      state = Authenticated(authResponse.user);
    } catch (e) {
      state = AuthError(e.toString());
    }
  }
  
  Future<void> register({
    required String name,
    required String email,
    required String phone,
    required String password,
  }) async {
    state = const AuthLoading();
    try {
      final authResponse = await _authRepository.register(
        name: name,
        email: email,
        phone: phone,
        password: password,
      );
      state = Authenticated(authResponse.user);
    } catch (e) {
      state = AuthError(e.toString());
    }
  }
  
  Future<void> logout() async {
    await _authRepository.logout();
    state = const Unauthenticated();
  }

  Future<void> updateProfile({
    required String name,
    required String phone,
  }) async {
    try {
      final updatedUser = await _authRepository.updateProfile(
        name: name,
        phone: phone,
      );
      state = Authenticated(updatedUser);
    } catch (e) {
      rethrow;
    }
  }

  Future<void> uploadProfilePicture(String filePath) async {
    try {
      final profilePictureUrl = await _authRepository.uploadProfilePicture(filePath);
      
      // Update current user state with new profile picture
      if (state is Authenticated) {
        final currentUser = (state as Authenticated).user;
        final updatedUser = User(
          id: currentUser.id,
          name: currentUser.name,
          email: currentUser.email,
          phone: currentUser.phone,
          kycStatus: currentUser.kycStatus,
          kycDocumentUrl: currentUser.kycDocumentUrl,
          profilePictureUrl: profilePictureUrl,
          walletBalance: currentUser.walletBalance,
          status: currentUser.status,
          createdAt: currentUser.createdAt,
        );
        state = Authenticated(updatedUser);
      }
    } catch (e) {
      rethrow;
    }
  }
}

/// Auth state provider
final authStateProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final authRepository = ref.watch(authRepositoryProvider);
  return AuthNotifier(authRepository);
});

