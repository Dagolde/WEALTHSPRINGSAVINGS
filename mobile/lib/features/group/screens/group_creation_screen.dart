import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';
import '../../../core/theme/app_colors.dart';
import '../../../providers/group_provider.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/app_text_field.dart';

class GroupCreationScreen extends ConsumerStatefulWidget {
  const GroupCreationScreen({super.key});

  @override
  ConsumerState<GroupCreationScreen> createState() =>
      _GroupCreationScreenState();
}

class _GroupCreationScreenState extends ConsumerState<GroupCreationScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _contributionAmountController = TextEditingController();
  final _totalMembersController = TextEditingController();
  final _cycleDaysController = TextEditingController();
  
  String _selectedFrequency = 'daily';

  @override
  void dispose() {
    _nameController.dispose();
    _descriptionController.dispose();
    _contributionAmountController.dispose();
    _totalMembersController.dispose();
    _cycleDaysController.dispose();
    super.dispose();
  }

  void _createGroup() {
    if (_formKey.currentState!.validate()) {
      ref.read(groupStateProvider.notifier).createGroup(
            name: _nameController.text.trim(),
            description: _descriptionController.text.trim().isEmpty
                ? null
                : _descriptionController.text.trim(),
            contributionAmount:
                double.parse(_contributionAmountController.text.trim()),
            totalMembers: int.parse(_totalMembersController.text.trim()),
            cycleDays: int.parse(_cycleDaysController.text.trim()),
            frequency: _selectedFrequency,
          );
    }
  }

  void _showSuccessDialog(String groupCode) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: const Text('Group Created Successfully!'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text('Your group has been created. Share the group code with members:'),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppColors.primary.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    groupCode,
                    style: const TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 2,
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton(
                    icon: const Icon(Icons.copy),
                    onPressed: () {
                      Clipboard.setData(ClipboardData(text: groupCode));
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Group code copied!')),
                      );
                    },
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () {
              Share.share('Join my savings group with code: $groupCode');
            },
            child: const Text('Share'),
          ),
          AppButton(
            text: 'Done',
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
      if (next is GroupCreated) {
        _showSuccessDialog(next.group.groupCode);
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
        title: const Text('Create Group'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Group Details',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 16),
              AppTextField(
                controller: _nameController,
                label: 'Group Name',
                hint: 'e.g., Friends Savings Circle',
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Please enter group name';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              AppTextField(
                controller: _descriptionController,
                label: 'Description (Optional)',
                hint: 'Brief description of the group',
                maxLines: 3,
              ),
              const SizedBox(height: 24),
              const Text(
                'Contribution Settings',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 16),
              AppTextField(
                controller: _contributionAmountController,
                label: 'Contribution Amount (₦)',
                hint: 'e.g., 1000',
                keyboardType: TextInputType.number,
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Please enter contribution amount';
                  }
                  final amount = double.tryParse(value);
                  if (amount == null || amount <= 0) {
                    return 'Please enter a valid amount';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              AppTextField(
                controller: _totalMembersController,
                label: 'Total Members',
                hint: 'e.g., 10',
                keyboardType: TextInputType.number,
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Please enter total members';
                  }
                  final members = int.tryParse(value);
                  if (members == null || members < 2) {
                    return 'Minimum 2 members required';
                  }
                  if (members > 100) {
                    return 'Maximum 100 members allowed';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              AppTextField(
                controller: _cycleDaysController,
                label: 'Cycle Days',
                hint: 'e.g., 10',
                keyboardType: TextInputType.number,
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Please enter cycle days';
                  }
                  final days = int.tryParse(value);
                  if (days == null || days <= 0) {
                    return 'Please enter valid cycle days';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              const Text(
                'Frequency',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: RadioListTile<String>(
                      title: const Text('Daily'),
                      value: 'daily',
                      groupValue: _selectedFrequency,
                      onChanged: (value) {
                        setState(() {
                          _selectedFrequency = value!;
                        });
                      },
                    ),
                  ),
                  Expanded(
                    child: RadioListTile<String>(
                      title: const Text('Weekly'),
                      value: 'weekly',
                      groupValue: _selectedFrequency,
                      onChanged: (value) {
                        setState(() {
                          _selectedFrequency = value!;
                        });
                      },
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppColors.primary.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Summary',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Each member contributes: ₦${_contributionAmountController.text.isEmpty ? '0' : _contributionAmountController.text}',
                    ),
                    Text(
                      'Total pool per cycle: ₦${_calculateTotalPool()}',
                    ),
                    Text(
                      'Cycle duration: ${_cycleDaysController.text.isEmpty ? '0' : _cycleDaysController.text} days',
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              AppButton(
                text: 'Create Group',
                onPressed: isLoading ? null : _createGroup,
                isLoading: isLoading,
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _calculateTotalPool() {
    final amount = double.tryParse(_contributionAmountController.text) ?? 0;
    final members = int.tryParse(_totalMembersController.text) ?? 0;
    return (amount * members).toStringAsFixed(2);
  }
}
