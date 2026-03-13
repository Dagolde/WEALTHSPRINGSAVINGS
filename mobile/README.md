# Ajo App - Flutter Mobile Application

## Overview

The Ajo App is a mobile application for the Rotational Contribution Platform (Ajo Platform). It enables users to participate in structured group savings cycles, manage contributions, and track payouts.

## Architecture

The app follows **Clean Architecture** principles with a feature-based structure:

```
lib/
├── core/                    # Core functionality
│   ├── config/             # App configuration
│   ├── di/                 # Dependency injection
│   ├── router/             # Navigation and routing
│   ├── state/              # Base state classes
│   ├── storage/            # Cache and offline sync
│   └── theme/              # App theme and design system
├── features/               # Feature modules
│   ├── auth/              # Authentication
│   ├── home/              # Home dashboard
│   ├── groups/            # Group management
│   ├── contributions/     # Contribution tracking
│   ├── wallet/            # Wallet management
│   └── profile/           # User profile
├── models/                # Data models
├── providers/             # State management (Riverpod)
├── repositories/          # Data repositories
├── services/              # API and storage services
└── shared/                # Shared widgets and utilities
```

## Technology Stack

- **Framework**: Flutter (latest stable SDK)
- **Language**: Dart
- **State Management**: Riverpod
- **HTTP Client**: Dio
- **Routing**: GoRouter
- **Local Storage**: SharedPreferences, Hive
- **Secure Storage**: flutter_secure_storage
- **Notifications**: Firebase Cloud Messaging

## Setup Instructions

### Prerequisites

- Flutter SDK (3.0.0 or higher)
- Dart SDK (3.0.0 or higher)
- Android Studio / Xcode (for mobile development)
- VS Code or Android Studio (recommended IDEs)

### Installation

1. **Install dependencies**:
   ```bash
   cd mobile
   flutter pub get
   ```

2. **Configure environment**:
   - Copy `.env` for development
   - Use `.env.staging` for staging
   - Use `.env.production` for production

3. **Run code generation** (for JSON serialization):
   ```bash
   flutter pub run build_runner build --delete-conflicting-outputs
   ```

4. **Run the app**:
   ```bash
   # Development
   flutter run
   
   # Staging
   flutter run --dart-define-from-file=.env.staging
   
   # Production
   flutter run --dart-define-from-file=.env.production --release
   ```

## Project Structure Details

### Core Layer

- **config/**: Environment configuration and app settings
- **di/**: Dependency injection setup using Riverpod providers
- **router/**: Navigation configuration with authentication guards
- **state/**: Base state classes for consistent state management
- **storage/**: Cache manager and offline sync functionality
- **theme/**: App theme, colors, text styles, and spacing

### Features Layer

Each feature follows this structure:
```
feature_name/
├── screens/        # UI screens
├── widgets/        # Feature-specific widgets
├── providers/      # Feature state management
└── models/         # Feature-specific models
```

### Services Layer

- **api_client.dart**: Dio HTTP client with interceptors
  - Authentication interceptor (JWT token injection)
  - Error handling interceptor
  - Logging interceptor (debug mode only)
- **token_storage.dart**: Secure token storage

### Repositories Layer

Data repositories handle API communication and data transformation:
- **auth_repository.dart**: Authentication operations
- More repositories to be added for other features

## State Management

The app uses **Riverpod** for state management with the following patterns:

1. **Provider**: For dependency injection
2. **StateNotifierProvider**: For mutable state
3. **FutureProvider**: For async data fetching
4. **StreamProvider**: For real-time data

Example:
```dart
final authStateProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final authRepository = ref.watch(authRepositoryProvider);
  return AuthNotifier(authRepository);
});
```

## Networking

### API Client Configuration

The app uses Dio with custom interceptors:

1. **Authentication Interceptor**: Automatically injects JWT tokens
2. **Error Interceptor**: Standardizes error responses
3. **Logging Interceptor**: Logs requests/responses in debug mode

### Error Handling

All API errors are standardized with user-friendly messages:
- Connection timeout
- Network errors
- HTTP status codes (400, 401, 403, 404, 422, 500+)
- Validation errors

## Local Storage

### Cache Manager

Caches API responses with TTL (Time To Live):
```dart
await cacheManager.save('key', data, ttlMinutes: 5);
final cachedData = cacheManager.get('key');
```

### Offline Sync

Queues actions when offline and syncs when connection is restored:
```dart
await offlineSync.queueAction(OfflineAction(
  id: uuid,
  type: 'contribution',
  data: contributionData,
  timestamp: DateTime.now(),
));
```

### Secure Storage

Sensitive data (tokens, credentials) stored securely:
```dart
await tokenStorage.saveToken(token);
final token = await tokenStorage.getToken();
```

## Routing

The app uses **GoRouter** with authentication guards:

```dart
final router = GoRouter(
  redirect: (context, state) {
    // Redirect logic based on auth state
  },
  routes: [
    GoRoute(path: '/login', builder: (context, state) => LoginScreen()),
    GoRoute(path: '/home', builder: (context, state) => HomeScreen()),
  ],
);
```

## Theme and Design System

### Colors

Defined in `app_colors.dart`:
- Primary: Green (#2E7D32) - Financial/savings theme
- Secondary: Orange (#FF6F00) - Accents
- Status colors: Success, Error, Warning, Info
- Group/Payment status colors

### Typography

Defined in `app_text_styles.dart`:
- Headings: h1, h2, h3, h4
- Body: body, bodySmall, caption
- Special: button, currency, label

### Spacing

Defined in `app_spacing.dart`:
- Base unit: 8px
- xs (4), sm (8), md (16), lg (24), xl (32), xxl (48)

### Reusable Components

- **AppButton**: Primary, secondary, outlined, text buttons
- **AppCard**: Consistent card styling
- **AppTextField**: Styled text input with validation
- **LoadingOverlay**: Loading indicator overlay
- **EmptyState**: Empty state placeholder

## Testing

### Unit Tests

```bash
flutter test
```

### Integration Tests

```bash
flutter test integration_test/
```

### Widget Tests

```bash
flutter test test/widgets/
```

## Build and Release

### Android

```bash
# Build APK
flutter build apk --release

# Build App Bundle
flutter build appbundle --release
```

### iOS

```bash
# Build IPA
flutter build ipa --release
```

## Environment Variables

### Development (.env)
```
ENVIRONMENT=development
API_BASE_URL=http://localhost:8000/api/v1
APP_NAME=Ajo App
APP_VERSION=1.0.0
```

### Staging (.env.staging)
```
ENVIRONMENT=staging
API_BASE_URL=https://staging-api.ajoapp.com/api/v1
APP_NAME=Ajo App (Staging)
APP_VERSION=1.0.0
```

### Production (.env.production)
```
ENVIRONMENT=production
API_BASE_URL=https://api.ajoapp.com/api/v1
APP_NAME=Ajo App
APP_VERSION=1.0.0
```

## Next Steps

The following features are ready to be implemented:

1. **Task 16**: Authentication module (login, register, profile)
2. **Task 17**: KYC and bank account module
3. **Task 18**: Group management module
4. **Task 19**: Contribution module
5. **Task 20**: Wallet module
6. **Task 21**: Notifications module
7. **Task 22**: Home dashboard

Each feature will follow the established architecture and use the core infrastructure set up in Task 15.

## Contributing

1. Follow the established project structure
2. Use Riverpod for state management
3. Follow the design system for UI components
4. Write tests for new features
5. Use the provided base classes and utilities

## License

Proprietary - All rights reserved
