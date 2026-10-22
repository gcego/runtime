{{--
    <x-gcego-runtime::render target="blueprint:subjects" />

    Renders a registered definition statically after checking the RenderAuthorizer.
--}}
@props(['target', 'panel' => null, 'theme' => null])
{{ app(\Gcego\Runtime\Runtime::class)->renderAuthorized($target, panel: $panel, theme: $theme) }}
