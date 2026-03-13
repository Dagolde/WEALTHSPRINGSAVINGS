import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_text_styles.dart';

enum ButtonType { primary, secondary, outlined, text }

class AppButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final ButtonType type;
  final bool isLoading;
  final IconData? icon;
  final double? width;
  
  const AppButton({
    super.key,
    required this.text,
    this.onPressed,
    this.type = ButtonType.primary,
    this.isLoading = false,
    this.icon,
    this.width,
  });

  @override
  Widget build(BuildContext context) {
    if (isLoading) {
      return _buildLoadingButton();
    }
    
    switch (type) {
      case ButtonType.primary:
        return _buildPrimaryButton();
      case ButtonType.secondary:
        return _buildSecondaryButton();
      case ButtonType.outlined:
        return _buildOutlinedButton();
      case ButtonType.text:
        return _buildTextButton();
    }
  }
  
  Widget _buildPrimaryButton() {
    return SizedBox(
      width: width,
      child: ElevatedButton(
        onPressed: onPressed,
        child: _buildButtonContent(),
      ),
    );
  }
  
  Widget _buildSecondaryButton() {
    return SizedBox(
      width: width,
      child: ElevatedButton(
        onPressed: onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.secondary,
        ),
        child: _buildButtonContent(),
      ),
    );
  }
  
  Widget _buildOutlinedButton() {
    return SizedBox(
      width: width,
      child: OutlinedButton(
        onPressed: onPressed,
        child: _buildButtonContent(),
      ),
    );
  }
  
  Widget _buildTextButton() {
    return SizedBox(
      width: width,
      child: TextButton(
        onPressed: onPressed,
        child: _buildButtonContent(),
      ),
    );
  }
  
  Widget _buildLoadingButton() {
    return SizedBox(
      width: width,
      child: ElevatedButton(
        onPressed: null,
        child: const SizedBox(
          height: 20,
          width: 20,
          child: CircularProgressIndicator(
            strokeWidth: 2,
            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
          ),
        ),
      ),
    );
  }
  
  Widget _buildButtonContent() {
    if (icon != null) {
      return Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 20),
          const SizedBox(width: 8),
          Text(text, style: AppTextStyles.button),
        ],
      );
    }
    return Text(text, style: AppTextStyles.button);
  }
}
