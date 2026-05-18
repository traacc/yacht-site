<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Models\FeedbackRequests;

class SubmitFeedbackAction
{
    /**
     * Сохраняет заявку обратной связи в БД.
     *
     * @param  array{name: string, phone: string, email?: string|null, message?: string|null, source?: string|null}  $data
     */
    public function handle(array $data, ?string $userId = null): FeedbackRequests
    {
        return FeedbackRequests::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'message' => $data['message'] ?? null,
            'source' => $data['source'] ?? request()->header('Referer', 'unknown'),
            'user_id' => $userId,
        ]);
    }
}
