<?php

namespace App\Livewire;

use App\Models\Regatta;
use Livewire\Component;

class HomeRegattaTimer extends Component
{
    public function render()
    {
        $regatta = Regatta::closestUpcoming();

        return view('livewire.home-regatta-timer', [
            'regatta' => $regatta,
        ]);
    }
}
