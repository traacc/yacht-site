<?php

declare(strict_types=1);

namespace App\Actions\YachtRental;

use App\Mail\YachtRentalRequested;
use App\Models\Yacht;
use App\Models\YachtRentalRequest;
use Illuminate\Support\Facades\Mail;

class SubmitYachtRentalRequestAction
{
    /**
     * Сохраняет запрос на аренду яхты и уведомляет администратора и владельца яхты.
     *
     * @param  array{name: string, phone: string, desired_date?: string|null, comment?: string|null, source?: string|null}  $data
     */
    public function handle(Yacht $yacht, array $data, ?string $userId = null): YachtRentalRequest
    {
        $rentalRequest = YachtRentalRequest::create([
            'yacht_id' => $yacht->id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'desired_date' => $data['desired_date'] ?? null,
            'comment' => $data['comment'] ?? null,
            'source' => $data['source'] ?? request()->header('Referer', 'unknown'),
            'user_id' => $userId,
        ]);

        $rentalRequest->setRelation('yacht', $yacht);

        $recipients = array_unique(array_filter([
            env('FEEDBACK_NOTIFICATION_EMAIL') ?? config('mail.from.address'),
            $yacht->user?->email,
        ]));

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(new YachtRentalRequested($rentalRequest));
        }

        return $rentalRequest;
    }
}
