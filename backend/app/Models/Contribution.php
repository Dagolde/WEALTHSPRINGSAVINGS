<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Contribution extends Model
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
        'payment_method',
        'payment_reference',
        'payment_status',
        'contribution_date',
        'paid_at',
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
            'contribution_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the group that the contribution belongs to.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the user who made the contribution.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include pending contributions.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * Scope a query to only include successful contributions.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('payment_status', 'successful');
    }

    /**
     * Scope a query to only include failed contributions.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('payment_status', 'failed');
    }

    /**
     * Scope a query to filter by payment status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('payment_status', $status);
    }

    /**
     * Scope a query to filter by date.
     */
    public function scopeByDate(Builder $query, Carbon|string $date): Builder
    {
        return $query->whereDate('contribution_date', $date);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeBetweenDates(Builder $query, Carbon|string $startDate, Carbon|string $endDate): Builder
    {
        return $query->whereBetween('contribution_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to filter contributions for a specific group.
     */
    public function scopeForGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * Scope a query to filter contributions for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if the contribution is pending.
     */
    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Check if the contribution is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->payment_status === 'successful';
    }

    /**
     * Check if the contribution is failed.
     */
    public function isFailed(): bool
    {
        return $this->payment_status === 'failed';
    }

    /**
     * Mark the contribution as successful.
     */
    public function markAsSuccessful(): bool
    {
        return $this->update([
            'payment_status' => 'successful',
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark the contribution as failed.
     */
    public function markAsFailed(): bool
    {
        return $this->update([
            'payment_status' => 'failed',
        ]);
    }
}

