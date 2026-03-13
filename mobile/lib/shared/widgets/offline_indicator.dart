import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';

class OfflineIndicator extends StatelessWidget {
  final bool isOffline;
  
  const OfflineIndicator({
    super.key,
    required this.isOffline,
  });

  @override
  Widget build(BuildContext context) {
    if (!isOffline) return const SizedBox.shrink();
    
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 16),
      color: AppColors.warning,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: const [
          Icon(
            Icons.cloud_off,
            color: Colors.white,
            size: 16,
          ),
          SizedBox(width: 8),
          Text(
            'You are offline',
            style: TextStyle(
              color: Colors.white,
              fontSize: 14,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }
}

class OfflineIndicatorBanner extends StatelessWidget {
  final bool isOffline;
  final Widget child;
  
  const OfflineIndicatorBanner({
    super.key,
    required this.isOffline,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        OfflineIndicator(isOffline: isOffline),
        Expanded(child: child),
      ],
    );
  }
}
