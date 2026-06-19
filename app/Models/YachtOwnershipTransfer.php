<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OwnershipTransferStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class YachtOwnershipTransfer extends Model
{
    use HasFactory, HasUuids;

    /** doc_type документа, подтверждающего владение яхтой */
    public const PROOF_DOC_TYPE = 'ownership_proof';

    protected $fillable = [
        'yacht_id',
        'requester_id',
        'previous_owner_id',
        'status',
        'comment',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status'      => OwnershipTransferStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function yacht(): BelongsTo
    {
        return $this->belongsTo(Yacht::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function previousOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_owner_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Документы, подтверждающие владение */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === OwnershipTransferStatus::Pending;
    }

    /**
     * Одобрить заявку: сменить владельца яхты на заявителя.
     */
    public function approve(?User $reviewer = null): void
    {
        DB::transaction(function () use ($reviewer): void {
            $requester = $this->requester;

            $this->yacht?->update([
                'user_id'     => $this->requester_id,
                'owner_name'  => $requester?->name,
                'owner_email' => $requester?->email,
                'owner_phone' => $requester?->phone,
            ]);

            $this->update([
                'status'      => OwnershipTransferStatus::Approved,
                'reviewed_by' => $reviewer?->getKey() ?? auth()->id(),
                'reviewed_at' => now(),
            ]);
        });
    }

    /**
     * Отклонить заявку.
     */
    public function reject(?string $reason = null, ?User $reviewer = null): void
    {
        $this->update([
            'status'           => OwnershipTransferStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by'      => $reviewer?->getKey() ?? auth()->id(),
            'reviewed_at'      => now(),
        ]);
    }
}
