import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/config/app_config.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/app_text_field.dart';
import '../../../providers/auth_provider.dart';
import '../../../models/user.dart';
import '../../../services/image_compression_service.dart';

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  bool _isEditing = false;
  bool _isLoading = false;

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  void _loadUserData(User user) {
    _nameController.text = user.name;
    _emailController.text = user.email;
    _phoneController.text = user.phone;
  }

  void _handleSave() async {
    if (_formKey.currentState!.validate()) {
      setState(() => _isLoading = true);

      try {
        await ref.read(authStateProvider.notifier).updateProfile(
          name: _nameController.text.trim(),
          phone: _phoneController.text.trim(),
        );
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Profile updated successfully'),
              backgroundColor: Colors.green,
            ),
          );
          setState(() => _isEditing = false);
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Failed to update profile: $e'),
              backgroundColor: Colors.red,
            ),
          );
        }
      } finally {
        if (mounted) {
          setState(() => _isLoading = false);
        }
      }
    }
  }

  void _handleLogout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Logout'),
        content: const Text('Are you sure you want to logout?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Logout'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await ref.read(authStateProvider.notifier).logout();
      if (mounted) {
        context.go('/login');
      }
    }
  }

  String _getKycButtonText(User user) {
    if (user.kycStatus == 'rejected') {
      return 'Resubmit KYC Document';
    } else if (user.kycStatus == 'pending' && user.hasSubmittedKyc) {
      return 'Check KYC Status';
    } else {
      return 'Complete KYC Verification';
    }
  }

  void _handleKycNavigation(User user) {
    // If user has submitted KYC (pending or rejected), go to status screen
    // Otherwise, go to submission screen
    if (user.hasSubmittedKyc) {
      context.push('/kyc/status');
    } else {
      context.push('/kyc/submit');
    }
  }

  Future<void> _handleProfilePictureUpload() async {
    final imagePicker = ImagePicker();
    
    // Show options: Camera or Gallery
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt),
              title: const Text('Take Photo'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: const Text('Choose from Gallery'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );

    if (source == null) return;

    try {
      // Pick image
      final XFile? image = await imagePicker.pickImage(
        source: source,
        maxWidth: 800,
        maxHeight: 800,
        imageQuality: 85,
      );

      if (image == null) return;

      setState(() => _isLoading = true);

      // Compress image
      final compressedFile = await ImageCompressionService.compressProfilePicture(
        File(image.path),
      );

      if (compressedFile == null) {
        throw Exception('Failed to process image');
      }

      // Upload to server
      await ref.read(authStateProvider.notifier).uploadProfilePicture(
        compressedFile.path,
      );

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Profile picture updated successfully'),
            backgroundColor: Colors.green,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to upload profile picture: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authStateProvider);

    if (authState is! Authenticated) {
      return const Scaffold(
        body: Center(
          child: CircularProgressIndicator(),
        ),
      );
    }

    final user = authState.user;
    
    // Load user data when screen is first built
    if (_nameController.text.isEmpty) {
      _loadUserData(user);
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile'),
        actions: [
          if (!_isEditing)
            IconButton(
              icon: const Icon(Icons.edit),
              onPressed: () => setState(() => _isEditing = true),
            ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.screenPadding),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: AppSpacing.lg),
                Center(
                  child: Stack(
                    children: [
                      CircleAvatar(
                        radius: 60,
                        backgroundColor: Colors.blue.shade100,
                        backgroundImage: user.profilePictureUrl != null
                            ? NetworkImage('${AppConfig.apiBaseUrl.replaceAll('/api/v1', '')}/storage/${user.profilePictureUrl}')
                            : null,
                        child: user.profilePictureUrl == null
                            ? Text(
                                user.name.isNotEmpty ? user.name[0].toUpperCase() : 'U',
                                style: const TextStyle(
                                  fontSize: 48,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.blue,
                                ),
                              )
                            : null,
                      ),
                      if (_isEditing)
                        Positioned(
                          bottom: 0,
                          right: 0,
                          child: CircleAvatar(
                            radius: 20,
                            backgroundColor: Colors.blue,
                            child: IconButton(
                              icon: const Icon(Icons.camera_alt, size: 20),
                              color: Colors.white,
                              onPressed: _handleProfilePictureUpload,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.xl),
                AppTextField(
                  label: 'Full Name',
                  hint: 'Enter your full name',
                  controller: _nameController,
                  prefixIcon: const Icon(Icons.person_outlined),
                  enabled: _isEditing && !_isLoading,
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter your name';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: AppSpacing.md),
                AppTextField(
                  label: 'Email',
                  hint: 'Enter your email',
                  controller: _emailController,
                  keyboardType: TextInputType.emailAddress,
                  prefixIcon: const Icon(Icons.email_outlined),
                  enabled: false, // Email cannot be changed
                ),
                const SizedBox(height: AppSpacing.md),
                AppTextField(
                  label: 'Phone Number',
                  hint: 'Enter your phone number',
                  controller: _phoneController,
                  keyboardType: TextInputType.phone,
                  prefixIcon: const Icon(Icons.phone_outlined),
                  enabled: _isEditing && !_isLoading,
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter your phone number';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: AppSpacing.md),
                _buildInfoCard('Wallet Balance', '₦${user.walletBalanceAmount.toStringAsFixed(2)}'),
                const SizedBox(height: AppSpacing.sm),
                _buildInfoCard('KYC Status', _getKycStatusText(user.kycStatus)),
                const SizedBox(height: AppSpacing.sm),
                _buildInfoCard('Account Status', _getAccountStatusText(user.status)),
                const SizedBox(height: AppSpacing.xl),
                if (_isEditing) ...[
                  AppButton(
                    text: 'Save Changes',
                    onPressed: _isLoading ? null : _handleSave,
                    isLoading: _isLoading,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  AppButton(
                    text: 'Cancel',
                    type: ButtonType.outlined,
                    onPressed: _isLoading
                        ? null
                        : () {
                            _loadUserData(user);
                            setState(() => _isEditing = false);
                          },
                  ),
                ] else ...[
                  if (user.kycStatus != 'verified')
                    AppButton(
                      text: _getKycButtonText(user),
                      onPressed: () => _handleKycNavigation(user),
                    ),
                  if (user.kycStatus != 'verified')
                    const SizedBox(height: AppSpacing.md),
                  AppButton(
                    text: 'Logout',
                    type: ButtonType.outlined,
                    onPressed: _handleLogout,
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildInfoCard(String label, String value) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: Colors.grey.shade100,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 16,
              color: Colors.grey,
            ),
          ),
          Text(
            value,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
            ),
          ),
        ],
      ),
    );
  }

  String _getKycStatusText(String status) {
    switch (status) {
      case 'verified':
        return 'Verified ✓';
      case 'pending':
        return 'Pending';
      case 'rejected':
        return 'Rejected';
      default:
        return 'Not Submitted';
    }
  }

  String _getAccountStatusText(String status) {
    switch (status) {
      case 'active':
        return 'Active ✓';
      case 'suspended':
        return 'Suspended';
      case 'inactive':
        return 'Inactive';
      default:
        return status;
    }
  }
}
