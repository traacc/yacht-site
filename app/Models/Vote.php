<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'voting_id',
        'voting_option_id',
        'user_id',
    ];

    public function voting(): BelongsTo
    {
        return $this->belongsTo(Voting::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(VotingOption::class, 'voting_option_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
