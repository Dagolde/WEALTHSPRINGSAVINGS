import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/theme/app_colors.dart';
import '../../../providers/group_provider.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/app_text_field.dart';

class UpperCaseTextFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    return TextEditingValue(
      text: newValue.text.toUpperCase(),
      selection: newValue.selection,
    );
  }
}

class GroupJoiningScreen extends ConsumerStatefulWidget {
  const GroupJoiningScreen({super.key});

  @override
  ConsumerState<GroupJoiningScreen> createState() =>
      _GroupJoiningScreenState();
}

class _GroupJoiningScreenState extends ConsumerState<GroupJoiningScreen> {
  final _formKey = GlobalKey<FormState>();
  final _groupCodeController = TextEditingController();

  @override
  void dispose() {
    _groupCodeController.dispose();
    super.dispose();
  }

  void _joinGroup() {
    if (_formKey.currentState!.validate()) {
      ref.read(groupStateProvider.notifier).joinGroup(
            _groupCodeController.text.trim().toUpperCase(),
          );
    }
  }

  void _showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: const Text('Success!'),
        content: const Text('You have successfully joined the group.'),
        actions: [
          AppButton(
            text: 'View Groups',
            onPressed: () {
              context.pop();
              context.pop();
            },
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    ref.listen<GroupState>(groupStateProvider, (previous, next) {
      if (next is GroupJoined) {
        _showSuccessDialog();
      } else if (next is GroupError) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(next.message),
            backgroundColor: Colors.red,
          ),
        );
      }
    });

    final groupState = ref.watch(groupStateProvider);
    final isLoading = groupState is GroupLoading;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Join Group'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Icon(
                Icons.group_add,
                size: 80,
                color: AppColors.primary,
              ),
              const SizedBox(height: 24),
              const Text(
                'Enter Group Code',
                style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.bold,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              const Text(
                'Ask the group creator for the unique group code',
                style: TextStyle(
                  fontSize: 14,
                  color: Colors.grey,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 32),
              AppTextField(
                controller: _groupCodeController,
                label: 'Group Code',
                hint: 'e.g., ABC12345',
                inputFormatters: [
                  FilteringTextInputFormatter.allow(RegExp(r'[A-Z0-9]')),
                  UpperCaseTextFormatter(),
                ],
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Please enter group code';
                  }
                  if (value.length < 6) {
                    return 'Group code must be at least 6 characters';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 24),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.blue.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.blue.withOpacity(0.3)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.info_outline, color: Colors.blue),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Make sure you understand the group terms before joining',
                        style: TextStyle(
                          fontSize: 14,
                          color: Colors.blue.shade700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 32),
              AppButton(
                text: 'Join Group',
                onPressed: isLoading ? null : _joinGroup,
                isLoading: isLoading,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
