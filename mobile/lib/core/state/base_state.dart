import 'package:flutter/foundation.dart';

/// Base state class for all feature states
@immutable
abstract class BaseState {
  const BaseState();
}

/// Loading state
class LoadingState extends BaseState {
  const LoadingState();
}

/// Success state with data
class SuccessState<T> extends BaseState {
  final T data;
  
  const SuccessState(this.data);
}

/// Error state with message
class ErrorState extends BaseState {
  final String message;
  final dynamic error;
  
  const ErrorState(this.message, [this.error]);
}

/// Initial/Idle state
class IdleState extends BaseState {
  const IdleState();
}

/// Generic async value state wrapper
class AsyncState<T> {
  final bool isLoading;
  final T? data;
  final String? error;
  
  const AsyncState({
    this.isLoading = false,
    this.data,
    this.error,
  });
  
  bool get hasData => data != null;
  bool get hasError => error != null;
  
  AsyncState<T> copyWith({
    bool? isLoading,
    T? data,
    String? error,
  }) {
    return AsyncState<T>(
      isLoading: isLoading ?? this.isLoading,
      data: data ?? this.data,
      error: error ?? this.error,
    );
  }
  
  static AsyncState<T> loading<T>() => const AsyncState(isLoading: true);
  
  static AsyncState<T> success<T>(T data) => AsyncState(data: data);
  
  static AsyncState<T> failure<T>(String error) => AsyncState(error: error);
}
