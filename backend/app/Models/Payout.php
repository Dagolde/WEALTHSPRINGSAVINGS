<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Payout extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'group_id',
        'user_id',
        'amount',
        'payout_day',
        'payout_date',
        'status',
        'payout_method',
        'payout_reference',
        'failure_reason',
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
            'payout_day' => 'integer',
            'payout_date' => 'date',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Get the group that the payout belongs to.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the user who receives the payout.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to filter by payout status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by payout date.
     */
    public function scopeByDate(Builder $query, Carbon|string $date): Builder
    {
        return $query->whereDate('payout_date', $date);
    }

    /**
     * Scope a query to only include pending payouts.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include successful payouts.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'successful');
    }

    /**
     * Scope a query to only include failed payouts.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include processed payouts.
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->whereNotNull('processed_at');
    }

    /**
     * Scope a query to only include unprocessed payouts.
     */
    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->whereNull('processed_at');
    }

    /**
     * Scope a query to filter payouts for a specific group.
     */
    public function scopeForGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * Scope a query to filter payouts for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Mark the payout as processing.
     */
    public function markAsProcessing(): bool
    {
        return $this->update([
            'status' => 'processing',
        ]);
    }

    /**
     * Mark the payout as successful.
     */
    public function markAsSuccessful(): bool
    {
        return $this->update([
            'status' => 'successful',
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark the payout as failed.
     */
    public function markAsFailed(string $reason): bool
    {
        return $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'processed_at' => now(),
        ]);
    }
}
