<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Team;
use Illuminate\Mail\Mailable;

class TeamRegistered extends Mailable
{
    public function __construct(
        public readonly Team $team,
    ) {}

    public function build(): self
    {
        $this->team->loadMissing('organizer');

        return $this
            ->subject('Зарегистрирована новая команда: ' . $this->team->name)
            ->markdown('mail.team-registered');
    }
}
