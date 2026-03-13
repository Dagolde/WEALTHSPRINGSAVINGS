<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Notification Service
 * Integrates with FastAPI notification microservice
 */
class NotificationService
{
    private string $notificationApiUrl;
    
    public function __construct()
    {
        $this->notificationApiUrl = config('services.notification.url');
    }
    
    /**
     * Send multi-channel notification
     *
     * @param User $user
     * @param string $title
     * @param string $message
     * @param array $channels
     * @param array $data
     * @return bool
     */
    public function sendMultiChannel(
        User $user,
        string $title,
        string $message,
        array $channels = ['push', 'sms', 'email'],
        array $data = []
    ): bool {
        try {
            $response = Http::timeout(30)
                ->post("{$this->notificationApiUrl}/send", [
                    'user_id' => $user->id,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'title' => $title,
                    'message' => $message,
                    'channels' => $channels,
                    'data' => $data,
                    'fcm_token' => $user->fcm_token ?? null,
                ]);
            
            if ($response->successful()) {
                $this->logNotification($user->id, $title, $message, $channels, $data);
                return true;
            }
            
            Log::error('Notification API error', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            
            return false;
        } catch (Exception $e) {
            Log::error('Failed to send notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Send push notification only
     *
     * @param User $user
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendPush(User $user, string $title, string $body, array $data = []): bool
    {
        try {
            $response = Http::timeout(30)
                ->post("{$this->notificationApiUrl}/push", [
                    'user_id' => $user->id,
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                    'fcm_token' => $user->fcm_token ?? null,
                ]);
            
            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to send push notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Send SMS only
     *
     * @param User $user
     * @param string $message
     * @return bool
     */
    public function sendSMS(User $user, string $message): bool
    {
        try {
            $response = Http::timeout(30)
                ->post("{$this->notificationApiUrl}/sms", [
                    'phone' => $user->phone,
                    'message' => $message,
                ]);
            
            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to send SMS', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Send email only
     *
     * @param User $user
     * @param string $subject
     * @param string $template
     * @param array $data
     * @return bool
     */
    public function sendEmail(User $user, string $subject, string $template, array $data = []): bool
    {
        try {
            $response = Http::timeout(30)
                ->post("{$this->notificationApiUrl}/email", [
                    'email' => $user->email,
                    'subject' => $subject,
                    'template' => $template,
                    'data' => $data,
                ]);
            
            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to send email', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Send contribution reminder
     *
     * @param User $user
     * @param array $groupData
     * @return bool
     */
    public function sendContributionReminder(User $user, array $groupData): bool
    {
        $message = "Reminder: Please make your daily contribution of ₦{$groupData['amount']} for {$groupData['name']}.";
        
        $data = [
            'template' => 'contribution_reminder',
            'user_name' => $user->name,
            'group_name' => $groupData['name'],
            'amount' => $groupData['amount'],
            'due_date' => $groupData['due_date'] ?? now()->toDateString(),
        ];
        
        return $this->sendMultiChannel(
            $user,
            'Contribution Reminder',
            $message,
            ['push', 'sms', 'email'],
            $data
        );
    }
    
    /**
     * Send payout notification
     *
     * @param User $user
     * @param float $amount
     * @param string $groupName
     * @return bool
     */
    public function sendPayoutNotification(User $user, float $amount, string $groupName): bool
    {
        $message = "Great news! Your payout of ₦{$amount} from {$groupName} has been credited to your wallet.";
        
        $data = [
            'template' => 'payout_notification',
            'user_name' => $user->name,
            'group_name' => $groupName,
            'amount' => number_format($amount, 2),
            'payout_date' => now()->toDateString(),
        ];
        
        return $this->sendMultiChannel(
            $user,
            'Payout Received',
            $message,
            ['push', 'sms', 'email'],
            $data
        );
    }
    
    /**
     * Send missed contribution alert
     *
     * @param User $user
     * @param array $groupData
     * @param string $missedDate
     * @return bool
     */
    public function sendMissedContributionAlert(User $user, array $groupData, string $missedDate): bool
    {
        $message = "You missed your contribution for {$groupData['name']} on {$missedDate}. Please contribute as soon as possible.";
        
        $data = [
            'template' => 'missed_contribution',
            'user_name' => $user->name,
            'group_name' => $groupData['name'],
            'amount' => $groupData['amount'],
            'missed_date' => $missedDate,
        ];
        
        return $this->sendMultiChannel(
            $user,
            'Missed Contribution Alert',
            $message,
            ['push', 'sms', 'email'],
            $data
        );
    }
    
    /**
     * Send group invitation
     *
     * @param User $user
     * @param array $groupData
     * @param string $inviterName
     * @return bool
     */
    public function sendGroupInvitation(User $user, array $groupData, string $inviterName): bool
    {
        $message = "{$inviterName} has invited you to join {$groupData['name']}. Group code: {$groupData['code']}";
        
        $data = [
            'template' => 'group_invitation',
            'user_name' => $user->name,
            'inviter_name' => $inviterName,
            'group_name' => $groupData['name'],
            'amount' => $groupData['amount'],
            'total_members' => $groupData['total_members'],
            'cycle_days' => $groupData['cycle_days'],
            'group_code' => $groupData['code'],
        ];
        
        return $this->sendMultiChannel(
            $user,
            'Group Invitation',
            $message,
            ['push', 'sms', 'email'],
            $data
        );
    }
    
    /**
     * Send KYC status update
     *
     * @param User $user
     * @param string $status
     * @param string|null $reason
     * @return bool
     */
    public function sendKYCStatusUpdate(User $user, string $status, ?string $reason = null): bool
    {
        $message = "Your KYC verification status has been updated to: {$status}";
        if ($reason) {
            $message .= ". Reason: {$reason}";
        }
        
        $data = [
            'template' => 'kyc_status',
            'user_name' => $user->name,
            'status' => $status,
            'reason' => $reason,
            'status_verified' => $status === 'verified',
        ];
        
        return $this->sendMultiChannel(
            $user,
            'KYC Status Update',
            $message,
            ['push', 'sms', 'email'],
            $data
        );
    }
    
    /**
     * Send withdrawal confirmation
     *
     * @param User $user
     * @param array $withdrawalData
     * @return bool
     */
    public function sendWithdrawalConfirmation(User $user, array $withdrawalData): bool
    {
        $message = "Your withdrawal of ₦{$withdrawalData['amount']} has been processed successfully.";
        
        $data = [
            'template' => 'withdrawal_confirmation',
            'user_name' => $user->name,
            'amount' => number_format($withdrawalData['amount'], 2),
            'account_number' => $withdrawalData['account_number'],
            'bank_name' => $withdrawalData['bank_name'],
            'reference' => $withdrawalData['reference'],
            'withdrawal_date' => $withdrawalData['date'] ?? now()->toDateString(),
        ];
        
        return $this->sendMultiChannel(
            $user,
            'Withdrawal Confirmation',
            $message,
            ['push', 'sms', 'email'],
            $data
        );
    }
    
    /**
     * Log notification to database
     *
     * @param int $userId
     * @param string $title
     * @param string $message
     * @param array $channels
     * @param array $data
     * @return void
     */
    private function logNotification(
        int $userId,
        string $title,
        string $message,
        array $channels,
        array $data
    ): void {
        try {
            Notification::create([
                'user_id' => $userId,
                'type' => $data['template'] ?? 'general',
                'title' => $title,
                'message' => $message,
                'data' => $data,
                'channels' => $channels,
                'sent_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to log notification', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
