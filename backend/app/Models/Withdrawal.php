<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'bank_account_id',
        'amount',
        'status',
        'admin_approval_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'payment_reference',
        'processed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the withdrawal.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the bank account for the withdrawal.
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Get the admin who approved/rejected the withdrawal.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if the withdrawal is pending.
     */
    public function isPending(): bool
    {
        return $this->admin_approval_status === 'pending';
    }

    /**
     * Check if the withdrawal is approved.
     */
    public function isApproved(): bool
    {
        return $this->admin_approval_status === 'approved';
    }

    /**
     * Check if the withdrawal is rejected.
     */
    public function isRejected(): bool
    {
        return $this->admin_approval_status === 'rejected';
    }
}
