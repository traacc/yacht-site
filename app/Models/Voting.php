<?php

namespace App\Models;

use App\Enums\VotingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voting extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'description',
        'status',
        'is_anonymous',
        'allow_multiple',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status'         => VotingStatus::class,
            'is_anonymous'   => 'boolean',
            'allow_multiple' => 'boolean',
            'starts_at'      => 'datetime',
            'ends_at'        => 'datetime',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(VotingOption::class)->orderBy('sort_order');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
