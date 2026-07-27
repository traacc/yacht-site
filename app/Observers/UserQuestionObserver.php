<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UserQuestion;
use App\Notifications\QuestionAnsweredNotification;

/**
 * Уведомляет автора вопроса, когда администрация написала ответ.
 * Регистрируется в AppServiceProvider:
 *   UserQuestion::observe(UserQuestionObserver::class);
 */
class UserQuestionObserver
{
    public function updated(UserQuestion $question): void
    {
        if (! $question->wasChanged('answer') || ! $question->isAnswered()) {
            return;
        }

        $question->user?->notify(new QuestionAnsweredNotification(
            question: (string) $question->question,
            answer: (string) $question->answer,
        ));
    }
}
