<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\FeedbackRequests;
use Illuminate\Mail\Mailable;

class FeedbackSubmitted extends Mailable
{
    public function __construct(
        public readonly FeedbackRequests $feedback,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Запрос на обратную связь - ' . $this->feedback->source . ': ' . $this->feedback->name)
            ->markdown('mail.feedback-submitted');
    }
}