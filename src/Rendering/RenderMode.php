<?php

declare(strict_types=1);

namespace Gcego\Runtime\Rendering;

enum RenderMode: string
{
    /**
     * Plain server-rendered HTML.
     */
    case Static = 'static';

    /**
     * Inside a Livewire host: renderers may bind state with wire:model.
     */
    case Livewire = 'livewire';
}
