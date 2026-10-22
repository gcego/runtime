{{-- gcego-runtime::renderers.container — the generic Gcego\Components\Component --}}
@if ($label !== null)
    <section id="{{ $context->domId() }}" aria-labelledby="{{ $context->domId('title') }}"{{ $styles->attribute('root') }}>
        <h{{ $level }} id="{{ $context->domId('title') }}"{{ $styles->attribute('title') }}>{{ $label }}</h{{ $level }}>
        @if ($description !== null)
            <p{{ $styles->attribute('description') }}>{{ $description }}</p>
        @endif
        {{ $children }}
    </section>
@else
    <div id="{{ $context->domId() }}"{{ $styles->attribute('root') }}>
        @if ($description !== null)
            <p{{ $styles->attribute('description') }}>{{ $description }}</p>
        @endif
        {{ $children }}
    </div>
@endif
