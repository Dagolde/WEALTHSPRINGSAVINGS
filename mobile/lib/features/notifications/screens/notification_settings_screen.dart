import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../providers/notification_provider.dart';
import '../../../models/notification.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../core/theme/app_colors.dart';

class NotificationSettingsScreen extends ConsumerStatefulWidget {
  const NotificationSettingsScreen({super.key});

  @override
  ConsumerState<NotificationSettingsScreen> createState() => _NotificationSettingsScreenState();
}

class _NotificationSettingsScreenState extends ConsumerState<NotificationSettingsScreen> {
  NotificationSettings? _currentSettings;
  bool _hasChanges = false;

  @override
  void initState() {
    super.initState();
    
    // Load settings on init
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(notificationSettingsProvider.notifier).loadSettings();
    });
  }

  void _updateSettings(NotificationSettings settings) {
    setState(() {
      _currentSettings = settings;
      _hasChanges = true;
    });
  }

  Future<void> _saveSettings() async {
    if (_currentSettings == null) return;

    await ref.read(notificationSettingsUpdateProvider.notifier).updateSettings(_currentSettings!);
  }

  @override
  Widget build(BuildContext context) {
    final settingsState = ref.watch(notificationSettingsProvider);
    final updateState = ref.watch(notificationSettingsUpdateProvider);

    // Listen to update state
    ref.listen<NotificationSettingsUpdateState>(
      notificationSettingsUpdateProvider,
      (previous, next) {
        if (next is NotificationSettingsUpdateSuccess) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Settings saved successfully')),
          );
          setState(() {
            _hasChanges = false;
          });
          // Refresh settings
          ref.read(notificationSettingsProvider.notifier).refreshSettings();
        } else if (next is NotificationSettingsUpdateError) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Error: ${next.message}')),
          );
        }
      },
    );

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notification Settings'),
      ),
      body: _buildBody(settingsState, updateState),
    );
  }

  Widget _buildBody(
    NotificationSettingsState settingsState,
    NotificationSettingsUpdateState updateState,
  ) {
    return switch (settingsState) {
      NotificationSettingsInitial() => const Center(child: CircularProgressIndicator()),
      NotificationSettingsLoading() => const Center(child: CircularProgressIndicator()),
      NotificationSettingsError(:final message) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 64, color: Colors.red),
              const SizedBox(height: 16),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () {
                  ref.read(notificationSettingsProvider.notifier).loadSettings();
                },
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      NotificationSettingsLoaded(:final settings) => _buildSettingsForm(
          _currentSettings ?? settings,
          updateState is NotificationSettingsUpdateLoading,
        ),
    };
  }

  Widget _buildSettingsForm(NotificationSettings settings, bool isLoading) {
    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // Channel preferences
              _buildSectionHeader('Notification Channels'),
              const SizedBox(height: 8),
              _buildChannelCard(settings, isLoading),
              const SizedBox(height: 24),

              // Category preferences
              _buildSectionHeader('Notification Categories'),
              const SizedBox(height: 8),
              _buildCategoryCard(settings, isLoading),
              const SizedBox(height: 24),

              // Info text
              Text(
                'You can customize which types of notifications you receive and how you receive them.',
                style: TextStyle(
                  fontSize: 13,
                  color: Colors.grey[600],
                ),
              ),
            ],
          ),
        ),

        // Save button
        if (_hasChanges)
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.05),
                  blurRadius: 10,
                  offset: const Offset(0, -5),
                ),
              ],
            ),
            child: AppButton(
              text: 'Save Settings',
              onPressed: isLoading ? null : _saveSettings,
              isLoading: isLoading,
            ),
          ),
      ],
    );
  }

  Widget _buildSectionHeader(String title) {
    return Text(
      title,
      style: const TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.bold,
      ),
    );
  }

  Widget _buildChannelCard(NotificationSettings settings, bool isLoading) {
    return Card(
      elevation: 2,
      child: Padding(
        padding: const EdgeInsets.all(8),
        child: Column(
          children: [
            _buildSwitchTile(
              title: 'Push Notifications',
              subtitle: 'Receive notifications on your device',
              icon: Icons.notifications_active,
              value: settings.pushEnabled,
              onChanged: isLoading
                  ? null
                  : (value) {
                      _updateSettings(settings.copyWith(pushEnabled: value));
                    },
            ),
            const Divider(height: 1),
            _buildSwitchTile(
              title: 'Email Notifications',
              subtitle: 'Receive notifications via email',
              icon: Icons.email,
              value: settings.emailEnabled,
              onChanged: isLoading
                  ? null
                  : (value) {
                      _updateSettings(settings.copyWith(emailEnabled: value));
                    },
            ),
            const Divider(height: 1),
            _buildSwitchTile(
              title: 'SMS Notifications',
              subtitle: 'Receive notifications via SMS',
              icon: Icons.sms,
              value: settings.smsEnabled,
              onChanged: isLoading
                  ? null
                  : (value) {
                      _updateSettings(settings.copyWith(smsEnabled: value));
                    },
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCategoryCard(NotificationSettings settings, bool isLoading) {
    return Card(
      elevation: 2,
      child: Padding(
        padding: const EdgeInsets.all(8),
        child: Column(
          children: [
            _buildSwitchTile(
              title: 'Group Activities',
              subtitle: 'Invitations, member joins, group updates',
              icon: Icons.group,
              value: settings.groupsEnabled,
              onChanged: isLoading
                  ? null
                  : (value) {
                      _updateSettings(settings.copyWith(groupsEnabled: value));
                    },
            ),
            const Divider(height: 1),
            _buildSwitchTile(
              title: 'Contributions',
              subtitle: 'Reminders, confirmations, missed contributions',
              icon: Icons.payment,
              value: settings.contributionsEnabled,
              onChanged: isLoading
                  ? null
                  : (value) {
                      _updateSettings(settings.copyWith(contributionsEnabled: value));
                    },
            ),
            const Divider(height: 1),
            _buildSwitchTile(
              title: 'Payouts',
              subtitle: 'Payout notifications and confirmations',
              icon: Icons.account_balance_wallet,
              value: settings.payoutsEnabled,
              onChanged: isLoading
                  ? null
                  : (value) {
                      _updateSettings(settings.copyWith(payoutsEnabled: value));
                    },
            ),
            const Divider(height: 1),
            _buildSwitchTile(
              title: 'Admin & KYC',
              subtitle: 'Account status, KYC updates, admin messages',
              icon: Icons.admin_panel_settings,
              value: settings.adminEnabled,
              onChanged: isLoading
                  ? null
                  : (value) {
                      _updateSettings(settings.copyWith(adminEnabled: value));
                    },
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSwitchTile({
    required String title,
    required String subtitle,
    required IconData icon,
    required bool value,
    required ValueChanged<bool>? onChanged,
  }) {
    return SwitchListTile(
      title: Row(
        children: [
          Icon(icon, size: 20, color: AppColors.primary),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              title,
              style: const TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
      subtitle: Padding(
        padding: const EdgeInsets.only(left: 32, top: 4),
        child: Text(
          subtitle,
          style: TextStyle(
            fontSize: 13,
            color: Colors.grey[600],
          ),
        ),
      ),
      value: value,
      onChanged: onChanged,
      activeColor: AppColors.primary,
      contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    );
  }
}
