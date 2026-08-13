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
        // applicant и crew нужны письму: у заявок без команды по ним и понятно,
        // кто подал заявку и как с ним связаться.
        $this->entry->loadMissing(['regatta', 'team', 'yacht', 'applicant', 'crew.user', 'crew.teamMember.user']);

        return $this
            ->subject('Важно: новая заявка на регату «'.$this->entry->regatta->name.'»')
            ->priority(1)
            ->markdown('mail.regatta-entry-submitted');
    }
}
