<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Send Notification Job
 * Asynchronously sends notifications via the notification service
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public int $tries = 3;
    public int $timeout = 60;
    public int $backoff = 60;
    
    private int $userId;
    private string $type;
    private array $data;
    
    /**
     * Create a new job instance
     *
     * @param int $userId
     * @param string $type
     * @param array $data
     */
    public function __construct(int $userId, string $type, array $data)
    {
        $this->userId = $userId;
        $this->type = $type;
        $this->data = $data;
    }
    
    /**
     * Execute the job
     *
     * @param NotificationService $notificationService
     * @return void
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            $user = User::findOrFail($this->userId);
            
            $result = match ($this->type) {
                'contribution_reminder' => $notificationService->sendContributionReminder(
                    $user,
                    $this->data
                ),
                'payout_notification' => $notificationService->sendPayoutNotification(
                    $user,
                    $this->data['amount'],
                    $this->data['group_name']
                ),
                'missed_contribution' => $notificationService->sendMissedContributionAlert(
                    $user,
                    $this->data['group'],
                    $this->data['missed_date']
                ),
                'group_invitation' => $notificationService->sendGroupInvitation(
                    $user,
                    $this->data['group'],
                    $this->data['inviter_name']
                ),
                'kyc_status' => $notificationService->sendKYCStatusUpdate(
                    $user,
                    $this->data['status'],
                    $this->data['reason'] ?? null
                ),
                'withdrawal_confirmation' => $notificationService->sendWithdrawalConfirmation(
                    $user,
                    $this->data
                ),
                default => throw new Exception("Unknown notification type: {$this->type}")
            };
            
            if ($result) {
                Log::info('Notification sent successfully', [
                    'user_id' => $this->userId,
                    'type' => $this->type
                ]);
            } else {
                Log::warning('Notification sending failed', [
                    'user_id' => $this->userId,
                    'type' => $this->type
                ]);
            }
        } catch (Exception $e) {
            Log::error('Notification job failed', [
                'user_id' => $this->userId,
                'type' => $this->type,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Handle a job failure
     *
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception): void
    {
        Log::error('Notification job failed permanently', [
            'user_id' => $this->userId,
            'type' => $this->type,
            'error' => $exception->getMessage()
        ]);
    }
}
