<?php

namespace App\Livewire;

use Livewire\Component;

class CookieConsent extends Component
{
    public function render(): \Illuminate\View\View
    {
        return view('livewire.cookie-consent');
    }
}
