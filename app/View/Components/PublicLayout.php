<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PublicLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        // Картинка для og:image / twitter:image (обложка альбома, новости и т.п.).
        public ?string $ogImage = null,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.public');
    }
}
