<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'user_id',
        'position_number',
        'payout_day',
        'has_received_payout',
        'payout_received_at',
        'joined_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'has_received_payout' => 'boolean',
            'payout_received_at' => 'datetime',
            'joined_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
