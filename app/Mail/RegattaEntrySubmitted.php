<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\RegattaEntry;
use Illuminate\Mail\Mailable;

class RegattaEntrySubmitted extends Mailable
{
    public function __construct(
        public readonly RegattaEntry $entry,
    ) {}

    public function build(): self
    {
        $this->entry->loadMissing(['regatta', 'team', 'yacht']);

        return $this
            ->subject('Новая заявка на регату «' . $this->entry->regatta->name . '»')
            ->markdown('mail.regatta-entry-submitted');
    }
}
