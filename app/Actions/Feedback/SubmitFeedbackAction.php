<?php

declare(strict_types=1);

namespace App\Actions\Feedback;

use App\Mail\FeedbackSubmitted;
use App\Models\FeedbackRequests;
use Illuminate\Support\Facades\Mail;

class SubmitFeedbackAction
{
    /**
     * Сохраняет заявку обратной связи в БД и отправляет уведомление на email.
     *
     * @param  array{name: string, phone: string, email?: string|null, message?: string|null, source?: string|null}  $data
     */
    public function handle(array $data, ?string $userId = null): FeedbackRequests
    {
        $feedback = FeedbackRequests::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'message' => $data['message'] ?? null,
            'source' => $data['source'] ?? request()->header('Referer', 'unknown'),
            'user_id' => $userId,
        ]);

        $recipient = env('FEEDBACK_NOTIFICATION_EMAIL')
            ?? config('mail.from.address');

        if ($recipient) {
            Mail::to($recipient)->queue(new FeedbackSubmitted($feedback));
        }

        return $feedback;
    }
}
