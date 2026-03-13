import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../models/kyc.dart';
import '../../../providers/kyc_provider.dart';
import '../../../shared/widgets/app_button.dart';
import 'kyc_submission_screen.dart';

class KycStatusScreen extends ConsumerStatefulWidget {
  const KycStatusScreen({super.key});

  @override
  ConsumerState<KycStatusScreen> createState() => _KycStatusScreenState();
}

class _KycStatusScreenState extends ConsumerState<KycStatusScreen> {
  @override
  void initState() {
    super.initState();
    // Load KYC status when screen opens
    Future.microtask(
      () => ref.read(kycStateProvider.notifier).getKycStatus(),
    );
  }

  void _navigateToSubmission() async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => const KycSubmissionScreen(),
      ),
    );

    // Reload status if submission was successful
    if (result == true && mounted) {
      ref.read(kycStateProvider.notifier).getKycStatus();
    }
  }

  Widget _buildStatusIcon(KycStatus status) {
    if (status.isPending) {
      return const Icon(
        Icons.hourglass_empty,
        size: 80,
        color: Colors.orange,
      );
    } else if (status.isVerified) {
      return const Icon(
        Icons.check_circle,
        size: 80,
        color: Colors.green,
      );
    } else {
      return const Icon(
        Icons.cancel,
        size: 80,
        color: Colors.red,
      );
    }
  }

  String _getStatusTitle(KycStatus status) {
    if (status.isPending) {
      return 'Verification Pending';
    } else if (status.isVerified) {
      return 'Verified';
    } else {
      return 'Verification Rejected';
    }
  }

  String _getStatusMessage(KycStatus status) {
    if (status.isPending) {
      return 'Your KYC document is being reviewed. This usually takes 24-48 hours.';
    } else if (status.isVerified) {
      return 'Your identity has been successfully verified. You can now access all features.';
    } else {
      return 'Your KYC document was rejected. Please review the reason below and resubmit.';
    }
  }

  Color _getStatusColor(KycStatus status) {
    if (status.isPending) {
      return Colors.orange;
    } else if (status.isVerified) {
      return Colors.green;
    } else {
      return Colors.red;
    }
  }

  Widget _buildTimeline(KycStatus status) {
    return Column(
      children: [
        _buildTimelineItem(
          icon: Icons.upload_file,
          title: 'Document Submitted',
          subtitle: status.submittedAt != null
              ? _formatDate(status.submittedAt!)
              : 'Not submitted',
          isCompleted: status.submittedAt != null,
          isActive: status.submittedAt != null,
        ),
        _buildTimelineConnector(isCompleted: status.submittedAt != null),
        _buildTimelineItem(
          icon: Icons.search,
          title: 'Under Review',
          subtitle: status.isPending ? 'In progress' : 'Completed',
          isCompleted: status.isVerified || status.isRejected,
          isActive: status.isPending,
        ),
        _buildTimelineConnector(
          isCompleted: status.isVerified || status.isRejected,
        ),
        _buildTimelineItem(
          icon: status.isVerified ? Icons.check_circle : Icons.info,
          title: status.isVerified ? 'Verified' : 'Final Status',
          subtitle: status.verifiedAt != null
              ? _formatDate(status.verifiedAt!)
              : status.isRejected
                  ? 'Rejected'
                  : 'Pending',
          isCompleted: status.isVerified,
          isActive: false,
        ),
      ],
    );
  }

  Widget _buildTimelineItem({
    required IconData icon,
    required String title,
    required String subtitle,
    required bool isCompleted,
    required bool isActive,
  }) {
    return Row(
      children: [
        Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: isCompleted
                ? Colors.green
                : isActive
                    ? Colors.orange
                    : Colors.grey.shade300,
          ),
          child: Icon(
            icon,
            color: Colors.white,
            size: 24,
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: isCompleted || isActive
                      ? Colors.black87
                      : Colors.grey.shade600,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                subtitle,
                style: TextStyle(
                  fontSize: 14,
                  color: Colors.grey.shade600,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildTimelineConnector({required bool isCompleted}) {
    return Container(
      margin: const EdgeInsets.only(left: 23),
      width: 2,
      height: 32,
      color: isCompleted ? Colors.green : Colors.grey.shade300,
    );
  }

  String _formatDate(String dateStr) {
    try {
      final date = DateTime.parse(dateStr);
      return '${date.day}/${date.month}/${date.year} ${date.hour}:${date.minute.toString().padLeft(2, '0')}';
    } catch (e) {
      return dateStr;
    }
  }

  @override
  Widget build(BuildContext context) {
    final kycState = ref.watch(kycStateProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('KYC Status'),
        elevation: 0,
      ),
      body: kycState is KycLoading
          ? const Center(child: CircularProgressIndicator())
          : kycState is KycStatusLoaded
              ? _buildStatusContent(kycState.status)
              : kycState is KycError
                  ? _buildErrorContent(kycState.message)
                  : const SizedBox.shrink(),
    );
  }

  Widget _buildStatusContent(KycStatus status) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Status icon and title
          Center(
            child: Column(
              children: [
                _buildStatusIcon(status),
                const SizedBox(height: AppSpacing.md),
                Text(
                  _getStatusTitle(status),
                  style: TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.bold,
                    color: _getStatusColor(status),
                  ),
                ),
                const SizedBox(height: AppSpacing.sm),
                Text(
                  _getStatusMessage(status),
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 14,
                    color: Colors.grey.shade700,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.xl),

          // Rejection reason (if rejected)
          if (status.isRejected && status.rejectionReason != null) ...[
            Container(
              padding: const EdgeInsets.all(AppSpacing.md),
              decoration: BoxDecoration(
                color: Colors.red.shade50,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: Colors.red.shade200),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(Icons.error_outline, color: Colors.red.shade700),
                      const SizedBox(width: AppSpacing.sm),
                      Text(
                        'Rejection Reason',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                          color: Colors.red.shade700,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Text(
                    status.rejectionReason!,
                    style: TextStyle(
                      fontSize: 14,
                      color: Colors.red.shade900,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
          ],

          // Timeline
          const Text(
            'Verification Timeline',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          _buildTimeline(status),
          const SizedBox(height: AppSpacing.xl),

          // Resubmit button (if rejected)
          if (status.isRejected)
            AppButton(
              text: 'Resubmit Document',
              onPressed: _navigateToSubmission,
            ),
        ],
      ),
    );
  }

  Widget _buildErrorContent(String message) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.error_outline,
              size: 64,
              color: Colors.grey.shade400,
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              'Failed to load KYC status',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w600,
                color: Colors.grey.shade700,
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 14,
                color: Colors.grey.shade600,
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            AppButton(
              text: 'Retry',
              onPressed: () {
                ref.read(kycStateProvider.notifier).getKycStatus();
              },
            ),
          ],
        ),
      ),
    );
  }
}
