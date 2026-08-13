<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\CrewJoinRequest;
use Illuminate\Mail\Mailable;

/**
 * Письмо экипажу (на указанную им почту) и в info@ об отклике «Хочу в этот экипаж».
 */
class CrewJoinRequestSubmitted extends Mailable
{
    public function __construct(
        public readonly CrewJoinRequest $joinRequest,
    ) {}

    public function build(): self
    {
        $this->joinRequest->loadMissing(['regattaEntry.regatta', 'regattaEntry.team']);

        $regatta = $this->joinRequest->regattaEntry->regatta;

        return $this
            ->subject('Заявка в экипаж на регату «'.$regatta->name.'»')
            ->markdown('mail.crew-join-request-submitted');
    }
}
