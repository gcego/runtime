{{-- gcego-runtime::renderers.blueprint — override in resources/views/vendor/gcego-runtime/renderers/blueprint.blade.php --}}
<section id="{{ $context->domId() }}" aria-labelledby="{{ $context->domId('title') }}" data-gcego-blueprint="{{ $context->identity ?? $component->name() }}"{{ $styles->attribute('root') }}>
    <header{{ $styles->attribute('header') }}>
        <h{{ $level }} id="{{ $context->domId('title') }}"{{ $styles->attribute('title') }}>{{ $title }}</h{{ $level }}>
        @if ($description !== null)
            <p{{ $styles->attribute('description') }}>{{ $description }}</p>
        @endif
    </header>
    @unless ($children->isEmpty())
        <div{{ $styles->attribute('body') }}>{{ $children }}</div>
    @endunless
</section>
