<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use App\Models\User;

class FraudDetectionService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = Config::get('services.fraud_detection.url', 'http://localhost:8000');
        $this->timeout = Config::get('services.fraud_detection.timeout', 10);
    }

    /**
     * Analyze user behavior for fraud patterns
     *
     * @param int $userId
     * @return array
     */
    public function analyzeUserBehavior(int $userId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/fraud/analyze-user", [
                    'user_id' => $userId
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            Log::error('Fraud detection API error', [
                'user_id' => $userId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return $this->getDefaultResponse();
        } catch (\Exception $e) {
            Log::error('Fraud detection service error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return $this->getDefaultResponse();
        }
    }

    /**
     * Check payment for fraud indicators
     *
     * @param int $userId
     * @param float $amount
     * @param string $paymentMethod
     * @param array $metadata
     * @return array
     */
    public function analyzePayment(int $userId, float $amount, string $paymentMethod, array $metadata = []): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/fraud/analyze-payment", [
                    'user_id' => $userId,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'metadata' => $metadata
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            Log::error('Payment fraud check API error', [
                'user_id' => $userId,
                'amount' => $amount,
                'status' => $response->status()
            ]);

            return $this->getDefaultResponse();
        } catch (\Exception $e) {
            Log::error('Payment fraud check error', [
                'user_id' => $userId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            return $this->getDefaultResponse();
        }
    }

    /**
     * Check for duplicate accounts
     *
     * @param string $email
     * @param string $phone
     * @param string|null $deviceId
     * @param string|null $ipAddress
     * @return array
     */
    public function checkDuplicateAccounts(string $email, string $phone, ?string $deviceId = null, ?string $ipAddress = null): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/fraud/check-duplicate-accounts", [
                    'email' => $email,
                    'phone' => $phone,
                    'device_id' => $deviceId,
                    'ip_address' => $ipAddress
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            Log::error('Duplicate account check API error', [
                'email' => $email,
                'status' => $response->status()
            ]);

            return ['duplicates_found' => 0, 'user_ids' => []];
        } catch (\Exception $e) {
            Log::error('Duplicate account check error', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return ['duplicates_found' => 0, 'user_ids' => []];
        }
    }

    /**
     * Analyze withdrawal for fraud indicators
     *
     * @param int $userId
     * @param float $amount
     * @param string $bankAccount
     * @return array
     */
    public function analyzeWithdrawal(int $userId, float $amount, string $bankAccount): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/fraud/analyze-withdrawal", [
                    'user_id' => $userId,
                    'amount' => $amount,
                    'bank_account' => $bankAccount
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            Log::error('Withdrawal fraud check API error', [
                'user_id' => $userId,
                'amount' => $amount,
                'status' => $response->status()
            ]);

            return $this->getDefaultResponse();
        } catch (\Exception $e) {
            Log::error('Withdrawal fraud check error', [
                'user_id' => $userId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            return $this->getDefaultResponse();
        }
    }

    /**
     * Flag suspicious activity
     *
     * @param int $userId
     * @param string $activityType
     * @param array $details
     * @return bool
     */
    public function flagActivity(int $userId, string $activityType, array $details): bool
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/fraud/flag-activity", [
                    'user_id' => $userId,
                    'activity_type' => $activityType,
                    'details' => $details
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('Activity flagging API error', [
                'user_id' => $userId,
                'activity_type' => $activityType,
                'status' => $response->status()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Activity flagging error', [
                'user_id' => $userId,
                'activity_type' => $activityType,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Handle fraud detection result and take appropriate action
     *
     * @param int $userId
     * @param array $fraudResult
     * @return void
     */
    public function handleFraudResult(int $userId, array $fraudResult): void
    {
        $riskScore = $fraudResult['risk_score'] ?? 0;
        $recommendation = $fraudResult['recommendation'] ?? 'approve';

        Log::info('Fraud detection result', [
            'user_id' => $userId,
            'risk_score' => $riskScore,
            'recommendation' => $recommendation,
            'flags' => $fraudResult['flags'] ?? []
        ]);

        // Take action based on recommendation
        if ($recommendation === 'suspend') {
            $this->suspendUser($userId, $fraudResult);
        } elseif ($recommendation === 'review') {
            $this->flagForReview($userId, $fraudResult);
        }
    }

    /**
     * Suspend user account due to high fraud risk
     *
     * @param int $userId
     * @param array $fraudResult
     * @return void
     */
    private function suspendUser(int $userId, array $fraudResult): void
    {
        try {
            $user = User::findOrFail($userId);
            $user->status = 'suspended';
            $user->save();

            Log::warning('User suspended due to fraud detection', [
                'user_id' => $userId,
                'risk_score' => $fraudResult['risk_score'] ?? 0,
                'flags' => $fraudResult['flags'] ?? []
            ]);

            // Flag activity for admin review
            $this->flagActivity($userId, 'auto_suspension', [
                'reason' => 'High fraud risk score',
                'risk_score' => $fraudResult['risk_score'] ?? 0,
                'flags' => $fraudResult['flags'] ?? []
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to suspend user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Flag user for manual review
     *
     * @param int $userId
     * @param array $fraudResult
     * @return void
     */
    private function flagForReview(int $userId, array $fraudResult): void
    {
        Log::info('User flagged for review', [
            'user_id' => $userId,
            'risk_score' => $fraudResult['risk_score'] ?? 0,
            'flags' => $fraudResult['flags'] ?? []
        ]);

        // Flag activity for admin review
        $this->flagActivity($userId, 'manual_review_required', [
            'reason' => 'Medium fraud risk score',
            'risk_score' => $fraudResult['risk_score'] ?? 0,
            'flags' => $fraudResult['flags'] ?? []
        ]);
    }

    /**
     * Get default response when fraud detection service is unavailable
     *
     * @return array
     */
    private function getDefaultResponse(): array
    {
        return [
            'risk_score' => 0,
            'flags' => [],
            'recommendation' => 'approve'
        ];
    }
}
