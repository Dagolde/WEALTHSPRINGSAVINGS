# Admin Mobile App Control

## Overview
Added comprehensive mobile app control features to the admin dashboard, allowing admins to manage mobile app settings, monitor usage, control user sessions, and send push notifications.

## Features Implemented

### 1. App Usage Statistics
- **Active Sessions**: Real-time count of users currently online (last 15 minutes)
- **Daily Active Users (DAU)**: Users active in the last 24 hours
- **Weekly Active Users (WAU)**: Users active in the last 7 days
- **Monthly Active Users (MAU)**: Users active in the last 30 days
- **Platform Distribution**: Breakdown of Android, iOS, and other platforms

### 2. App Version Control
- **Current Version**: Set the current app version displayed to users
- **Minimum Supported Version**: Define the minimum version required
- **Force Update**: Toggle to force users to update before using the app
- **Version Comparison**: Automatically prompt users with older versions to update

### 3. Maintenance Mode
- **Enable/Disable**: Toggle maintenance mode on/off
- **Custom Message**: Set a custom maintenance message for users
- **Graceful Degradation**: Users see maintenance screen instead of app crash

### 4. Feature Flags
Control which features are enabled in the mobile app:
- Wallet functionality
- Group management
- Contributions
- Withdrawals
- KYC requirement

### 5. Session Management
- **View Active Sessions**: See all active user sessions with device info
- **Session Status**: Real-time vs idle status (15-minute threshold)
- **Revoke Session**: Force logout a specific session
- **Logout User**: Force logout user from all devices
- **Device Information**: View device name and platform

### 6. Push Notifications
- **Send to All Users**: Broadcast notifications to all active users
- **Custom Title & Message**: Personalized notification content
- **Notification Types**: General, Update, Promotion, Alert
- **Delivery Tracking**: See how many notifications were sent/failed

## Backend Endpoints

### Mobile App Settings
```
GET    /api/v1/admin/mobile/settings          - Get mobile app settings
PUT    /api/v1/admin/mobile/settings          - Update mobile app settings
```

### Session Management
```
GET    /api/v1/admin/mobile/sessions          - Get active user sessions
DELETE /api/v1/admin/mobile/sessions/{id}     - Revoke specific session
DELETE /api/v1/admin/mobile/users/{id}/sessions - Revoke all user sessions
```

### Push Notifications
```
POST   /api/v1/admin/mobile/notifications/push - Send push notification
```

### App Usage
```
GET    /api/v1/admin/mobile/usage             - Get app usage statistics
```

## Environment Variables

Add these to your `.env` file:

```env
# Mobile App Configuration
MOBILE_APP_VERSION=1.0.0
MOBILE_MIN_VERSION=1.0.0
MOBILE_FORCE_UPDATE=false
MOBILE_MAINTENANCE=false
MOBILE_MAINTENANCE_MESSAGE=App is under maintenance. Please try again later.

# Feature Flags
FEATURE_WALLET_ENABLED=true
FEATURE_GROUPS_ENABLED=true
FEATURE_CONTRIBUTIONS_ENABLED=true
FEATURE_WITHDRAWALS_ENABLED=true
FEATURE_KYC_REQUIRED=true
```

## Usage Guide

### Accessing Mobile App Control
1. Login to admin dashboard
2. Click "Mobile App Control" in the sidebar
3. View real-time statistics and manage settings

### Setting Maintenance Mode
1. Go to Mobile App Control
2. Click "Edit Settings"
3. Check "Enable Maintenance Mode"
4. Enter a custom maintenance message
5. Click "Save Settings"
6. Users will see the maintenance screen on next app launch

### Forcing App Updates
1. Go to Mobile App Control
2. Click "Edit Settings"
3. Update "Current App Version" (e.g., 1.1.0)
4. Update "Minimum Supported Version" (e.g., 1.1.0)
5. Check "Force Update"
6. Click "Save Settings"
7. Users with older versions will be forced to update

### Revoking User Sessions
1. Go to Mobile App Control
2. Scroll to "Active Sessions"
3. Find the user/session
4. Click "Revoke" to logout specific session
5. Click "Logout User" to logout from all devices

### Sending Push Notifications
1. Go to Mobile App Control
2. Click "Send Notification"
3. Enter title and message
4. Select notification type
5. Choose "Send to all active users"
6. Click "Send Notification"

## Mobile App Integration

### Version Check
The mobile app should check the version on startup:

```dart
// Example Flutter code
Future<void> checkAppVersion() async {
  final response = await api.get('/admin/mobile/settings');
  final settings = response.data;
  
  final currentVersion = packageInfo.version;
  final minVersion = settings['min_supported_version'];
  final forceUpdate = settings['force_update'];
  
  if (isVersionLower(currentVersion, minVersion)) {
    if (forceUpdate) {
      // Show force update dialog (cannot dismiss)
      showForceUpdateDialog();
    } else {
      // Show optional update dialog
      showUpdateDialog();
    }
  }
}
```

### Maintenance Mode Check
```dart
Future<void> checkMaintenanceMode() async {
  final response = await api.get('/admin/mobile/settings');
  final settings = response.data;
  
  if (settings['maintenance_mode']) {
    // Show maintenance screen
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(
        builder: (context) => MaintenanceScreen(
          message: settings['maintenance_message'],
        ),
      ),
    );
  }
}
```

### Feature Flags
```dart
class FeatureFlags {
  static bool walletEnabled = true;
  static bool groupsEnabled = true;
  static bool contributionsEnabled = true;
  static bool withdrawalsEnabled = true;
  static bool kycRequired = true;
  
  static Future<void> loadFeatureFlags() async {
    final response = await api.get('/admin/mobile/settings');
    final features = response.data['features'];
    
    walletEnabled = features['wallet_enabled'];
    groupsEnabled = features['groups_enabled'];
    contributionsEnabled = features['contributions_enabled'];
    withdrawalsEnabled = features['withdrawals_enabled'];
    kycRequired = features['kyc_required'];
  }
}

// Usage
if (FeatureFlags.walletEnabled) {
  // Show wallet features
}
```

### Push Notifications
Integrate with Firebase Cloud Messaging (FCM) or OneSignal:

```dart
// Save device token on login
Future<void> saveDeviceToken() async {
  final token = await FirebaseMessaging.instance.getToken();
  await api.post('/user/device-token', {
    'token': token,
    'platform': Platform.isAndroid ? 'android' : 'ios',
  });
}

// Handle incoming notifications
FirebaseMessaging.onMessage.listen((RemoteMessage message) {
  showNotification(
    title: message.notification?.title,
    body: message.notification?.body,
  );
});
```

## Security Considerations

1. **Session Revocation**: When a session is revoked, the token is deleted from the database. The mobile app will receive a 401 Unauthorized on the next API call and should redirect to login.

2. **Force Update**: Use this carefully as it will prevent users from using the app until they update. Ensure the new version is available in app stores first.

3. **Maintenance Mode**: Users will see the maintenance screen but can still receive push notifications. Plan maintenance during low-traffic periods.

4. **Feature Flags**: Disabling features doesn't remove data, it just hides the UI. Re-enabling will restore access.

## Monitoring & Analytics

### Key Metrics to Monitor
- **DAU/MAU Ratio**: Indicates user engagement (healthy ratio: 20-30%)
- **Session Duration**: Average time users spend in the app
- **Platform Distribution**: Helps prioritize platform-specific features
- **Update Adoption Rate**: How quickly users update to new versions
- **Notification Delivery Rate**: Success rate of push notifications

### Audit Trail
All admin actions are logged:
- Mobile settings changes
- Session revocations
- Push notifications sent
- Feature flag changes

View audit logs in the database:
```sql
SELECT * FROM audit_logs 
WHERE action IN ('mobile_app_settings_updated', 'session_revoked', 'push_notification_sent')
ORDER BY created_at DESC;
```

## Troubleshooting

### Users Not Receiving Push Notifications
1. Check if notification service is configured (Firebase/OneSignal)
2. Verify device tokens are being saved
3. Check notification permissions on user devices
4. Review notification delivery logs

### Force Update Not Working
1. Verify `MOBILE_FORCE_UPDATE=true` in `.env`
2. Check mobile app is comparing versions correctly
3. Ensure new version is available in app stores
4. Clear app cache and restart

### Maintenance Mode Not Showing
1. Verify `MOBILE_MAINTENANCE=true` in `.env`
2. Check mobile app is checking maintenance status on startup
3. Clear app cache and restart
4. Verify API endpoint is accessible

## Future Enhancements

- [ ] Scheduled maintenance mode (set start/end time)
- [ ] A/B testing for feature flags
- [ ] Targeted push notifications (by user segment)
- [ ] In-app messaging
- [ ] Remote config for app behavior
- [ ] Crash reporting integration
- [ ] Performance monitoring
- [ ] User feedback collection

## Testing

### Test Mobile Settings Update
```bash
curl -X PUT "http://localhost:8002/api/v1/admin/mobile/settings" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "app_version": "1.1.0",
    "min_supported_version": "1.0.0",
    "force_update": false,
    "maintenance_mode": false,
    "maintenance_message": "We are updating the app"
  }'
```

### Test Push Notification
```bash
curl -X POST "http://localhost:8002/api/v1/admin/mobile/notifications/push" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Important Update",
    "message": "Please update your app to the latest version",
    "type": "update"
  }'
```

### Test Session Revocation
```bash
curl -X DELETE "http://localhost:8002/api/v1/admin/mobile/sessions/1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Summary

The mobile app control feature provides admins with comprehensive tools to:
- Monitor app usage and user engagement
- Control app versions and force updates
- Manage maintenance windows
- Toggle features on/off remotely
- Manage user sessions and security
- Communicate with users via push notifications

All features are accessible through an intuitive admin dashboard interface with real-time updates and detailed analytics.
