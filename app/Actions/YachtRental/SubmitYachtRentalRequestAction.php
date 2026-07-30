<?php

declare(strict_types=1);

namespace App\Actions\YachtRental;

use App\Mail\YachtRentalRequested;
use App\Models\Yacht;
use App\Models\YachtRentalRequest;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Mail;

class SubmitYachtRentalRequestAction
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * Сохраняет запрос на аренду яхты и отправляет уведомление в отдел заказов.
     *
     * @param  array{name: string, phone: string, desired_date?: string|null, desired_date_end?: string|null, comment?: string|null, source?: string|null}  $data
     */
    public function handle(Yacht $yacht, array $data, ?string $userId = null): YachtRentalRequest
    {
        $rentalRequest = YachtRentalRequest::create([
            'yacht_id' => $yacht->id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'desired_date' => $data['desired_date'] ?? null,
            'desired_date_end' => $data['desired_date_end'] ?? null,
            'comment' => $data['comment'] ?? null,
            'source' => $data['source'] ?? request()->header('Referer', 'unknown'),
            'user_id' => $userId,
        ]);

        $rentalRequest->setRelation('yacht', $yacht);

        Mail::to($this->settings->orderEmail())->send(new YachtRentalRequested($rentalRequest));

        return $rentalRequest;
    }
}
