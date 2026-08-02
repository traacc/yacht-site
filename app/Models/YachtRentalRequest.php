<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RentalRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YachtRentalRequest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'yacht_id',
        'name',
        'phone',
        'email',
        'desired_date',
        'desired_date_end',
        'comment',
        'agreement_accepted_at',
        'status',
        'source',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'desired_date' => 'date',
            'desired_date_end' => 'date',
            'agreement_accepted_at' => 'datetime',
            'status' => RentalRequestStatus::class,
        ];
    }

    /** Сколько суток забронировано: один и тот же день — это один день. */
    public function days(): int
    {
        if ($this->desired_date === null) {
            return 0;
        }

        $end = $this->desired_date_end ?? $this->desired_date;

        return $end->lt($this->desired_date)
            ? 1
            : (int) $this->desired_date->diffInDays($end) + 1;
    }

    public function isPending(): bool
    {
        return $this->status === RentalRequestStatus::Pending;
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
