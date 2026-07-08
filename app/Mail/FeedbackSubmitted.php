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
            ->subject('Новая заявка с сайта - ' . $this->feedback->source . ': ' . $this->feedback->name)
            ->markdown('mail.feedback-submitted');
    }
}