import 'package:flutter/material.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_text_styles.dart';

class QuickAction {
  final String title;
  final IconData icon;
  final VoidCallback onTap;
  final Color? color;
  
  QuickAction({
    required this.title,
    required this.icon,
    required this.onTap,
    this.color,
  });
}

class QuickActionsWidget extends StatelessWidget {
  final VoidCallback onCreateGroup;
  final VoidCallback onJoinGroup;
  final VoidCallback onMakeContribution;
  
  const QuickActionsWidget({
    super.key,
    required this.onCreateGroup,
    required this.onJoinGroup,
    required this.onMakeContribution,
  });

  @override
  Widget build(BuildContext context) {
    final actions = [
      QuickAction(
        title: 'Create Group',
        icon: Icons.add_circle_outline,
        onTap: onCreateGroup,
        color: AppColors.primary,
      ),
      QuickAction(
        title: 'Join Group',
        icon: Icons.group_add,
        onTap: onJoinGroup,
        color: AppColors.secondary,
      ),
      QuickAction(
        title: 'Contribute',
        icon: Icons.payment,
        onTap: onMakeContribution,
        color: AppColors.success,
      ),
    ];
    
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Quick Actions',
              style: AppTextStyles.h3,
            ),
            const SizedBox(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: actions.map((action) {
                return _QuickActionButton(action: action);
              }).toList(),
            ),
          ],
        ),
      ),
    );
  }
}

class _QuickActionButton extends StatelessWidget {
  final QuickAction action;
  
  const _QuickActionButton({required this.action});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4),
        child: InkWell(
          onTap: action.onTap,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            padding: const EdgeInsets.symmetric(vertical: 16),
            decoration: BoxDecoration(
              color: (action.color ?? AppColors.primary).withOpacity(0.05),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: (action.color ?? AppColors.primary).withOpacity(0.2),
              ),
            ),
            child: Column(
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: (action.color ?? AppColors.primary).withOpacity(0.1),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    action.icon,
                    color: action.color ?? AppColors.primary,
                    size: 24,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  action.title,
                  style: AppTextStyles.caption.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
