<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    /**
     * Fund a user's wallet (credit operation).
     *
     * @param User $user The user whose wallet to fund
     * @param float $amount The amount to credit
     * @param string $purpose The purpose of the transaction
     * @param array $metadata Additional metadata for the transaction
     * @param string|null $reference Optional custom reference (if null, generates one)
     * @return WalletTransaction The created wallet transaction record
     * @throws \Exception If the operation fails
     */
    public function fundWallet(User $user, float $amount, string $purpose, array $metadata = [], ?string $reference = null): WalletTransaction
    {
        return $this->creditWallet($user, $amount, $purpose, $metadata, $reference);
    }

    /**
     * Debit a user's wallet.
     *
     * @param User $user The user whose wallet to debit
     * @param float $amount The amount to debit
     * @param string $purpose The purpose of the transaction
     * @param array $metadata Additional metadata for the transaction
     * @param string|null $reference Optional custom reference (if null, generates one)
     * @return WalletTransaction The created wallet transaction record
     * @throws \Exception If insufficient balance or operation fails
     */
    public function debitWallet(User $user, float $amount, string $purpose, array $metadata = [], ?string $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $purpose, $metadata, $reference) {
            // Lock user for update to prevent race conditions
            $userModel = User::where('id', $user->id)->lockForUpdate()->first();

            if (!$userModel) {
                throw new \Exception('User not found');
            }

            // Check sufficient balance
            if ($userModel->wallet_balance < $amount) {
                throw new \Exception(
                    "Insufficient wallet balance. Required: ₦{$amount}, Available: ₦{$userModel->wallet_balance}"
                );
            }

            // Record balance before transaction
            $balanceBefore = $userModel->wallet_balance;

            // Debit wallet
            $userModel->decrement('wallet_balance', $amount);

            // Get updated balance
            $balanceAfter = $userModel->wallet_balance;

            // Generate unique transaction reference if not provided
            if (!$reference) {
                $reference = $this->generateTransactionReference();
            }

            // Create wallet transaction record
            $transaction = WalletTransaction::create([
                'user_id' => $userModel->id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'purpose' => $purpose,
                'reference' => $reference,
                'metadata' => $metadata,
                'status' => 'successful',
            ]);

            Log::info('Wallet debited successfully', [
                'user_id' => $userModel->id,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference' => $reference,
                'purpose' => $purpose,
            ]);

            return $transaction;
        });
    }

    /**
     * Credit a user's wallet.
     *
     * @param User $user The user whose wallet to credit
     * @param float $amount The amount to credit
     * @param string $purpose The purpose of the transaction
     * @param array $metadata Additional metadata for the transaction
     * @param string|null $reference Optional custom reference (if null, generates one)
     * @return WalletTransaction The created wallet transaction record
     * @throws \Exception If the operation fails
     */
    public function creditWallet(User $user, float $amount, string $purpose, array $metadata = [], ?string $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $purpose, $metadata, $reference) {
            // Lock user for update to prevent race conditions
            $userModel = User::where('id', $user->id)->lockForUpdate()->first();

            if (!$userModel) {
                throw new \Exception('User not found');
            }

            // Record balance before transaction
            $balanceBefore = $userModel->wallet_balance;

            // Credit wallet
            $userModel->increment('wallet_balance', $amount);

            // Get updated balance
            $balanceAfter = $userModel->wallet_balance;

            // Generate unique transaction reference if not provided
            if (!$reference) {
                $reference = $this->generateTransactionReference();
            }

            // Create wallet transaction record
            $transaction = WalletTransaction::create([
                'user_id' => $userModel->id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'purpose' => $purpose,
                'reference' => $reference,
                'metadata' => $metadata,
                'status' => 'successful',
            ]);

            Log::info('Wallet credited successfully', [
                'user_id' => $userModel->id,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference' => $reference,
                'purpose' => $purpose,
            ]);

            return $transaction;
        });
    }

    /**
     * Get the current wallet balance for a user.
     *
     * @param User $user The user whose balance to retrieve
     * @return float The current wallet balance
     */
    public function getBalance(User $user): float
    {
        // Use fresh query to avoid stale data
        $freshUser = User::find($user->id);

        if (!$freshUser) {
            throw new \Exception('User not found');
        }

        return (float) $freshUser->wallet_balance;
    }

    /**
     * Generate a unique transaction reference.
     *
     * Format: TXN-YYYYMMDD-XXXXXXXX
     *
     * @return string The generated transaction reference
     */
    private function generateTransactionReference(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

        return "TXN-{$date}-{$random}";
    }
}
