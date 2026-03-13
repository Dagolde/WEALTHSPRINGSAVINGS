<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private NotificationService $notificationService;
    private User $user;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->notificationService = new NotificationService();
        
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+2348012345678',
        ]);
    }
    
    public function test_send_multi_channel_notification_success()
    {
        Http::fake([
            '*/notifications/send' => Http::response([
                'success' => true,
                'message' => 'Multi-channel notification sent',
                'results' => [
                    'push' => true,
                    'sms' => true,
                    'email' => true,
                ],
            ], 200),
        ]);
        
        $result = $this->notificationService->sendMultiChannel(
            $this->user,
            'Test Notification',
            'Test message',
            ['push', 'sms', 'email'],
            ['key' => 'value']
        );
        
        $this->assertTrue($result);
        
        Http::assertSent(function ($request) {
            return $request->url() === config('services.notification.url') . '/send' &&
                   $request['user_id'] === $this->user->id &&
                   $request['title'] === 'Test Notification';
        });
    }
    
    public function test_send_push_notification_success()
    {
        Http::fake([
            '*/notifications/push' => Http::response([
                'success' => true,
                'message' => 'Push notification sent',
            ], 200),
        ]);
        
        $result = $this->notificationService->sendPush(
            $this->user,
            'Test Title',
            'Test body',
            ['data' => 'value']
        );
        
        $this->assertTrue($result);
    }
    
    public function test_send_sms_success()
    {
        Http::fake([
            '*/notifications/sms' => Http::response([
                'success' => true,
                'message' => 'SMS sent',
            ], 200),
        ]);
        
        $result = $this->notificationService->sendSMS(
            $this->user,
            'Test SMS message'
        );
        
        $this->assertTrue($result);
    }
    
    public function test_send_email_success()
    {
        Http::fake([
            '*/notifications/email' => Http::response([
                'success' => true,
                'message' => 'Email sent',
            ], 200),
        ]);
        
        $result = $this->notificationService->sendEmail(
            $this->user,
            'Test Subject',
            'default',
            ['message' => 'Test']
        );
        
        $this->assertTrue($result);
    }
    
    public function test_send_contribution_reminder()
    {
        Http::fake([
            '*/notifications/send' => Http::response([
                'success' => true,
                'message' => 'Multi-channel notification sent',
            ], 200),
        ]);
        
        $groupData = [
            'name' => 'Test Group',
            'amount' => 1000,
            'due_date' => now()->toDateString(),
        ];
        
        $result = $this->notificationService->sendContributionReminder(
            $this->user,
            $groupData
        );
        
        $this->assertTrue($result);
        
        Http::assertSent(function ($request) {
            return $request['title'] === 'Contribution Reminder' &&
                   str_contains($request['message'], '₦1000');
        });
    }
    
    public function test_send_payout_notification()
    {
        Http::fake([
            '*/notifications/send' => Http::response([
                'success' => true,
                'message' => 'Multi-channel notification sent',
            ], 200),
        ]);
        
        $result = $this->notificationService->sendPayoutNotification(
            $this->user,
            10000.00,
            'Test Group'
        );
        
        $this->assertTrue($result);
        
        Http::assertSent(function ($request) {
            return $request['title'] === 'Payout Received' &&
                   str_contains($request['message'], '₦10000');
        });
    }
    
    public function test_send_missed_contribution_alert()
    {
        Http::fake([
            '*/notifications/send' => Http::response([
                'success' => true,
                'message' => 'Multi-channel notification sent',
            ], 200),
        ]);
        
        $groupData = [
            'name' => 'Test Group',
            'amount' => 1000,
        ];
        
        $result = $this->notificationService->sendMissedContributionAlert(
            $this->user,
            $groupData,
            '2024-01-15'
        );
        
        $this->assertTrue($result);
        
        Http::assertSent(function ($request) {
            return $request['title'] === 'Missed Contribution Alert';
        });
    }
    
    public function test_send_group_invitation()
    {
        Http::fake([
            '*/notifications/send' => Http::response([
                'success' => true,
                'message' => 'Multi-channel notification sent',
            ], 200),
        ]);
        
        $groupData = [
            'name' => 'Test Group',
            'code' => 'ABC12345',
            'amount' => 1000,
            'total_members' => 10,
            'cycle_days' => 10,
        ];
        
        $result = $this->notificationService->sendGroupInvitation(
            $this->user,
            $groupData,
            'John Doe'
        );
        
        $this->assertTrue($result);
        
        Http::assertSent(function ($request) {
            return $request['title'] === 'Group Invitation' &&
                   str_contains($request['message'], 'ABC12345');
        });
    }
    
    public function test_send_kyc_status_update()
    {
        Http::fake([
            '*/notifications/send' => Http::response([
                'success' => true,
                'message' => 'Multi-channel notification sent',
            ], 200),
        ]);
        
        $result = $this->notificationService->sendKYCStatusUpdate(
            $this->user,
            'verified',
            null
        );
        
        $this->assertTrue($result);
        
        Http::assertSent(function ($request) {
            return $request['title'] === 'KYC Status Update' &&
                   str_contains($request['message'], 'verified');
        });
    }
    
    public function test_send_withdrawal_confirmation()
    {
        Http::fake([
            '*/notifications/send' => Http::response([
                'success' => true,
                'message' => 'Multi-channel notification sent',
            ], 200),
        ]);
        
        $withdrawalData = [
            'amount' => 5000,
            'account_number' => '1234567890',
            'bank_name' => 'Test Bank',
            'reference' => 'WD-123456',
            'date' => now()->toDateString(),
        ];
        
        $result = $this->notificationService->sendWithdrawalConfirmation(
            $this->user,
            $withdrawalData
        );
        
        $this->assertTrue($result);
        
        Http::assertSent(function ($request) {
            return $request['title'] === 'Withdrawal Confirmation' &&
                   str_contains($request['message'], '₦5000');
        });
    }
    
    public function test_notification_api_failure_returns_false()
    {
        Http::fake([
            '*/notifications/send' => Http::response([
                'error' => 'Service unavailable',
            ], 500),
        ]);
        
        $result = $this->notificationService->sendMultiChannel(
            $this->user,
            'Test Notification',
            'Test message'
        );
        
        $this->assertFalse($result);
    }
    
    public function test_notification_timeout_returns_false()
    {
        Http::fake([
            '*/notifications/send' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);
        
        $result = $this->notificationService->sendMultiChannel(
            $this->user,
            'Test Notification',
            'Test message'
        );
        
        $this->assertFalse($result);
    }
}
