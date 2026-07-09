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
        'desired_date',
        'desired_date_end',
        'comment',
        'status',
        'source',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'desired_date'     => 'date',
            'desired_date_end' => 'date',
            'status'           => RentalRequestStatus::class,
        ];
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
