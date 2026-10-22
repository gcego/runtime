{{-- gcego-runtime::errors.component — shown in place of a component that failed (non-strict mode) --}}
<div id="{{ $id }}" role="alert" data-gcego-error="{{ $path }}">
    <p>This section could not be displayed.</p>
    @if ($message !== null)
        <details>
            <summary>Details</summary>
            <p>{{ $message }}</p>
        </details>
    @endif
</div>
