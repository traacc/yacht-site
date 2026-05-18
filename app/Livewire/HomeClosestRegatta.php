<?php

namespace App\Livewire;

use App\Models\Regatta;
use Livewire\Component;

class HomeClosestRegatta extends Component
{
    public function render()
    {
        $regatta = Regatta::closestUpcoming();

        return view('livewire.home-closest-regatta', [
            'regatta' => $regatta,
        ]);
    }
}
