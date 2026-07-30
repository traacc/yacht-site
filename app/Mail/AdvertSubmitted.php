<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Advert;
use Illuminate\Mail\Mailable;

class AdvertSubmitted extends Mailable
{
    public function __construct(
        public readonly Advert $advert,
        public readonly string $moderationUrl,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Новое объявление на модерацию: '.$this->advert->title)
            ->markdown('mail.advert-submitted');
    }
}
